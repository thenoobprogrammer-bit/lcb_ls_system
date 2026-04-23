<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_auth([ACCOUNT_RENTAL]);

$pageTitle = 'Rental Dashboard';
$pageKey = 'rental-dashboard';
$userId = (int) current_user()['id'];

$stats = [
    'Available Packages' => count_table('packages', "approval_status = 'Approved' AND availability_status = 'Available'"),
    'My Events' => count_table('events', 'rental_user_id = ' . $userId),
];

$upcomingEvents = fetch_all('SELECT e.*, p.package_name FROM events e LEFT JOIN packages p ON p.id = e.package_id WHERE e.rental_user_id = ? ORDER BY e.event_date DESC', 'i', [$userId]);

require BASE_PATH . '/partials/layout_top.php';
?>
<div class="row g-4">
    <?php foreach ($stats as $label => $value): ?>
        <div class="col-md-6">
            <div class="stats-card">
                <div class="stats-value"><?= e((string) $value) ?></div>
                <div class="stats-label"><?= e($label) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
    <div class="col-12">
        <div class="content-card">
            <div class="section-title mb-3">My Event Requests</div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Package</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($upcomingEvents as $event): ?>
                        <tr>
                            <td><?= e($event['event_name']) ?></td>
                            <td><?= e($event['package_name'] ?? 'Custom Request') ?></td>
                            <td><?= e($event['event_date']) ?> <?= e(substr($event['start_time'], 0, 5)) ?>-<?= e(substr($event['end_time'], 0, 5)) ?></td>
                            <td><span class="status-pill status-<?= strtolower(str_replace(' ', '-', $event['event_status'])) ?>"><?= e($event['event_status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>
