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
            $eventSummary = fetch_event_payment_summary($eventId);
            if (!$eventSummary) {
                throw new RuntimeException('Selected event was not found.');
            }

            $totalCost = (float) ($eventSummary['total_cost'] ?? 0);
            $downpayment = post_float('downpayment');
            $amountPaid = post_float('amount_paid');
            if ($amountPaid < $downpayment) {
                throw new RuntimeException('Amount paid cannot be less than the downpayment.');
            }
            $remainingBalance = payment_remaining_balance($totalCost, $downpayment, $amountPaid);
            $paymentStatus = payment_status_from_values($totalCost, $downpayment, $amountPaid);
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

$events = fetch_all('
    SELECT e.id, e.event_name, e.event_status, p.package_name, COALESCE(p.price, 0) AS package_price,
           COALESCE(e.is_overtime, 0) AS is_overtime, COALESCE(e.overtime_hours, 0) AS overtime_hours,
           COALESCE(e.overtime_fee_percentage, 0) AS overtime_fee_percentage, COALESCE(e.overtime_fee_amount, 0) AS overtime_fee_amount
    FROM events e
    LEFT JOIN packages p ON p.id = e.package_id
    ORDER BY e.event_date DESC
');
$payments = fetch_all('
    SELECT pay.*, e.event_name, p.package_name, COALESCE(p.price, 0) AS package_price,
           COALESCE(e.is_overtime, 0) AS is_overtime, COALESCE(e.overtime_hours, 0) AS overtime_hours,
           COALESCE(e.overtime_fee_percentage, 0) AS overtime_fee_percentage, COALESCE(e.overtime_fee_amount, 0) AS overtime_fee_amount
    FROM payments pay
    JOIN events e ON e.id = pay.event_id
    LEFT JOIN packages p ON p.id = e.package_id
    ORDER BY pay.created_at DESC
');
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
                    <td>
                        <?= e($payment['event_name']) ?>
                        <div class="small text-muted"><?= e($payment['package_name'] ?? '-') ?></div>
                        <?php if ((int) ($payment['is_overtime'] ?? 0) === 1): ?>
                            <div class="small text-muted">Overtime: <?= e((string) $payment['overtime_hours']) ?> hr(s) at <?= e((string) $payment['overtime_fee_percentage']) ?>%</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= e(money((float) $payment['total_cost'])) ?>
                        <div class="small text-muted">Base <?= e(money((float) ($payment['package_price'] ?? 0))) ?><?php if ((float) ($payment['overtime_fee_amount'] ?? 0) > 0): ?> + OT <?= e(money((float) $payment['overtime_fee_amount'])) ?><?php endif; ?></div>
                    </td>
                    <td>
                        <?= e(money((float) $payment['amount_paid'])) ?>
                        <div class="small text-muted">Downpayment <?= e(money((float) $payment['downpayment'])) ?> minimum paid amount</div>
                    </td>
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
            <div class="col-md-6"><label class="form-label">Event</label><select class="form-select payment-event-select" name="event_id"><?php foreach ($events as $event): $eventTotal = (float) $event['package_price'] + ((int) ($event['is_overtime'] ?? 0) === 1 ? (float) $event['overtime_fee_amount'] : 0); ?><option value="<?= (int) $event['id'] ?>" data-total-cost="<?= e(number_format($eventTotal, 2, '.', '')) ?>" data-package-price="<?= e(number_format((float) $event['package_price'], 2, '.', '')) ?>" data-overtime-fee="<?= e(number_format((float) $event['overtime_fee_amount'], 2, '.', '')) ?>"><?= e($event['event_name']) ?><?= $event['package_name'] ? ' - ' . e($event['package_name']) : '' ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Total Cost</label><input type="number" step="0.01" name="total_cost" class="form-control payment-total-cost" readonly></div>
            <div class="col-md-3"><label class="form-label">Downpayment</label><input type="number" min="0" step="0.01" name="downpayment" class="form-control payment-downpayment" value="0"></div>
            <div class="col-md-6"><label class="form-label">Amount Paid</label><input type="number" min="0" step="0.01" name="amount_paid" class="form-control payment-amount-paid" value="0"></div>
            <div class="col-12">
                <div class="small text-muted payment-cost-note"></div>
                <div class="small text-danger payment-validation-message d-none">Amount paid cannot be less than the downpayment.</div>
            </div>
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
            <div class="col-md-6"><label class="form-label">Event</label><select class="form-select payment-event-select" name="event_id"><?php foreach ($events as $event): $eventTotal = (float) $event['package_price'] + ((int) ($event['is_overtime'] ?? 0) === 1 ? (float) $event['overtime_fee_amount'] : 0); ?><option value="<?= (int) $event['id'] ?>" data-total-cost="<?= e(number_format($eventTotal, 2, '.', '')) ?>" data-package-price="<?= e(number_format((float) $event['package_price'], 2, '.', '')) ?>" data-overtime-fee="<?= e(number_format((float) $event['overtime_fee_amount'], 2, '.', '')) ?>" <?= (int) $event['id'] === (int) $payment['event_id'] ? 'selected' : '' ?>><?= e($event['event_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Total Cost</label><input type="number" step="0.01" name="total_cost" class="form-control payment-total-cost" value="<?= e($payment['total_cost']) ?>" readonly></div>
            <div class="col-md-3"><label class="form-label">Downpayment</label><input type="number" min="0" step="0.01" name="downpayment" class="form-control payment-downpayment" value="<?= e($payment['downpayment']) ?>"></div>
            <div class="col-md-6"><label class="form-label">Amount Paid</label><input type="number" min="0" step="0.01" name="amount_paid" class="form-control payment-amount-paid" value="<?= e($payment['amount_paid']) ?>"></div>
            <div class="col-12">
                <div class="small text-muted payment-cost-note"></div>
                <div class="small text-danger payment-validation-message d-none">Amount paid cannot be less than the downpayment.</div>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Update Payment</button></div>
    </form></div></div>
</div>
<?php endforeach; ?>
<script>
document.querySelectorAll('form').forEach((form) => {
    const eventSelect = form.querySelector('.payment-event-select');
    const totalCostInput = form.querySelector('.payment-total-cost');
    const costNote = form.querySelector('.payment-cost-note');
    const downpaymentInput = form.querySelector('.payment-downpayment');
    const amountPaidInput = form.querySelector('.payment-amount-paid');
    const validationMessage = form.querySelector('.payment-validation-message');
    if (!eventSelect || !totalCostInput || !costNote || !downpaymentInput || !amountPaidInput || !validationMessage) {
        return;
    }

    const syncPaymentEventCost = () => {
        const selectedOption = eventSelect.options[eventSelect.selectedIndex];
        const totalCost = selectedOption?.dataset.totalCost || '0.00';
        const packagePrice = selectedOption?.dataset.packagePrice || '0.00';
        const overtimeFee = selectedOption?.dataset.overtimeFee || '0.00';

        totalCostInput.value = totalCost;
        costNote.textContent = Number(overtimeFee) > 0
            ? `Base package price: PHP ${Number(packagePrice).toFixed(2)} | Overtime fee: PHP ${Number(overtimeFee).toFixed(2)}`
            : `Base package price: PHP ${Number(packagePrice).toFixed(2)} | No overtime fee`;
    };

    const validateAmountPaid = () => {
        const downpayment = Number(downpaymentInput.value || 0);
        const amountPaid = amountPaidInput.value === '' ? NaN : Number(amountPaidInput.value);
        const isInvalid = !Number.isNaN(amountPaid) && amountPaid < downpayment;

        amountPaidInput.min = '0';
        amountPaidInput.setCustomValidity(isInvalid ? 'Amount paid cannot be less than the downpayment.' : '');
        validationMessage.classList.toggle('d-none', !isInvalid);
    };

    eventSelect.addEventListener('change', syncPaymentEventCost);
    downpaymentInput.addEventListener('input', validateAmountPaid);
    amountPaidInput.addEventListener('input', validateAmountPaid);
    syncPaymentEventCost();
    validateAmountPaid();
});
</script>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>
