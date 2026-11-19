<?php
/**
 * Live Proctoring Telemetry Feed API Endpoint
 * Supplies real-time student statuses, violation counts, and streaming audit alerts
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/db.php';

// Allow teachers and admins
$role = $_SESSION['role'] ?? 'guest';
if (!in_array($role, ['teacher', 'admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized telemetry access']);
    exit;
}

$examId = !empty($_GET['exam_id']) ? intval($_GET['exam_id']) : null;
$lastLogId = !empty($_GET['last_log_id']) ? intval($_GET['last_log_id']) : 0;

try {
    $pdo = getDb();

    // 1. Fetch active/in-progress student attempts
    $attemptSql = "SELECT 
                    a.id as attempt_id,
                    a.exam_id,
                    a.student_id,
                    a.status,
                    a.violation_count,
                    a.start_time,
                    u.full_name,
                    u.roll_number,
                    u.avatar,
                    p.pc_name,
                    (SELECT COUNT(*) FROM questions q WHERE q.exam_id = a.exam_id) as total_questions,
                    (SELECT COUNT(*) FROM student_answers sa WHERE sa.attempt_id = a.id) as answered_questions
                   FROM exam_attempts a
                   JOIN users u ON a.student_id = u.id
                   LEFT JOIN lab_pcs p ON a.lab_pc_id = p.id";

    if ($examId) {
        $attemptSql .= " WHERE a.exam_id = :exam_id ORDER BY a.violation_count DESC, a.start_time DESC";
        $stmt = $pdo->prepare($attemptSql);
        $stmt->execute([':exam_id' => $examId]);
    } else {
        $attemptSql .= " WHERE a.status = 'in_progress' ORDER BY a.violation_count DESC, a.start_time DESC LIMIT 30";
        $stmt = $pdo->query($attemptSql);
    }

    $rawAttempts = $stmt->fetchAll();
    $attempts = [];
    $activeCount = 0;
    $totalViolations = 0;
    $submittedCount = 0;

    foreach ($rawAttempts as $row) {
        if ($row['status'] === 'in_progress') $activeCount++;
        if ($row['status'] === 'completed') $submittedCount++;
        $totalViolations += (int)$row['violation_count'];

        $attempts[] = [
            'attempt_id'         => (int)$row['attempt_id'],
            'student_id'         => (int)$row['student_id'],
            'full_name'          => $row['full_name'],
            'roll_number'        => $row['roll_number'],
            'pc_name'            => $row['pc_name'],
            'status'             => $row['status'],
            'violation_count'    => (int)$row['violation_count'],
            'total_questions'    => (int)$row['total_questions'],
            'answered_questions' => (int)$row['answered_questions'],
            'started_time_ago'   => timeAgo($row['start_time'])
        ];
    }

    // 2. Fetch recent proctoring violation alerts
    $logSql = "SELECT 
                l.id,
                l.violation_type,
                l.violation_message,
                l.severity,
                l.timestamp,
                u.full_name as student_name,
                u.roll_number
               FROM proctor_logs l
               JOIN users u ON l.student_id = u.id";

    if ($examId) {
        $logSql .= " WHERE l.exam_id = :exam_id AND l.id > :last_id ORDER BY l.id DESC LIMIT 15";
        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([':exam_id' => $examId, ':last_id' => $lastLogId]);
    } else {
        $logSql .= " WHERE l.id > :last_id ORDER BY l.id DESC LIMIT 15";
        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([':last_id' => $lastLogId]);
    }

    $rawLogs = $logStmt->fetchAll();
    $recentLogs = [];
    foreach ($rawLogs as $log) {
        $recentLogs[] = [
            'id'                => (int)$log['id'],
            'student_name'      => $log['student_name'],
            'roll_number'       => $log['roll_number'],
            'violation_type'    => $log['violation_type'],
            'violation_message' => $log['violation_message'],
            'severity'          => $log['severity'],
            'time_ago'          => timeAgo($log['timestamp']),
            'timestamp'         => $log['timestamp']
        ];
    }

    echo json_encode([
        'status'      => 'success',
        'attempts'    => $attempts,
        'recent_logs' => $recentLogs,
        'summary'     => [
            'active_count'    => $activeCount,
            'violation_count' => $totalViolations,
            'submitted_count' => $submittedCount
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
