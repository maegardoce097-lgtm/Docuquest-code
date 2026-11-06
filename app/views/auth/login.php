<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · DocuQuest</title>
    <link rel="stylesheet" href="<?= dq_escape(site_url('assets/docuquest.css')) ?>">
</head>
<body class="auth-page">
<main class="auth-card">
    <a class="brand" href="<?= dq_escape(site_url()) ?>"><span class="brand-mark">DQ</span><span>DocuQuest<small>PUBLIC DOCUMENT SERVICES</small></span></a>
    <p class="eyebrow">WELCOME BACK</p>
    <h1>Sign in to your account</h1>
    <p class="muted">Track document requests and stay in touch with your government office.</p>
    <?php if (!empty($registered)): ?><div class="notice success">Your account is ready. You can sign in now.</div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="notice error"><?= dq_escape($error) ?></div><?php endif; ?>
    <form method="post" action="<?= dq_escape(site_url('login')) ?>" class="stack">
        <?php csrf_field(); ?>
        <label>Email address<input type="email" name="email" required maxlength="255" autocomplete="email" value="<?= dq_escape($_POST['email'] ?? '') ?>"></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="button primary full" type="submit">Sign in</button>
    </form>
    <p class="auth-foot">New to DocuQuest? <a href="<?= dq_escape(site_url('register')) ?>">Create an account</a></p>
</main>
</body>
</html>
