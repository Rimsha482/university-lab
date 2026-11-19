<?php
/**
 * Authentication Processing Handler
 * Verifies credentials, enforces role segregation, and tracks Lab PC association
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$loginInput = trim($_POST['username_or_email'] ?? '');
$password   = trim($_POST['password'] ?? '');
$roleInput  = trim($_POST['role'] ?? '');
$labPcId    = !empty($_POST['lab_pc_id']) ? intval($_POST['lab_pc_id']) : null;

if (empty($loginInput) || empty($password)) {
    $_SESSION['auth_error'] = "Please provide both username/email and password.";
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

try {
    $pdo = getDb();
    
    // Look up by username or email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1");
    $stmt->execute([$loginInput, $loginInput]);
    $user = $stmt->fetch();

    if (!$user) {
        $_SESSION['auth_error'] = "Invalid username/email or password.";
        header("Location: " . BASE_URL . "/index.php");
        exit;
    }

    // Check account status
    if ($user['status'] !== 'active') {
        $_SESSION['auth_error'] = "This account has been deactivated. Please contact lab administration.";
        header("Location: " . BASE_URL . "/index.php");
        exit;
    }

    // Role check if explicitly chosen on login form tab
    if (!empty($roleInput) && $user['role'] !== $roleInput) {
        $_SESSION['auth_error'] = "The selected role (" . ucfirst($roleInput) . ") does not match this account's credentials.";
        header("Location: " . BASE_URL . "/index.php");
        exit;
    }

    // Verify password hash
    if (!password_verify($password, $user['password'])) {
        logSystemActivity($user['id'], 'LOGIN_FAILED', "Failed password attempt for user '{$loginInput}'");
        $_SESSION['auth_error'] = "Invalid username/email or password.";
        header("Location: " . BASE_URL . "/index.php");
        exit;
    }

    // Password verified! Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id']     = (int)$user['id'];
    $_SESSION['username']    = $user['username'];
    $_SESSION['email']       = $user['email'];
    $_SESSION['full_name']   = $user['full_name'];
    $_SESSION['role']        = $user['role'];
    $_SESSION['roll_number'] = $user['roll_number'];
    $_SESSION['avatar']      = !empty($user['avatar']) ? $user['avatar'] : 'default_avatar.png';
    $_SESSION['last_activity'] = time();

    // If student selected a Lab PC, bind student to that workstation
    if ($user['role'] === 'student' && $labPcId) {
        $pcStmt = $pdo->prepare("SELECT id, pc_name FROM lab_pcs WHERE id = ?");
        $pcStmt->execute([$labPcId]);
        $pc = $pcStmt->fetch();
        if ($pc) {
            $_SESSION['lab_pc_id'] = $pc['id'];
            $_SESSION['lab_pc_name'] = $pc['pc_name'];

            $updatePc = $pdo->prepare("UPDATE lab_pcs SET current_student_id = ?, status = 'online', last_heartbeat = NOW() WHERE id = ?");
            $updatePc->execute([$user['id'], $pc['id']]);
        }
    }

    // Log successful login
    logSystemActivity($user['id'], 'USER_LOGIN_SUCCESS', "User {$user['username']} logged in successfully as {$user['role']}");

    // Redirect to role module
    if ($user['role'] === 'admin') {
        header("Location: " . BASE_URL . "/admin/dashboard.php");
    } elseif ($user['role'] === 'teacher') {
        header("Location: " . BASE_URL . "/teacher/dashboard.php");
    } else {
        header("Location: " . BASE_URL . "/student/dashboard.php");
    }
    exit;

} catch (Exception $e) {
    $_SESSION['auth_error'] = "System authentication error: " . $e->getMessage();
    header("Location: " . BASE_URL . "/index.php");
    exit;
}
