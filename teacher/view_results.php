<?php
/**
 * Exam Results & Proctoring Audit Reports
 */

require_once dirname(__DIR__) . '/auth/role_guard.php';
requireRole(['teacher', 'admin']);

$pageTitle = "Exam Results";
$teacherId = $_SESSION['user_id'];
$pdo = getDb();

// Fetch Teacher's Exams
$examStmt = $pdo->prepare("SELECT id, title, exam_code, total_marks, passing_marks FROM exams WHERE teacher_id = ? OR ? = 'admin' ORDER BY id DESC");
$examStmt->execute([$teacherId, $_SESSION['role']]);
$exams = $examStmt->fetchAll();

$selectedExamId = !empty($_GET['exam_id']) ? intval($_GET['exam_id']) : (!empty($exams[0]['id']) ? $exams[0]['id'] : 0);

// Fetch attempts for selected exam
$attempts = [];
$currentExam = null;
if ($selectedExamId > 0) {
    foreach ($exams as $e) {
        if ($e['id'] == $selectedExamId) {
            $currentExam = $e;
            break;
        }
    }

    $attStmt = $pdo->prepare("SELECT a.*, u.full_name, u.roll_number, u.email, p.pc_name
                              FROM exam_attempts a
                              JOIN users u ON a.student_id = u.id
                              LEFT JOIN lab_pcs p ON a.lab_pc_id = p.id
                              WHERE a.exam_id = ?
                              ORDER BY a.total_score DESC, a.violation_count ASC");
    $attStmt->execute([$selectedExamId]);
    $attempts = $attStmt->fetchAll();
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once dirname(__DIR__) . '/includes/sidebar_teacher.php'; ?>

    <div class="main-content">
        <?php require_once dirname(__DIR__) . '/includes/navbar.php'; ?>

        <div class="container-fluid p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div>
                    <h3 class="fw-bold mb-1">Candidate Results & Scorecards</h3>
                    <p class="text-muted small mb-0">Review graded assessments, anti-cheat violations, and question-level responses.</p>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($selectedExamId > 0): ?>
                        <a href="<?= BASE_URL ?>/teacher/export_csv.php?exam_id=<?= $selectedExamId ?>" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Excel/CSV
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Exam Selector -->
            <div class="card mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="" class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <select name="exam_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <?php foreach ($exams as $ex): ?>
                                    <option value="<?= $ex['id'] ?>" <?= ($selectedExamId === (int)$ex['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ex['title']) ?> (<?= htmlspecialchars($ex['exam_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($currentExam): ?>
                            <div class="col-md-7 text-md-end small text-muted">
                                Total Attempts: <strong><?= count($attempts) ?></strong> &bull; Total Marks: <strong><?= $currentExam['total_marks'] ?></strong> &bull; Pass Mark: <strong><?= $currentExam['passing_marks'] ?></strong>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Results Table -->
            <div class="card shadow-sm border-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Roll Number</th>
                                <th>Student Name</th>
                                <th>Terminal PC</th>
                                <th>Marks Obtained</th>
                                <th>Percentage</th>
                                <th>Result</th>
                                <th>Violations</th>
                                <th>Submission Time</th>
                                <th class="text-end">Breakdown</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attempts)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted small">No submitted attempts found for this examination yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attempts as $att): 
                                    $pct = ($currentExam['total_marks'] > 0) ? round(($att['total_score'] / $currentExam['total_marks']) * 100, 1) : 0;
                                    $isPassed = ($att['total_score'] >= $currentExam['passing_marks'] && $att['status'] !== 'disqualified');
                                ?>
                                    <tr>
                                        <td>
                                            <span class="font-monospace fw-bold small"><?= htmlspecialchars($att['roll_number'] ?? 'N/A') ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold small"><?= htmlspecialchars($att['full_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($att['email']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($att['pc_name'] ?? 'Virtual') ?></span>
                                        </td>
                                        <td>
                                            <strong class="fs-6 <?= $isPassed ? 'text-success' : 'text-danger' ?>">
                                                <?= number_format($att['total_score'], 1) ?>
                                            </strong>
                                            <span class="text-muted small">/ <?= $currentExam['total_marks'] ?></span>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?= $pct ?>%</div>
                                        </td>
                                        <td>
                                            <?php if ($att['status'] === 'disqualified'): ?>
                                                <span class="badge bg-danger">Disqualified</span>
                                            <?php elseif ($isPassed): ?>
                                                <span class="badge bg-success">Passed</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Failed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= ($att['violation_count'] > 0) ? 'bg-warning text-dark' : 'bg-light text-muted border' ?>">
                                                <?= $att['violation_count'] ?> Breaches
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= htmlspecialchars($att['submitted_at'] ?? 'In Progress') ?></small>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL ?>/student/result_card.php?attempt_id=<?= $att['id'] ?>&view=teacher" class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="bi bi-eye"></i> View Card
                                            </a>
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
