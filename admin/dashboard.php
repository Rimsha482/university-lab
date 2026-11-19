<?php
/**
 * Admin Control & Lab Incharge Dashboard
 */

require_once dirname(__DIR__) . '/auth/role_guard.php';
requireRole('admin');

$pageTitle = "Admin Dashboard";
$pdo = getDb();

// 1. Fetch Key System Statistics
$totalPcs = $pdo->query("SELECT COUNT(*) FROM lab_pcs")->fetchColumn();
$onlinePcs = $pdo->query("SELECT COUNT(*) FROM lab_pcs WHERE status IN ('online', 'in_exam')")->fetchColumn();
$activeExams = $pdo->query("SELECT COUNT(*) FROM exams WHERE status IN ('published', 'ongoing')")->fetchColumn();
$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$totalTeachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$totalViolations = $pdo->query("SELECT COUNT(*) FROM proctor_logs")->fetchColumn();

// 2. Fetch Recent System Logs
$recentLogs = $pdo->query("SELECT l.*, u.username, u.full_name 
                           FROM system_logs l 
                           LEFT JOIN users u ON l.user_id = u.id 
                           ORDER BY l.id DESC LIMIT 6")->fetchAll();

// 3. Fetch Recent Exam Attempts
$recentAttempts = $pdo->query("SELECT a.*, e.title as exam_title, u.full_name, u.roll_number, p.pc_name
                               FROM exam_attempts a
                               JOIN exams e ON a.exam_id = e.id
                               JOIN users u ON a.student_id = u.id
                               LEFT JOIN lab_pcs p ON a.lab_pc_id = p.id
                               ORDER BY a.id DESC LIMIT 6")->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-wrapper">
    <!-- Admin Sidebar -->
    <?php require_once dirname(__DIR__) . '/includes/sidebar_admin.php'; ?>

    <div class="main-content">
        <!-- Top Navbar -->
        <?php require_once dirname(__DIR__) . '/includes/navbar.php'; ?>

        <!-- Dashboard Content -->
        <div class="container-fluid p-4">
            <!-- Header greeting -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div>
                    <h3 class="fw-bold mb-1">Lab Incharge Dashboard</h3>
                    <p class="text-muted small mb-0">Overview of physical lab workstations, ongoing exams, and security alerts.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>/admin/lab_pcs.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-display me-1"></i> Live PC Grid
                    </a>
                    <a href="<?= BASE_URL ?>/admin/manage_users.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-person-plus me-1"></i> Add User
                    </a>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card stat-success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Workstations</span>
                                <h3 class="fw-bold my-1"><?= $onlinePcs ?> / <?= $totalPcs ?></h3>
                                <small class="text-success"><i class="bi bi-broadcast me-1"></i> Online & Active</small>
                            </div>
                            <div class="stat-icon bg-success-subtle text-success">
                                <i class="bi bi-pc-display"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Active Exams</span>
                                <h3 class="fw-bold my-1"><?= $activeExams ?></h3>
                                <small class="text-primary"><i class="bi bi-check-circle me-1"></i> Live In Lab</small>
                            </div>
                            <div class="stat-icon bg-primary-subtle text-primary">
                                <i class="bi bi-journal-check"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card stat-warning">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Enrolled Students</span>
                                <h3 class="fw-bold my-1"><?= $totalStudents ?></h3>
                                <small class="text-muted"><i class="bi bi-person-badge me-1"></i> <?= $totalTeachers ?> Teachers Assigned</small>
                            </div>
                            <div class="stat-icon bg-warning-subtle text-warning">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card stat-danger">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Proctor Breaches</span>
                                <h3 class="fw-bold my-1"><?= $totalViolations ?></h3>
                                <small class="text-danger"><i class="bi bi-shield-alert me-1"></i> Total Violations Logged</small>
                            </div>
                            <div class="stat-icon bg-danger-subtle text-danger">
                                <i class="bi bi-shield-slash"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Two-column Section: Attempts & System Audit -->
            <div class="row g-4">
                <!-- Recent Attempts -->
                <div class="col-lg-7">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center py-3">
                            <span class="fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i> Recent Student Attempts</span>
                            <a href="<?= BASE_URL ?>/admin/lab_pcs.php" class="btn btn-sm btn-link text-decoration-none">Monitor Live</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th>Student</th>
                                        <th>Exam</th>
                                        <th>PC</th>
                                        <th>Score</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentAttempts)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted small">No exam attempts recorded yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentAttempts as $att): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold small"><?= htmlspecialchars($att['full_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($att['roll_number'] ?? 'N/A') ?></small>
                                                </td>
                                                <td>
                                                    <small class="fw-semibold text-truncate d-block" style="max-width: 140px;"><?= htmlspecialchars($att['exam_title']) ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($att['pc_name'] ?? 'Virtual') ?></span>
                                                </td>
                                                <td>
                                                    <strong class="text-primary"><?= number_format($att['total_score'], 1) ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($att['status'] === 'completed'): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success">Completed</span>
                                                    <?php elseif ($att['status'] === 'disqualified'): ?>
                                                        <span class="badge bg-danger-subtle text-danger border border-danger">Disqualified</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning-subtle text-warning border border-warning">In Progress</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent System Audit Logs -->
                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center py-3">
                            <span class="fw-bold"><i class="bi bi-clock-history text-secondary me-2"></i> Security & Audit Stream</span>
                            <a href="<?= BASE_URL ?>/admin/system_logs.php" class="btn btn-sm btn-link text-decoration-none">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush small">
                                <?php if (empty($recentLogs)): ?>
                                    <li class="list-group-item text-center py-4 text-muted">No system logs available.</li>
                                <?php else: ?>
                                    <?php foreach ($recentLogs as $log): ?>
                                        <li class="list-group-item p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($log['action']) ?></span>
                                                <span class="text-muted" style="font-size: 0.75rem;"><?= timeAgo($log['created_at']) ?></span>
                                            </div>
                                            <div class="text-secondary text-truncate"><?= htmlspecialchars($log['details']) ?></div>
                                            <div class="text-muted" style="font-size: 0.72rem;">User: <?= htmlspecialchars($log['username'] ?? 'System') ?> &bull; IP: <?= htmlspecialchars($log['ip_address']) ?></div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- End container-fluid -->
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
