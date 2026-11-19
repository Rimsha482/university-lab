<?php
/**
 * Teacher / Examiner Sidebar Navigation
 */
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<div class="sidebar collapse d-lg-flex flex-column" id="sidebarMenu">
    <div class="brand-box d-flex align-items-center gap-2">
        <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Logo" height="38" class="bg-white rounded p-1">
        <div>
            <div class="fw-bold fs-6 text-white text-uppercase" style="letter-spacing: 0.5px;">EXAM PORTAL</div>
            <div class="text-secondary small" style="font-size: 0.72rem;">Examiner Module</div>
        </div>
    </div>

    <div class="py-3 flex-grow-1 overflow-auto">
        <div class="px-3 pb-2 text-uppercase text-secondary fw-bold" style="font-size: 0.65rem; letter-spacing: 1px;">Examiner Tools</div>
        <ul class="nav flex-column mb-auto">
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/teacher/dashboard.php" class="nav-link <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
                    <i class="bi bi-grid"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/teacher/create_exam.php" class="nav-link <?= ($currentPage === 'create_exam.php') ? 'active' : '' ?>">
                    <i class="bi bi-plus-circle"></i>
                    <span>Create Exam</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/teacher/manage_questions.php" class="nav-link <?= ($currentPage === 'manage_questions.php') ? 'active' : '' ?>">
                    <i class="bi bi-patch-question"></i>
                    <span>Manage Questions</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/teacher/live_proctor.php" class="nav-link <?= ($currentPage === 'live_proctor.php') ? 'active' : '' ?>">
                    <i class="bi bi-shield-shaded text-danger"></i>
                    <span class="text-danger fw-bold">Live Proctoring</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/teacher/view_results.php" class="nav-link <?= ($currentPage === 'view_results.php') ? 'active' : '' ?>">
                    <i class="bi bi-card-checklist"></i>
                    <span>Exam Results</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/teacher/export_csv.php" class="nav-link <?= ($currentPage === 'export_csv.php') ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-spreadsheet"></i>
                    <span>Export CSV Reports</span>
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
