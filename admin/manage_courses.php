<?php
/**
 * Course & Subject Allocation Management
 */

require_once dirname(__DIR__) . '/auth/role_guard.php';
requireRole('admin');

$pageTitle = "Manage Courses";
$pdo = getDb();
$message = '';
$error = '';

// Handle Actions: Add, Edit, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_course') {
        $courseCode = strtoupper(trim($_POST['course_code'] ?? ''));
        $courseName = trim($_POST['course_name'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $teacherId  = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;
        $description = trim($_POST['description'] ?? '');

        if (empty($courseCode) || empty($courseName) || empty($department)) {
            $error = "Course Code, Course Name, and Department are mandatory.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name, department, teacher_id, description) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$courseCode, $courseName, $department, $teacherId, $description]);
                $message = "Course {$courseCode} added successfully.";
                logSystemActivity($_SESSION['user_id'], 'COURSE_CREATED', "Created course {$courseCode}");
            } catch (Exception $e) {
                $error = "Error adding course: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_course') {
        $courseId = intval($_POST['course_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->execute([$courseId]);
            $message = "Course deleted successfully.";
        } catch (Exception $e) {
            $error = "Error deleting course: " . $e->getMessage();
        }
    }
}

// Fetch Teachers for dropdown
$teachers = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY full_name ASC")->fetchAll();

// Fetch Courses with assigned Teacher & Exam Count
$courses = $pdo->query("SELECT c.*, u.full_name as teacher_name,
                        (SELECT COUNT(*) FROM exams e WHERE e.course_id = c.id) as exam_count
                        FROM courses c
                        LEFT JOIN users u ON c.teacher_id = u.id
                        ORDER BY c.course_code ASC")->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once dirname(__DIR__) . '/includes/sidebar_admin.php'; ?>

    <div class="main-content">
        <?php require_once dirname(__DIR__) . '/includes/navbar.php'; ?>

        <div class="container-fluid p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div>
                    <h3 class="fw-bold mb-1">Courses & Subject Allocation</h3>
                    <p class="text-muted small mb-0">Define academic curriculum and assign course examiners.</p>
                </div>
                <div>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                        <i class="bi bi-plus-lg me-1"></i> Add Course
                    </button>
                </div>
            </div>

            <!-- Alerts -->
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

            <!-- Courses Grid -->
            <div class="row g-4">
                <?php if (empty($courses)): ?>
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
                        <p>No courses registered yet. Click "Add Course" to get started.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($courses as $c): ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="card h-100 shadow-sm border-0">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="badge bg-primary-subtle text-primary border border-primary font-monospace fw-bold px-2 py-1">
                                            <?= htmlspecialchars($c['course_code']) ?>
                                        </span>
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-journal-text me-1"></i> <?= $c['exam_count'] ?> Exams
                                        </span>
                                    </div>
                                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($c['course_name']) ?></h5>
                                    <div class="text-muted small mb-3">
                                        <i class="bi bi-building me-1"></i> <?= htmlspecialchars($c['department']) ?>
                                    </div>

                                    <p class="small text-secondary mb-3" style="min-height: 40px;">
                                        <?= htmlspecialchars(substr($c['description'] ?? 'No description provided.', 0, 110)) ?>...
                                    </p>

                                    <div class="pt-2 border-top d-flex justify-content-between align-items-center small">
                                        <div>
                                            <span class="text-muted">Instructor:</span>
                                            <strong class="text-dark d-block"><?= htmlspecialchars($c['teacher_name'] ?? 'Not Assigned') ?></strong>
                                        </div>
                                        <form method="POST" action="" onsubmit="return confirm('Delete course <?= htmlspecialchars($c['course_code']) ?>? Associated exams will be deleted.');">
                                            <input type="hidden" name="action" value="delete_course">
                                            <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger p-1" title="Delete Course">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- Add Course Modal -->
<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Register New Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_course">
                    <div class="row g-2 mb-3">
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold">Course Code <span class="text-danger">*</span></label>
                            <input type="text" name="course_code" class="form-control" placeholder="CS-501" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small fw-semibold">Department <span class="text-danger">*</span></label>
                            <input type="text" name="department" class="form-control" placeholder="Computer Science & IT" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Course Title <span class="text-danger">*</span></label>
                        <input type="text" name="course_name" class="form-control" placeholder="e.g. Artificial Intelligence & Deep Learning" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Lead Examiner / Teacher</label>
                        <select name="teacher_id" class="form-select">
                            <option value="">-- Assign Later --</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?> (@<?= htmlspecialchars($t['username']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Course Overview / Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Brief outline of syllabus..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
