<?php
/**
 * Landing Page & Role-Based Authentication Gateway
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';

// If already logged in, redirect straight to their role dashboard
if (isset($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: " . BASE_URL . "/admin/dashboard.php");
        exit;
    } elseif ($_SESSION['role'] === 'teacher') {
        header("Location: " . BASE_URL . "/teacher/dashboard.php");
        exit;
    } elseif ($_SESSION['role'] === 'student') {
        header("Location: " . BASE_URL . "/student/dashboard.php");
        exit;
    }
}

// Fetch list of available lab PCs for student workstation check-in
$availablePcs = [];
try {
    $pdo = getDb();
    $pcQuery = $pdo->query("SELECT id, pc_name, lab_room FROM lab_pcs ORDER BY lab_room ASC, pc_name ASC");
    $availablePcs = $pcQuery->fetchAll();
} catch (Exception $e) {
    $availablePcs = [];
}

// Check if registration allowed
$allowReg = getSystemSetting('allow_student_registration', '1');

$authError = $_SESSION['auth_error'] ?? '';
$authSuccess = $_SESSION['auth_success'] ?? '';
unset($_SESSION['auth_error'], $_SESSION['auth_success']);

if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $authSuccess = "You have successfully signed out of the exam portal.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart University Lab & Exam Proctoring Portal</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/logo.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
        }
        .login-card {
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .portal-hero {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
            color: #ffffff;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .role-pill-btn {
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.88rem;
            color: #475569;
            transition: all 0.2s;
        }
        .role-pill-btn.active {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
        }
    </style>
</head>
<body class="d-flex align-items-center py-4">

<div class="container my-auto">
    <div class="row justify-content-center">
        <div class="col-xl-10 col-lg-11">
            <div class="card login-card shadow-lg">
                <div class="row g-0">
                    <!-- Left Hero Banner -->
                    <div class="col-lg-5 portal-hero d-none d-lg-flex">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-4">
                                <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="University Logo" height="42" class="bg-white rounded p-1">
                                <span class="fw-bold fs-5 tracking-wide text-white">SMART PROCTOR</span>
                            </div>
                            <h2 class="fw-bold mb-3">Integrity-First University Exam Environment</h2>
                            <p class="text-white-50">Automated fullscreen lockdown, real-time tab switch deterrence, heartbeat workstation telemetry, and instant auto-grading.</p>
                        </div>

                        <div class="border-top border-white border-opacity-25 pt-4">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="p-2 rounded bg-white bg-opacity-10">
                                        <div class="fw-bold fs-5"><i class="bi bi-shield-check text-warning me-1"></i> Anti-Cheat</div>
                                        <small class="text-white-50">Fullscreen & Tab Guard</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 rounded bg-white bg-opacity-10">
                                        <div class="fw-bold fs-5"><i class="bi bi-hdd-network text-info me-1"></i> Lab Telemetry</div>
                                        <small class="text-white-50">Live Workstation Grid</small>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 text-white-50 small">
                                Protected System &bull; Active Academic Year 2026
                            </div>
                        </div>
                    </div>

                    <!-- Right Login & Register Forms -->
                    <div class="col-lg-7 p-4 p-md-5">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="fw-bold text-dark mb-0">Sign In to Portal</h4>
                            <?php if ($allowReg === '1'): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#registerModal">
                                    <i class="bi bi-person-plus me-1"></i> Student Register
                                </button>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted small mb-4">Select your university portal role and enter your institutional credentials.</p>

                        <!-- Alerts -->
                        <?php if (!empty($authError)): ?>
                            <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($authError) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($authSuccess)): ?>
                            <div class="alert alert-success alert-dismissible fade show small" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($authSuccess) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Role Selector Tabs -->
                        <div class="d-flex gap-2 mb-4">
                            <button type="button" class="role-pill-btn active flex-fill text-center" onclick="selectRole('student', this)">
                                <i class="bi bi-mortarboard me-1"></i> Student
                            </button>
                            <button type="button" class="role-pill-btn flex-fill text-center" onclick="selectRole('teacher', this)">
                                <i class="bi bi-person-video3 me-1"></i> Teacher
                            </button>
                            <button type="button" class="role-pill-btn flex-fill text-center" onclick="selectRole('admin', this)">
                                <i class="bi bi-shield-shaded me-1"></i> Admin
                            </button>
                        </div>

                        <!-- Login Form -->
                        <form action="<?= BASE_URL ?>/auth/login_process.php" method="POST" id="loginForm">
                            <input type="hidden" name="role" id="selectedRoleInput" value="student">

                            <div class="mb-3">
                                <label class="form-label small fw-semibold" id="usernameLabel">Student Roll No or Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-person" id="roleIcon"></i></span>
                                    <input type="text" name="username_or_email" id="loginInput" class="form-control" placeholder="e.g. student or student@university.edu" required autofocus>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <label class="form-label small fw-semibold">Password</label>
                                    <a href="<?= BASE_URL ?>/auth/reset_password.php" class="text-decoration-none small">Forgot password?</a>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                                    <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Enter password" required>
                                </div>
                            </div>

                            <!-- Student Lab PC Selector -->
                            <div class="mb-4" id="labPcSelectionGroup">
                                <label class="form-label small fw-semibold">Physical Lab Workstation <span class="text-muted fw-normal">(Optional Check-In)</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-display"></i></span>
                                    <select name="lab_pc_id" class="form-select">
                                        <option value="">-- Virtual / Remote Access (No PC Assigned) --</option>
                                        <?php foreach ($availablePcs as $pc): ?>
                                            <option value="<?= $pc['id'] ?>"><?= htmlspecialchars($pc['lab_room'] . ' - ' . $pc['pc_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-text small">Select your designated terminal in Computer Lab 101/102.</div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold rounded-3 shadow-sm">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Authenticate & Enter Portal
                            </button>
                        </form>

                        <!-- Quick Test Credentials Helper -->
                        <div class="mt-4 pt-3 border-top text-center">
                            <span class="text-muted small d-block mb-2">⚡ Quick 1-Click Demo Credentials:</span>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-secondary" onclick="fillDemo('student')">Student Demo</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="fillDemo('teacher')">Teacher Demo</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="fillDemo('admin')">Admin Demo</button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Student Self-Registration Modal -->
<div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="registerModalLabel">Student Self-Registration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="<?= BASE_URL ?>/auth/register_process.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Full Legal Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" placeholder="e.g. John Doe" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Roll Number <span class="text-danger">*</span></label>
                            <input type="text" name="roll_number" class="form-control" placeholder="CS-2024-005" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" placeholder="john.d" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Institutional Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="john@university.edu" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Contact Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="+1-555-0199">
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">
                        <i class="bi bi-person-check me-1"></i> Register Student Account
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
<script>
    function selectRole(role, btn) {
        document.querySelectorAll('.role-pill-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        document.getElementById('selectedRoleInput').value = role;
        const pcGroup = document.getElementById('labPcSelectionGroup');
        const userLabel = document.getElementById('usernameLabel');

        if (role === 'student') {
            pcGroup.style.display = 'block';
            userLabel.textContent = 'Student Roll No or Email';
        } else if (role === 'teacher') {
            pcGroup.style.display = 'none';
            userLabel.textContent = 'Teacher Username or Email';
        } else {
            pcGroup.style.display = 'none';
            userLabel.textContent = 'Administrator Username or Email';
        }
    }

    function fillDemo(role) {
        const roleBtn = document.querySelectorAll('.role-pill-btn');
        if (role === 'student') {
            selectRole('student', roleBtn[0]);
            document.getElementById('loginInput').value = 'student';
            document.getElementById('passwordInput').value = 'Password@123';
        } else if (role === 'teacher') {
            selectRole('teacher', roleBtn[1]);
            document.getElementById('loginInput').value = 'teacher';
            document.getElementById('passwordInput').value = 'Password@123';
        } else if (role === 'admin') {
            selectRole('admin', roleBtn[2]);
            document.getElementById('loginInput').value = 'admin';
            document.getElementById('passwordInput').value = 'Password@123';
        }
    }
</script>
</body>
</html>
