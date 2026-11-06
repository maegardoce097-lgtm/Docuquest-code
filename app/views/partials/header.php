<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= dq_escape($title ?? 'DocuQuest') ?></title>
    <link rel="stylesheet" href="<?= dq_escape(site_url('assets/docuquest.css?v=20261020-naujan-building')) ?>">
</head>
<body>
<?php $requesterMode = empty($adminMode) && isset($user['role']) && $user['role'] === 'user'; ?>
<?php $adminWorkspaceMode = !empty($adminMode) && isset($user['role']) && $user['role'] === 'admin'; ?>
<?php if ($requesterMode): ?>
<div class="requester-app">
    <aside class="requester-sidebar">
        <a class="brand requester-brand" href="<?= dq_escape(site_url()) ?>">
            <span class="brand-mark">DQ</span><span>DocuQuest<small>PUBLIC SERVICE PORTAL</small></span>
        </a>
        <div class="requester-sidebar-label">REQUESTER MENU</div>
        <nav class="requester-sidebar-nav" aria-label="Requester navigation">
            <a class="<?= ($activeRequesterNav ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= dq_escape(site_url('dashboard')) ?>"><span class="requester-nav-icon">⌂</span><span>My dashboard</span></a>
            <a class="<?= ($activeRequesterNav ?? '') === 'requests' ? 'active' : '' ?>" href="<?= dq_escape(site_url('dashboard#request-form')) ?>"><span class="requester-nav-icon">＋</span><span>New request</span></a>
            <a href="<?= dq_escape(site_url('dashboard#request-history')) ?>"><span class="requester-nav-icon">▤</span><span>Request history</span></a>
            <a class="<?= ($activeRequesterNav ?? '') === 'notifications' ? 'active' : '' ?>" href="<?= dq_escape(site_url('notifications')) ?>"><span class="requester-nav-icon">◉</span><span>Notifications</span><span class="requester-sidebar-count" data-notification-count><?= (int) ($unreadCount ?? 0) ?></span></a>
        </nav>
        <div class="requester-sidebar-spacer"></div>
        <section class="requester-sidebar-help">
            <span class="requester-help-icon">i</span>
            <strong>Need assistance?</strong>
            <p>Open a request to message the office about it.</p>
            <a href="<?= dq_escape(site_url('dashboard#request-history')) ?>">View my requests →</a>
        </section>
        <div class="requester-sidebar-account">
            <span class="requester-sidebar-avatar"><?= dq_escape(strtoupper(substr($user['username'], 0, 1))) ?></span>
            <span class="requester-sidebar-user"><strong><?= dq_escape($user['username']) ?></strong><small>REQUESTER ACCOUNT</small></span>
            <form method="post" action="<?= dq_escape(site_url('logout')) ?>">
                <?php csrf_field(); ?>
                <button type="submit" class="requester-logout" aria-label="Sign out" title="Sign out">↗</button>
            </form>
        </div>
    </aside>
    <div class="requester-main">
        <header class="requester-topbar">
            <div><span class="requester-topbar-kicker">DOCUQUEST / REQUESTER</span><strong><?= dq_escape($title ?? 'My account') ?></strong></div>
            <div class="requester-topbar-actions">
                <a class="requester-office-link" href="<?= dq_escape(site_url()) ?>">Public portal <span aria-hidden="true">↗</span></a>
                <span class="requester-online"><i></i> Service online</span>
            </div>
        </header>
        <main class="page-shell requester-page-shell">
<?php elseif ($adminWorkspaceMode): ?>
<div class="admin-app">
    <aside class="admin-sidebar">
        <a class="brand admin-brand" href="<?= dq_escape(site_url('admin')) ?>">
            <span class="brand-mark">DQ</span><span>DocuQuest<small>GOVERNMENT OFFICE</small></span>
        </a>
        <div class="admin-sidebar-label">OFFICE WORKSPACE</div>
        <nav class="admin-sidebar-nav" aria-label="Admin navigation">
            <a class="<?= ($activeAdminNav ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= dq_escape(site_url('admin')) ?>"><span class="admin-nav-icon">▦</span><span>Request dashboard</span></a>
            <a class="<?= ($activeAdminNav ?? '') === 'archive' ? 'active' : '' ?>" href="<?= dq_escape(site_url('admin/archive')) ?>"><span class="admin-nav-icon">▤</span><span>Request archive</span></a>
            <a class="<?= ($activeAdminNav ?? '') === 'notifications' ? 'active' : '' ?>" href="<?= dq_escape(site_url('admin/notifications')) ?>"><span class="admin-nav-icon">◉</span><span>Notifications</span><span class="admin-sidebar-count" data-notification-count><?= (int) ($unreadCount ?? 0) ?></span></a>
        </nav>
        <div class="admin-sidebar-spacer"></div>
        <section class="admin-sidebar-help">
            <span class="admin-help-icon">i</span>
            <strong>Review and respond</strong>
            <p>Open a request to check its details, update progress, or message the requester.</p>
            <a href="<?= dq_escape(site_url('admin/archive')) ?>">Search request records →</a>
        </section>
        <div class="admin-sidebar-account">
            <span class="admin-sidebar-avatar"><?= dq_escape(strtoupper(substr($user['username'], 0, 1))) ?></span>
            <span class="admin-sidebar-user"><strong><?= dq_escape($user['username']) ?></strong><small>OFFICE ADMIN</small></span>
            <form method="post" action="<?= dq_escape(site_url('admin/logout')) ?>">
                <?php csrf_field(); ?>
                <button type="submit" class="admin-logout" aria-label="Sign out of admin" title="Sign out">↗</button>
            </form>
        </div>
    </aside>
    <div class="admin-main">
        <header class="admin-topbar">
            <div><span class="admin-topbar-kicker">DOCUQUEST / ADMINISTRATION</span><strong><?= dq_escape($title ?? 'Office workspace') ?></strong></div>
            <div class="admin-topbar-actions">
                <a class="admin-public-link" href="<?= dq_escape(site_url()) ?>">Public portal <span aria-hidden="true">↗</span></a>
                <span class="admin-online"><i></i> Office workspace</span>
            </div>
        </header>
        <main class="page-shell admin-page-shell">
<?php else: ?>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= dq_escape(site_url(!empty($adminMode) ? 'admin' : '')) ?>"><span class="brand-mark">DQ</span><span>DocuQuest<small>PUBLIC DOCUMENT SERVICES</small></span></a>
        <nav class="main-nav" aria-label="Main navigation">
            <?php if (!empty($adminMode)): ?>
                <a href="<?= dq_escape(site_url('admin')) ?>">Admin dashboard</a>
                <a href="<?= dq_escape(site_url('admin/archive')) ?>">Request archive</a>
                <a href="<?= dq_escape(site_url('admin/notifications')) ?>">Notifications <span class="nav-count" data-notification-count><?= (int) ($unreadCount ?? 0) ?></span></a>
            <?php else: ?>
                <a href="<?= dq_escape(site_url()) ?>">Home</a>
                <a href="<?= dq_escape(site_url('dashboard')) ?>">Dashboard</a>
                <a href="<?= dq_escape(site_url('notifications')) ?>">Notifications <span class="nav-count" data-notification-count><?= (int) ($unreadCount ?? 0) ?></span></a>
                <a href="<?= dq_escape(site_url('admin/login')) ?>">Admin sign in</a>
            <?php endif; ?>
        </nav>
        <div class="account">
            <span class="avatar"><?= dq_escape(strtoupper(substr($user['username'], 0, 1))) ?></span>
            <span class="account-name"><?= dq_escape($user['username']) ?><small><?= $user['role'] === 'admin' ? 'OFFICE ADMIN' : 'REQUESTER' ?></small></span>
            <form method="post" action="<?= dq_escape(site_url(!empty($adminMode) ? 'admin/logout' : 'logout')) ?>">
                <?php csrf_field(); ?>
                <button class="button subtle" type="submit">Sign out</button>
            </form>
        </div>
    </div>
</header>
<main class="page-shell">
<?php endif; ?>
<?php if (!empty($success)): ?><div class="notice success"><?= dq_escape($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="notice error"><?= dq_escape($error) ?></div><?php endif; ?>
