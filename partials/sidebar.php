<?php
declare(strict_types=1);

$accountType = $user['account_type'] ?? '';
$menus = [
    ACCOUNT_ADMIN => [
        ['Dashboard', 'admin/dashboard.php', 'bi-speedometer2'],
        ['Inventory', 'admin/inventory.php', 'bi-box-seam'],
        ['Employees', 'admin/employees.php', 'bi-people'],
        ['Payroll', 'admin/payroll.php', 'bi-cash-stack'],
        ['Packages', 'admin/packages.php', 'bi-collection'],
        ['Events', 'admin/events.php', 'bi-calendar-event'],
        ['Payments', 'admin/payments.php', 'bi-wallet2'],
        ['Messages', 'admin/messages.php', 'bi-chat-dots'],
        ['Reports', 'admin/reports.php', 'bi-graph-up-arrow'],
        ['Profile', 'profile.php', 'bi-person-circle'],
    ],
    ACCOUNT_EMPLOYEE => [
        ['Dashboard', 'employee/dashboard.php', 'bi-speedometer2'],
        ['Inventory', 'employee/inventory.php', 'bi-box-seam'],
        ['Packages', 'employee/packages.php', 'bi-collection'],
        ['Payroll', 'employee/payroll.php', 'bi-cash-stack'],
        ['Profile', 'profile.php', 'bi-person-circle'],
    ],
    ACCOUNT_RENTAL => [
        ['Dashboard', 'rental/dashboard.php', 'bi-house-door'],
        ['Packages', 'rental/packages.php', 'bi-stars'],
        ['Events', 'rental/events.php', 'bi-calendar-check'],
        ['Profile', 'profile.php', 'bi-person-circle'],
    ],
];
?>
<aside class="sidebar">
    <div class="sidebar-content">
        <div class="brand-card">
            <img src="<?= e(app_url('assets/img/logo-placeholder.svg')) ?>" alt="LCB logo" class="brand-logo">
            <div>
                <div class="brand-name">LCB LIGHTS AND SOUND</div>
                <div class="brand-role"><?= e(role_label($accountType)) ?></div>
            </div>
        </div>
        <nav class="nav flex-column mt-4 gap-2">
            <?php foreach ($menus[$accountType] ?? [] as [$label, $path, $icon]): ?>
                <a class="nav-link sidebar-link <?= str_contains($_SERVER['PHP_SELF'], basename($path)) ? 'active' : '' ?>" href="<?= e(app_url($path)) ?>">
                    <i class="bi <?= e($icon) ?>"></i>
                    <span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
    <a class="nav-link sidebar-link sidebar-logout" href="<?= e(app_url('auth/logout.php')) ?>">
        <i class="bi bi-box-arrow-right"></i>
        <span>Logout</span>
    </a>
</aside>
