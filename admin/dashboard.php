<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_auth([ACCOUNT_ADMIN]);

$pageTitle = 'Admin Dashboard';
$pageKey = 'admin-dashboard';

$stats = [
    'Total Inventory Items' => count_table('inventory_items'),
    'Active Events' => count_table('events', "event_status IN ('Pending', 'Approved')"),
    'Monthly Revenue' => money((float) (fetch_one("SELECT COALESCE(SUM(amount_paid),0) AS total FROM payments WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')")['total'] ?? 0)),
    'Most Used Equipment' => fetch_one('SELECT item_name FROM inventory_items ORDER BY quantity ASC LIMIT 1')['item_name'] ?? 'N/A',
];

$recentLogs = fetch_all('
    SELECT al.*, u.full_name
    FROM activity_logs al
    JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC
    LIMIT 8
');

$calendarEvents = fetch_all('SELECT event_name, event_date, event_type, start_time, end_time, event_status, full_name FROM events WHERE event_status IN ("Pending", "Approved")');

require BASE_PATH . '/partials/layout_top.php';
?>
<div class="row g-4">
    <?php foreach ($stats as $label => $value): ?>
        <div class="col-md-6 col-xl-3">
            <div class="stats-card">
                <div class="stats-value"><?= e((string) $value) ?></div>
                <div class="stats-label"><?= e($label) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
    <div class="col-12">
        <div class="calendar-card" data-calendar data-events='<?= e(json_encode($calendarEvents)) ?>'></div>
    </div>
    <div class="col-12">
        <div class="content-card">
            <div class="section-title mb-3">Recent Activity Logs</div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Details</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td><?= e($log['full_name']) ?></td>
                            <td><?= e($log['action_name']) ?></td>
                            <td><?= e($log['module_name']) ?></td>
                            <td><?= e($log['details']) ?></td>
                            <td><?= e(format_display_datetime($log['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>
