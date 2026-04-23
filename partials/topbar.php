<?php
declare(strict_types=1);
$notificationCount = 0;
if ($user) {
    $row = fetch_one('SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0', 'i', [(int) $user['id']]);
    $notificationCount = (int) ($row['total'] ?? 0);
}
?>
<div class="topbar">
    <div>
        <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
        <div class="text-muted small">Lights, sound, rentals, scheduling, and payroll in one system.</div>
    </div>
    <div class="topbar-actions">
        <div class="notification-pill">
            <i class="bi bi-bell"></i>
            <span><?= $notificationCount ?></span>
        </div>
        <div class="user-chip">
            <img src="<?= e(user_avatar($user['profile_image'] ?? null)) ?>" alt="Profile">
            <div>
                <div class="fw-semibold"><?= e($user['full_name'] ?? $user['username']) ?></div>
                <div class="small text-muted"><?= e($user['employee_role'] ?? role_label($user['account_type'])) ?></div>
            </div>
        </div>
    </div>
</div>

