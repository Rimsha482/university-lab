<?php
/**
 * Live Invigilation & Proctoring Telemetry Center
 */

require_once dirname(__DIR__) . '/auth/role_guard.php';
requireRole(['teacher', 'admin']);

$pageTitle = "Live Proctoring";
$teacherId = $_SESSION['user_id'];
$pdo = getDb();

// Handle Manual Disqualification action from teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'force_disqualify') {
    $attemptId = intval($_POST['attempt_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("UPDATE exam_attempts SET status = 'disqualified', is_auto_submitted = 1, submitted_at = NOW() WHERE id = ?");
        $stmt->execute([$attemptId]);
        logSystemActivity($_SESSION['user_id'], 'TEACHER_DISQUALIFIED_STUDENT', "Examiner manually disqualified attempt #{$attemptId}");
    } catch (Exception $e) {
        // ignore
    }
}

// Fetch Teacher's active or recent exams
$examStmt = $pdo->prepare("SELECT id, title, exam_code FROM exams WHERE teacher_id = ? OR ? = 'admin' ORDER BY id DESC");
$examStmt->execute([$teacherId, $_SESSION['role']]);
$exams = $examStmt->fetchAll();

$selectedExamId = !empty($_GET['exam_id']) ? intval($_GET['exam_id']) : (!empty($exams[0]['id']) ? $exams[0]['id'] : 0);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once dirname(__DIR__) . '/includes/sidebar_teacher.php'; ?>

    <div class="main-content">
        <?php require_once dirname(__DIR__) . '/includes/navbar.php'; ?>

        <div class="container-fluid p-4">
            <!-- Header -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div>
                    <h3 class="fw-bold mb-1 d-flex align-items-center gap-2">
                        <span class="pulse-indicator red"></span> Live Proctoring Telemetry
                    </h3>
                    <p class="text-muted small mb-0">Active invigilation stream with automated tab-switch, blur, and fullscreen breach alarms.</p>
                </div>
                <!-- Exam Filter -->
                <div class="d-flex align-items-center gap-2">
                    <form method="GET" action="" class="d-flex align-items-center gap-2">
                        <label class="form-label small fw-semibold text-nowrap mb-0">Monitoring Exam:</label>
                        <select name="exam_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php foreach ($exams as $ex): ?>
                                <option value="<?= $ex['id'] ?>" <?= ($selectedExamId === (int)$ex['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ex['title']) ?> (<?= htmlspecialchars($ex['exam_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Summary KPI Bar -->
            <div class="row g-3 mb-4">
                <div class="col-sm-4">
                    <div class="card stat-card stat-success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Candidates Active</span>
                                <h3 class="fw-bold my-1" id="statActiveStudents">0</h3>
                                <small class="text-success"><i class="bi bi-broadcast me-1"></i> Inside Locked Exam Room</small>
                            </div>
                            <div class="stat-icon bg-success-subtle text-success">
                                <i class="bi bi-person-check"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-4">
                    <div class="card stat-card stat-danger">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Security Breaches</span>
                                <h3 class="fw-bold my-1 text-danger" id="statTotalViolations">0</h3>
                                <small class="text-danger"><i class="bi bi-shield-slash me-1"></i> Recorded Violations</small>
                            </div>
                            <div class="stat-icon bg-danger-subtle text-danger">
                                <i class="bi bi-shield-alert"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-4">
                    <div class="card stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Papers Submitted</span>
                                <h3 class="fw-bold my-1" id="statSubmittedCount">0</h3>
                                <small class="text-primary"><i class="bi bi-check2-all me-1"></i> Completed or Locked</small>
                            </div>
                            <div class="stat-icon bg-primary-subtle text-primary">
                                <i class="bi bi-file-earmark-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Two-Column Proctoring Room -->
            <div class="row g-4">
                <!-- Left: Candidate Workstation Grid -->
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark"><i class="bi bi-people-fill text-primary me-2"></i> Examinee Workstations</span>
                            <span class="badge bg-light text-secondary border small">Auto-refreshes every 3s</span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3" id="liveProctorContainer">
                                <div class="col-12 text-center py-5 text-muted">
                                    <div class="spinner-border text-primary mb-2" role="status"></div>
                                    <p class="mb-0">Establishing live telemetry stream with examination room...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Real-Time Breach Feed -->
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-danger"><i class="bi bi-bell-fill me-2"></i> Live Security Log</span>
                            <span class="pulse-indicator red"></span>
                        </div>
                        <div class="card-body p-0" style="max-height: 520px; overflow-y: auto;">
                            <ul class="list-group list-group-flush" id="liveAlertsFeed">
                                <li class="list-group-item text-center py-4 text-muted small">
                                    <i class="bi bi-shield-check fs-2 text-success d-block mb-1"></i>
                                    Awaiting telemetry signals. Cheating breaches will stream here in real time.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Local Scripts -->
<script src="<?= BASE_URL ?>/assets/js/lab_monitor.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const monitor = new LabMonitor({
            endpointUrl: '<?= BASE_URL ?>/api/fetch_live_alerts.php',
            examId: <?= (int)$selectedExamId ?>,
            pollInterval: 3000,
            containerId: 'liveProctorContainer',
            alertsListId: 'liveAlertsFeed'
        });
    });
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
