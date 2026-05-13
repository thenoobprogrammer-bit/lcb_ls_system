<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_auth([ACCOUNT_ADMIN]);

$id = (int) ($_GET['id'] ?? 0);
$payment = fetch_one('
    SELECT pay.*, e.event_name, e.event_type, e.event_date, e.address, e.full_name, e.contact_number,
           COALESCE(e.is_overtime, 0) AS is_overtime, COALESCE(e.overtime_hours, 0) AS overtime_hours,
           COALESCE(e.overtime_fee_percentage, 0) AS overtime_fee_percentage, COALESCE(e.overtime_fee_amount, 0) AS overtime_fee_amount,
           p.package_name, p.equipment_summary
    FROM payments pay
    JOIN events e ON e.id = pay.event_id
    LEFT JOIN packages p ON p.id = e.package_id
    WHERE pay.id = ?
', 'i', [$id]);

if (!$payment) {
    exit('Invoice not found.');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?= e($payment['invoice_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-5">
                <div class="d-flex justify-content-between mb-4">
                    <div>
                        <h2 class="mb-1">LCB LIGHTS AND SOUND</h2>
                        <div>Lights and Sounds Rental Invoice</div>
                    </div>
                    <div class="text-end">
                        <div><strong>Invoice:</strong> <?= e($payment['invoice_number']) ?></div>
                        <div><strong>Status:</strong> <?= e($payment['payment_status']) ?></div>
                    </div>
                </div>
                <hr>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Customer</h5>
                        <div><?= e($payment['full_name']) ?></div>
                        <div><?= e($payment['contact_number']) ?></div>
                        <div><?= e($payment['address']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <h5>Event Details</h5>
                        <div><?= e($payment['event_name']) ?> (<?= e($payment['event_type']) ?>)</div>
                        <div><?= e(format_display_date($payment['event_date'])) ?></div>
                        <div><?= e($payment['package_name'] ?? 'Custom Package') ?></div>
                    </div>
                </div>
                <table class="table">
                    <thead><tr><th>Description</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        <tr><td>Package / Event Cost</td><td class="text-end"><?= e(money((float) $payment['total_cost'])) ?></td></tr>
                        <?php if ((int) ($payment['is_overtime'] ?? 0) === 1): ?>
                            <tr><td>Overtime Fee (<?= e((string) $payment['overtime_hours']) ?> hr(s) at <?= e((string) $payment['overtime_fee_percentage']) ?>%)</td><td class="text-end"><?= e(money((float) $payment['overtime_fee_amount'])) ?></td></tr>
                        <?php endif; ?>
                        <tr><td>Downpayment</td><td class="text-end"><?= e(money((float) $payment['downpayment'])) ?></td></tr>
                        <tr><td>Amount Paid</td><td class="text-end"><?= e(money((float) $payment['amount_paid'])) ?></td></tr>
                        <tr><td>Remaining Balance</td><td class="text-end"><?= e(money((float) $payment['remaining_balance'])) ?></td></tr>
                    </tbody>
                </table>
                <div class="mt-4">
                    <h5>Equipment List</h5>
                    <div><?= e($payment['equipment_summary'] ?: 'Package items not listed.') ?></div>
                </div>
                <div class="mt-4 text-end">
                    <button class="btn btn-primary" onclick="window.print()">Print Invoice</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
