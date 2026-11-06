<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$adminMode = true;
$activeAdminNav = 'archive';
include APP_DIR . 'views/partials/header.php';
$progressWidth = function ($status) use ($statuses) {
    return (int) ($statuses[$status]['progress'] ?? 0);
};
?>
<section class="page-heading">
    <div><p class="eyebrow">RECORDS</p><h1>Request archive</h1>
    <p class="muted">Search all document requests, including active, completed, and rejected records.</p></div>
</section>
<section class="panel requests-panel">
    <form method="get" action="<?= dq_escape(site_url('admin/archive')) ?>" class="archive-search">
        <label for="archive-search">Search requests</label>
        <div class="archive-search-controls">
            <input id="archive-search" type="search" name="q" maxlength="150" value="<?= dq_escape($search) ?>" placeholder="Request number, name, email, phone, address, document, or purpose">
            <button class="button primary" type="submit">Search</button>
            <?php if ($search !== ''): ?><a class="button subtle" href="<?= dq_escape(site_url('admin/archive')) ?>">Clear</a><?php endif; ?>
        </div>
    </form>
    <div class="panel-heading"><div><p class="eyebrow">SAVED RECORDS</p><h2><?= $search !== '' ? 'Search results' : 'All requests' ?></h2></div><span class="muted"><?= count($requests) ?> found</span></div>
    <?php if (!$requests): ?>
        <div class="empty-state"><span class="empty-icon">⌕</span><h3>No matching requests</h3><p>Try another request number or requester detail.</p></div>
    <?php else: ?>
    <div class="request-list">
        <?php foreach ($requests as $request): ?>
        <article class="request-card">
            <div class="request-topline"><span class="request-number"><?= dq_escape($request['request_number']) ?></span><span class="status-chip status-<?= dq_escape($request['status']) ?>"><?= dq_escape($statuses[$request['status']]['label'] ?? ucfirst($request['status'])) ?></span></div>
            <h3><?= dq_escape($request['document_type']) ?></h3>
            <p class="requester-line"><?= dq_escape($request['full_name']) ?> · <?= dq_escape($request['contact_email']) ?> · <?= dq_escape($request['phone']) ?></p>
            <p class="request-purpose"><?= dq_escape($request['address']) ?></p>
            <p class="request-purpose"><?= dq_escape($request['purpose']) ?></p>
            <?php if ($request['status'] === 'rejected'): ?>
                <div class="progress-track rejected-track"><span style="width:100%"></span></div><div class="progress-caption"><span>Request closed</span><span>Rejected</span></div>
            <?php else: ?>
                <div class="progress-track"><span style="width:<?= $progressWidth($request['status']) ?>%"></span></div><div class="progress-caption"><span>Progress</span><span><?= $progressWidth($request['status']) ?>%</span></div>
            <?php endif; ?>
            <div class="request-bottom"><span class="muted">Submitted <?= dq_escape(date('M j, Y', strtotime($request['created_at']))) ?></span><a class="text-link" href="<?= dq_escape(site_url('admin/requests/show')) ?>?id=<?= (int) $request['id'] ?>">Review request <span aria-hidden="true">→</span></a></div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php include APP_DIR . 'views/partials/footer.php'; ?>
