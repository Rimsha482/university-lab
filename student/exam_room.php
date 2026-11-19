<?php
/**
 * Distraction-Free Proctored Exam Environment
 * Enforces Fullscreen Integrity, Tab Monitoring, Autosave & Remote Telemetry
 */

require_once dirname(__DIR__) . '/auth/role_guard.php';
requireRole('student');

$studentId = $_SESSION['user_id'];
$examId = !empty($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$pdo = getDb();

// 1. Fetch Exam Configuration
$eStmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
$eStmt->execute([$examId]);
$exam = $eStmt->fetch();

if (!$exam || !in_array($exam['status'], ['published', 'ongoing'])) {
    header("Location: " . BASE_URL . "/student/dashboard.php?error=exam_not_available");
    exit;
}

// 2. Locate or Create Exam Attempt
$attStmt = $pdo->prepare("SELECT * FROM exam_attempts WHERE exam_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1");
$attStmt->execute([$examId, $studentId]);
$attempt = $attStmt->fetch();

if (!$attempt) {
    // Initialize new attempt
    $labPcId = $_SESSION['lab_pc_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $insStmt = $pdo->prepare("INSERT INTO exam_attempts (exam_id, student_id, lab_pc_id, start_time, status, ip_address) VALUES (?, ?, ?, NOW(), 'in_progress', ?)");
    $insStmt->execute([$examId, $studentId, $labPcId, $ip]);
    $attemptId = $pdo->lastInsertId();

    $attStmt->execute([$examId, $studentId]);
    $attempt = $attStmt->fetch();

    logSystemActivity($studentId, 'EXAM_STARTED', "Candidate started exam '{$exam['title']}' on attempt #{$attemptId}");
} else {
    $attemptId = $attempt['id'];
}

// If already finalized, redirect to result card
if (in_array($attempt['status'], ['completed', 'disqualified', 'timed_out'])) {
    header("Location: " . BASE_URL . "/student/result_card.php?attempt_id=" . $attemptId);
    exit;
}

// 3. Compute Remaining Time
$durationSec = (int)$exam['duration_minutes'] * 60;
$elapsedSec = time() - strtotime($attempt['start_time']);
$remainingSec = $durationSec - $elapsedSec;

if ($remainingSec <= 0) {
    // Time expired! Redirect to auto-submit
    header("Location: " . BASE_URL . "/api/submit_exam.php?attempt_id=" . $attemptId . "&status=timed_out");
    exit;
}

// 4. Fetch Exam Questions
$qSql = "SELECT * FROM questions WHERE exam_id = ?";
if ($exam['shuffle_questions']) {
    // Deterministic shuffle using student and attempt ID so question order remains constant on refresh
    $seed = $studentId * 1000 + $attemptId;
    $qSql .= " ORDER BY RAND({$seed})";
} else {
    $qSql .= " ORDER BY id ASC";
}
$qStmt = $pdo->prepare($qSql);
$qStmt->execute([$examId]);
$questions = $qStmt->fetchAll();

if (empty($questions)) {
    $pageTitle = "No Questions Available";
    require_once dirname(__DIR__) . '/includes/header.php';
    echo '<div class="app-wrapper"><div class="main-content">' .
         '<div class="container py-5 text-center">' .
         '<div class="card shadow-sm border-0 rounded-4 mx-auto p-5" style="max-width: 550px;">' .
         '<i class="bi bi-file-earmark-x text-warning" style="font-size: 4rem;"></i>' .
         '<h4 class="fw-bold mt-3">Paper Under Preparation</h4>' .
         '<p class="text-muted small">This examination currently has no authored questions. Your teacher or lab examiner is preparing the question paper. Please check back shortly or notify your examiner.</p>' .
         '<div class="mt-3"><a href="' . BASE_URL . '/student/dashboard.php" class="btn btn-primary btn-sm px-4"><i class="bi bi-arrow-left me-1"></i> Return to Dashboard</a></div>' .
         '</div></div></div></div>';
    require_once dirname(__DIR__) . '/includes/footer.php';
    exit;
}

// 5. Fetch Previously Saved Answers for this Attempt
$ansStmt = $pdo->prepare("SELECT question_id, selected_option, answer_text FROM student_answers WHERE attempt_id = ?");
$ansStmt->execute([$attemptId]);
$savedAnswers = [];
while ($row = $ansStmt->fetch()) {
    $savedAnswers[$row['question_id']] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($exam['title']) ?> - Proctored Examination</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/logo.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/exam_lock.css">
</head>
<body class="exam-mode">

<!-- Persistent Header Bar -->
<header class="exam-header-bar d-flex justify-content-between align-items-center">
    <!-- Left: Exam Title & Roll -->
    <div class="d-flex align-items-center gap-3">
        <span class="badge bg-primary font-monospace px-2 py-1"><?= htmlspecialchars($exam['exam_code']) ?></span>
        <div>
            <h6 class="fw-bold mb-0 text-white"><?= htmlspecialchars($exam['title']) ?></h6>
            <small class="text-white-50">
                Candidate: <strong><?= htmlspecialchars($_SESSION['full_name']) ?></strong> (<?= htmlspecialchars($_SESSION['roll_number'] ?? 'CS-STUDENT') ?>)
                <?php if (!empty($_SESSION['lab_pc_name'])): ?>
                    &bull; Terminal: <span class="text-info"><?= htmlspecialchars($_SESSION['lab_pc_name']) ?></span>
                <?php endif; ?>
            </small>
        </div>
    </div>

    <!-- Right: Proctoring Violations, Timer & Final Submit -->
    <div class="d-flex align-items-center gap-3">
        <!-- Live Violations Indicator -->
        <div id="violationPill" class="violation-pill <?= ($attempt['violation_count'] === 0 ? 'safe' : 'warning') ?>" title="Max Allowed Violations: <?= $exam['max_violations'] ?>">
            <i class="bi bi-shield-lock-fill"></i>
            <span>Violations: <strong id="violationCountDisplay"><?= $attempt['violation_count'] ?> / <?= $exam['max_violations'] ?></strong></span>
        </div>

        <!-- Countdown Timer Box -->
        <div id="timerBox" class="exam-timer-box">
            <i class="bi bi-clock-history"></i>
            <span id="timerDisplay">--:--</span>
        </div>

        <!-- Submit Button -->
        <button type="button" class="btn btn-danger btn-sm px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#submitConfirmModal">
            <i class="bi bi-send-check me-1"></i> Finish Exam
        </button>
    </div>
</header>

<!-- Main Examination Workstation Layout -->
<main class="container-fluid px-4 py-4">
    <div class="row g-4">
        <!-- Question Workspace Area -->
        <div class="col-lg-8 col-xl-9">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4 p-md-5">
                    <?php foreach ($questions as $qIndex => $q): 
                        $saved = $savedAnswers[$q['id']] ?? null;
                        $chosenOpt = $saved['selected_option'] ?? '';
                    ?>
                        <div class="question-slide" id="q-pane-<?= $qIndex ?>" style="<?= ($qIndex === 0) ? 'display:block;' : 'display:none;' ?>">
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                <span class="badge bg-primary fs-6 px-3 py-2 rounded-pill">
                                    Question <?= $qIndex + 1 ?> of <?= count($questions) ?>
                                </span>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-light text-dark border"><i class="bi bi-award me-1"></i> <?= $q['marks'] ?> Marks</span>
                                    <span class="badge bg-light text-secondary border text-uppercase" style="font-size:0.7rem;"><?= htmlspecialchars($q['question_type']) ?></span>
                                </div>
                            </div>

                            <!-- Question Statement -->
                            <div class="fs-5 fw-bold text-dark mb-4 lh-base">
                                <?= nl2br(htmlspecialchars($q['question_text'])) ?>
                            </div>

                            <!-- Question Image / Diagram -->
                            <?php if (!empty($q['question_image'])): ?>
                                <div class="mb-4 text-center">
                                    <img src="<?= BASE_URL ?>/uploads/question_images/<?= htmlspecialchars($q['question_image']) ?>" alt="Diagram" class="img-fluid rounded border shadow-sm" style="max-height: 280px;">
                                </div>
                            <?php endif; ?>

                            <!-- Options Form (Instant Autosave) -->
                            <?php if ($q['question_type'] === 'mcq'): ?>
                                <div class="options-container mb-4">
                                    <?php foreach (['A' => $q['option_a'], 'B' => $q['option_b'], 'C' => $q['option_c'], 'D' => $q['option_d']] as $key => $optVal): 
                                        if (empty($optVal)) continue;
                                        $isSelected = ($chosenOpt === $key);
                                    ?>
                                        <label class="option-label <?= $isSelected ? 'active' : '' ?>" id="opt-label-<?= $q['id'] ?>-<?= $key ?>">
                                            <input type="radio" name="opt_<?= $q['id'] ?>" value="<?= $key ?>" class="d-none" <?= $isSelected ? 'checked' : '' ?> onchange="saveMcqAnswer(<?= $attemptId ?>, <?= $q['id'] ?>, '<?= $key ?>', <?= $qIndex ?>)">
                                            <span class="option-badge"><?= $key ?></span>
                                            <span class="option-content flex-grow-1"><?= htmlspecialchars($optVal) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <!-- Coding / Short Conceptual Question -->
                                <div class="mb-4">
                                    <label class="form-label small fw-semibold text-muted">Type Solution / Code Below:</label>
                                    <textarea class="form-control font-monospace" rows="8" placeholder="Type your answer here..." onblur="saveTextAnswer(<?= $attemptId ?>, <?= $q['id'] ?>, this.value, <?= $qIndex ?>)"><?= htmlspecialchars($saved['answer_text'] ?? '') ?></textarea>
                                </div>
                            <?php endif; ?>

                            <!-- Question Navigation Toolbar -->
                            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                <div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="navQuestion(<?= $qIndex - 1 ?>)" <?= ($qIndex === 0) ? 'disabled' : '' ?>>
                                        <i class="bi bi-chevron-left me-1"></i> Previous
                                    </button>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="flagQuestion(<?= $qIndex ?>)">
                                        <i class="bi bi-flag me-1"></i> Review Later
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearOption(<?= $q['id'] ?>, <?= $qIndex ?>)">
                                        <i class="bi bi-eraser me-1"></i> Clear Selection
                                    </button>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-primary btn-sm px-3" onclick="navQuestion(<?= $qIndex + 1 ?>)" <?= ($qIndex === count($questions) - 1) ? 'disabled' : '' ?>>
                                        Next <i class="bi bi-chevron-right ms-1"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Right Side: Question Palette & Proctor Legend -->
        <div class="col-lg-4 col-xl-3">
            <div class="card shadow-sm border-0 rounded-4 sticky-top" style="top: 80px;">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-grid-3x3-gap-fill text-primary me-2"></i> Question Palette</h6>
                </div>
                <div class="card-body">
                    <!-- Palette Buttons Grid -->
                    <div class="d-flex flex-wrap mb-3" id="questionPaletteGrid">
                        <?php foreach ($questions as $idx => $q): 
                            $isAnswered = !empty($savedAnswers[$q['id']]['selected_option']) || !empty($savedAnswers[$q['id']]['answer_text']);
                            $stateClass = $isAnswered ? 'answered' : '';
                            if ($idx === 0) $stateClass .= ' current';
                        ?>
                            <button type="button" class="question-nav-btn <?= $stateClass ?>" id="palette-btn-<?= $idx ?>" onclick="navQuestion(<?= $idx ?>)">
                                <?= $idx + 1 ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Legend -->
                    <div class="p-3 bg-light rounded-3 border small">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="question-nav-btn answered" style="width:22px;height:22px;font-size:0.7rem;">&check;</span>
                            <span>Answered</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="question-nav-btn flagged" style="width:22px;height:22px;font-size:0.7rem;"><i class="bi bi-flag-fill" style="font-size:0.6rem;"></i></span>
                            <span>Marked for Review</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="question-nav-btn" style="width:22px;height:22px;font-size:0.7rem;">•</span>
                            <span>Unanswered / Pending</span>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="button" class="btn btn-outline-danger w-100 py-2 fw-semibold rounded-3" data-bs-toggle="modal" data-bs-target="#submitConfirmModal">
                            <i class="bi bi-check-circle me-1"></i> Final Paper Submission
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Fullscreen Required Overlay -->
<div id="fullscreenOverlay">
    <i class="bi bi-fullscreen text-primary" style="font-size: 4rem;"></i>
    <h3 class="fw-bold mt-3">Proctored Fullscreen Mode Required</h3>
    <p class="text-white-50" style="max-width: 500px;">
        To maintain examination integrity, this assessment must be completed strictly in fullscreen mode. Any attempt to minimize or switch windows is flagged.
    </p>
    <button type="button" class="btn btn-primary btn-lg px-4 mt-2 fw-bold" onclick="proctorEngine.enterFullscreen()">
        <i class="bi bi-arrows-fullscreen me-2"></i> Resume Fullscreen Mode
    </button>
</div>

<!-- Lockout / Disqualification Overlay -->
<div id="lockoutOverlay">
    <i class="bi bi-shield-slash-fill text-warning" style="font-size: 4.5rem;"></i>
    <h2 class="fw-bold mt-3 text-white">Exam Session Disqualified</h2>
    <p class="text-white-70 fs-5" style="max-width: 550px;">
        You have exceeded the maximum allowed security breaches (tab switching, window minimization, or exiting fullscreen). Your examination has been automatically locked and submitted.
    </p>
    <div class="spinner-border text-light mt-3" role="status"></div>
    <small class="text-white-50 mt-2">Redirecting to candidate result scorecard...</small>
</div>

<!-- Submission Confirmation Modal -->
<div class="modal fade" id="submitConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Submit Examination?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="bi bi-question-circle text-primary" style="font-size: 3.5rem;"></i>
                <h5 class="fw-bold mt-2">Are you sure you want to finish?</h5>
                <p class="text-muted small">Once submitted, your answers will be automatically evaluated and you will not be able to modify any responses.</p>
                <form id="examSubmitForm" action="<?= BASE_URL ?>/api/submit_exam.php" method="POST">
                    <input type="hidden" name="attempt_id" value="<?= $attemptId ?>">
                    <input type="hidden" name="final_status" id="examFinalStatus" value="completed">
                    <input type="hidden" name="is_auto" value="0">
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Keep Reviewing</button>
                        <button type="submit" class="btn btn-danger px-4 fw-bold">Yes, Final Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Core Engine Scripts -->
<script src="<?= BASE_URL ?>/assets/js/jquery.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/proctor_engine.js"></script>
<script src="<?= BASE_URL ?>/assets/js/exam_timer.js"></script>

<script>
    let currentQuestionIndex = 0;
    const totalQuestionsCount = <?= count($questions) ?>;

    // 1. Initialize Proctoring Engine
    const proctorEngine = new ProctorEngine({
        attemptId: <?= $attemptId ?>,
        examId: <?= $examId ?>,
        studentId: <?= $studentId ?>,
        maxViolations: <?= (int)$exam['max_violations'] ?>,
        currentViolations: <?= (int)$attempt['violation_count'] ?>,
        apiUrl: '<?= BASE_URL ?>/api/log_violation.php',
        submitUrl: '<?= BASE_URL ?>/api/submit_exam.php'
    });

    // 2. Initialize Countdown Timer
    const examTimer = new ExamTimer(<?= $remainingSec ?>, function() {
        // Auto-submit when time expires
        document.getElementById('examFinalStatus').value = 'timed_out';
        document.getElementById('examSubmitForm').submit();
    });

    // 3. Heartbeat Polling (every 10s)
    setInterval(function() {
        fetch('<?= BASE_URL ?>/api/heartbeat.php?attempt_id=<?= $attemptId ?>')
            .then(res => res.json())
            .then(data => {
                if (data.force_submit) {
                    proctorEngine.lockStudentOut();
                }
            })
            .catch(() => {});
    }, 10000);

    // 4. Question Navigation Logic
    function navQuestion(targetIndex) {
        if (targetIndex < 0 || targetIndex >= totalQuestionsCount) return;

        // Hide current
        const curPane = document.getElementById(`q-pane-${currentQuestionIndex}`);
        const curBtn = document.getElementById(`palette-btn-${currentQuestionIndex}`);
        if (curPane) curPane.style.display = 'none';
        if (curBtn) curBtn.classList.remove('current');

        // Show target
        currentQuestionIndex = targetIndex;
        const nextPane = document.getElementById(`q-pane-${currentQuestionIndex}`);
        const nextBtn = document.getElementById(`palette-btn-${currentQuestionIndex}`);
        if (nextPane) nextPane.style.display = 'block';
        if (nextBtn) nextBtn.classList.add('current');
    }

    // 5. Autosave MCQ Answer
    function saveMcqAnswer(attemptId, qId, selectedOption, qIndex) {
        // Highlight active radio container
        document.querySelectorAll(`input[name="opt_${qId}"]`).forEach(radio => {
            const label = radio.closest('.option-label');
            if (label) label.classList.toggle('active', radio.value === selectedOption);
        });

        // Mark Palette Button
        const pBtn = document.getElementById(`palette-btn-${qIndex}`);
        if (pBtn) pBtn.classList.add('answered');

        // Post AJAX to API
        const payload = new URLSearchParams({
            attempt_id: attemptId,
            question_id: qId,
            selected_option: selectedOption
        });

        fetch('<?= BASE_URL ?>/api/save_answer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload.toString()
        }).catch(err => console.warn('Autosave network pause:', err));
    }

    // 6. Autosave Text / Coding Answer
    function saveTextAnswer(attemptId, qId, textVal, qIndex) {
        if (!textVal.trim()) return;

        const pBtn = document.getElementById(`palette-btn-${qIndex}`);
        if (pBtn) pBtn.classList.add('answered');

        const payload = new URLSearchParams({
            attempt_id: attemptId,
            question_id: qId,
            answer_text: textVal
        });

        fetch('<?= BASE_URL ?>/api/save_answer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload.toString()
        }).catch(err => console.warn('Autosave network pause:', err));
    }

    // 7. Flag / Review Later
    function flagQuestion(qIndex) {
        const pBtn = document.getElementById(`palette-btn-${qIndex}`);
        if (pBtn) {
            pBtn.classList.toggle('flagged');
        }
    }

    // 8. Clear Selection
    function clearOption(qId, qIndex) {
        document.querySelectorAll(`input[name="opt_${qId}"]`).forEach(radio => {
            radio.checked = false;
            const label = radio.closest('.option-label');
            if (label) label.classList.remove('active');
        });

        const pBtn = document.getElementById(`palette-btn-${qIndex}`);
        if (pBtn) pBtn.classList.remove('answered');

        const payload = new URLSearchParams({
            attempt_id: <?= $attemptId ?>,
            question_id: qId,
            selected_option: ''
        });

        fetch('<?= BASE_URL ?>/api/save_answer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload.toString()
        }).catch(err => {});
    }
</script>
</body>
</html>
