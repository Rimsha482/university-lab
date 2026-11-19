<?php
/**
 * Global Top Navigation Bar
 */
$currentUserRole = $_SESSION['role'] ?? 'guest';
$currentUserName = $_SESSION['full_name'] ?? 'User';
$currentUserAvatar = $_SESSION['avatar'] ?? 'default_avatar.png';
$currentPcName = $_SESSION['lab_pc_name'] ?? null;
?>
<nav class="navbar navbar-expand-lg top-navbar sticky-top">
    <div class="container-fluid">
        <!-- Mobile Sidebar Toggle -->
        <button class="btn btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
            <i class="bi bi-list"></i>
        </button>

        <!-- Current Breadcrumb / Section -->
        <div class="d-flex align-items-center gap-2">
            <span class="badge rounded-pill bg-primary px-3 py-2 text-uppercase" style="letter-spacing: 0.5px;">
                <i class="bi bi-person-badge me-1"></i> <?= htmlspecialchars($currentUserRole) ?>
            </span>
            <?php if (!empty($currentPcName)): ?>
                <span class="badge rounded-pill bg-success-subtle text-success border border-success px-3 py-2">
                    <i class="bi bi-pc-display me-1"></i> <?= htmlspecialchars($currentPcName) ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Right Side User Dropdown -->
        <div class="ms-auto d-flex align-items-center gap-3">
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="<?= BASE_URL ?>/assets/img/<?= htmlspecialchars($currentUserAvatar) ?>" alt="Avatar" width="34" height="34" class="rounded-circle me-2 border">
                    <span class="d-none d-md-inline fw-semibold small"><?= htmlspecialchars($currentUserName) ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2 rounded-3" aria-labelledby="dropdownUser">
                    <li><h6 class="dropdown-header small text-muted">Signed in as <strong><?= htmlspecialchars($_SESSION['username'] ?? '') ?></strong></h6></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>/auth/reset_password.php"><i class="bi bi-key me-2 text-secondary"></i>Change Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item small text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
