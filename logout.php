<?php
/**
 * Logout Handler
 * Safely unbinds lab workstation, logs termination, and destroys session
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'] ?? 'guest';

    // Release lab PC if student was tied to one
    if ($role === 'student' && !empty($_SESSION['lab_pc_id'])) {
        try {
            $pdo = getDb();
            $stmt = $pdo->prepare("UPDATE lab_pcs SET current_student_id = NULL, current_exam_id = NULL, status = 'online' WHERE id = ?");
            $stmt->execute([$_SESSION['lab_pc_id']]);
        } catch (Exception $e) {
            // Continue with logout
        }
    }

    logSystemActivity($userId, 'USER_LOGOUT', "User {$_SESSION['username']} logged out successfully.");
}

// Unset all session keys
$_SESSION = [];

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header("Location: " . BASE_URL . "/index.php?msg=logged_out");
exit;
