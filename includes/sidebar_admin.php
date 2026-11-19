<?php
/**
 * Admin Sidebar Navigation
 */
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<div class="sidebar collapse d-lg-flex flex-column" id="sidebarMenu">
    <div class="brand-box d-flex align-items-center gap-2">
        <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Logo" height="38" class="bg-white rounded p-1">
        <div>
            <div class="fw-bold fs-6 text-white text-uppercase" style="letter-spacing: 0.5px;">SMART LAB</div>
            <div class="text-secondary small" style="font-size: 0.72rem;">Admin Control Center</div>
        </div>
    </div>

    <div class="py-3 flex-grow-1 overflow-auto">
        <div class="px-3 pb-2 text-uppercase text-secondary fw-bold" style="font-size: 0.65rem; letter-spacing: 1px;">Core Lab Management</div>
        <ul class="nav flex-column mb-auto">
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-link <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/admin/lab_pcs.php" class="nav-link <?= ($currentPage === 'lab_pcs.php') ? 'active' : '' ?>">
                    <i class="bi bi-display"></i>
                    <span>Lab PCs Monitor</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/admin/manage_users.php" class="nav-link <?= ($currentPage === 'manage_users.php') ? 'active' : '' ?>">
                    <i class="bi bi-people"></i>
                    <span>Manage Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/admin/manage_courses.php" class="nav-link <?= ($currentPage === 'manage_courses.php') ? 'active' : '' ?>">
                    <i class="bi bi-journal-bookmark"></i>
                    <span>Courses & Subjects</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/admin/system_logs.php" class="nav-link <?= ($currentPage === 'system_logs.php') ? 'active' : '' ?>">
                    <i class="bi bi-clock-history"></i>
                    <span>System Audit Trail</span>
                </a>
            </li>
        </ul>

        <div class="px-3 pt-4 pb-2 text-uppercase text-secondary fw-bold" style="font-size: 0.65rem; letter-spacing: 1px;">Maintenance & Backup</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/backups/backup_db.php" class="nav-link">
                    <i class="bi bi-cloud-arrow-down"></i>
                    <span>Backup Database</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/backups/restore_db.php" class="nav-link">
                    <i class="bi bi-cloud-arrow-up"></i>
                    <span>Restore Database</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="p-3 border-top border-secondary border-opacity-25">
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-danger btn-sm w-100 d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-box-arrow-right"></i>
            <span>Log Out</span>
        </a>
    </div>
</div>
