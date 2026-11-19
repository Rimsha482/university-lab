<?php
/**
 * Exam Final Submission & Auto-Grading Engine
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/db.php';

$attemptId = $_POST['attempt_id'] ?? $_GET['attempt_id'] ?? null;
$finalStatus = $_POST['final_status'] ?? $_GET['status'] ?? 'completed';
$isAutoSubmitted = (!empty($_POST['is_auto']) || in_array($finalStatus, ['disqualified', 'timed_out'])) ? 1 : 0;

if (!$attemptId) {
    die("Invalid attempt identifier.");
}

try {
    $pdo = getDb();

    // 1. Fetch attempt and exam details
    $attStmt = $pdo->prepare("SELECT a.*, e.passing_marks, e.total_marks, u.full_name, u.roll_number
                              FROM exam_attempts a
                              JOIN exams e ON a.exam_id = e.id
                              JOIN users u ON a.student_id = u.id
                              WHERE a.id = ?");
    $attStmt->execute([$attemptId]);
    $attempt = $attStmt->fetch();

    if (!$attempt) {
        die("Exam attempt not found.");
    }

    // 2. Sum up awarded marks for all questions in this attempt
    $scoreStmt = $pdo->prepare("SELECT COALESCE(SUM(marks_awarded), 0.00) as final_score FROM student_answers WHERE attempt_id = ?");
    $scoreStmt->execute([$attemptId]);
    $scoreRow = $scoreStmt->fetch();
    $totalScore = (float)$scoreRow['final_score'];

    // If disqualified, zero out score or mark accordingly
    if ($finalStatus === 'disqualified') {
        $totalScore = 0.00;
    }

    // 3. Update exam_attempts record
    $updateStmt = $pdo->prepare("UPDATE exam_attempts SET
                                    total_score = ?,
                                    status = ?,
                                    is_auto_submitted = ?,
                                    end_time = NOW(),
                                    submitted_at = NOW()
                                 WHERE id = ?");
    $updateStmt->execute([$totalScore, $finalStatus, $isAutoSubmitted, $attemptId]);

    // 4. Update PC status if linked
    if (!empty($attempt['lab_pc_id'])) {
        $pcStmt = $pdo->prepare("UPDATE lab_pcs SET status = 'online', current_exam_id = NULL WHERE id = ?");
        $pcStmt->execute([$attempt['lab_pc_id']]);
    }

    logSystemActivity(
        $attempt['student_id'],
        'EXAM_SUBMITTED',
        "Student {$attempt['full_name']} finished exam #{$attempt['exam_id']} with score {$totalScore}/{$attempt['total_marks']} (Status: {$finalStatus})"
    );

    // If AJAX request, respond JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'redirect_url' => BASE_URL . "/student/result_card.php?attempt_id=" . $attemptId
        ]);
        exit;
    }

    // Standard redirect to student scorecard
    header("Location: " . BASE_URL . "/student/result_card.php?attempt_id=" . $attemptId);
    exit;

} catch (Exception $e) {
    die("Submission processing error: " . htmlspecialchars($e->getMessage()));
}
