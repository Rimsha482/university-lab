<?php
/**
 * User Management & Bulk CSV Enrollment
 */

require_once dirname(__DIR__) . '/auth/role_guard.php';
requireRole('admin');

$pageTitle = "Manage Users";
$pdo = getDb();
$message = '';
$error = '';

// Handle Actions: Add, Edit, Delete, Bulk CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $username = strtolower(trim($_POST['username'] ?? ''));
        $email = strtolower(trim($_POST['email'] ?? ''));
        $fullName = trim($_POST['full_name'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['student', 'teacher', 'admin']) ? $_POST['role'] : 'student';
        $rollNumber = !empty($_POST['roll_number']) ? strtoupper(trim($_POST['roll_number'])) : null;
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? 'Password@123';

        if (empty($username) || empty($email) || empty($fullName)) {
            $error = "Please fill in all mandatory fields.";
        } else {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role, roll_number, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$username, $email, $hash, $fullName, $role, $rollNumber, $phone]);
                $message = "User '{$username}' added successfully with default password.";
                logSystemActivity($_SESSION['user_id'], 'USER_CREATED', "Admin created user '{$username}' ({$role})");
            } catch (Exception $e) {
                $error = "Error adding user: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_user') {
        $userId = intval($_POST['user_id'] ?? 0);
        if ($userId === (int)$_SESSION['user_id']) {
            $error = "You cannot delete your own active administrator account.";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $message = "User removed successfully.";
            } catch (Exception $e) {
                $error = "Error deleting user: " . $e->getMessage();
            }
        }
    } elseif ($action === 'toggle_status') {
        $userId = intval($_POST['user_id'] ?? 0);
        $newStatus = ($_POST['current_status'] === 'active') ? 'inactive' : 'active';
        try {
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $userId]);
            $message = "User status updated to " . ucfirst($newStatus);
        } catch (Exception $e) {
            $error = "Error updating status: " . $e->getMessage();
        }
    } elseif ($action === 'bulk_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $tmpFile = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($tmpFile, 'r');
            if ($handle !== false) {
                $header = fgetcsv($handle); // Read header
                $importedCount = 0;
                $defaultPass = password_hash('Password@123', PASSWORD_BCRYPT);

                $stmt = $pdo->prepare("INSERT INTO users (full_name, roll_number, email, username, password, role, status) VALUES (?, ?, ?, ?, ?, 'student', 'active') ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), roll_number = VALUES(roll_number)");

                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                    if (count($data) >= 4) {
                        $name = trim($data[0]);
                        $roll = strtoupper(trim($data[1]));
                        $email = strtolower(trim($data[2]));
                        $uname = strtolower(trim($data[3]));
                        $pass = !empty($data[4]) ? password_hash(trim($data[4]), PASSWORD_BCRYPT) : $defaultPass;

                        if (!empty($name) && !empty($uname) && !empty($email)) {
                            try {
                                $stmt->execute([$name, $roll, $email, $uname, $pass]);
                                $importedCount++;
                            } catch (Exception $e) {
                                // Skip on duplicate key conflict
                            }
                        }
                    }
                }
                fclose($handle);
                $message = "Bulk CSV enrollment completed: {$importedCount} students imported/synced.";
                logSystemActivity($_SESSION['user_id'], 'BULK_CSV_IMPORT', "Imported {$importedCount} students via CSV");
            } else {
                $error = "Could not open uploaded CSV file.";
            }
        } else {
            $error = "Please choose a valid CSV file to upload.";
        }
    }
}

// Filter by role
$filterRole = $_GET['role'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($filterRole !== 'all') {
    $sql .= " AND role = :role";
    $params[':role'] = $filterRole;
}

if (!empty($searchQuery)) {
    $sql .= " AND (full_name LIKE :q OR username LIKE :q OR email LIKE :q OR roll_number LIKE :q)";
    $params[':q'] = "%{$searchQuery}%";
}

$sql .= " ORDER BY role ASC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once dirname(__DIR__) . '/includes/sidebar_admin.php'; ?>

    <div class="main-content">
        <?php require_once dirname(__DIR__) . '/includes/navbar.php'; ?>

        <div class="container-fluid p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div>
                    <h3 class="fw-bold mb-1">User Administration</h3>
                    <p class="text-muted small mb-0">Manage university examiners, enrolled candidates, and system administrators.</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#bulkCsvModal">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Bulk CSV Import
                    </button>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-person-plus me-1"></i> Add User
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

            <!-- Filter & Search Toolbar -->
            <div class="card mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="" class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="all" <?= ($filterRole === 'all') ? 'selected' : '' ?>>All Roles</option>
                                <option value="student" <?= ($filterRole === 'student') ? 'selected' : '' ?>>Students Only</option>
                                <option value="teacher" <?= ($filterRole === 'teacher') ? 'selected' : '' ?>>Teachers Only</option>
                                <option value="admin" <?= ($filterRole === 'admin') ? 'selected' : '' ?>>Admins Only</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                <input type="text" name="q" class="form-control" placeholder="Search name, roll no, email, or username..." value="<?= htmlspecialchars($searchQuery) ?>">
                                <button type="submit" class="btn btn-outline-secondary">Search</button>
                            </div>
                        </div>
                        <div class="col-md-3 text-md-end text-muted small">
                            Total Records: <strong><?= count($users) ?></strong>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>User Profile</th>
                                <th>Role</th>
                                <th>Roll Number</th>
                                <th>Contact Email</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted small">No users match your filter criteria.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <img src="<?= BASE_URL ?>/assets/img/<?= htmlspecialchars($u['avatar'] ?? 'default_avatar.png') ?>" alt="Avatar" width="36" height="36" class="rounded-circle border">
                                                <div>
                                                    <div class="fw-semibold small"><?= htmlspecialchars($u['full_name']) ?></div>
                                                    <small class="text-muted">@<?= htmlspecialchars($u['username']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill text-uppercase <?= ($u['role'] === 'admin') ? 'bg-danger' : (($u['role'] === 'teacher') ? 'bg-primary' : 'bg-info text-dark') ?>" style="font-size:0.7rem;">
                                                <?= htmlspecialchars($u['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="small font-monospace"><?= htmlspecialchars($u['roll_number'] ?? '—') ?></span>
                                        </td>
                                        <td>
                                            <small><?= htmlspecialchars($u['email']) ?></small>
                                        </td>
                                        <td>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="current_status" value="<?= $u['status'] ?>">
                                                <button type="submit" class="btn btn-sm p-0 border-0" title="Click to toggle status">
                                                    <?php if ($u['status'] === 'active'): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                                <form method="POST" action="" onsubmit="return confirm('Permanently delete user <?= htmlspecialchars($u['username']) ?>?');" class="d-inline">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger p-1" title="Delete User">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add Individual User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Full Legal Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" placeholder="Dr. Alice Vance" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" placeholder="alice.v" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Role <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" id="roleSelector">
                                <option value="student">Student</option>
                                <option value="teacher">Teacher / Examiner</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Institutional Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="alice@university.edu" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6" id="rollNumberBox">
                            <label class="form-label small fw-semibold">Roll Number (Students)</label>
                            <input type="text" name="roll_number" class="form-control" placeholder="CS-2024-010">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Password</label>
                            <input type="text" name="password" class="form-control" value="Password@123">
                            <div class="form-text small">Default: Password@123</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk CSV Modal -->
<div class="modal fade" id="bulkCsvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Bulk Student Enrollment via CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="bulk_csv">
                    <p class="small text-muted mb-3">Upload a standard CSV file with the following column structure in order:</p>
                    <div class="p-2 bg-light border rounded small font-monospace mb-3">
                        full_name, roll_number, email, username, password(optional)
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select CSV Spreadsheet <span class="text-danger">*</span></label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Upload & Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
