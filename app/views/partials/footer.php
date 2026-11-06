<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
</main>
<?php if (!empty($requesterMode)): ?>
        <footer class="requester-page-footer"><span>DocuQuest · Public Document Request Portal</span><a href="<?= dq_escape(site_url()) ?>">Public information</a></footer>
    </div>
</div>
<?php elseif (!empty($adminWorkspaceMode)): ?>
        <footer class="admin-page-footer"><span>DocuQuest · Government Office Workspace</span><a href="<?= dq_escape(site_url()) ?>">Public portal</a></footer>
    </div>
</div>
<?php else: ?>
<footer class="page-footer">DocuQuest · A clearer way to request public documents.</footer>
<?php endif; ?>
<script>
(() => {
    const badges = document.querySelectorAll('[data-notification-count]');
    const pollUrl = <?= json_encode(site_url(!empty($adminMode) ? 'admin/notifications/poll' : 'notifications/poll')) ?>;
    const refresh = async () => {
        try {
            const response = await fetch(pollUrl, {credentials: 'same-origin', headers: {'Accept': 'application/json'}});
            if (!response.ok) return;
            const data = await response.json();
            badges.forEach(badge => {
                badge.textContent = data.unread_count > 99 ? '99+' : String(data.unread_count);
            });
        } catch (error) {
            console.error('Could not refresh DocuQuest notifications.', error);
        }
    };
    refresh();
    window.setInterval(refresh, 6000);
})();
</script>
</body>
</html>
