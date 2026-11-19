<?php
/**
 * Teacher / Examiner Control Dashboard
 */

require_once dirname(__DIR__) . '/auth/role_guard.php';
requireRole(['teacher', 'admin']);

$pageTitle = "Teacher Dashboard";
$teacherId = $_SESSION['user_id'];
$pdo = getDb();

// 1. Fetch Teacher Stats
$examCount = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE teacher_id = ?");
$examCount->execute([$teacherId]);
$totalExams = $examCount->fetchColumn();

$activeExamCount = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE teacher_id = ? AND status IN ('published', 'ongoing')");
$activeExamCount->execute([$teacherId]);
$totalActiveExams = $activeExamCount->fetchColumn();

$questionCount = $pdo->prepare("SELECT COUNT(*) FROM questions q JOIN exams e ON q.exam_id = e.id WHERE e.teacher_id = ?");
$questionCount->execute([$teacherId]);
$totalQuestions = $questionCount->fetchColumn();

$studentEvaluatedCount = $pdo->prepare("SELECT COUNT(DISTINCT a.student_id) FROM exam_attempts a JOIN exams e ON a.exam_id = e.id WHERE e.teacher_id = ?");
$studentEvaluatedCount->execute([$teacherId]);
$totalStudentsEvaluated = $studentEvaluatedCount->fetchColumn();

// 2. Fetch Teacher's Exams with Subject & Attempt counts
$examStmt = $pdo->prepare("SELECT e.*, c.course_code, c.course_name,
                           (SELECT COUNT(*) FROM questions q WHERE q.exam_id = e.id) as question_count,
                           (SELECT COUNT(*) FROM exam_attempts a WHERE a.exam_id = e.id) as attempt_count,
                           (SELECT COUNT(*) FROM exam_attempts a WHERE a.exam_id = e.id AND a.status = 'in_progress') as active_students
                           FROM exams e
                           JOIN courses c ON e.course_id = c.id
                           WHERE e.teacher_id = ?
                           ORDER BY e.id DESC");
$examStmt->execute([$teacherId]);
$myExams = $examStmt->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once dirname(__DIR__) . '/includes/sidebar_teacher.php'; ?>

    <div class="main-content">
        <?php require_once dirname(__DIR__) . '/includes/navbar.php'; ?>

        <div class="container-fluid p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div>
                    <h3 class="fw-bold mb-1">Examiner Control Center</h3>
                    <p class="text-muted small mb-0">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>. Manage test schedules, questions, and live invigilation.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>/teacher/create_exam.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i> Create New Exam
                    </a>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">My Exams</span>
                                <h3 class="fw-bold my-1"><?= $totalExams ?></h3>
                                <small class="text-primary"><i class="bi bi-journal-check me-1"></i> Total Scheduled</small>
                            </div>
                            <div class="stat-icon bg-primary-subtle text-primary">
                                <i class="bi bi-file-earmark-text"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card stat-success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Live Ongoing</span>
                                <h3 class="fw-bold my-1"><?= $totalActiveExams ?></h3>
                                <small class="text-success"><i class="bi bi-broadcast me-1"></i> Active in Labs</small>
                            </div>
                            <div class="stat-icon bg-success-subtle text-success">
                                <i class="bi bi-shield-shaded"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card stat-warning">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Questions Authored</span>
                                <h3 class="fw-bold my-1"><?= $totalQuestions ?></h3>
                                <small class="text-warning"><i class="bi bi-patch-question me-1"></i> In Question Bank</small>
                            </div>
                            <div class="stat-icon bg-warning-subtle text-warning">
                                <i class="bi bi-question-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card stat-danger">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Candidates Evaluated</span>
                                <h3 class="fw-bold my-1"><?= $totalStudentsEvaluated ?></h3>
                                <small class="text-muted"><i class="bi bi-mortarboard me-1"></i> Unique Examinees</small>
                            </div>
                            <div class="stat-icon bg-danger-subtle text-danger">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Exams List Table -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="bi bi-list-task text-primary me-2"></i> Scheduled Examinations</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Exam Details</th>
                                <th>Course</th>
                                <th>Duration</th>
                                <th>Questions</th>
                                <th>Status</th>
                                <th>Live In Lab</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($myExams)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted small">No exams scheduled yet. Click "Create New Exam" to begin.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($myExams as $exam): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark small"><?= htmlspecialchars($exam['title']) ?></div>
                                            <span class="badge bg-light text-dark border font-monospace" style="font-size:0.7rem;"><?= htmlspecialchars($exam['exam_code']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary border border-primary font-monospace"><?= htmlspecialchars($exam['course_code']) ?></span>
                                            <small class="text-muted d-block"><?= htmlspecialchars($exam['course_name']) ?></small>
                                        </td>
                                        <td>
                                            <small><i class="bi bi-clock me-1 text-muted"></i><?= $exam['duration_minutes'] ?> mins</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= $exam['question_count'] ?> MCQs</span>
                                        </td>
                                        <td>
                                            <?php if ($exam['status'] === 'published'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success">Published</span>
                                            <?php elseif ($exam['status'] === 'completed'): ?>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary">Completed</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning">Draft</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($exam['active_students'] > 0): ?>
                                                <span class="badge bg-danger animate-pulse">
                                                    <i class="bi bi-broadcast me-1"></i> <?= $exam['active_students'] ?> Taking Now
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">0 active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= BASE_URL ?>/teacher/live_proctor.php?exam_id=<?= $exam['id'] ?>" class="btn btn-outline-danger" title="Live Proctoring">
                                                    <i class="bi bi-shield-shaded"></i> Live
                                                </a>
                                                <a href="<?= BASE_URL ?>/teacher/manage_questions.php?exam_id=<?= $exam['id'] ?>" class="btn btn-outline-primary" title="Edit Questions">
                                                    <i class="bi bi-patch-question"></i> Questions
                                                </a>
                                                <a href="<?= BASE_URL ?>/teacher/view_results.php?exam_id=<?= $exam['id'] ?>" class="btn btn-outline-success" title="View Results">
                                                    <i class="bi bi-card-checklist"></i> Results
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
