<?php
/**
 * Export Examination Results to Excel / CSV
 */

require_once dirname(__DIR__) . '/auth/role_guard.php';
requireRole(['teacher', 'admin']);

$examId = !empty($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
if (!$examId) {
    die("Please select an exam to export.");
}

$pdo = getDb();

// Fetch Exam
$eStmt = $pdo->prepare("SELECT title, exam_code, total_marks, passing_marks FROM exams WHERE id = ?");
$eStmt->execute([$examId]);
$exam = $eStmt->fetch();

if (!$exam) {
    die("Exam not found.");
}

// Fetch Attempts
$attStmt = $pdo->prepare("SELECT a.*, u.full_name, u.roll_number, u.email, p.pc_name
                          FROM exam_attempts a
                          JOIN users u ON a.student_id = u.id
                          LEFT JOIN lab_pcs p ON a.lab_pc_id = p.id
                          WHERE a.exam_id = ?
                          ORDER BY a.total_score DESC");
$attStmt->execute([$examId]);
$attempts = $attStmt->fetchAll();

// Clean output buffer to ensure pure CSV download
if (ob_get_level()) {
    ob_end_clean();
}

$filename = "Exam_Results_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $exam['exam_code']) . "_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Microsoft Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Header Metadata
fputcsv($output, ['EXAMINATION REPORT', $exam['title']]);
fputcsv($output, ['Exam Code', $exam['exam_code'], 'Total Marks', $exam['total_marks'], 'Passing Marks', $exam['passing_marks']]);
fputcsv($output, ['Generated On', date('Y-m-d H:i:s')]);
fputcsv($output, []); // Empty row

// Table Columns
fputcsv($output, [
    'Roll Number',
    'Student Name',
    'Institutional Email',
    'Lab Workstation',
    'Marks Obtained',
    'Total Marks',
    'Percentage (%)',
    'Final Result',
    'Attempt Status',
    'Proctoring Violations',
    'Start Time',
    'Submission Time'
]);

foreach ($attempts as $row) {
    $pct = ($exam['total_marks'] > 0) ? round(($row['total_score'] / $exam['total_marks']) * 100, 2) : 0;
    $result = ($row['status'] === 'disqualified') ? 'DISQUALIFIED' : (($row['total_score'] >= $exam['passing_marks']) ? 'PASSED' : 'FAILED');

    fputcsv($output, [
        $row['roll_number'] ?? 'N/A',
        $row['full_name'],
        $row['email'],
        $row['pc_name'] ?? 'Virtual Workstation',
        $row['total_score'],
        $exam['total_marks'],
        $pct . '%',
        $result,
        strtoupper($row['status']),
        $row['violation_count'],
        $row['start_time'],
        $row['submitted_at'] ?? 'Incomplete'
    ]);
}

fclose($output);
exit;
