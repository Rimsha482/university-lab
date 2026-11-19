<?php
/**
 * Create & Schedule Examination Module
 */

require_once dirname(__DIR__) . '/auth/role_guard.php';
requireRole(['teacher', 'admin']);

$pageTitle = "Create Exam";
$teacherId = $_SESSION['user_id'];
$pdo = getDb();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseId        = intval($_POST['course_id'] ?? 0);
    $title           = trim($_POST['title'] ?? '');
    $examCode        = strtoupper(trim($_POST['exam_code'] ?? ''));
    $durationMinutes = intval($_POST['duration_minutes'] ?? 45);
    $totalMarks      = floatval($_POST['total_marks'] ?? 50.00);
    $passingMarks    = floatval($_POST['passing_marks'] ?? 20.00);
    $maxViolations   = intval($_POST['max_violations'] ?? 3);
    $startTime       = $_POST['start_time'] ?? date('Y-m-d H:i:s');
    $endTime         = $_POST['end_time'] ?? date('Y-m-d H:i:s', strtotime('+7 days'));
    $status          = in_array($_POST['status'] ?? '', ['draft', 'published']) ? $_POST['status'] : 'published';
    $shuffle         = !empty($_POST['shuffle_questions']) ? 1 : 0;
    $description     = trim($_POST['description'] ?? '');

    if (empty($title) || empty($examCode) || empty($courseId)) {
        $error = "Exam title, code, and course selection are mandatory.";
    } else {
        try {
            // Check duplicate exam code
            $chk = $pdo->prepare("SELECT id FROM exams WHERE exam_code = ?");
            $chk->execute([$examCode]);
            if ($chk->fetch()) {
                $error = "Exam code '{$examCode}' is already taken. Please specify a unique code.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO exams (course_id, teacher_id, title, description, exam_code, duration_minutes, total_marks, passing_marks, max_violations, start_time, end_time, status, shuffle_questions) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $courseId, $teacherId, $title, $description, $examCode,
                    $durationMinutes, $totalMarks, $passingMarks, $maxViolations,
                    $startTime, $endTime, $status, $shuffle
                ]);
                $newExamId = $pdo->lastInsertId();

                logSystemActivity($teacherId, 'EXAM_CREATED', "Teacher created exam '{$title}' ({$examCode})");
                header("Location: " . BASE_URL . "/teacher/manage_questions.php?exam_id=" . $newExamId . "&msg=created");
                exit;
            }
        } catch (Exception $e) {
            $error = "Error scheduling exam: " . $e->getMessage();
        }
    }
}

// Fetch Courses
$courses = $pdo->query("SELECT id, course_code, course_name FROM courses ORDER BY course_code ASC")->fetchAll();
$suggestedCode = 'EXAM-' . strtoupper(substr(uniqid(), -5));

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once dirname(__DIR__) . '/includes/sidebar_teacher.php'; ?>

    <div class="main-content">
        <?php require_once dirname(__DIR__) . '/includes/navbar.php'; ?>

        <div class="container-fluid p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div>
                    <h3 class="fw-bold mb-1">Create & Configure Exam</h3>
                    <p class="text-muted small mb-0">Set duration, passing criteria, and anti-cheat strictness for the new assessment.</p>
                </div>
                <div>
                    <a href="<?= BASE_URL ?>/teacher/dashboard.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <form method="POST" action="">
                        <div class="row g-3">
                            <!-- Course Selection -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Academic Course <span class="text-danger">*</span></label>
                                <select name="course_id" class="form-select" required>
                                    <option value="">-- Choose Course / Subject --</option>
                                    <?php foreach ($courses as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Exam Code -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Unique Exam Code <span class="text-danger">*</span></label>
                                <input type="text" name="exam_code" class="form-control font-monospace" value="<?= htmlspecialchars($suggestedCode) ?>" required>
                                <div class="form-text small">Used by students or lab monitors to identify the paper.</div>
                            </div>

                            <!-- Title -->
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Examination Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" placeholder="e.g. Midterm Examination - Fall 2026" required>
                            </div>

                            <!-- Duration & Marks -->
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Duration (Minutes) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" name="duration_minutes" class="form-control" value="45" min="5" max="300" required>
                                    <span class="input-group-text">mins</span>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Total Maximum Marks</label>
                                <input type="number" step="0.5" name="total_marks" class="form-control" value="50.00" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Passing Marks</label>
                                <input type="number" step="0.5" name="passing_marks" class="form-control" value="20.00" required>
                            </div>

                            <!-- Anti-Cheat Settings -->
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded-3 border">
                                    <label class="form-label small fw-bold text-danger"><i class="bi bi-shield-lock me-1"></i> Proctoring Strictness</label>
                                    <div class="mb-2">
                                        <label class="form-label small">Max Allowed Tab Switches / Violations</label>
                                        <select name="max_violations" class="form-select form-select-sm">
                                            <option value="1">1 (Strict - Disqualify on first breach)</option>
                                            <option value="2">2 (Moderate - 1 warning before lockout)</option>
                                            <option value="3" selected>3 (Standard Lab Default)</option>
                                            <option value="5">5 (Lenient for practice tests)</option>
                                        </select>
                                    </div>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" name="shuffle_questions" id="shuffleCheck" value="1" checked>
                                        <label class="form-check-label small" for="shuffleCheck">Randomize question order per student</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Schedule Window -->
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded-3 border">
                                    <label class="form-label small fw-bold text-primary"><i class="bi bi-calendar3 me-1"></i> Availability Window</label>
                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <label class="form-label small">Start Time</label>
                                            <input type="datetime-local" name="start_time" class="form-control form-control-sm" value="<?= date('Y-m-d\TH:i') ?>" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small">End Time</label>
                                            <input type="datetime-local" name="end_time" class="form-control form-control-sm" value="<?= date('Y-m-d\TH:i', strtotime('+7 days')) ?>" required>
                                        </div>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label small">Initial Status</label>
                                        <select name="status" class="form-select form-select-sm">
                                            <option value="published" selected>Published (Candidates can see and attempt)</option>
                                            <option value="draft">Draft (Hidden from candidates)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Special Exam Instructions</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Provide any special instructions shown on student screen before starting..."></textarea>
                            </div>

                            <!-- Submit -->
                            <div class="col-12 text-end mt-4">
                                <button type="submit" class="btn btn-primary px-4 py-2">
                                    <i class="bi bi-arrow-right-circle me-1"></i> Save Exam & Add Questions
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
