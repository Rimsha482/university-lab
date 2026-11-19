<?php
/**
 * Lab Workstations Real-Time Visual Grid
 */

require_once dirname(__DIR__) . '/auth/role_guard.php';
requireRole('admin');

$pageTitle = "Lab PCs Monitor";
$pdo = getDb();
$message = '';
$error = '';

// Handle PC Actions: Add, Reset, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_pc') {
        $pcName = strtoupper(trim($_POST['pc_name'] ?? ''));
        $labRoom = trim($_POST['lab_room'] ?? '');
        $ip = trim($_POST['ip_address'] ?? '');

        if (!empty($pcName) && !empty($labRoom)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO lab_pcs (pc_name, lab_room, ip_address, status) VALUES (?, ?, ?, 'online')");
                $stmt->execute([$pcName, $labRoom, $ip]);
                $message = "Workstation {$pcName} added successfully.";
            } catch (Exception $e) {
                $error = "Error adding workstation: " . $e->getMessage();
            }
        }
    } elseif ($action === 'reset_pc') {
        $pcId = intval($_POST['pc_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("UPDATE lab_pcs SET current_student_id = NULL, current_exam_id = NULL, status = 'online', last_heartbeat = NOW() WHERE id = ?");
            $stmt->execute([$pcId]);
            $message = "Workstation session cleared and reset to Online.";
        } catch (Exception $e) {
            $error = "Error resetting PC: " . $e->getMessage();
        }
    } elseif ($action === 'delete_pc') {
        $pcId = intval($_POST['pc_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM lab_pcs WHERE id = ?");
            $stmt->execute([$pcId]);
            $message = "Workstation removed from inventory.";
        } catch (Exception $e) {
            $error = "Error deleting PC: " . $e->getMessage();
        }
    }
}

// Fetch all Lab PCs with student info
$pcs = $pdo->query("SELECT p.*, u.full_name as student_name, u.roll_number, e.title as exam_title
                    FROM lab_pcs p
                    LEFT JOIN users u ON p.current_student_id = u.id
                    LEFT JOIN exams e ON p.current_exam_id = e.id
                    ORDER BY p.lab_room ASC, p.pc_name ASC")->fetchAll();

// Group PCs by lab room
$labs = [];
foreach ($pcs as $p) {
    $labs[$p['lab_room']][] = $p;
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
                    <h3 class="fw-bold mb-1">Lab Workstations Visual Monitor</h3>
                    <p class="text-muted small mb-0">Live status grid of hardware terminals across University Computer Labs.</p>
                </div>
                <div>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPcModal">
                        <i class="bi bi-plus-lg me-1"></i> Add Workstation
                    </button>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Legend Bar -->
            <div class="card mb-4 bg-white">
                <div class="card-body py-2 px-3 d-flex flex-wrap gap-4 align-items-center small">
                    <span class="fw-bold text-secondary">Status Legend:</span>
                    <span class="d-flex align-items-center gap-2">
                        <span class="pulse-indicator green"></span> Online / Idle
                    </span>
                    <span class="d-flex align-items-center gap-2">
                        <span class="pulse-indicator blue"></span> In Proctored Exam
                    </span>
                    <span class="d-flex align-items-center gap-2">
                        <span class="pulse-indicator red"></span> Offline / Disconnected
                    </span>
                </div>
            </div>

            <!-- Labs Grid -->
            <?php if (empty($labs)): ?>
                <div class="card text-center py-5">
                    <i class="bi bi-display fs-1 text-muted"></i>
                    <p class="text-muted mt-2">No workstations configured. Click "Add Workstation" to register lab terminals.</p>
                </div>
            <?php else: ?>
                <?php foreach ($labs as $roomName => $roomPcs): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
                            <span class="fw-bold"><i class="bi bi-building text-primary me-2"></i> <?= htmlspecialchars($roomName) ?></span>
                            <span class="badge bg-secondary"><?= count($roomPcs) ?> Terminals</span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($roomPcs as $pc): 
                                    // Determine if offline by heartbeat threshold (> 60 seconds)
                                    $isRecent = false;
                                    if ($pc['last_heartbeat']) {
                                        $diff = time() - strtotime($pc['last_heartbeat']);
                                        if ($diff <= 60) $isRecent = true;
                                    }
                                    
                                    $computedStatus = $pc['status'];
                                    if ($computedStatus !== 'in_exam' && !$isRecent) {
                                        $computedStatus = 'offline';
                                    }
                                ?>
                                    <div class="col-6 col-sm-4 col-md-3 col-xl-2">
                                        <div class="pc-grid-box status-<?= $computedStatus ?>">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="badge bg-dark bg-opacity-75 font-monospace" style="font-size:0.75rem;"><?= htmlspecialchars($pc['pc_name']) ?></span>
                                                <span class="pulse-indicator <?= ($computedStatus === 'in_exam' ? 'blue' : ($computedStatus === 'online' ? 'green' : 'red')) ?>"></span>
                                            </div>

                                            <i class="bi bi-display fs-2 <?= ($computedStatus === 'in_exam' ? 'text-primary' : ($computedStatus === 'online' ? 'text-success' : 'text-secondary')) ?>"></i>

                                            <div class="mt-2 text-truncate small fw-semibold">
                                                <?php if (!empty($pc['student_name'])): ?>
                                                    <span class="text-primary d-block text-truncate"><?= htmlspecialchars($pc['student_name']) ?></span>
                                                    <span class="text-muted" style="font-size:0.7rem;"><?= htmlspecialchars($pc['roll_number']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-size:0.75rem;"><?= ucfirst($computedStatus) ?></span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="mt-2 pt-2 border-top d-flex justify-content-center gap-1">
                                                <!-- Reset PC Button -->
                                                <form method="POST" action="" onsubmit="return confirm('Reset this workstation? Current student will be detached.');" class="d-inline">
                                                    <input type="hidden" name="action" value="reset_pc">
                                                    <input type="hidden" name="pc_id" value="<?= $pc['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary p-1" title="Reset Session" style="font-size: 0.7rem;">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </button>
                                                </form>
                                                <!-- Delete PC Button -->
                                                <form method="POST" action="" onsubmit="return confirm('Remove workstation <?= $pc['pc_name'] ?>?');" class="d-inline">
                                                    <input type="hidden" name="action" value="delete_pc">
                                                    <input type="hidden" name="pc_id" value="<?= $pc['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger p-1" title="Delete PC" style="font-size: 0.7rem;">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Add Workstation Modal -->
<div class="modal fade" id="addPcModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Register New Workstation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_pc">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Workstation Identifier <span class="text-danger">*</span></label>
                        <input type="text" name="pc_name" class="form-control" placeholder="e.g. LAB1-PC21" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Lab Room Name <span class="text-danger">*</span></label>
                        <input type="text" name="lab_room" class="form-control" placeholder="e.g. Lab 101 - Systems Lab" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Static IP Address (Optional)</label>
                        <input type="text" name="ip_address" class="form-control" placeholder="192.168.1.121">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Workstation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
