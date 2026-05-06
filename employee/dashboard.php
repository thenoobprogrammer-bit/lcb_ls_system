<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_auth([ACCOUNT_EMPLOYEE]);

$pageTitle = 'Employee Dashboard';
$pageKey = 'employee-dashboard';
$userId = (int) current_user()['id'];

$stats = [
    'Inventory Items' => count_table('inventory_items'),
    'Approved Packages' => count_table('packages', "approval_status = 'Approved'"),
    'My Payroll Records' => count_table('payrolls', 'employee_id = ' . $userId),
    'Assigned Events' => count_table('event_assignments', 'employee_id = ' . $userId),
];

$myPayroll = fetch_all('SELECT * FROM payrolls WHERE employee_id = ? ORDER BY payroll_period_end DESC LIMIT 5', 'i', [$userId]);
$assignedEvents = fetch_all('
    SELECT e.event_name, e.event_type, e.event_date, e.start_time, e.end_time, e.event_status, e.address,
           customer.full_name AS customer_name, p.package_name,
           GROUP_CONCAT(DISTINCT crew.full_name ORDER BY crew.full_name SEPARATOR ", ") AS assigned_employees
    FROM event_assignments ea
    JOIN events e ON e.id = ea.event_id
    LEFT JOIN packages p ON p.id = e.package_id
    JOIN users customer ON customer.id = e.rental_user_id
    LEFT JOIN event_assignments team ON team.event_id = e.id
    LEFT JOIN users crew ON crew.id = team.employee_id
    WHERE ea.employee_id = ? AND e.event_status = "Approved"
    GROUP BY e.id, customer.full_name, p.package_name
    ORDER BY e.event_date ASC, e.start_time ASC
', 'i', [$userId]);

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
        <div class="calendar-card" data-calendar data-events='<?= e(json_encode(array_map(static fn ($row): array => [
            'event_name' => $row['event_name'],
            'event_date' => $row['event_date'],
            'event_type' => $row['event_type'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'event_status' => $row['event_status'],
            'package_name' => $row['package_name'],
            'customer_name' => $row['customer_name'],
            'assigned_employees' => $row['assigned_employees'],
            'address' => $row['address'],
        ], $assignedEvents))) ?>'></div>
    </div>
    <div class="col-12">
        <div class="content-card">
            <div class="section-title mb-3">Assigned Events</div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Customer</th>
                            <th>Schedule</th>
                            <th>Package</th>
                            <th>Crew</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($assignedEvents as $event): ?>
                        <tr>
                            <td><?= e($event['event_name']) ?><div class="small text-muted"><?= e($event['event_type']) ?></div></td>
                            <td><?= e($event['customer_name']) ?></td>
                            <td><?= e(format_display_date($event['event_date'])) ?><div class="small text-muted"><?= e(format_display_time($event['start_time'])) ?> - <?= e(format_display_time($event['end_time'])) ?></div></td>
                            <td><?= e($event['package_name'] ?? 'Custom') ?></td>
                            <td><?= e($event['assigned_employees'] ?: 'Not set') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="content-card">
            <div class="section-title mb-3">Recent Payroll</div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Basic Salary</th>
                            <th>Allowance</th>
                            <th>Deductions</th>
                            <th>Net Pay</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($myPayroll as $row): ?>
                        <tr>
                            <td><?= e(format_display_date($row['payroll_period_start'])) ?> to <?= e(format_display_date($row['payroll_period_end'])) ?></td>
                            <td><?= e(money((float) $row['basic_salary'])) ?></td>
                            <td><?= e(money((float) $row['allowance'])) ?></td>
                            <td><?= e(money((float) $row['deductions'])) ?></td>
                            <td><?= e(money((float) $row['net_pay'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>
