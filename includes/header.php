<?php
// includes/header.php
requireLogin(); // Every page using this header is protected

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$is_admin     = isAdmin();
$user_name    = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$user_role    = $_SESSION['user_role'] ?? 'company';
$role_label   = $is_admin ? 'Administrator' : 'Company';
$role_color   = $is_admin ? '#7c3aed' : '#4f46e5';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' — ' : '' ?><?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= $base ?? '' ?>assets/css/style.css">
</head>
<body>

<nav class="sidebar">
    <div class="sidebar-logo">
        <span class="logo-icon">📄</span>
        <span class="logo-text"><?= APP_NAME ?></span>
    </div>

    <!-- Logged-in user card -->
    <div class="sidebar-user">
        <div class="sidebar-user-avatar"><?= strtoupper(mb_substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= $user_name ?></div>
            <div class="sidebar-user-role" style="background:<?= $role_color ?>"><?= $role_label ?></div>
        </div>
    </div>

    <ul class="nav-links">
        <li><a href="<?= $base ?? '' ?>index.php"
               class="<?= $current_page==='index'?'active':'' ?>">
            <span>🏠</span> Dashboard
        </a></li>

        <li><a href="<?= $base ?? '' ?>pages/invoices.php"
               class="<?= $current_page==='invoices'?'active':'' ?>">
            <span>📋</span> Invoices
        </a></li>

        <li><a href="<?= $base ?? '' ?>pages/payments.php"
               class="<?= $current_page==='payments'?'active':'' ?>">
            <span>💳</span> Payments
        </a></li>

        <li><a href="<?= $base ?? '' ?>pages/invoice_status.php"
               class="<?= $current_page==='invoice_status'?'active':'' ?>">
            <span>🔍</span> Status Check
        </a></li>

        <?php if ($is_admin): ?>
        <li class="nav-section-label">Admin</li>
        <li><a href="<?= $base ?? '' ?>pages/clients.php"
               class="<?= $current_page==='clients'?'active':'' ?>">
            <span>👥</span> Clients
        </a></li>
        <li><a href="<?= $base ?? '' ?>pages/manage_users.php"
               class="<?= $current_page==='manage_users'?'active':'' ?>">
            <span>🔐</span> Users
        </a></li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-bottom">
        <a href="<?= $base ?? '' ?>logout.php" class="btn-logout">
            <span>🚪</span> Sign Out
        </a>
        <div class="sidebar-footer">Invoice Manager v1.0</div>
    </div>
</nav>

<div class="main-wrapper">
    <header class="topbar">
        <div class="topbar-title"><?= $page_title ?? 'Dashboard' ?></div>
        <div class="topbar-right">
            <span class="date-badge">📅 <?= date('D, d M Y') ?></span>
            <?php if (!$is_admin): ?>
            <span class="date-badge" style="background:#ede9fe;border-color:#c4b5fd;color:#5b21b6;">
                🏢 <?= htmlspecialchars($_SESSION['user_name']) ?>
            </span>
            <?php endif; ?>
        </div>
    </header>
    <main class="content">
