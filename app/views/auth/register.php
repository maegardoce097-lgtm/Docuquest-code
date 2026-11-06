<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create account · DocuQuest</title>
    <link rel="stylesheet" href="<?= dq_escape(site_url('assets/docuquest.css')) ?>">
</head>
<body class="auth-page">
<main class="auth-card">
    <a class="brand" href="<?= dq_escape(site_url()) ?>"><span class="brand-mark">DQ</span><span>DocuQuest<small>PUBLIC DOCUMENT SERVICES</small></span></a>
    <p class="eyebrow">GET STARTED</p>
    <h1>Create your account</h1>
    <p class="muted">One account to request documents, follow progress, and contact the office.</p>
    <?php if (!empty($error)): ?><div class="notice error"><?= dq_escape($error) ?></div><?php endif; ?>
    <form method="post" action="<?= dq_escape(site_url('register')) ?>" class="stack">
        <?php csrf_field(); ?>
        <label>Full name<input type="text" name="username" required minlength="2" maxlength="100" autocomplete="name" value="<?= dq_escape($values['username'] ?? '') ?>"></label>
        <label>Email address<input type="email" name="email" required maxlength="255" autocomplete="email" value="<?= dq_escape($values['email'] ?? '') ?>"></label>
        <label>Password<input type="password" name="password" required minlength="10" autocomplete="new-password"><span class="field-hint">Use at least 10 characters.</span></label>
        <label>Confirm password<input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>
        <button class="button primary full" type="submit">Create account</button>
    </form>
    <p class="auth-foot">Already registered? <a href="<?= dq_escape(site_url('login')) ?>">Sign in</a></p>
</main>
</body>
</html>
