<?php
/**
 * Role-Based Access Control Guard
 * Prevents unauthorized privilege escalation (e.g. students accessing admin/teacher pages)
 */

require_once __DIR__ . '/session_check.php';

if (!function_exists('requireRole')) {
    function requireRole($allowedRoles) {
        if (!is_array($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }

        $userRole = $_SESSION['role'] ?? 'guest';

        if (!in_array($userRole, $allowedRoles)) {
            // Unauthorized access attempt!
            logSystemActivity(
                $_SESSION['user_id'] ?? null,
                'UNAUTHORIZED_ACCESS_BLOCKED',
                "User with role '{$userRole}' attempted to access restricted page: " . ($_SERVER['REQUEST_URI'] ?? '')
            );

            // Redirect according to their actual role
            if ($userRole === 'admin') {
                header("Location: " . BASE_URL . "/admin/dashboard.php?msg=access_denied");
            } elseif ($userRole === 'teacher') {
                header("Location: " . BASE_URL . "/teacher/dashboard.php?msg=access_denied");
            } elseif ($userRole === 'student') {
                header("Location: " . BASE_URL . "/student/dashboard.php?msg=access_denied");
            } else {
                header("Location: " . BASE_URL . "/index.php");
            }
            exit;
        }
    }
}
