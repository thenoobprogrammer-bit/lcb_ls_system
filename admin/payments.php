<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_auth([ACCOUNT_ADMIN]);

$userId = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = post_string('action');
        if (in_array($action, ['add', 'edit'], true)) {
            $eventId = post_int('event_id');
            $totalCost = post_float('total_cost');
            $downpayment = post_float('downpayment');
            $amountPaid = post_float('amount_paid');
            $remainingBalance = max($totalCost - $amountPaid, 0);
            $paymentStatus = $remainingBalance <= 0 ? 'Paid' : ($amountPaid > 0 ? 'Partial' : 'Pending');
            if ($action === 'add') {
                $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                $stmt = db()->prepare('INSERT INTO payments (event_id, total_cost, downpayment, amount_paid, remaining_balance, payment_status, invoice_number) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('iddddss', $eventId, $totalCost, $downpayment, $amountPaid, $remainingBalance, $paymentStatus, $invoiceNumber);
                $stmt->execute();
                log_activity($userId, 'Create Payment', 'Payments', "Created payment for event ID {$eventId}.");
                set_flash('success', 'Payment record created.');
            }
            if ($action === 'edit') {
                $id = post_int('id');
                $stmt = db()->prepare('UPDATE payments SET event_id = ?, total_cost = ?, downpayment = ?, amount_paid = ?, remaining_balance = ?, payment_status = ? WHERE id = ?');
                $stmt->bind_param('iddddsi', $eventId, $totalCost, $downpayment, $amountPaid, $remainingBalance, $paymentStatus, $id);
                $stmt->execute();
                log_activity($userId, 'Update Payment', 'Payments', "Updated payment ID {$id}.");
                set_flash('success', 'Payment record updated.');
            }
        }
    } catch (Throwable $exception) {
        set_flash('danger', $exception->getMessage());
    }
    redirect('admin/payments.php');
}

$events = fetch_all('SELECT e.id, e.event_name, p.package_name FROM events e LEFT JOIN packages p ON p.id = e.package_id ORDER BY e.event_date DESC');
$payments = fetch_all('SELECT pay.*, e.event_name, p.package_name FROM payments pay JOIN events e ON e.id = pay.event_id LEFT JOIN packages p ON p.id = e.package_id ORDER BY pay.created_at DESC');
$pageTitle = 'Payments';
$pageKey = 'admin-payments';
require BASE_PATH . '/partials/layout_top.php';
?>
<div class="content-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="section-title">Payment and Invoice Management</div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">Add Payment</button>
    </div>
    <div class="table-responsive">
        <table class="table datatable align-middle">
            <thead><tr><th>Invoice</th><th>Event</th><th>Total Cost</th><th>Paid</th><th>Balance</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?= e($payment['invoice_number']) ?></td>
                    <td><?= e($payment['event_name']) ?><div class="small text-muted"><?= e($payment['package_name'] ?? '-') ?></div></td>
                    <td><?= e(money((float) $payment['total_cost'])) ?></td>
                    <td><?= e(money((float) $payment['amount_paid'])) ?></td>
                    <td><?= e(money((float) $payment['remaining_balance'])) ?></td>
                    <td><span class="status-pill status-<?= strtolower($payment['payment_status']) ?>"><?= e($payment['payment_status']) ?></span></td>
                    <td class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPayment<?= (int) $payment['id'] ?>">Edit</button>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('admin/invoice.php?id=' . (int) $payment['id'])) ?>" target="_blank">Invoice</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content"><form method="post">
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Add Payment Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-md-6"><label class="form-label">Event</label><select class="form-select" name="event_id"><?php foreach ($events as $event): ?><option value="<?= (int) $event['id'] ?>"><?= e($event['event_name']) ?><?= $event['package_name'] ? ' - ' . e($event['package_name']) : '' ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Total Cost</label><input type="number" step="0.01" name="total_cost" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Downpayment</label><input type="number" step="0.01" name="downpayment" class="form-control" value="0"></div>
            <div class="col-md-6"><label class="form-label">Amount Paid</label><input type="number" step="0.01" name="amount_paid" class="form-control" value="0"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Save Payment</button></div>
    </form></div></div>
</div>

<?php foreach ($payments as $payment): ?>
<div class="modal fade" id="editPayment<?= (int) $payment['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content"><form method="post">
        <input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int) $payment['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Edit Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-md-6"><label class="form-label">Event</label><select class="form-select" name="event_id"><?php foreach ($events as $event): ?><option value="<?= (int) $event['id'] ?>" <?= (int) $event['id'] === (int) $payment['event_id'] ? 'selected' : '' ?>><?= e($event['event_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Total Cost</label><input type="number" step="0.01" name="total_cost" class="form-control" value="<?= e($payment['total_cost']) ?>" required></div>
            <div class="col-md-3"><label class="form-label">Downpayment</label><input type="number" step="0.01" name="downpayment" class="form-control" value="<?= e($payment['downpayment']) ?>"></div>
            <div class="col-md-6"><label class="form-label">Amount Paid</label><input type="number" step="0.01" name="amount_paid" class="form-control" value="<?= e($payment['amount_paid']) ?>"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Update Payment</button></div>
    </form></div></div>
</div>
<?php endforeach; ?>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>

