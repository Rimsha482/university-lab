<?php
/**
 * Global Header Component
 */
if (!defined('ROOT_PATH')) {
    require_once dirname(__DIR__) . '/config/constants.php';
}

$pageTitle = isset($pageTitle) ? $pageTitle . ' - ' . APP_SHORT_NAME : APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/logo.png">
    <!-- Local Offline Bootstrap 5 CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <!-- Local Bootstrap Icons CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.min.css">
    <!-- Portal Theme CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
