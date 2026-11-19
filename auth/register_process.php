<?php
/**
 * Student Self-Registration Handler
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/index.php#register");
    exit;
}

// Check if registration is permitted by system settings
$allowReg = getSystemSetting('allow_student_registration', '1');
if ($allowReg !== '1') {
    $_SESSION['auth_error'] = "Student self-registration is currently disabled by the lab administrator.";
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$fullName    = trim($_POST['full_name'] ?? '');
$rollNumber  = strtoupper(trim($_POST['roll_number'] ?? ''));
$username    = strtolower(trim($_POST['username'] ?? ''));
$email       = strtolower(trim($_POST['email'] ?? ''));
$password    = $_POST['password'] ?? '';
$confirmPass = $_POST['confirm_password'] ?? '';
$phone       = trim($_POST['phone'] ?? '');

// Basic validation
if (empty($fullName) || empty($rollNumber) || empty($username) || empty($email) || empty($password)) {
    $_SESSION['auth_error'] = "All mandatory fields marked with an asterisk must be provided.";
    header("Location: " . BASE_URL . "/index.php#register");
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['auth_error'] = "Please provide a valid institutional email address.";
    header("Location: " . BASE_URL . "/index.php#register");
    exit;
}

if (strlen($password) < 6) {
    $_SESSION['auth_error'] = "Password must be at least 6 characters in length.";
    header("Location: " . BASE_URL . "/index.php#register");
    exit;
}

if ($password !== $confirmPass) {
    $_SESSION['auth_error'] = "Password and confirm password do not match.";
    header("Location: " . BASE_URL . "/index.php#register");
    exit;
}

try {
    $pdo = getDb();

    // Check duplicate username or email or roll number
    $stmt = $pdo->prepare("SELECT id, username, email, roll_number FROM users WHERE username = ? OR email = ? OR (roll_number = ? AND roll_number IS NOT NULL) LIMIT 1");
    $stmt->execute([$username, $email, $rollNumber]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['username'] === $username) {
            $_SESSION['auth_error'] = "Username '{$username}' is already in use.";
        } elseif ($existing['email'] === $email) {
            $_SESSION['auth_error'] = "Email address '{$email}' is already registered.";
        } else {
            $_SESSION['auth_error'] = "Roll number '{$rollNumber}' is already registered.";
        }
        header("Location: " . BASE_URL . "/index.php#register");
        exit;
    }

    // Insert student record
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $insertStmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role, roll_number, phone, status, avatar) VALUES (?, ?, ?, ?, 'student', ?, ?, 'active', 'default_avatar.png')");
    $insertStmt->execute([$username, $email, $hash, $fullName, $rollNumber, $phone]);

    $newId = $pdo->lastInsertId();
    logSystemActivity($newId, 'STUDENT_SELF_REGISTER', "Student {$fullName} ({$rollNumber}) registered an account.");

    $_SESSION['auth_success'] = "Registration successful! You may now sign in using your credentials.";
    header("Location: " . BASE_URL . "/index.php");
    exit;

} catch (Exception $e) {
    $_SESSION['auth_error'] = "Registration error: " . $e->getMessage();
    header("Location: " . BASE_URL . "/index.php#register");
    exit;
}
