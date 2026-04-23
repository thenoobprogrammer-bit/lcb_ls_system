<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_auth([ACCOUNT_EMPLOYEE]);

$userId = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = post_string('action');
        if ($action === 'add') {
            $imageFilename = upload_image($_FILES['image'] ?? [], 'inventory');
            $stmt = db()->prepare('INSERT INTO inventory_items (item_name, category, description, quantity, unit_price, status, image_filename, last_maintenance_date, maintenance_notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $itemName = post_string('item_name');
            $category = post_string('category');
            $description = post_string('description');
            $quantity = post_int('quantity');
            $unitPrice = post_float('unit_price');
            $status = post_string('status');
            $maintenanceDate = post_string('last_maintenance_date') ?: null;
            $maintenanceNotes = post_string('maintenance_notes');
            $stmt->bind_param('sssidssssi', $itemName, $category, $description, $quantity, $unitPrice, $status, $imageFilename, $maintenanceDate, $maintenanceNotes, $userId);
            $stmt->execute();
            log_activity($userId, 'Add Item', 'Inventory', "Employee added inventory item {$itemName}.");
            set_flash('success', 'Inventory item added.');
        }
        if ($action === 'edit') {
            $id = post_int('id');
            $current = fetch_one('SELECT * FROM inventory_items WHERE id = ?', 'i', [$id]);
            $imageFilename = $current['image_filename'];
            if (!empty($_FILES['image']['name'])) {
                $imageFilename = upload_image($_FILES['image'], 'inventory');
            }
            $stmt = db()->prepare('UPDATE inventory_items SET item_name = ?, category = ?, description = ?, quantity = ?, unit_price = ?, status = ?, image_filename = ?, last_maintenance_date = ?, maintenance_notes = ? WHERE id = ?');
            $itemName = post_string('item_name');
            $category = post_string('category');
            $description = post_string('description');
            $quantity = post_int('quantity');
            $unitPrice = post_float('unit_price');
            $status = post_string('status');
            $maintenanceDate = post_string('last_maintenance_date') ?: null;
            $maintenanceNotes = post_string('maintenance_notes');
            $stmt->bind_param('sssidssssi', $itemName, $category, $description, $quantity, $unitPrice, $status, $imageFilename, $maintenanceDate, $maintenanceNotes, $id);
            $stmt->execute();
            log_activity($userId, 'Update Item', 'Inventory', "Employee updated inventory item {$itemName}.");
            set_flash('success', 'Inventory item updated.');
        }
    } catch (Throwable $exception) {
        set_flash('danger', $exception->getMessage());
    }
    redirect('employee/inventory.php');
}

$items = fetch_all('SELECT * FROM inventory_items ORDER BY id DESC');
$pageTitle = 'Inventory';
$pageKey = 'employee-inventory';
require BASE_PATH . '/partials/layout_top.php';
?>
<div class="content-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="section-title">Inventory Access</div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal"><i class="bi bi-plus-circle me-1"></i>Add Item</button>
    </div>
    <div class="table-responsive">
        <table class="table datatable align-middle">
            <thead><tr><th>Image</th><th>Item</th><th>Category</th><th>Quantity</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><img class="table-thumb" src="<?= e(inventory_image($item['image_filename'])) ?>" alt=""></td>
                    <td><?= e($item['item_name']) ?></td>
                    <td><?= e($item['category']) ?></td>
                    <td><?= e((string) $item['quantity']) ?></td>
                    <td><span class="status-pill status-<?= strtolower(str_replace(' ', '-', $item['status'])) ?>"><?= e($item['status']) ?></span></td>
                    <td class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#viewItem<?= (int) $item['id'] ?>">View</button>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editItem<?= (int) $item['id'] ?>">Edit</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content"><form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Add Inventory Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-md-6"><label class="form-label">Item Name</label><input class="form-control" name="item_name" required></div>
            <div class="col-md-6"><label class="form-label">Category</label><input class="form-control" name="category" required></div>
            <div class="col-md-4"><label class="form-label">Quantity</label><input type="number" class="form-control" name="quantity" min="0" required></div>
            <div class="col-md-4"><label class="form-label">Unit Price</label><input type="number" step="0.01" class="form-control" name="unit_price" min="0" required></div>
            <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status"><option>Available</option><option>In Use</option><option>Maintenance</option><option>Damaged</option></select></div>
            <div class="col-md-6"><label class="form-label">Last Maintenance Date</label><input type="date" class="form-control" name="last_maintenance_date"></div>
            <div class="col-md-6"><label class="form-label">Image</label><input type="file" class="form-control" name="image" accept="image/*"></div>
            <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"></textarea></div>
            <div class="col-12"><label class="form-label">Maintenance Notes</label><textarea class="form-control" name="maintenance_notes" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button><button class="btn btn-primary" type="submit">Save Item</button></div>
    </form></div></div>
</div>

<?php foreach ($items as $item): ?>
<div class="modal fade" id="viewItem<?= (int) $item['id'] ?>" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">View Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><img class="img-fluid rounded-4 mb-3" src="<?= e(inventory_image($item['image_filename'])) ?>" alt=""><div><strong><?= e($item['item_name']) ?></strong></div><div><?= e($item['description']) ?></div></div></div></div>
</div>
<div class="modal fade" id="editItem<?= (int) $item['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content"><form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Edit Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-md-6"><label class="form-label">Item Name</label><input class="form-control" name="item_name" value="<?= e($item['item_name']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Category</label><input class="form-control" name="category" value="<?= e($item['category']) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Quantity</label><input type="number" class="form-control" name="quantity" value="<?= (int) $item['quantity'] ?>" min="0" required></div>
            <div class="col-md-4"><label class="form-label">Unit Price</label><input type="number" step="0.01" class="form-control" name="unit_price" value="<?= e($item['unit_price']) ?>" min="0" required></div>
            <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['Available','In Use','Maintenance','Damaged'] as $status): ?><option value="<?= e($status) ?>" <?= $item['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Last Maintenance Date</label><input type="date" class="form-control" name="last_maintenance_date" value="<?= e((string) $item['last_maintenance_date']) ?>"></div>
            <div class="col-md-6"><label class="form-label">Replace Image</label><input type="file" class="form-control" name="image" accept="image/*"></div>
            <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"><?= e($item['description']) ?></textarea></div>
            <div class="col-12"><label class="form-label">Maintenance Notes</label><textarea class="form-control" name="maintenance_notes" rows="2"><?= e($item['maintenance_notes']) ?></textarea></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button><button class="btn btn-primary" type="submit">Update Item</button></div>
    </form></div></div>
</div>
<?php endforeach; ?>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>

