<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_auth([ACCOUNT_ADMIN]);

$pageTitle = 'Reports';
$pageKey = 'admin-reports';

$inventorySummary = fetch_all('SELECT category, COUNT(*) AS total_items, SUM(quantity) AS total_quantity FROM inventory_items GROUP BY category ORDER BY category');
$paymentSummary = fetch_all("SELECT payment_status, COUNT(*) AS total_count, SUM(amount_paid) AS total_paid FROM payments GROUP BY payment_status");
$feedbackSummary = fetch_all('SELECT f.rating, COUNT(*) AS total FROM feedback f GROUP BY f.rating ORDER BY f.rating DESC');
$recentInvoices = fetch_all('SELECT id, invoice_number, payment_status, created_at FROM payments ORDER BY created_at DESC LIMIT 10');

require BASE_PATH . '/partials/layout_top.php';
?>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="content-card">
            <div class="section-title mb-3">Inventory Report</div>
            <table class="table align-middle">
                <thead><tr><th>Category</th><th>Total Items</th><th>Total Quantity</th></tr></thead>
                <tbody>
                <?php foreach ($inventorySummary as $row): ?>
                    <tr><td><?= e($row['category']) ?></td><td><?= e((string) $row['total_items']) ?></td><td><?= e((string) $row['total_quantity']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="content-card">
            <div class="section-title mb-3">Payment Report</div>
            <table class="table align-middle">
                <thead><tr><th>Status</th><th>Records</th><th>Total Paid</th></tr></thead>
                <tbody>
                <?php foreach ($paymentSummary as $row): ?>
                    <tr><td><?= e($row['payment_status']) ?></td><td><?= e((string) $row['total_count']) ?></td><td><?= e(money((float) $row['total_paid'])) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="content-card">
            <div class="section-title mb-3">Feedback Summary</div>
            <table class="table align-middle">
                <thead><tr><th>Rating</th><th>Total Responses</th></tr></thead>
                <tbody>
                <?php foreach ($feedbackSummary as $row): ?>
                    <tr><td><?= e((string) $row['rating']) ?> Star<?= (int) $row['rating'] > 1 ? 's' : '' ?></td><td><?= e((string) $row['total']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="content-card">
            <div class="section-title mb-3">Recent Invoices</div>
            <table class="table align-middle">
                <thead><tr><th>Invoice</th><th>Status</th><th>Date</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recentInvoices as $invoice): ?>
                    <tr>
                        <td><?= e($invoice['invoice_number']) ?></td>
                        <td><?= e($invoice['payment_status']) ?></td>
                        <td><?= e($invoice['created_at']) ?></td>
                        <td><a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('admin/invoice.php?id=' . (int) $invoice['id'])) ?>" target="_blank">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>
