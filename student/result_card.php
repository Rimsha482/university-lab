<?php
/**
 * Official Examination Scorecard & Proctoring Audit Report
 */

require_once dirname(__DIR__) . '/auth/session_check.php';

$attemptId = !empty($_GET['attempt_id']) ? intval($_GET['attempt_id']) : 0;
if (!$attemptId) {
    header("Location: " . BASE_URL . "/student/dashboard.php");
    exit;
}

$pdo = getDb();
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'student';

// Fetch Attempt, Student & Exam Details
$stmt = $pdo->prepare("SELECT a.*, e.title as exam_title, e.exam_code, e.total_marks, e.passing_marks, e.duration_minutes,
                       c.course_code, c.course_name, c.department,
                       u.full_name, u.roll_number, u.email,
                       p.pc_name, p.lab_room
                       FROM exam_attempts a
                       JOIN exams e ON a.exam_id = e.id
                       JOIN courses c ON e.course_id = c.id
                       JOIN users u ON a.student_id = u.id
                       LEFT JOIN lab_pcs p ON a.lab_pc_id = p.id
                       WHERE a.id = ?");
$stmt->execute([$attemptId]);
$attempt = $stmt->fetch();

if (!$attempt) {
    die("Examination record not found.");
}

// Student can only view their own scorecard
if ($currentUserRole === 'student' && (int)$attempt['student_id'] !== (int)$currentUserId) {
    die("Unauthorized access to candidate scorecard.");
}

// Fetch Question Breakdown & Student Answers
$qStmt = $pdo->prepare("SELECT q.id as question_id, q.question_text, q.question_type, q.marks, q.correct_option,
                        sa.selected_option, sa.answer_text, sa.marks_awarded, sa.is_correct
                        FROM questions q
                        LEFT JOIN student_answers sa ON (sa.question_id = q.id AND sa.attempt_id = ?)
                        WHERE q.exam_id = ?
                        ORDER BY q.id ASC");
$qStmt->execute([$attemptId, $attempt['exam_id']]);
$answers = $qStmt->fetchAll();

// Calculations
$totalMarks = (float)$attempt['total_marks'];
$passingMarks = (float)$attempt['passing_marks'];
$obtainedMarks = (float)$attempt['total_score'];
$percentage = ($totalMarks > 0) ? round(($obtainedMarks / $totalMarks) * 100, 1) : 0;

$isDisqualified = ($attempt['status'] === 'disqualified');
$isPassed = (!$isDisqualified && $obtainedMarks >= $passingMarks);

// Letter grade assignment
$grade = 'F';
if (!$isDisqualified) {
    if ($percentage >= 90) $grade = 'A+';
    elseif ($percentage >= 80) $grade = 'A';
    elseif ($percentage >= 70) $grade = 'B';
    elseif ($percentage >= 60) $grade = 'C';
    elseif ($percentage >= 50) $grade = 'D';
}

$pageTitle = "Scorecard - " . $attempt['roll_number'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/logo.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #ffffff !important; padding: 0 !important; }
            .card { box-shadow: none !important; border: 1px solid #ccc !important; }
        }
        .scorecard-banner {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            border-radius: 12px 12px 0 0;
            padding: 30px;
        }
        .grade-badge {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 800;
        }
    </style>
</head>
<body class="bg-light py-4">

<div class="container my-auto">
    <!-- Action buttons (hidden in print) -->
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <?php if ($currentUserRole === 'student'): ?>
            <a href="<?= BASE_URL ?>/student/dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Return to Portal
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/teacher/view_results.php?exam_id=<?= $attempt['exam_id'] ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Result Sheet
            </a>
        <?php endif; ?>

        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print / Save PDF
        </button>
    </div>

    <!-- Official Printable Scorecard -->
    <div class="card shadow border-0 rounded-4 overflow-hidden bg-white mb-4">
        <!-- Institution Header -->
        <div class="scorecard-banner">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="University Logo" height="52" class="bg-white rounded p-1">
                    <div>
                        <h4 class="fw-bold mb-0 text-white"><?= htmlspecialchars(APP_NAME) ?></h4>
                        <div class="text-white-50 small"><?= htmlspecialchars($attempt['department'] ?? 'Department of Examinations') ?></div>
                    </div>
                </div>
                <div class="text-md-end">
                    <span class="badge bg-light text-dark font-monospace px-3 py-2 fs-6">
                        ATTEMPT #<?= str_pad($attempt['id'], 6, '0', STR_PAD_LEFT) ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="card-body p-4 p-md-5">
            <!-- Candidate & Exam Info Grid -->
            <div class="row g-3 mb-4 pb-3 border-bottom">
                <div class="col-sm-6 col-md-3">
                    <small class="text-muted text-uppercase fw-semibold d-block">Candidate Name</small>
                    <strong class="text-dark fs-6"><?= htmlspecialchars($attempt['full_name']) ?></strong>
                </div>
                <div class="col-sm-6 col-md-3">
                    <small class="text-muted text-uppercase fw-semibold d-block">Roll Number</small>
                    <strong class="font-monospace text-primary fs-6"><?= htmlspecialchars($attempt['roll_number'] ?? 'N/A') ?></strong>
                </div>
                <div class="col-sm-6 col-md-3">
                    <small class="text-muted text-uppercase fw-semibold d-block">Course / Subject</small>
                    <strong class="text-dark fs-6"><?= htmlspecialchars($attempt['course_code']) ?> - <?= htmlspecialchars($attempt['course_name']) ?></strong>
                </div>
                <div class="col-sm-6 col-md-3">
                    <small class="text-muted text-uppercase fw-semibold d-block">Workstation Terminal</small>
                    <strong class="text-dark fs-6"><?= htmlspecialchars($attempt['pc_name'] ?? 'Virtual Workstation') ?></strong>
                </div>
            </div>

            <!-- Score Summary Cards -->
            <div class="row g-4 align-items-center mb-5">
                <div class="col-md-7">
                    <div class="p-4 rounded-4 bg-light border">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted fw-bold text-uppercase small">Assessment Evaluation</span>
                            <?php if ($isDisqualified): ?>
                                <span class="badge bg-danger fs-6 px-3 py-2"><i class="bi bi-shield-slash me-1"></i> DISQUALIFIED</span>
                            <?php elseif ($isPassed): ?>
                                <span class="badge bg-success fs-6 px-3 py-2"><i class="bi bi-check-circle me-1"></i> PASSED</span>
                            <?php else: ?>
                                <span class="badge bg-danger fs-6 px-3 py-2"><i class="bi bi-x-circle me-1"></i> FAILED</span>
                            <?php endif; ?>
                        </div>

                        <div class="row g-3 text-center">
                            <div class="col-4">
                                <small class="text-muted d-block">Marks Scored</small>
                                <h3 class="fw-bold my-1 <?= $isPassed ? 'text-success' : 'text-danger' ?>">
                                    <?= number_format($obtainedMarks, 1) ?>
                                </h3>
                                <small class="text-muted">Out of <?= $totalMarks ?></small>
                            </div>
                            <div class="col-4 border-start border-end">
                                <small class="text-muted d-block">Percentage</small>
                                <h3 class="fw-bold my-1 text-dark"><?= $percentage ?>%</h3>
                                <small class="text-muted">Passing: <?= $passingMarks ?></small>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block">Overall Grade</small>
                                <h3 class="fw-bold my-1 <?= $isPassed ? 'text-primary' : 'text-danger' ?>"><?= $grade ?></h3>
                                <small class="text-muted">Standard Scale</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Anti-Cheat Proctoring Audit -->
                <div class="col-md-5">
                    <div class="p-4 rounded-4 border <?= ($attempt['violation_count'] > 0 ? 'bg-danger-subtle border-danger' : 'bg-success-subtle border-success') ?>">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi <?= ($attempt['violation_count'] > 0 ? 'bi-shield-exclamation text-danger' : 'bi-shield-check text-success') ?> fs-4"></i>
                            <h6 class="fw-bold mb-0 text-dark">Proctoring Telemetry Audit</h6>
                        </div>
                        <p class="small text-secondary mb-3">
                            Workstation monitor tracking tab navigation, fullscreen integrity, and clipboard behavior.
                        </p>
                        <div class="d-flex justify-content-between align-items-center small">
                            <span>Recorded Security Breaches:</span>
                            <span class="badge <?= ($attempt['violation_count'] > 0 ? 'bg-danger' : 'bg-success') ?> px-2 py-1">
                                <?= $attempt['violation_count'] ?> Breaches
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center small mt-2 pt-2 border-top">
                            <span>Submission Trigger:</span>
                            <strong><?= ($attempt['is_auto_submitted'] ? 'Automated Security Lock' : 'Normal Candidate Submit') ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Question-by-Question Response Audit -->
            <h5 class="fw-bold mb-3"><i class="bi bi-list-check text-primary me-2"></i> Question-by-Question Evaluation</h5>
            <div class="table-responsive">
                <table class="table table-bordered align-middle small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 50%;">Question</th>
                            <th style="width: 15%;">Candidate Choice</th>
                            <th style="width: 15%;">Answer Key</th>
                            <th style="width: 15%;" class="text-center">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($answers as $idx => $ans): 
                            $correct = ($ans['is_correct'] == 1);
                        ?>
                            <tr>
                                <td class="fw-bold text-center"><?= $idx + 1 ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= nl2br(htmlspecialchars($ans['question_text'])) ?></div>
                                    <?php if (!empty($ans['answer_text'])): ?>
                                        <div class="text-muted font-monospace mt-1 small bg-light p-2 rounded">
                                            <?= nl2br(htmlspecialchars($ans['answer_text'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($ans['selected_option'])): ?>
                                        <span class="badge <?= $correct ? 'bg-success' : 'bg-danger' ?> px-2 py-1">
                                            Option <?= htmlspecialchars($ans['selected_option']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">Unanswered</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1">
                                        Option <?= htmlspecialchars($ans['correct_option']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <strong class="<?= $correct ? 'text-success' : 'text-muted' ?>">
                                        <?= number_format($ans['marks_awarded'] ?? 0, 1) ?>
                                    </strong>
                                    <span class="text-muted">/ <?= $ans['marks'] ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Footer Sign-off -->
            <div class="row mt-5 pt-4 border-top text-center text-muted small">
                <div class="col-4">
                    <div class="border-top pt-2">Candidate Signature</div>
                </div>
                <div class="col-4">
                    <div class="border-top pt-2">Lab Invigilator / Examiner</div>
                </div>
                <div class="col-4">
                    <div class="border-top pt-2">Controller of Examinations</div>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>
