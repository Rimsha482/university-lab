<?php
/**
 * System Audit Trail & Security Logs
 */

require_once dirname(__DIR__) . '/auth/role_guard.php';
requireRole('admin');

$pageTitle = "System Audit Trail";
$pdo = getDb();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_old_logs') {
    try {
        $pdo->query("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $message = "Audit logs older than 30 days have been cleared.";
    } catch (Exception $e) {
        // ignore
    }
}

$filterAction = $_GET['action_type'] ?? 'all';
$sql = "SELECT l.*, u.username, u.full_name, u.role
        FROM system_logs l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE 1=1";
$params = [];

if ($filterAction !== 'all') {
    $sql .= " AND l.action = :action";
    $params[':action'] = $filterAction;
}

$sql .= " ORDER BY l.id DESC LIMIT 150";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Fetch distinct actions for dropdown
$actions = $pdo->query("SELECT DISTINCT action FROM system_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once dirname(__DIR__) . '/includes/sidebar_admin.php'; ?>

    <div class="main-content">
        <?php require_once dirname(__DIR__) . '/includes/navbar.php'; ?>

        <div class="container-fluid p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div>
                    <h3 class="fw-bold mb-1">System Audit Trail</h3>
                    <p class="text-muted small mb-0">Immutable tracking of user authentication, remote commands, and exam occurrences.</p>
                </div>
                <div>
                    <form method="POST" action="" onsubmit="return confirm('Clear logs older than 30 days?');">
                        <input type="hidden" name="action" value="clear_old_logs">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-trash me-1"></i> Clean Old Logs
                        </button>
                    </form>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filter Toolbar -->
            <div class="card mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="" class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <select name="action_type" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="all" <?= ($filterAction === 'all') ? 'selected' : '' ?>>All System Actions</option>
                                <?php foreach ($actions as $act): ?>
                                    <option value="<?= htmlspecialchars($act) ?>" <?= ($filterAction === $act) ? 'selected' : '' ?>><?= htmlspecialchars($act) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8 text-md-end text-muted small">
                            Showing latest <strong><?= count($logs) ?></strong> logged system events.
                        </div>
                    </form>
                </div>
            </div>

            <!-- Audit Log Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 font-monospace small">
                        <thead class="table-light">
                            <tr>
                                <th>Timestamp</th>
                                <th>Action</th>
                                <th>Initiator</th>
                                <th>Audit Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No audit logs found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td class="text-nowrap text-muted"><?= htmlspecialchars($l['created_at']) ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($l['action']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($l['username']): ?>
                                                <span class="fw-bold text-dark"><?= htmlspecialchars($l['username']) ?></span>
                                                <span class="text-muted">(<?= htmlspecialchars($l['role']) ?>)</span>
                                            <?php else: ?>
                                                <span class="text-muted">SYSTEM</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-break" style="max-width: 380px;">
                                            <?= htmlspecialchars($l['details']) ?>
                                        </td>
                                        <td class="text-muted"><?= htmlspecialchars($l['ip_address']) ?></td>
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
