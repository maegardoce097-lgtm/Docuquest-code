<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Request public documents online, follow progress, and contact the government office through DocuQuest.">
    <title><?= dq_escape($title) ?></title>
    <link rel="stylesheet" href="<?= dq_escape(site_url('assets/docuquest.css?v=20261020-naujan-building')) ?>">
</head>
<body class="landing-page" id="top">
<aside class="landing-sidebar">
    <a class="brand landing-sidebar-brand" href="<?= dq_escape(site_url()) ?>">
        <span class="brand-mark">DQ</span><span>DocuQuest<small>PUBLIC SERVICE PORTAL</small></span>
    </a>
    <p class="landing-sidebar-label">PUBLIC PORTAL</p>
    <nav class="landing-sidebar-nav" aria-label="Public portal navigation">
        <a class="active" href="#top"><span class="landing-sidebar-icon">⌂</span><span>Home</span></a>
        <a href="#how-it-works"><span class="landing-sidebar-icon">↗</span><span>How it works</span></a>
        <a href="#services"><span class="landing-sidebar-icon">▤</span><span>Services</span></a>
        <a href="#about"><span class="landing-sidebar-icon">i</span><span>About DocuQuest</span></a>
        <?php if ($user): ?>
            <a href="<?= dq_escape(site_url('dashboard')) ?>"><span class="landing-sidebar-icon">▣</span><span>My dashboard</span></a>
            <a href="<?= dq_escape(site_url('notifications')) ?>"><span class="landing-sidebar-icon">◉</span><span>Notifications</span></a>
        <?php endif; ?>
    </nav>
    <div class="landing-sidebar-spacer"></div>
    <div class="landing-sidebar-service">
        <span class="landing-sidebar-service-mark">i</span>
        <strong>Need assistance?</strong>
        <p>Sign in to contact the office about your request.</p>
        <a href="<?= dq_escape(site_url($user ? 'dashboard#request-history' : 'login')) ?>"><?= $user ? 'View my requests →' : 'Requester sign in →' ?></a>
    </div>
    <div class="landing-sidebar-account">
        <?php if ($user): ?>
            <span class="landing-sidebar-avatar"><?= dq_escape(strtoupper(substr($user['username'], 0, 1))) ?></span>
            <span class="landing-sidebar-user"><strong><?= dq_escape($user['username']) ?></strong><small>REQUESTER ACCOUNT</small></span>
            <a class="landing-sidebar-signout" href="<?= dq_escape(site_url('dashboard')) ?>" aria-label="Open dashboard">↗</a>
        <?php else: ?>
            <span class="landing-sidebar-avatar">DQ</span>
            <span class="landing-sidebar-user"><strong>Public access</strong><small>DOCUMENT SERVICES</small></span>
            <a class="landing-sidebar-signout" href="<?= dq_escape(site_url('admin/login')) ?>" aria-label="Government staff sign in">↗</a>
        <?php endif; ?>
    </div>
</aside>
<header class="landing-header">
    <a class="brand" href="<?= dq_escape(site_url()) ?>" aria-label="DocuQuest home">
        <span class="brand-mark">DQ</span><span>DocuQuest<small>PUBLIC DOCUMENT SERVICES</small></span>
    </a>
    <span class="landing-header-context">GOVERNMENT DOCUMENT REQUESTS</span>
    <div class="landing-actions">
        <?php if ($user): ?>
            <a class="button subtle" href="<?= dq_escape(site_url('notifications')) ?>">Notifications</a>
            <a class="button primary" href="<?= dq_escape(site_url('dashboard')) ?>">Go to dashboard <span aria-hidden="true">→</span></a>
        <?php else: ?>
            <a class="landing-sign-in" href="<?= dq_escape(site_url('login')) ?>">Sign in</a>
            <a class="button primary" href="<?= dq_escape(site_url('register')) ?>">Create an account <span aria-hidden="true">→</span></a>
        <?php endif; ?>
    </div>
</header>
<main>
    <section class="landing-hero">
        <div class="landing-hero-copy">
            <p class="eyebrow"><span class="hero-status-dot"></span> PUBLIC DOCUMENT SERVICES, MADE CLEARER</p>
            <h1>Your documents.<br><span>Your time.</span></h1>
            <p class="landing-lede">Request the public documents you need online, follow every progress update, and reach the office without repeated trips.</p>
            <div class="landing-hero-actions">
                <?php if ($user): ?>
                    <a class="button primary landing-cta" href="<?= dq_escape(site_url('dashboard')) ?>">Open my dashboard <span aria-hidden="true">→</span></a>
                <?php else: ?>
                    <a class="button primary landing-cta" href="<?= dq_escape(site_url('register')) ?>">Start a request <span aria-hidden="true">→</span></a>
                    <a class="button secondary landing-cta-secondary" href="<?= dq_escape(site_url('login')) ?>">Sign in to your account</a>
                <?php endif; ?>
            </div>
            <p class="landing-trust"><span aria-hidden="true">✓</span> Secure account <span aria-hidden="true">✓</span> Progress you can follow</p>
        </div>
        <div class="hero-visual" aria-hidden="true">
            <div class="hero-visual-glow"></div>
            <div class="hero-document">
                <span class="hero-document-mark">DQ</span>
                <span class="hero-doc-line hero-doc-line-long"></span>
                <span class="hero-doc-line"></span>
                <span class="hero-doc-line hero-doc-line-short"></span>
                <span class="hero-progress"><i></i></span>
                <span class="hero-doc-caption">REQUEST IN PROGRESS</span>
            </div>
            <div class="hero-float-card"><span class="hero-float-icon">✓</span><span><strong>Request received</strong><small>We’ll keep you updated</small></span></div>
            <span class="hero-spark hero-spark-one">✳</span><span class="hero-spark hero-spark-two">✦</span>
        </div>
        <a class="hero-scroll" href="#how-it-works"><span></span> See how it works</a>
    </section>

    <section class="landing-section landing-process" id="how-it-works">
        <div class="landing-section-heading">
            <p class="eyebrow">A SIMPLE, CLEAR PROCESS</p>
            <h2>From request to release</h2>
            <p>Everything you need to know, with updates at every step.</p>
        </div>
        <div class="process-grid">
            <article class="process-card"><span class="process-number">01</span><span class="process-icon">↗</span><h3>Create your account</h3><p>Sign up with your name and email, then sign in securely to access your requester dashboard.</p></article>
            <article class="process-card"><span class="process-number">02</span><span class="process-icon">▤</span><h3>Send your request</h3><p>Choose a document, add your contact details and purpose, then submit it for office review.</p></article>
            <article class="process-card"><span class="process-number">03</span><span class="process-icon">◷</span><h3>Follow progress</h3><p>Check the progress bar and timeline. The office can share status updates and message you.</p></article>
            <article class="process-card"><span class="process-number">04</span><span class="process-icon">✓</span><h3>Get your document</h3><p>When the request is ready, follow the office’s instructions for the next steps or release.</p></article>
        </div>
    </section>

    <section class="landing-section landing-services" id="services">
        <div class="service-copy">
            <p class="eyebrow">ONE PLACE TO STAY INFORMED</p>
            <h2>Know what’s happening with your request.</h2>
            <p>DocuQuest keeps your submission, office updates, and messages together so you can return to your request whenever you need.</p>
            <a class="text-link" href="<?= dq_escape(site_url($user ? 'dashboard' : 'register')) ?>"><?= $user ? 'Continue to your dashboard' : 'Get started with DocuQuest' ?> <span aria-hidden="true">→</span></a>
        </div>
        <div class="service-list">
            <article><span>01</span><div><h3>Request history</h3><p>Find your submitted document requests and open each record.</p></div></article>
            <article><span>02</span><div><h3>Progress and timeline</h3><p>See the current stage and a record of updates from the office.</p></div></article>
            <article><span>03</span><div><h3>Messages and notifications</h3><p>Share a concern and receive updates connected to your request.</p></div></article>
        </div>
    </section>

    <section class="landing-about" id="about">
        <div><p class="eyebrow">ABOUT DOCUQUEST</p><h2>A clearer way to request public documents.</h2></div>
        <p>DocuQuest is an online request and tracking portal. The requester submits information for review; authorized office staff review requests, update progress, and communicate next steps. Please provide accurate contact details and only the information needed for your request.</p>
    </section>
    <section class="landing-bottom-cta">
        <div><p class="eyebrow">READY WHEN YOU ARE</p><h2>Start your request online.</h2><p>Create an account to submit a request and keep track of its progress.</p></div>
        <?php if ($user): ?>
            <a class="button primary landing-cta" href="<?= dq_escape(site_url('dashboard')) ?>">Go to my dashboard <span aria-hidden="true">→</span></a>
        <?php else: ?>
            <a class="button primary landing-cta" href="<?= dq_escape(site_url('register')) ?>">Create an account <span aria-hidden="true">→</span></a>
        <?php endif; ?>
    </section>
</main>
<footer class="landing-footer">
    <div class="landing-footer-main">
        <div class="landing-footer-brand"><a class="brand" href="<?= dq_escape(site_url()) ?>"><span class="brand-mark">DQ</span><span>DocuQuest<small>PUBLIC DOCUMENT SERVICES</small></span></a><p>A clearer way to request and track public documents online.</p></div>
        <div class="landing-footer-links"><strong>Explore</strong><a href="#how-it-works">How it works</a><a href="#services">Services</a><a href="#about">About DocuQuest</a></div>
        <div class="landing-footer-links"><strong>Your account</strong><a href="<?= dq_escape(site_url($user ? 'dashboard' : 'login')) ?>"><?= $user ? 'Requester dashboard' : 'Requester sign in' ?></a><a href="<?= dq_escape(site_url('admin/login')) ?>">Government staff sign in</a></div>
        <div class="landing-footer-links"><strong>Need help?</strong><a href="<?= dq_escape(site_url($user ? 'notifications' : 'register')) ?>"><?= $user ? 'View notifications' : 'Create an account to get started' ?></a><span>For request concerns, use Messages on the request detail page.</span></div>
    </div>
    <div class="landing-footer-bottom"><span>© <?= date('Y') ?> DocuQuest · Public document request portal</span><a href="#top" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">Back to top ↑</a></div>
</footer>
</body>
</html>
