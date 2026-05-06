<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_auth([ACCOUNT_EMPLOYEE]);

$userId = (int) current_user()['id'];
$inventory = fetch_all('SELECT id, item_name, category, wire_length_label, quantity, status FROM inventory_items ORDER BY item_name');
$inventoryById = [];
foreach ($inventory as $item) {
    $inventoryById[(int) $item['id']] = $item;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = post_string('action');
        if (in_array($action, ['add', 'edit'], true)) {
            $packageItems = package_items_from_post(post_array('equipment_quantities'));
            if (!$packageItems) {
                throw new RuntimeException('Select at least one equipment item and enter its package quantity.');
            }
            if (!package_items_available($packageItems)) {
                throw new RuntimeException('Selected equipment exceeds available stock or includes unavailable inventory.');
            }

            $equipmentIds = array_map(static fn (array $row): int => (int) $row['inventory_id'], $packageItems);
            $idsString = implode(',', $equipmentIds);
            $itemsJson = encode_package_items($packageItems);
            $summaryString = package_items_summary($packageItems, $inventoryById);
            $approvalStatus = 'Pending';

            if ($action === 'add') {
                $stmt = db()->prepare('INSERT INTO packages (package_name, description, equipment_ids, package_items_json, equipment_summary, price, availability_status, approval_status, submitted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $packageName = post_string('package_name');
                $description = post_string('description');
                $price = post_float('price');
                $availability = post_string('availability_status');
                $stmt->bind_param('sssssdssi', $packageName, $description, $idsString, $itemsJson, $summaryString, $price, $availability, $approvalStatus, $userId);
                $stmt->execute();
                log_activity($userId, 'Submit Package', 'Packages', "Submitted package {$packageName} for approval.");
                create_notification(1, 'New package approval', "Employee submitted package {$packageName}.");
                set_flash('success', 'Package submitted for admin approval.');
            }
            if ($action === 'edit') {
                $id = post_int('id');
                $stmt = db()->prepare('UPDATE packages SET package_name = ?, description = ?, equipment_ids = ?, package_items_json = ?, equipment_summary = ?, price = ?, availability_status = ?, approval_status = ? WHERE id = ? AND submitted_by = ?');
                $packageName = post_string('package_name');
                $description = post_string('description');
                $price = post_float('price');
                $availability = post_string('availability_status');
                $stmt->bind_param('sssssdssii', $packageName, $description, $idsString, $itemsJson, $summaryString, $price, $availability, $approvalStatus, $id, $userId);
                $stmt->execute();
                log_activity($userId, 'Update Package', 'Packages', "Updated submitted package {$packageName}.");
                create_notification(1, 'Package updated', "Employee updated package {$packageName} and it requires approval.");
                set_flash('success', 'Package updated and returned to pending approval.');
            }
        }
    } catch (Throwable $exception) {
        set_flash('danger', $exception->getMessage());
    }
    redirect('employee/packages.php');
}

$packages = fetch_all('SELECT * FROM packages WHERE submitted_by = ? OR approval_status = "Approved" ORDER BY created_at DESC', 'i', [$userId]);
$packageItemMap = [];
foreach ($packages as $package) {
    $packageItemMap[(int) $package['id']] = decode_package_items($package['package_items_json'] ?? null, $package['equipment_ids'] ?? null);
}

$pageTitle = 'Packages';
$pageKey = 'employee-packages';
require BASE_PATH . '/partials/layout_top.php';
?>
<div class="content-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="section-title">Package Builder</div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPackageModal">Create Package</button>
    </div>
    <div class="table-responsive">
        <table class="table datatable align-middle">
            <thead><tr><th>Package</th><th>Equipment</th><th>Price</th><th>Availability</th><th>Approval</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($packages as $package): ?>
                <tr>
                    <td><div class="fw-semibold"><?= e($package['package_name']) ?></div><div class="small text-muted"><?= e($package['description']) ?></div></td>
                    <td><?= e($package['equipment_summary']) ?></td>
                    <td><?= e(money((float) $package['price'])) ?></td>
                    <td><?= e($package['availability_status']) ?></td>
                    <td><span class="status-pill status-<?= strtolower($package['approval_status']) ?>"><?= e($package['approval_status']) ?></span></td>
                    <td><?php if ((int) $package['submitted_by'] === $userId): ?><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPackage<?= (int) $package['id'] ?>">Edit</button><?php else: ?><span class="small text-muted">Approved library</span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addPackageModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content"><form method="post">
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Create Package</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-md-6"><label class="form-label">Package Name</label><input class="form-control" name="package_name" required></div>
            <div class="col-md-3"><label class="form-label">Price</label><input type="number" step="0.01" class="form-control" name="price" required></div>
            <div class="col-md-3"><label class="form-label">Availability</label><select class="form-select" name="availability_status"><option>Available</option><option>Unavailable</option></select></div>
            <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"></textarea></div>
            <div class="col-12">
                <label class="form-label">Included Equipment</label>
                <div class="row g-2">
                    <?php foreach ($inventory as $item): $available = $item['status'] === 'Available' && (int) $item['quantity'] > 0; ?>
                        <div class="col-md-6">
                            <div class="package-equipment-card <?= $available ? '' : 'package-equipment-card-muted' ?>">
                                <div class="fw-semibold"><?= e(inventory_display_name($item)) ?></div>
                                <div class="small text-muted mb-2">Available stock: <?= e((string) $item['quantity']) ?> | Status: <?= e($item['status']) ?></div>
                                <input type="number" class="form-control" name="equipment_quantities[<?= (int) $item['id'] ?>]" min="0" max="<?= (int) $item['quantity'] ?>" value="0" <?= $available ? '' : 'disabled' ?>>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Submit Package</button></div>
    </form></div></div>
</div>
<?php foreach ($packages as $package): if ((int) $package['submitted_by'] !== $userId) continue; $selectedItems = $packageItemMap[(int) $package['id']] ?? []; $selectedQuantities = []; foreach ($selectedItems as $selectedItem) { $selectedQuantities[(int) $selectedItem['inventory_id']] = (int) $selectedItem['quantity']; } ?>
<div class="modal fade" id="editPackage<?= (int) $package['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content"><form method="post">
        <input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int) $package['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Edit Package</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-md-6"><label class="form-label">Package Name</label><input class="form-control" name="package_name" value="<?= e($package['package_name']) ?>" required></div>
            <div class="col-md-3"><label class="form-label">Price</label><input type="number" step="0.01" class="form-control" name="price" value="<?= e($package['price']) ?>" required></div>
            <div class="col-md-3"><label class="form-label">Availability</label><select class="form-select" name="availability_status"><?php foreach (['Available','Unavailable'] as $status): ?><option value="<?= e($status) ?>" <?= $package['availability_status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
            <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"><?= e($package['description']) ?></textarea></div>
            <div class="col-12">
                <label class="form-label">Included Equipment</label>
                <div class="row g-2">
                    <?php foreach ($inventory as $item): $selectedQuantity = $selectedQuantities[(int) $item['id']] ?? 0; $available = ($item['status'] === 'Available' && (int) $item['quantity'] > 0) || $selectedQuantity > 0; ?>
                        <div class="col-md-6">
                            <div class="package-equipment-card <?= $available ? '' : 'package-equipment-card-muted' ?>">
                                <div class="fw-semibold"><?= e(inventory_display_name($item)) ?></div>
                                <div class="small text-muted mb-2">Available stock: <?= e((string) $item['quantity']) ?> | Status: <?= e($item['status']) ?></div>
                                <input type="number" class="form-control" name="equipment_quantities[<?= (int) $item['id'] ?>]" min="0" max="<?= max((int) $item['quantity'], $selectedQuantity) ?>" value="<?= $selectedQuantity ?>" <?= $available ? '' : 'disabled' ?>>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Update Submission</button></div>
    </form></div></div>
</div>
<?php endforeach; ?>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>
