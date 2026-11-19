<?php
/**
 * Exam Instructions & System Readiness Verification
 */

require_once dirname(__DIR__) . '/auth/role_guard.php';
requireRole('student');

$studentId = $_SESSION['user_id'];
$examId = !empty($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$pdo = getDb();

// Fetch Exam Details
$stmt = $pdo->prepare("SELECT e.*, c.course_code, c.course_name, u.full_name as teacher_name,
                       (SELECT COUNT(*) FROM questions q WHERE q.exam_id = e.id) as question_count
                       FROM exams e
                       JOIN courses c ON e.course_id = c.id
                       JOIN users u ON e.teacher_id = u.id
                       WHERE e.id = ?");
$stmt->execute([$examId]);
$exam = $stmt->fetch();

if (!$exam) {
    header("Location: " . BASE_URL . "/student/dashboard.php");
    exit;
}

// Check existing attempt
$attStmt = $pdo->prepare("SELECT id, status FROM exam_attempts WHERE exam_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1");
$attStmt->execute([$examId, $studentId]);
$existingAttempt = $attStmt->fetch();

if ($existingAttempt && in_array($existingAttempt['status'], ['completed', 'disqualified', 'timed_out'])) {
    header("Location: " . BASE_URL . "/student/result_card.php?attempt_id=" . $existingAttempt['id']);
    exit;
}

$pageTitle = "Exam Instructions - " . $exam['title'];
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-wrapper">
    <div class="main-content">
        <?php require_once dirname(__DIR__) . '/includes/navbar.php'; ?>

        <div class="container py-4">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="card shadow-sm border-0 rounded-4">
                        <div class="card-header bg-dark text-white p-4 rounded-top-4">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="badge bg-primary font-monospace px-3 py-1 mb-2"><?= htmlspecialchars($exam['course_code']) ?></span>
                                    <h3 class="fw-bold mb-1"><?= htmlspecialchars($exam['title']) ?></h3>
                                    <p class="text-white-50 mb-0">Examiner: <?= htmlspecialchars($exam['teacher_name']) ?> &bull; Session 2026</p>
                                </div>
                                <span class="badge bg-warning text-dark px-3 py-2 fs-6">
                                    <i class="bi bi-clock me-1"></i> <?= $exam['duration_minutes'] ?> Minutes
                                </span>
                            </div>
                        </div>

                        <div class="card-body p-4 p-md-5">
                            <!-- Quick Meta Badges -->
                            <div class="row g-3 mb-4 text-center">
                                <div class="col-4">
                                    <div class="p-3 bg-light rounded-3 border">
                                        <small class="text-muted text-uppercase fw-semibold d-block">Questions</small>
                                        <h4 class="fw-bold my-1 text-primary"><?= $exam['question_count'] ?></h4>
                                        <small class="text-muted">MCQs</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-3 bg-light rounded-3 border">
                                        <small class="text-muted text-uppercase fw-semibold d-block">Maximum Marks</small>
                                        <h4 class="fw-bold my-1 text-dark"><?= $exam['total_marks'] ?></h4>
                                        <small class="text-muted">Passing: <?= $exam['passing_marks'] ?></small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-3 bg-light rounded-3 border">
                                        <small class="text-muted text-uppercase fw-semibold d-block">Max Violations</small>
                                        <h4 class="fw-bold my-1 text-danger"><?= $exam['max_violations'] ?></h4>
                                        <small class="text-danger">Auto-Disqualification</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Special Instructions -->
                            <?php if (!empty($exam['description'])): ?>
                                <div class="alert alert-info border-info mb-4">
                                    <h6 class="fw-bold mb-1"><i class="bi bi-info-circle me-1"></i> Examiner Instructions:</h6>
                                    <p class="mb-0 small"><?= nl2br(htmlspecialchars($exam['description'])) ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Mandatory Anti-Cheat Rules -->
                            <h5 class="fw-bold mb-3"><i class="bi bi-shield-exclamation text-danger me-2"></i> Mandatory Proctoring Code of Conduct</h5>
                            <div class="border rounded-3 p-3 mb-4 bg-light">
                                <ul class="small mb-0 d-flex flex-column gap-2 text-secondary ps-3">
                                    <li><strong class="text-dark">Strict Fullscreen Mode:</strong> The assessment will immediately launch into full-screen. Exiting full-screen is logged as a security breach.</li>
                                    <li><strong class="text-dark">Zero Tab Switching:</strong> Navigating to another browser tab, window, or opening external software triggers an immediate violation alarm.</li>
                                    <li><strong class="text-dark">Disabled Clipboard & Context Menu:</strong> Right-clicking, copying, cutting, pasting, and developer inspection keys (`F12`, `Ctrl+Shift+I`) are permanently blocked.</li>
                                    <li><strong class="text-dark">Real-Time Heartbeat:</strong> Your terminal PC sends a live status ping every 10 seconds to the teacher's proctoring monitor.</li>
                                    <li><strong class="text-danger">Automatic Disqualification:</strong> If you accumulate <strong><?= $exam['max_violations'] ?> violations</strong>, your assessment is instantly locked, terminated, and auto-submitted with zero score.</li>
                                </ul>
                            </div>

                            <!-- Readiness Check -->
                            <div class="p-3 border rounded-3 mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="agreeTermsCheck" onchange="toggleStartBtn()">
                                    <label class="form-check-label fw-semibold small text-dark" for="agreeTermsCheck">
                                        I have read, understood, and agree to adhere to the university proctoring regulations. I consent to real-time tab monitoring and fullscreen integrity enforcement.
                                    </label>
                                </div>
                            </div>

                            <!-- Start Button -->
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="<?= BASE_URL ?>/student/dashboard.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Cancel & Return
                                </a>
                                <button type="button" class="btn btn-primary btn-lg px-4 fw-bold" id="startExamBtn" disabled onclick="launchExamEnvironment()">
                                    <i class="bi bi-fullscreen me-1"></i> Enter Exam Room
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleStartBtn() {
        const check = document.getElementById('agreeTermsCheck');
        const btn = document.getElementById('startExamBtn');
        btn.disabled = !check.checked;
    }

    function launchExamEnvironment() {
        // Request fullscreen before navigating to exam room
        const el = document.documentElement;
        const req = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen;
        
        if (req) {
            req.call(el).then(() => {
                window.location.href = "<?= BASE_URL ?>/student/exam_room.php?exam_id=<?= $exam['id'] ?>";
            }).catch(() => {
                // If browser blocks initial request, proceed anyway to room which has an overlay prompt
                window.location.href = "<?= BASE_URL ?>/student/exam_room.php?exam_id=<?= $exam['id'] ?>";
            });
        } else {
            window.location.href = "<?= BASE_URL ?>/student/exam_room.php?exam_id=<?= $exam['id'] ?>";
        }
    }
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
