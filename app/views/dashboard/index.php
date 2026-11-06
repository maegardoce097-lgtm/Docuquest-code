<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$title = 'Dashboard · DocuQuest';
$adminMode = !empty($adminMode);
$activeRequesterNav = 'dashboard';
$activeAdminNav = $adminMode ? 'dashboard' : '';
include APP_DIR . 'views/partials/header.php';
$progressWidth = function ($status) use ($statuses) {
    return (int) ($statuses[$status]['progress'] ?? 0);
};
?>
<section class="page-heading">
    <div><p class="eyebrow"><?= $user['role'] === 'admin' ? 'OFFICE WORKSPACE' : 'YOUR DOCUMENTS' ?></p>
    <h1><?= $user['role'] === 'admin' ? 'Request dashboard' : 'Good day, ' . dq_escape($user['username']) ?></h1>
    <p class="muted"><?= $user['role'] === 'admin' ? 'Review requests, update progress, and respond to concerns.' : 'Submit an online request and follow every step until release.' ?></p></div>
    <span class="live-label"><i></i> Live updates</span>
</section>
<section class="stats-grid">
    <article class="stat-card"><span>Total requests</span><strong><?= (int) ($stats['total'] ?? 0) ?></strong><small>All time</small></article>
    <article class="stat-card"><span>In progress</span><strong><?= (int) ($stats['active'] ?? 0) ?></strong><small>Currently being handled</small></article>
    <article class="stat-card"><span>Completed</span><strong><?= (int) ($stats['completed'] ?? 0) ?></strong><small>Ready or released</small></article>
</section>
<div class="content-grid <?= $user['role'] === 'admin' ? 'single-column' : '' ?>">
    <?php if ($user['role'] === 'user'): ?>
    <section class="panel request-form-panel" id="request-form">
        <div class="panel-heading"><div><p class="eyebrow">START A REQUEST</p><h2>Request a document</h2></div><span class="panel-icon">+</span></div>
        <form method="post" action="<?= dq_escape(site_url('requests/create')) ?>" class="stack">
            <?php csrf_field(); ?>
            <label>Full name
                <input type="text" name="full_name" required minlength="2" maxlength="150" autocomplete="name" value="<?= dq_escape($requestValues['full_name'] ?? $user['username']) ?>">
            </label>
            <label>Email address
                <input type="email" value="<?= dq_escape($user['email']) ?>" readonly aria-describedby="request-email-hint">
                <span class="field-hint" id="request-email-hint">From your signed-in account.</span>
            </label>
            <label>Mobile number
                <span class="phone-input"><span aria-hidden="true">+63</span><input type="tel" name="phone" required inputmode="numeric" pattern="9[0-9]{9}" minlength="10" maxlength="10" autocomplete="tel-national" value="<?= dq_escape($requestValues['phone'] ?? '') ?>" placeholder="9XXXXXXXXX"></span>
                <span class="field-hint">Enter 10 digits after +63.</span>
            </label>
            <label>Current address
                <select name="address" required>
                    <option value="">Select a Naujan barangay</option>
                    <?php foreach ($barangays as $barangay): ?>
                    <option value="<?= dq_escape($barangay) ?>" <?= ($requestValues['address'] ?? '') === $barangay ? 'selected' : '' ?>><?= dq_escape($barangay) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Document type
                <select name="document_type" required>
                    <option value="">Select a document</option>
                    <?php foreach ($documentTypes as $documentType): ?>
                    <option value="<?= dq_escape($documentType) ?>" <?= ($requestValues['document_type'] ?? '') === $documentType ? 'selected' : '' ?>><?= dq_escape($documentType) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Purpose of request<textarea name="purpose" rows="4" minlength="10" maxlength="2000" required placeholder="Tell us what you need this document for."><?= dq_escape($requestValues['purpose'] ?? '') ?></textarea><span class="field-hint">10 to 2,000 characters. Please do not include sensitive information unless necessary.</span></label>
            <button class="button primary" type="submit">Submit request <span aria-hidden="true">→</span></button>
        </form>
    </section>
    <?php endif; ?>
    <section class="panel requests-panel" id="request-history">
        <div class="panel-heading"><div><p class="eyebrow"><?= $user['role'] === 'admin' ? 'INCOMING WORK' : 'REQUEST HISTORY' ?></p><h2><?= $user['role'] === 'admin' ? 'All requests' : 'Your requests' ?></h2></div><span class="muted"><?= count($requests) ?> total</span></div>
        <?php if (!$requests): ?>
            <div class="empty-state"><span class="empty-icon">▤</span><h3>No requests yet</h3><p>Submitted requests and their progress will appear here.</p></div>
        <?php else: ?>
        <div class="request-list">
            <?php foreach ($requests as $request): ?>
            <article class="request-card">
                <div class="request-topline"><span class="request-number"><?= dq_escape($request['request_number']) ?></span><span class="status-chip status-<?= dq_escape($request['status']) ?>"><?= dq_escape($statuses[$request['status']]['label']) ?></span></div>
                <h3><?= dq_escape($request['document_type']) ?></h3>
                <?php if ($user['role'] === 'admin'): ?><p class="requester-line">Requested by <?= dq_escape($request['full_name']) ?> · <?= dq_escape($request['contact_email']) ?></p><?php endif; ?>
                <p class="request-purpose"><?= dq_escape($request['purpose']) ?></p>
                <?php if ($request['status'] === 'rejected'): ?>
                    <div class="progress-track rejected-track"><span style="width:100%"></span></div><div class="progress-caption"><span>Request closed</span><span>Rejected</span></div>
                <?php else: ?>
                    <div class="progress-track"><span style="width:<?= $progressWidth($request['status']) ?>%"></span></div><div class="progress-caption"><span>Progress</span><span><?= $progressWidth($request['status']) ?>%</span></div>
                <?php endif; ?>
                <div class="request-bottom"><span class="muted">Submitted <?= dq_escape(date('M j, Y', strtotime($request['created_at']))) ?></span><a class="text-link" href="<?= dq_escape(site_url($adminMode ? 'admin/requests/show' : 'requests/show')) ?>?id=<?= (int) $request['id'] ?>">View request <span aria-hidden="true">→</span></a></div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</div>
<?php include APP_DIR . 'views/partials/footer.php'; ?>
