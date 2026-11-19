<?php
/**
 * Session Check Middleware
 * Ensures user is authenticated and session is alive
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Check if it is an API / AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
        exit;
    }
    
    $_SESSION['auth_error'] = "Please log in to access this page.";
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

// Check session inactivity timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT_SEC)) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['auth_error'] = "Session timed out due to inactivity. Please log in again.";
    header("Location: " . BASE_URL . "/index.php");
    exit;
}
$_SESSION['last_activity'] = time();

// Optional: refresh user state if needed
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'] ?? 'student';
$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_avatar = $_SESSION['avatar'] ?? 'default_avatar.png';
