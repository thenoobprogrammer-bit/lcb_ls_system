<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_auth([ACCOUNT_RENTAL]);

$packages = fetch_all('SELECT * FROM packages WHERE approval_status = "Approved" AND availability_status = "Available" ORDER BY created_at DESC');
$pageTitle = 'Available Packages';
$pageKey = 'rental-packages';
require BASE_PATH . '/partials/layout_top.php';
?>
<div class="row g-4">
    <?php foreach ($packages as $package): ?>
        <div class="col-lg-4">
            <div class="content-card h-100 bg-soft">
                <div class="section-title"><?= e($package['package_name']) ?></div>
                <div class="mt-2 text-muted"><?= e($package['description']) ?></div>
                <div class="mt-3"><strong>Included:</strong> <?= e($package['equipment_summary']) ?></div>
                <div class="mt-3 fs-4 fw-bold"><?= e(money((float) $package['price'])) ?></div>
                <a href="<?= e(app_url('rental/events.php')) ?>" class="btn btn-primary mt-3">Book This Package</a>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>

