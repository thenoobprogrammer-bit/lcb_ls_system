<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_auth([ACCOUNT_EMPLOYEE]);

$payrolls = fetch_all('SELECT * FROM payrolls WHERE employee_id = ? ORDER BY payroll_period_end DESC', 'i', [(int) current_user()['id']]);
$pageTitle = 'My Payroll';
$pageKey = 'employee-payroll';
require BASE_PATH . '/partials/layout_top.php';
?>
<div class="content-card">
    <div class="section-title mb-3">Payroll Records</div>
    <div class="table-responsive">
        <table class="table datatable align-middle">
            <thead><tr><th>Period</th><th>Basic</th><th>Allowance</th><th>Deductions</th><th>Net Pay</th><th>Notes</th></tr></thead>
            <tbody>
            <?php foreach ($payrolls as $row): ?>
                <tr>
                    <td><?= e($row['payroll_period_start']) ?> to <?= e($row['payroll_period_end']) ?></td>
                    <td><?= e(money((float) $row['basic_salary'])) ?></td>
                    <td><?= e(money((float) $row['allowance'])) ?></td>
                    <td><?= e(money((float) $row['deductions'])) ?></td>
                    <td><?= e(money((float) $row['net_pay'])) ?></td>
                    <td><?= e($row['notes'] ?: '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>
