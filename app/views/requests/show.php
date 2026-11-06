<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$title = $request['request_number'] . ' · DocuQuest';
$adminMode = !empty($adminMode);
$activeRequesterNav = $adminMode ? '' : 'requests';
$activeAdminNav = $adminMode ? 'dashboard' : '';
include APP_DIR . 'views/partials/header.php';
$statusLabel = $statuses[$request['status']]['label'];
$messagesUrl = site_url($adminMode ? 'admin/requests/messages' : 'requests/messages') . '?id=' . (int) $request['id'];
$statusUrl = site_url($adminMode ? 'admin/requests/status-feed' : 'requests/status-feed') . '?id=' . (int) $request['id'];
?>
<a class="back-link" href="<?= dq_escape(site_url($adminMode ? 'admin' : 'dashboard')) ?>">← Back to dashboard</a>
<section class="detail-heading">
    <div><p class="eyebrow">DOCUMENT REQUEST · <?= dq_escape($request['request_number']) ?></p><h1><?= dq_escape($request['document_type']) ?></h1>
    <p class="muted"><?= $user['role'] === 'admin' ? 'Requested by ' . dq_escape($request['full_name']) . ' · ' . dq_escape($request['contact_email']) : 'Submitted ' . dq_escape(date('F j, Y', strtotime($request['created_at']))) ?></p></div>
    <span class="status-chip status-<?= dq_escape($request['status']) ?>" data-request-status><?= dq_escape($statusLabel) ?></span>
</section>
<div class="detail-grid">
    <section class="panel">
        <div class="panel-heading"><div><p class="eyebrow">REQUEST PROGRESS</p><h2>Track your request</h2></div><strong class="progress-value" data-progress-label><?= (int) $request['progress'] ?>%</strong></div>
        <div class="progress-track large <?= $request['status'] === 'rejected' ? 'rejected-track' : '' ?>"><span data-progress-bar style="width:<?= (int) $request['progress'] ?>%"></span></div>
        <div class="progress-caption"><span>Submitted</span><span>In review</span><span>Processing</span><span>Ready</span></div>
        <div class="detail-note"><span class="eyebrow">PURPOSE</span><p><?= nl2br(dq_escape($request['purpose'])) ?></p></div>
        <div class="detail-note"><span class="eyebrow">REQUESTER DETAILS</span>
            <p><strong><?= dq_escape($request['full_name']) ?></strong><br>
            <?= dq_escape($request['contact_email']) ?><br>
            <?= dq_escape($request['phone']) ?><br>
            <?= nl2br(dq_escape($request['address'])) ?></p>
        </div>
        <?php if (!empty($request['admin_note'])): ?><div class="office-note"><span class="eyebrow">OFFICE UPDATE</span><p data-admin-note><?= nl2br(dq_escape($request['admin_note'])) ?></p></div><?php endif; ?>
        <?php if ($user['role'] === 'admin' && !in_array($request['status'], ['completed', 'rejected'], true)): ?>
        <form method="post" action="<?= dq_escape(site_url($adminMode ? 'admin/requests/status' : 'requests/status')) ?>" class="status-update-form">
            <?php csrf_field(); ?><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
            <h3>Update request</h3>
            <label>New status<select name="status" required><?php foreach ($statuses as $key => $item): ?><option value="<?= dq_escape($key) ?>" <?= $request['status'] === $key ? 'selected' : '' ?>><?= dq_escape($item['label']) ?></option><?php endforeach; ?></select></label>
            <label>Note for requester <textarea name="note" maxlength="1000" rows="3" placeholder="Optional progress note or instructions"></textarea></label>
            <button class="button primary" type="submit">Save progress update</button>
        </form>
        <?php endif; ?>
    </section>
    <aside class="panel timeline-panel">
        <div class="panel-heading"><div><p class="eyebrow">ACTIVITY</p><h2>Request timeline</h2></div></div>
        <?php if (!$updates): ?><p class="muted">No activity recorded yet.</p><?php else: ?>
        <ol class="timeline">
            <?php foreach ($updates as $update): ?>
            <li><span class="timeline-dot"></span><div><strong><?= dq_escape($update['actor_name']) ?><?= $update['status'] ? ' · ' . dq_escape($statuses[$update['status']]['label'] ?? ucfirst($update['status'])) : '' ?></strong><p><?= dq_escape($update['note']) ?></p><time><?= dq_escape(date('M j, Y · g:i a', strtotime($update['created_at']))) ?></time></div></li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>
    </aside>
</div>
<section class="panel conversation-panel">
    <div class="panel-heading"><div><p class="eyebrow">NEED HELP?</p><h2>Messages & concerns</h2><p class="muted">Talk directly with the <?= $user['role'] === 'admin' ? 'requester' : 'government office' ?> about this request.</p></div><span class="live-label"><i></i> Live</span></div>
    <div class="message-list" id="message-list" aria-live="polite">
        <?php foreach ($messages as $message): ?>
        <article class="message <?= (int) $message['sender_id'] === (int) $user['id'] ? 'mine' : '' ?>">
            <div class="message-meta"><strong><?= dq_escape($message['sender_name']) ?></strong><span><?= dq_escape(date('M j · g:i a', strtotime($message['created_at']))) ?></span></div>
            <p><?= nl2br(dq_escape($message['body'])) ?></p>
        </article>
        <?php endforeach; ?>
    </div>
    <form method="post" action="<?= dq_escape(site_url($adminMode ? 'admin/requests/message' : 'requests/message')) ?>" class="message-form">
        <?php csrf_field(); ?><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
        <label class="sr-only" for="message-body">Write a message</label>
        <textarea id="message-body" name="body" rows="2" maxlength="2000" required placeholder="Write a message or share a concern…"></textarea>
        <button class="button primary" type="submit">Send message</button>
    </form>
</section>
<script>
(() => {
    const list = document.getElementById('message-list');
    const messagesUrl = <?= json_encode($messagesUrl) ?>;
    const statusUrl = <?= json_encode($statusUrl) ?>;
    let latestMessageId = <?= (int) (end($messages)['id'] ?? 0) ?>;
    const addMessage = message => {
        const article = document.createElement('article');
        article.className = 'message' + (Number(message.sender_id) === <?= (int) $user['id'] ?> ? ' mine' : '');
        const meta = document.createElement('div'); meta.className = 'message-meta';
        const sender = document.createElement('strong'); sender.textContent = message.sender_name;
        const time = document.createElement('span'); time.textContent = new Date(message.created_at.replace(' ', 'T')).toLocaleString();
        const body = document.createElement('p'); body.textContent = message.body;
        meta.append(sender, time); article.append(meta, body); list.append(article);
    };
    const refreshMessages = async () => {
        const response = await fetch(messagesUrl, {credentials: 'same-origin'});
        if (!response.ok) return;
        const data = await response.json();
        data.messages.forEach(message => {
            if (Number(message.id) > latestMessageId) { addMessage(message); latestMessageId = Number(message.id); }
        });
        if (data.messages.length && list.lastElementChild) list.lastElementChild.scrollIntoView({block: 'nearest'});
    };
    const refreshStatus = async () => {
        const response = await fetch(statusUrl, {credentials: 'same-origin'});
        if (!response.ok) return;
        const data = await response.json();
        document.querySelector('[data-request-status]').textContent = data.label;
        document.querySelector('[data-progress-label]').textContent = data.progress + '%';
        const progressTrack = document.querySelector('[data-progress-bar]');
        progressTrack.style.width = data.progress + '%';
        progressTrack.parentElement.classList.toggle('rejected-track', data.status === 'rejected');
        const note = document.querySelector('[data-admin-note]');
        if (data.admin_note && note) note.textContent = data.admin_note;
    };
    window.setInterval(() => {
        refreshMessages().catch(error => console.error('Could not refresh request messages.', error));
        refreshStatus().catch(error => console.error('Could not refresh request status.', error));
    }, 5000);
})();
</script>
<?php include APP_DIR . 'views/partials/footer.php'; ?>
