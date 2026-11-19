<?php
/**
 * Password Recovery & Reset Handler
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $username = trim($_POST['username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($email) || empty($username) || empty($newPassword)) {
        $error = "Please fill in all required fields.";
    } elseif (strlen($newPassword) < 6) {
        $error = "New password must be at least 6 characters long.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        try {
            $pdo = getDb();
            $stmt = $pdo->prepare("SELECT id, username, email, full_name FROM users WHERE email = ? AND username = ? LIMIT 1");
            $stmt->execute([$email, $username]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = "No matching account found with that email and username.";
            } else {
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->execute([$newHash, $user['id']]);

                logSystemActivity($user['id'], 'PASSWORD_RESET', "User {$user['username']} successfully reset password.");
                $message = "Your password has been reset successfully. You can now log in with your new password.";
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="bg-light d-flex align-items-center min-vh-100 py-4">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-shield-lock-fill text-primary" style="font-size: 3rem;"></i>
                        <h4 class="fw-bold mt-2">Account Recovery</h4>
                        <p class="text-muted small">Verify your registered username & email to reset your access credentials.</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($message) ?>
                            <div class="mt-2">
                                <a href="<?= BASE_URL ?>/index.php" class="btn btn-sm btn-success">Proceed to Sign In</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Username <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                    <input type="text" name="username" class="form-control" placeholder="e.g. student or roll number" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Registered Institutional Email <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" placeholder="name@university.edu" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">New Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-key"></i></span>
                                    <input type="password" name="new_password" class="form-control" placeholder="Min. 6 characters" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-semibold">Confirm New Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-key-fill"></i></span>
                                    <input type="password" name="confirm_password" class="form-control" placeholder="Re-type password" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">
                                <i class="bi bi-arrow-repeat me-1"></i> Update Password
                            </button>
                        </form>
                    <?php endif; ?>

                    <div class="text-center mt-4 border-top pt-3">
                        <a href="<?= BASE_URL ?>/index.php" class="text-decoration-none small text-muted">
                            <i class="bi bi-arrow-left me-1"></i> Back to Login Portal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
