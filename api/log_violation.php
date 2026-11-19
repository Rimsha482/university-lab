<?php
/**
 * Proctoring Violation Logger API Endpoint
 * Records security breaches and triggers auto-lockout when violation threshold is breached
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/db.php';

$attemptId = $_POST['attempt_id'] ?? null;
$examId    = $_POST['exam_id'] ?? null;
$studentId = $_POST['student_id'] ?? $_SESSION['user_id'] ?? null;
$type      = cleanInput($_POST['violation_type'] ?? 'unknown');
$message   = cleanInput($_POST['violation_message'] ?? 'Suspicious activity recorded.');
$severity  = in_array($_POST['severity'] ?? '', ['low', 'medium', 'high']) ? $_POST['severity'] : 'medium';

if (!$attemptId || !$examId || !$studentId) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameter fields']);
    exit;
}

try {
    $pdo = getDb();

    // 1. Fetch exam configuration to verify max allowed violations
    $examStmt = $pdo->prepare("SELECT max_violations FROM exams WHERE id = ?");
    $examStmt->execute([$examId]);
    $exam = $examStmt->fetch();
    $maxViolations = $exam ? (int)$exam['max_violations'] : MAX_TAB_SWITCH_LIMIT;

    // 2. Insert into proctor_logs
    $logStmt = $pdo->prepare("INSERT INTO proctor_logs (attempt_id, exam_id, student_id, violation_type, violation_message, severity, timestamp) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $logStmt->execute([$attemptId, $examId, $studentId, $type, $message, $severity]);

    // 3. Increment violation count on attempt
    $updateStmt = $pdo->prepare("UPDATE exam_attempts SET violation_count = violation_count + 1 WHERE id = ?");
    $updateStmt->execute([$attemptId]);

    // 4. Check current violation count
    $cntStmt = $pdo->prepare("SELECT violation_count, status FROM exam_attempts WHERE id = ?");
    $cntStmt->execute([$attemptId]);
    $attempt = $cntStmt->fetch();

    $currentCount = $attempt ? (int)$attempt['violation_count'] : 1;
    $isLocked = false;

    if ($currentCount >= $maxViolations) {
        $isLocked = true;
        // Mark student as disqualified
        $disStmt = $pdo->prepare("UPDATE exam_attempts SET status = 'disqualified', is_auto_submitted = 1, submitted_at = NOW() WHERE id = ?");
        $disStmt->execute([$attemptId]);

        logSystemActivity($studentId, 'STUDENT_DISQUALIFIED', "Attempt #{$attemptId} disqualified due to {$currentCount} security violations.");
    }

    echo json_encode([
        'status' => 'success',
        'violation_count' => $currentCount,
        'max_violations' => $maxViolations,
        'locked' => $isLocked,
        'type' => $type
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
