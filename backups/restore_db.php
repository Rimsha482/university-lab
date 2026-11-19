<?php
/**
 * Database Restoration Engine
 * Safely imports SQL archives to restore system state
 */

require_once dirname(__DIR__) . '/auth/role_guard.php';
requireRole('admin');

$pageTitle = "Restore Database";
$pdo = getDb();
$message = '';
$error = '';

$backupDir = BACKUP_DIR;

// Handle restore action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sqlContent = '';

    // Check if uploaded file
    if (!empty($_FILES['sql_file']['name']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
        $sqlContent = file_get_contents($_FILES['sql_file']['tmp_name']);
    } elseif (!empty($_POST['selected_backup'])) {
        $chosen = basename($_POST['selected_backup']);
        $target = $backupDir . $chosen;
        if (file_exists($target)) {
            $sqlContent = file_get_contents($target);
        }
    }

    if (empty($sqlContent)) {
        $error = "Please choose or upload a valid SQL file to restore.";
    } else {
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $pdo->exec($sqlContent);
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

            $message = "Database restoration executed successfully. All tables and records updated.";
            logSystemActivity($_SESSION['user_id'], 'DATABASE_RESTORED', "Admin executed database restore from SQL archive.");
        } catch (Exception $e) {
            $error = "Restoration encountered an error: " . $e->getMessage();
        }
    }
}

// Fetch list of local archives
$localBackups = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $f) {
        if (pathinfo($f, PATHINFO_EXTENSION) === 'sql') {
            $localBackups[] = $f;
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once dirname(__DIR__) . '/includes/sidebar_admin.php'; ?>

    <div class="main-content">
        <?php require_once dirname(__DIR__) . '/includes/navbar.php'; ?>

        <div class="container-fluid p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div>
                    <h3 class="fw-bold mb-1">Database Restoration Console</h3>
                    <p class="text-muted small mb-0">Re-import SQL snapshots to restore previous exam configurations and candidate records.</p>
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

            <div class="row g-4">
                <!-- Option 1: Select Existing Server Backup -->
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-hdd me-2 text-primary"></i> Restore from Server Archive</h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if (empty($localBackups)): ?>
                                <p class="text-muted small">No backup snapshots currently saved on server disk.</p>
                            <?php else: ?>
                                <form method="POST" action="" onsubmit="return confirm('WARNING: Restoring will overwrite existing tables and student records. Continue?');">
                                    <div class="mb-4">
                                        <label class="form-label small fw-semibold">Choose Snapshot File</label>
                                        <select name="selected_backup" class="form-select font-monospace" required>
                                            <option value="">-- Select .sql snapshot --</option>
                                            <?php foreach ($localBackups as $bf): ?>
                                                <option value="<?= htmlspecialchars($bf) ?>"><?= htmlspecialchars($bf) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-warning w-100 py-2 fw-semibold">
                                        <i class="bi bi-arrow-repeat me-1"></i> Execute Database Restore
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Option 2: Upload Custom SQL File -->
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-cloud-arrow-up me-2 text-success"></i> Upload External SQL Dump</h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="" enctype="multipart/form-data" onsubmit="return confirm('WARNING: This will execute the uploaded SQL script against exam_portal_db. Proceed?');">
                                <div class="mb-4">
                                    <label class="form-label small fw-semibold">Select .SQL File from Computer</label>
                                    <input type="file" name="sql_file" class="form-control" accept=".sql" required>
                                    <div class="form-text small">Ensure the SQL file was exported from this portal or matches schema.sql.</div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                                    <i class="bi bi-upload me-1"></i> Upload & Import Database
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
