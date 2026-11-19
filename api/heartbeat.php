<?php
/**
 * Student PC Heartbeat API Endpoint
 * Pinged every 10 seconds to maintain online workstation status & receive remote proctor directives
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/db.php';

$studentId = $_SESSION['user_id'] ?? null;
$attemptId = $_POST['attempt_id'] ?? $_GET['attempt_id'] ?? null;
$labPcId = $_SESSION['lab_pc_id'] ?? $_POST['lab_pc_id'] ?? null;

if (!$studentId) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthenticated heartbeat']);
    exit;
}

try {
    $pdo = getDb();

    // 1. Update Lab PC status if assigned
    if ($labPcId) {
        $pcStmt = $pdo->prepare("UPDATE lab_pcs SET last_heartbeat = NOW(), status = 'in_exam' WHERE id = ?");
        $pcStmt->execute([$labPcId]);
    }

    // 2. Check current attempt status to see if examiner forced submission / disqualification
    $forceSubmit = false;
    $attemptStatus = 'in_progress';

    if ($attemptId) {
        $attStmt = $pdo->prepare("SELECT status FROM exam_attempts WHERE id = ? AND student_id = ? LIMIT 1");
        $attStmt->execute([$attemptId, $studentId]);
        $attempt = $attStmt->fetch();
        if ($attempt) {
            $attemptStatus = $attempt['status'];
            if ($attempt['status'] === 'disqualified' || $attempt['status'] === 'completed') {
                $forceSubmit = true;
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'server_time' => date('Y-m-d H:i:s'),
        'attempt_status' => $attemptStatus,
        'force_submit' => $forceSubmit
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
