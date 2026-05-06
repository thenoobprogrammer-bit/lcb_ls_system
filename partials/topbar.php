<?php
declare(strict_types=1);
$notificationCount = 0;
$notifications = [];
if ($user) {
    $row = fetch_one('SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0', 'i', [(int) $user['id']]);
    $notificationCount = (int) ($row['total'] ?? 0);
    $notifications = fetch_user_notifications((int) $user['id']);
}
?>
<div class="topbar">
    <div>
        <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
        <div class="text-muted small">LCB Lights and Sounds Panel.</div>
    </div>
    <div class="topbar-actions">
        <button type="button" class="notification-pill notification-button" data-bs-toggle="modal" data-bs-target="#notificationsModal">
            <i class="bi bi-bell"></i>
            <span><?= $notificationCount ?></span>
        </button>
        <a class="user-chip text-decoration-none" href="<?= e(app_url('profile.php')) ?>">
            <img src="<?= e(user_avatar($user['profile_image'] ?? null)) ?>" alt="Profile">
            <div>
                <div class="fw-semibold"><?= e($user['full_name'] ?? $user['username']) ?></div>
                <div class="small text-muted"><?= e($user['employee_role'] ?? role_label($user['account_type'])) ?></div>
            </div>
        </a>
    </div>
</div>

<div class="modal fade" id="notificationsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Notifications</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($notifications): ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-entry <?= (int) $notification['is_read'] === 0 ? 'notification-entry-unread' : '' ?>">
                            <div class="fw-semibold"><?= e($notification['title']) ?></div>
                            <div class="small text-muted mb-2"><?= e(format_display_datetime($notification['created_at'])) ?></div>
                            <div><?= e($notification['message']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-muted">No notifications yet for this account.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
