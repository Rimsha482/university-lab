<?php
/**
 * Instant Autosave Answer Endpoint
 * Saves each question response in real time to prevent loss on network glitch or power outage
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/db.php';

$attemptId      = $_POST['attempt_id'] ?? null;
$questionId     = $_POST['question_id'] ?? null;
$selectedOption = isset($_POST['selected_option']) ? trim($_POST['selected_option']) : null;
$answerText     = isset($_POST['answer_text']) ? trim($_POST['answer_text']) : null;

if (!$attemptId || !$questionId) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid question or attempt reference']);
    exit;
}

try {
    $pdo = getDb();

    // 1. Verify attempt is still active
    $attStmt = $pdo->prepare("SELECT status FROM exam_attempts WHERE id = ?");
    $attStmt->execute([$attemptId]);
    $attempt = $attStmt->fetch();

    if (!$attempt || $attempt['status'] !== 'in_progress') {
        echo json_encode(['status' => 'error', 'message' => 'Exam is no longer active for submission.']);
        exit;
    }

    // 2. Fetch question details for auto-grading
    $qStmt = $pdo->prepare("SELECT question_type, correct_option, marks FROM questions WHERE id = ?");
    $qStmt->execute([$questionId]);
    $question = $qStmt->fetch();

    if (!$question) {
        echo json_encode(['status' => 'error', 'message' => 'Question does not exist.']);
        exit;
    }

    $isCorrect = 0;
    $marksAwarded = 0.00;

    // Auto-grade MCQs
    if ($question['question_type'] === 'mcq') {
        if (!empty($selectedOption) && strtoupper($selectedOption) === strtoupper($question['correct_option'])) {
            $isCorrect = 1;
            $marksAwarded = (float)$question['marks'];
        }
    }

    // 3. Upsert into student_answers
    $upsertSql = "INSERT INTO student_answers (attempt_id, question_id, selected_option, answer_text, marks_awarded, is_correct, answered_at)
                  VALUES (:attempt_id, :question_id, :selected_option, :answer_text, :marks_awarded, :is_correct, NOW())
                  ON DUPLICATE KEY UPDATE
                    selected_option = VALUES(selected_option),
                    answer_text = VALUES(answer_text),
                    marks_awarded = VALUES(marks_awarded),
                    is_correct = VALUES(is_correct),
                    answered_at = NOW()";

    $stmt = $pdo->prepare($upsertSql);
    $stmt->execute([
        ':attempt_id'      => $attemptId,
        ':question_id'     => $questionId,
        ':selected_option' => $selectedOption,
        ':answer_text'     => $answerText,
        ':marks_awarded'   => $marksAwarded,
        ':is_correct'      => $isCorrect
    ]);

    echo json_encode([
        'status' => 'success',
        'question_id' => (int)$questionId,
        'saved_option' => $selectedOption,
        'timestamp' => date('h:i:s A')
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
