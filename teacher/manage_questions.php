<?php
/**
 * Question Bank Management Module
 */

require_once dirname(__DIR__) . '/auth/role_guard.php';
requireRole(['teacher', 'admin']);

$pageTitle = "Manage Questions";
$teacherId = $_SESSION['user_id'];
$pdo = getDb();
$message = '';
$error = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'created') {
    $message = "Exam saved successfully! You may now populate questions into the paper.";
}

// 1. Fetch Teacher's Exams for selection dropdown
$examsStmt = $pdo->prepare("SELECT id, title, exam_code, total_marks FROM exams WHERE teacher_id = ? OR ? = 'admin' ORDER BY id DESC");
$examsStmt->execute([$teacherId, $_SESSION['role']]);
$teacherExams = $examsStmt->fetchAll();

$selectedExamId = !empty($_GET['exam_id']) ? intval($_GET['exam_id']) : (!empty($teacherExams[0]['id']) ? $teacherExams[0]['id'] : 0);

// Handle Actions: Add Question, Delete Question
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_question') {
        $examId = intval($_POST['exam_id'] ?? 0);
        $qText  = trim($_POST['question_text'] ?? '');
        $qType  = in_array($_POST['question_type'] ?? '', ['mcq', 'coding', 'short']) ? $_POST['question_type'] : 'mcq';
        $optA   = trim($_POST['option_a'] ?? '');
        $optB   = trim($_POST['option_b'] ?? '');
        $optC   = trim($_POST['option_c'] ?? '');
        $optD   = trim($_POST['option_d'] ?? '');
        $correct = strtoupper(trim($_POST['correct_option'] ?? 'A'));
        $marks   = floatval($_POST['marks'] ?? 2.00);

        if (empty($qText) || ($qType === 'mcq' && (empty($optA) || empty($optB)))) {
            $error = "Please provide question text and at least options A and B.";
        } else {
            try {
                $imagePath = null;
                // Handle optional image upload
                if (!empty($_FILES['question_image']['name']) && $_FILES['question_image']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['question_image']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
                        $imageFilename = 'q_' . time() . '_' . rand(100, 999) . '.' . $ext;
                        $uploadTarget = UPLOAD_DIR_QUESTIONS . $imageFilename;
                        if (move_uploaded_file($_FILES['question_image']['tmp_name'], $uploadTarget)) {
                            $imagePath = $imageFilename;
                        }
                    }
                }

                $stmt = $pdo->prepare("INSERT INTO questions (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_option, marks, question_image) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$examId, $qText, $qType, $optA, $optB, $optC, $optD, $correct, $marks, $imagePath]);
                $message = "Question added to exam successfully.";
            } catch (Exception $e) {
                $error = "Error adding question: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_question') {
        $qId = intval($_POST['question_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
            $stmt->execute([$qId]);
            $message = "Question deleted.";
        } catch (Exception $e) {
            $error = "Error deleting question: " . $e->getMessage();
        }
    }
}

// Fetch selected exam details
$currentExam = null;
$questions = [];
if ($selectedExamId > 0) {
    $eStmt = $pdo->prepare("SELECT e.*, c.course_code, c.course_name FROM exams e JOIN courses c ON e.course_id = c.id WHERE e.id = ?");
    $eStmt->execute([$selectedExamId]);
    $currentExam = $eStmt->fetch();

    $qListStmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY id ASC");
    $qListStmt->execute([$selectedExamId]);
    $questions = $qListStmt->fetchAll();
}

$accumulatedMarks = array_sum(array_column($questions, 'marks'));

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once dirname(__DIR__) . '/includes/sidebar_teacher.php'; ?>

    <div class="main-content">
        <?php require_once dirname(__DIR__) . '/includes/navbar.php'; ?>

        <div class="container-fluid p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div>
                    <h3 class="fw-bold mb-1">Manage Questions</h3>
                    <p class="text-muted small mb-0">Author and review questions, set scoring rubrics, and upload diagrams.</p>
                </div>
                <?php if ($currentExam): ?>
                    <div>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                            <i class="bi bi-plus-lg me-1"></i> Add Question
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Exam Selector Bar -->
            <div class="card mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="" class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold mb-1">Select Active Exam</label>
                            <select name="exam_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <?php foreach ($teacherExams as $te): ?>
                                    <option value="<?= $te['id'] ?>" <?= ($selectedExamId === (int)$te['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($te['title']) ?> (Code: <?= htmlspecialchars($te['exam_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($currentExam): ?>
                            <div class="col-md-7 text-md-end pt-3 pt-md-0 small">
                                <span class="badge bg-light text-dark border px-3 py-2 me-2">
                                    Course: <strong><?= htmlspecialchars($currentExam['course_code']) ?></strong>
                                </span>
                                <span class="badge bg-info-subtle text-info border border-info px-3 py-2 me-2">
                                    Questions: <strong><?= count($questions) ?></strong>
                                </span>
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2">
                                    Accumulated Marks: <strong><?= $accumulatedMarks ?> / <?= $currentExam['total_marks'] ?></strong>
                                </span>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Questions Listing -->
            <?php if (empty($questions)): ?>
                <div class="card text-center py-5">
                    <i class="bi bi-patch-question fs-1 text-muted"></i>
                    <h5 class="fw-bold mt-2">No Questions in this Exam</h5>
                    <p class="text-muted small">Click "Add Question" to insert MCQs or coding problems.</p>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($questions as $idx => $q): ?>
                        <div class="card shadow-sm border-0">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-primary rounded-circle p-2" style="width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;">
                                            <?= $idx + 1 ?>
                                        </span>
                                        <span class="badge bg-light text-dark border text-uppercase" style="font-size:0.7rem;"><?= htmlspecialchars($q['question_type']) ?></span>
                                        <span class="badge bg-success-subtle text-success border border-success"><?= $q['marks'] ?> Marks</span>
                                    </div>
                                    <form method="POST" action="" onsubmit="return confirm('Delete question #<?= $idx + 1 ?>?');">
                                        <input type="hidden" name="action" value="delete_question">
                                        <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger p-1" title="Delete Question">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>

                                <div class="fw-semibold text-dark mb-3 ps-1">
                                    <?= nl2br(htmlspecialchars($q['question_text'])) ?>
                                </div>

                                <?php if (!empty($q['question_image'])): ?>
                                    <div class="mb-3">
                                        <img src="<?= BASE_URL ?>/uploads/question_images/<?= htmlspecialchars($q['question_image']) ?>" alt="Question Diagram" class="img-thumbnail rounded" style="max-height: 200px;">
                                    </div>
                                <?php endif; ?>

                                <?php if ($q['question_type'] === 'mcq'): ?>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <div class="p-2 border rounded <?= ($q['correct_option'] === 'A') ? 'bg-success-subtle border-success fw-bold text-success' : 'bg-light' ?> small">
                                                <span class="badge bg-secondary me-1">A</span> <?= htmlspecialchars($q['option_a']) ?>
                                                <?= ($q['correct_option'] === 'A') ? ' <i class="bi bi-check-circle-fill ms-1"></i> (Correct)' : '' ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-2 border rounded <?= ($q['correct_option'] === 'B') ? 'bg-success-subtle border-success fw-bold text-success' : 'bg-light' ?> small">
                                                <span class="badge bg-secondary me-1">B</span> <?= htmlspecialchars($q['option_b']) ?>
                                                <?= ($q['correct_option'] === 'B') ? ' <i class="bi bi-check-circle-fill ms-1"></i> (Correct)' : '' ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-2 border rounded <?= ($q['correct_option'] === 'C') ? 'bg-success-subtle border-success fw-bold text-success' : 'bg-light' ?> small">
                                                <span class="badge bg-secondary me-1">C</span> <?= htmlspecialchars($q['option_c']) ?>
                                                <?= ($q['correct_option'] === 'C') ? ' <i class="bi bi-check-circle-fill ms-1"></i> (Correct)' : '' ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-2 border rounded <?= ($q['correct_option'] === 'D') ? 'bg-success-subtle border-success fw-bold text-success' : 'bg-light' ?> small">
                                                <span class="badge bg-secondary me-1">D</span> <?= htmlspecialchars($q['option_d']) ?>
                                                <?= ($q['correct_option'] === 'D') ? ' <i class="bi bi-check-circle-fill ms-1"></i> (Correct)' : '' ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Add Question Modal -->
<div class="modal fade" id="addQuestionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add Question to Examination</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_question">
                    <input type="hidden" name="exam_id" value="<?= $selectedExamId ?>">

                    <div class="row g-2 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Question Type</label>
                            <select name="question_type" class="form-select form-select-sm" id="questionTypeSelect" onchange="toggleQuestionType(this.value)">
                                <option value="mcq" selected>Multiple Choice (MCQ)</option>
                                <option value="coding">Coding Challenge / Code Snippet</option>
                                <option value="short">Short Conceptual Answer</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Marks Weightage</label>
                            <input type="number" step="0.5" name="marks" class="form-control form-control-sm" value="2.0" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Question Statement <span class="text-danger">*</span></label>
                        <textarea name="question_text" class="form-control" rows="3" placeholder="Enter the prompt, problem statement, or code snippet..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Diagram / Screenshot Attachment (Optional)</label>
                        <input type="file" name="question_image" class="form-control form-control-sm" accept="image/*">
                    </div>

                    <div id="mcqOptionsBlock">
                        <h6 class="fw-bold small text-muted text-uppercase mb-2">Options & Key</h6>
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label small">Option A <span class="text-danger">*</span></label>
                                <input type="text" name="option_a" class="form-control form-control-sm" placeholder="Option A text">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Option B <span class="text-danger">*</span></label>
                                <input type="text" name="option_b" class="form-control form-control-sm" placeholder="Option B text">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Option C</label>
                                <input type="text" name="option_c" class="form-control form-control-sm" placeholder="Option C text">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Option D</label>
                                <input type="text" name="option_d" class="form-control form-control-sm" placeholder="Option D text">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Correct Option Key <span class="text-danger">*</span></label>
                            <select name="correct_option" class="form-select form-select-sm">
                                <option value="A">Option A</option>
                                <option value="B">Option B</option>
                                <option value="C">Option C</option>
                                <option value="D">Option D</option>
                            </select>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function toggleQuestionType(type) {
        const mcqBlock = document.getElementById('mcqOptionsBlock');
        if (type === 'mcq') {
            mcqBlock.style.display = 'block';
        } else {
            mcqBlock.style.display = 'none';
        }
    }
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
