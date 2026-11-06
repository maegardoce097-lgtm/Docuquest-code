<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$title = 'Notifications · DocuQuest';
$adminMode = !empty($adminMode);
$activeRequesterNav = $adminMode ? '' : 'notifications';
$activeAdminNav = $adminMode ? 'notifications' : '';
include APP_DIR . 'views/partials/header.php';
?>
<section class="page-heading">
    <div><p class="eyebrow">UPDATES FOR YOU</p><h1>Notifications</h1><p class="muted">New requests, progress updates, and messages about your document requests.</p></div>
    <?php if ($unreadCount): ?><form method="post" action="<?= dq_escape(site_url($adminMode ? 'admin/notifications/read' : 'notifications/read')) ?>"><?php csrf_field(); ?><button class="button secondary" type="submit">Mark all as read</button></form><?php endif; ?>
</section>
<section class="panel notification-panel">
    <div class="panel-heading"><div><p class="eyebrow">ACTIVITY FEED</p><h2>Recent updates</h2></div><span class="muted"><span data-notification-count><?= (int) $unreadCount ?></span> unread</span></div>
    <div class="notification-list" id="notification-list">
        <?php if (!$notifications): ?><div class="empty-state"><span class="empty-icon">◷</span><h3>You're all caught up</h3><p>New updates will appear here and refresh automatically.</p></div><?php endif; ?>
        <?php foreach ($notifications as $notification): ?>
        <a class="notification-item <?= (int) $notification['is_read'] === 0 ? 'unread' : '' ?>" href="<?= dq_escape(site_url($adminMode ? 'admin/requests/show' : 'requests/show')) ?>?id=<?= (int) $notification['request_id'] ?>">
            <span class="notification-symbol"><?= (int) $notification['is_read'] === 0 ? '●' : '○' ?></span>
            <span class="notification-content"><strong><?= dq_escape($notification['title']) ?></strong><span><?= dq_escape($notification['body']) ?></span></span>
            <time><?= dq_escape(date('M j · g:i a', strtotime($notification['created_at']))) ?></time>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<script>
(() => {
    const list = document.getElementById('notification-list');
    const pollUrl = <?= json_encode(site_url($adminMode ? 'admin/notifications/poll' : 'notifications/poll')) ?>;
    const detailUrl = <?= json_encode(site_url($adminMode ? 'admin/requests/show' : 'requests/show')) ?>;
    const knownIds = new Set(<?= json_encode(array_map('intval', array_column($notifications, 'id'))) ?>);
    window.setInterval(async () => {
        try {
            const response = await fetch(pollUrl, {credentials: 'same-origin'});
            if (!response.ok) return;
            const data = await response.json();
            data.notifications.forEach(item => {
                if (knownIds.has(Number(item.id))) return;
                knownIds.add(Number(item.id));
                const link = document.createElement('a'); link.className = 'notification-item unread'; link.href = detailUrl + '?id=' + encodeURIComponent(item.request_id);
                const symbol = document.createElement('span'); symbol.className = 'notification-symbol'; symbol.textContent = '●';
                const content = document.createElement('span'); content.className = 'notification-content';
                const title = document.createElement('strong'); title.textContent = item.title;
                const body = document.createElement('span'); body.textContent = item.body;
                const time = document.createElement('time'); time.textContent = new Date(item.created_at.replace(' ', 'T')).toLocaleString();
                content.append(title, body); link.append(symbol, content, time);
                const empty = list.querySelector('.empty-state'); if (empty) empty.remove();
                list.prepend(link);
            });
        } catch (error) {
            console.error('Could not refresh DocuQuest notifications.', error);
        }
    }, 6000);
})();
</script>
<?php include APP_DIR . 'views/partials/footer.php'; ?>
