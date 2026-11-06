<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin sign in · DocuQuest</title>
    <link rel="stylesheet" href="<?= dq_escape(site_url('assets/docuquest.css?v=20261012-workspace')) ?>">
</head>
<body class="auth-page">
<main class="auth-card">
    <a class="brand" href="<?= dq_escape(site_url()) ?>"><span class="brand-mark">DQ</span><span>DocuQuest<small>GOVERNMENT ADMINISTRATION</small></span></a>
    <p class="eyebrow">STAFF ACCESS</p>
    <h1>Admin sign in</h1>
    <p class="muted">Sign in to manage requests. Your requester session, if any, will remain active.</p>
    <?php if (!empty($error)): ?><div class="notice error"><?= dq_escape($error) ?></div><?php endif; ?>
    <form method="post" action="<?= dq_escape(site_url('admin/login')) ?>" class="stack">
        <?php csrf_field(); ?>
        <label>Admin email<input type="email" name="email" required maxlength="255" autocomplete="username" value="<?= dq_escape($_POST['email'] ?? '') ?>"></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="button primary full" type="submit">Sign in to admin</button>
    </form>
    <p class="auth-foot"><a href="<?= dq_escape(site_url('login')) ?>">Back to requester sign in</a></p>
</main>
</body>
</html>
