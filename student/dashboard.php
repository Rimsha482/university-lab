<?php
/**
 * Student Examination Portal & Dashboard
 */

require_once dirname(__DIR__) . '/auth/role_guard.php';
requireRole('student');

$pageTitle = "Student Portal";
$studentId = $_SESSION['user_id'];
$pdo = getDb();

// 1. Fetch Student Profile Details
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$studentId]);
$student = $userStmt->fetch();

// 2. Fetch Available & Upcoming Exams
$availableExams = $pdo->query("SELECT e.*, c.course_code, c.course_name, u.full_name as teacher_name,
                               (SELECT COUNT(*) FROM questions q WHERE q.exam_id = e.id) as question_count,
                               (SELECT id FROM exam_attempts a WHERE a.exam_id = e.id AND a.student_id = {$studentId} ORDER BY a.id DESC LIMIT 1) as existing_attempt_id,
                               (SELECT status FROM exam_attempts a WHERE a.exam_id = e.id AND a.student_id = {$studentId} ORDER BY a.id DESC LIMIT 1) as attempt_status
                               FROM exams e
                               JOIN courses c ON e.course_id = c.id
                               JOIN users u ON e.teacher_id = u.id
                               WHERE e.status IN ('published', 'ongoing')
                               ORDER BY e.start_time ASC")->fetchAll();

// 3. Fetch Completed / Past Results for this Student
$resultsStmt = $pdo->prepare("SELECT a.*, e.title as exam_title, e.exam_code, e.total_marks, e.passing_marks, c.course_code
                              FROM exam_attempts a
                              JOIN exams e ON a.exam_id = e.id
                              JOIN courses c ON e.course_id = c.id
                              WHERE a.student_id = ? AND a.status IN ('completed', 'disqualified', 'timed_out')
                              ORDER BY a.submitted_at DESC");
$resultsStmt->execute([$studentId]);
$pastResults = $resultsStmt->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-wrapper">
    <div class="main-content">
        <!-- Top Student Navbar -->
        <?php require_once dirname(__DIR__) . '/includes/navbar.php'; ?>

        <div class="container py-4">
            <!-- Student Banner -->
            <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); color: #fff;">
                <div class="card-body p-4 p-md-5 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4">
                    <div>
                        <span class="badge bg-white bg-opacity-25 px-3 py-1 mb-2 text-uppercase font-monospace" style="letter-spacing: 0.5px;">
                            Roll No: <?= htmlspecialchars($student['roll_number'] ?? 'CS-STUDENT') ?>
                        </span>
                        <h2 class="fw-bold mb-1">Welcome, <?= htmlspecialchars($student['full_name']) ?>!</h2>
                        <p class="text-white-50 mb-0">University Examination System &bull; Active Academic Session 2026</p>
                    </div>
                    <?php if (!empty($_SESSION['lab_pc_name'])): ?>
                        <div class="bg-white bg-opacity-10 border border-white border-opacity-25 p-3 rounded-3 text-center">
                            <span class="text-white-50 small d-block">Workstation Terminal</span>
                            <h4 class="fw-bold my-1 text-white"><i class="bi bi-display me-1 text-info"></i> <?= htmlspecialchars($_SESSION['lab_pc_name']) ?></h4>
                            <small class="badge bg-success-subtle text-success border border-success">Heartbeat Online</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Two Columns: Available Exams vs Recent Scorecards -->
            <div class="row g-4">
                <!-- Available Exams -->
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-pencil-square text-primary me-2"></i> Scheduled Examinations</h5>
                        </div>
                        <div class="card-body p-3">
                            <?php if (empty($availableExams)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                                    <p class="mb-0">No active examinations are scheduled for your enrolled courses right now.</p>
                                </div>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($availableExams as $exam): 
                                        $hasAttempted = in_array($exam['attempt_status'], ['completed', 'disqualified', 'timed_out']);
                                        $isInProgress = ($exam['attempt_status'] === 'in_progress');
                                    ?>
                                        <div class="card border rounded-3 p-3">
                                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                                <div>
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        <span class="badge bg-primary-subtle text-primary border border-primary font-monospace"><?= htmlspecialchars($exam['course_code']) ?></span>
                                                        <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($exam['exam_code']) ?></span>
                                                        <span class="badge bg-secondary"><i class="bi bi-clock me-1"></i><?= $exam['duration_minutes'] ?> Mins</span>
                                                    </div>
                                                    <h5 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($exam['title']) ?></h5>
                                                    <div class="text-muted small">
                                                        Course: <?= htmlspecialchars($exam['course_name']) ?> &bull; Examiner: <?= htmlspecialchars($exam['teacher_name']) ?>
                                                    </div>
                                                </div>

                                                <div class="text-md-end">
                                                    <?php if ($hasAttempted): ?>
                                                        <a href="<?= BASE_URL ?>/student/result_card.php?attempt_id=<?= $exam['existing_attempt_id'] ?>" class="btn btn-outline-success btn-sm">
                                                            <i class="bi bi-award me-1"></i> View Result
                                                        </a>
                                                    <?php elseif ($isInProgress): ?>
                                                        <a href="<?= BASE_URL ?>/student/exam_room.php?exam_id=<?= $exam['id'] ?>" class="btn btn-warning btn-sm fw-bold">
                                                            <i class="bi bi-play-circle me-1"></i> Resume Exam
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="<?= BASE_URL ?>/student/instructions.php?exam_id=<?= $exam['id'] ?>" class="btn btn-primary btn-sm px-3 fw-semibold">
                                                            <i class="bi bi-arrow-right-circle me-1"></i> Start Exam
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Past Results -->
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-award-fill text-warning me-2"></i> Past Scorecards</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($pastResults)): ?>
                                <div class="text-center py-5 text-muted small">
                                    <i class="bi bi-journal-check fs-2 d-block mb-1"></i>
                                    No completed assessments yet.
                                </div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($pastResults as $res): 
                                        $isPass = ($res['total_score'] >= $res['passing_marks'] && $res['status'] !== 'disqualified');
                                    ?>
                                        <li class="list-group-item p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="badge bg-light text-dark border font-monospace small"><?= htmlspecialchars($res['course_code']) ?></span>
                                                <span class="badge <?= $isPass ? 'bg-success' : 'bg-danger' ?>">
                                                    <?= $isPass ? 'PASSED' : ($res['status'] === 'disqualified' ? 'DISQUALIFIED' : 'FAILED') ?>
                                                </span>
                                            </div>
                                            <h6 class="fw-bold mb-1 small text-truncate"><?= htmlspecialchars($res['exam_title']) ?></h6>
                                            <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                                <div class="small">
                                                    Score: <strong class="<?= $isPass ? 'text-success' : 'text-danger' ?>"><?= number_format($res['total_score'], 1) ?></strong> / <?= $res['total_marks'] ?>
                                                </div>
                                                <a href="<?= BASE_URL ?>/student/result_card.php?attempt_id=<?= $res['id'] ?>" class="btn btn-sm btn-link text-decoration-none p-0 small">
                                                    Scorecard &rarr;
                                                </a>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- End container -->
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
