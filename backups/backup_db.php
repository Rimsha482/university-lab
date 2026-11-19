<?php
/**
 * Automated Database Backup Engine
 * Generates comprehensive SQL dump of schema and data with single-click download
 */

require_once dirname(__DIR__) . '/auth/role_guard.php';
requireRole('admin');

$pageTitle = "Backup Database";
$pdo = getDb();
$message = '';
$error = '';

$backupDir = BACKUP_DIR;
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

// Handle backup generation trigger
if (isset($_GET['action']) && $_GET['action'] === 'generate') {
    try {
        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);

        $sqlDump  = "-- ==========================================================\n";
        $sqlDump .= "-- Smart University Proctoring Portal - Database Backup\n";
        $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sqlDump .= "-- Host: localhost | Database: exam_portal_db\n";
        $sqlDump .= "-- ==========================================================\n\n";
        $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            // Drop & Create table statement
            $sqlDump .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
            $sqlDump .= $createStmt['Create Table'] . ";\n\n";

            // Table Data Dump
            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $sqlDump .= "INSERT INTO `{$table}` VALUES\n";
                $rowStrings = [];
                foreach ($rows as $row) {
                    $values = array_map(function($val) use ($pdo) {
                        return is_null($val) ? "NULL" : $pdo->quote($val);
                    }, array_values($row));
                    $rowStrings[] = "(" . implode(", ", $values) . ")";
                }
                $sqlDump .= implode(",\n", $rowStrings) . ";\n\n";
            }
        }

        $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";

        $filename = "backup_exam_portal_" . date('Y_m_d_His') . ".sql";
        $filePath = $backupDir . $filename;

        file_put_contents($filePath, $sqlDump);
        logSystemActivity($_SESSION['user_id'], 'DATABASE_BACKUP_CREATED', "Generated database backup file: {$filename}");

        // If direct download requested
        if (isset($_GET['download']) && $_GET['download'] === '1') {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . strlen($sqlDump));
            echo $sqlDump;
            exit;
        }

        $message = "Database backup generated successfully: <strong>{$filename}</strong> (" . round(strlen($sqlDump) / 1024, 1) . " KB)";

    } catch (Exception $e) {
        $error = "Backup generation failed: " . $e->getMessage();
    }
}

// Fetch list of existing backup files
$existingBackups = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $f) {
        if (pathinfo($f, PATHINFO_EXTENSION) === 'sql') {
            $existingBackups[] = [
                'name' => $f,
                'size' => round(filesize($backupDir . $f) / 1024, 1),
                'date' => date('Y-m-d H:i:s', filemtime($backupDir . $f))
            ];
        }
    }
}
usort($existingBackups, function($a, $b) {
    return strcmp($b['date'], $a['date']);
});

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once dirname(__DIR__) . '/includes/sidebar_admin.php'; ?>

    <div class="main-content">
        <?php require_once dirname(__DIR__) . '/includes/navbar.php'; ?>

        <div class="container-fluid p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div>
                    <h3 class="fw-bold mb-1">Database Backup Center</h3>
                    <p class="text-muted small mb-0">Export entire portal database schema and examinee records into secure SQL archives.</p>
                </div>
                <div>
                    <a href="<?= BASE_URL ?>/backups/backup_db.php?action=generate&download=1" class="btn btn-success btn-sm">
                        <i class="bi bi-cloud-arrow-down-fill me-1"></i> Quick Export & Download
                    </a>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Backup Action Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="fw-bold mb-1">Generate Server Backup</h5>
                            <p class="text-muted small mb-0">Creates a snapshot file stored on the server under <code>backups/auto_backups/</code> ready for disaster recovery.</p>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <a href="<?= BASE_URL ?>/backups/backup_db.php?action=generate" class="btn btn-primary">
                                <i class="bi bi-cpu me-1"></i> Snapshot to Server Disk
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Existing Archives Table -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-archive me-2 text-primary"></i> Saved Database Backups</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 font-monospace small">
                        <thead class="table-light">
                            <tr>
                                <th>Backup Filename</th>
                                <th>File Size</th>
                                <th>Created Timestamp</th>
                                <th class="text-end font-sans-serif">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($existingBackups)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted font-sans-serif">No backup files found on disk yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($existingBackups as $b): ?>
                                    <tr>
                                        <td>
                                            <i class="bi bi-file-earmark-code text-secondary me-2"></i>
                                            <strong><?= htmlspecialchars($b['name']) ?></strong>
                                        </td>
                                        <td><?= $b['size'] ?> KB</td>
                                        <td class="text-muted"><?= $b['date'] ?></td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL ?>/backups/auto_backups/<?= htmlspecialchars($b['name']) ?>" download class="btn btn-sm btn-outline-primary" title="Download File">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
