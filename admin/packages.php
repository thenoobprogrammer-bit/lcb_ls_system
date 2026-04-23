<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_auth([ACCOUNT_ADMIN]);

$userId = (int) current_user()['id'];
$inventory = fetch_all('SELECT id, item_name, quantity, status FROM inventory_items ORDER BY item_name');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = post_string('action');
        if (in_array($action, ['add', 'edit'], true)) {
            $equipmentIds = $_POST['equipment_ids'] ?? [];
            $equipmentIds = array_map('intval', (array) $equipmentIds);
            $equipmentSummary = [];
            foreach ($inventory as $item) {
                if (in_array((int) $item['id'], $equipmentIds, true)) {
                    $equipmentSummary[] = $item['item_name'] . ' x' . $item['quantity'];
                }
            }
            $idsString = implode(',', $equipmentIds);
            $summaryString = implode(', ', $equipmentSummary);
            if ($action === 'add') {
                $stmt = db()->prepare('INSERT INTO packages (package_name, description, equipment_ids, equipment_summary, price, availability_status, approval_status, submitted_by, approved_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $packageName = post_string('package_name');
                $description = post_string('description');
                $price = post_float('price');
                $availabilityStatus = post_string('availability_status');
                $approvalStatus = post_string('approval_status');
                $stmt->bind_param('ssssdssii', $packageName, $description, $idsString, $summaryString, $price, $availabilityStatus, $approvalStatus, $userId, $userId);
                $stmt->execute();
                log_activity($userId, 'Create Package', 'Packages', "Created package {$packageName}.");
                set_flash('success', 'Package created.');
            }
            if ($action === 'edit') {
                $id = post_int('id');
                $packageName = post_string('package_name');
                $description = post_string('description');
                $price = post_float('price');
                $availabilityStatus = post_string('availability_status');
                $approvalStatus = post_string('approval_status');
                $stmt = db()->prepare('UPDATE packages SET package_name = ?, description = ?, equipment_ids = ?, equipment_summary = ?, price = ?, availability_status = ?, approval_status = ?, approved_by = ? WHERE id = ?');
                $stmt->bind_param('ssssdssii', $packageName, $description, $idsString, $summaryString, $price, $availabilityStatus, $approvalStatus, $userId, $id);
                $stmt->execute();
                log_activity($userId, 'Update Package', 'Packages', "Updated package {$packageName}.");
                set_flash('success', 'Package updated.');
            }
        }
        if (in_array($action, ['approve', 'deny'], true)) {
            $id = post_int('id');
            $status = $action === 'approve' ? 'Approved' : 'Denied';
            $stmt = db()->prepare('UPDATE packages SET approval_status = ?, approved_by = ? WHERE id = ?');
            $stmt->bind_param('sii', $status, $userId, $id);
            $stmt->execute();
            log_activity($userId, ucfirst($action) . ' Package', 'Packages', "Changed package ID {$id} to {$status}.");
            set_flash('success', "Package {$status}.");
        }
    } catch (Throwable $exception) {
        set_flash('danger', $exception->getMessage());
    }
    redirect('admin/packages.php');
}

$packages = fetch_all('SELECT p.*, u.full_name AS submitter_name FROM packages p LEFT JOIN users u ON u.id = p.submitted_by ORDER BY p.created_at DESC');
$pageTitle = 'Packages';
$pageKey = 'admin-packages';
require BASE_PATH . '/partials/layout_top.php';
?>
<div class="content-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="section-title">Package Management</div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPackageModal">Add Package</button>
    </div>
    <div class="table-responsive">
        <table class="table datatable align-middle">
            <thead><tr><th>Package</th><th>Equipment</th><th>Price</th><th>Availability</th><th>Approval</th><th>Submitted By</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($packages as $package): ?>
                <tr>
                    <td><div class="fw-semibold"><?= e($package['package_name']) ?></div><div class="small text-muted"><?= e($package['description']) ?></div></td>
                    <td><?= e($package['equipment_summary'] ?: 'No linked equipment') ?></td>
                    <td><?= e(money((float) $package['price'])) ?></td>
                    <td><span class="status-pill status-<?= strtolower($package['availability_status']) ?>"><?= e($package['availability_status']) ?></span></td>
                    <td><span class="status-pill status-<?= strtolower($package['approval_status']) ?>"><?= e($package['approval_status']) ?></span></td>
                    <td><?= e($package['submitter_name'] ?? 'System') ?></td>
                    <td class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPackage<?= (int) $package['id'] ?>">Edit</button>
                        <?php if ($package['approval_status'] !== 'Approved'): ?><form method="post"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int) $package['id'] ?>"><button class="btn btn-sm btn-outline-success">Approve</button></form><?php endif; ?>
                        <?php if ($package['approval_status'] !== 'Denied'): ?><form method="post"><input type="hidden" name="action" value="deny"><input type="hidden" name="id" value="<?= (int) $package['id'] ?>"><button class="btn btn-sm btn-outline-danger">Deny</button></form><?php endif; ?>
                    </td>
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
            <div class="col-md-6"><label class="form-label">Approval</label><select class="form-select" name="approval_status"><option>Approved</option><option>Pending</option><option>Denied</option></select></div>
            <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"></textarea></div>
            <div class="col-12"><label class="form-label">Included Equipment</label><div class="row g-2"><?php foreach ($inventory as $item): ?><div class="col-md-6"><div class="form-check border rounded-3 p-2"><input class="form-check-input" type="checkbox" name="equipment_ids[]" value="<?= (int) $item['id'] ?>" id="eqAdd<?= (int) $item['id'] ?>"><label class="form-check-label" for="eqAdd<?= (int) $item['id'] ?>"><?= e($item['item_name']) ?> (<?= e($item['status']) ?>)</label></div></div><?php endforeach; ?></div></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Save Package</button></div>
    </form></div></div>
</div>
<?php foreach ($packages as $package): $selectedIds = array_map('intval', array_filter(explode(',', (string) $package['equipment_ids']))); ?>
<div class="modal fade" id="editPackage<?= (int) $package['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content"><form method="post">
        <input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int) $package['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Edit Package</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-md-6"><label class="form-label">Package Name</label><input class="form-control" name="package_name" value="<?= e($package['package_name']) ?>" required></div>
            <div class="col-md-3"><label class="form-label">Price</label><input type="number" step="0.01" class="form-control" name="price" value="<?= e($package['price']) ?>" required></div>
            <div class="col-md-3"><label class="form-label">Availability</label><select class="form-select" name="availability_status"><?php foreach (['Available','Unavailable'] as $status): ?><option value="<?= e($status) ?>" <?= $package['availability_status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Approval</label><select class="form-select" name="approval_status"><?php foreach (['Approved','Pending','Denied'] as $status): ?><option value="<?= e($status) ?>" <?= $package['approval_status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
            <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"><?= e($package['description']) ?></textarea></div>
            <div class="col-12"><label class="form-label">Included Equipment</label><div class="row g-2"><?php foreach ($inventory as $item): ?><div class="col-md-6"><div class="form-check border rounded-3 p-2"><input class="form-check-input" type="checkbox" name="equipment_ids[]" value="<?= (int) $item['id'] ?>" id="eq<?= (int) $package['id'] ?>_<?= (int) $item['id'] ?>" <?= in_array((int) $item['id'], $selectedIds, true) ? 'checked' : '' ?>><label class="form-check-label" for="eq<?= (int) $package['id'] ?>_<?= (int) $item['id'] ?>"><?= e($item['item_name']) ?></label></div></div><?php endforeach; ?></div></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Update Package</button></div>
    </form></div></div>
</div>
<?php endforeach; ?>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>
