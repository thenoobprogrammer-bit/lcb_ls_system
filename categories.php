<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_auth([ACCOUNT_ADMIN, ACCOUNT_EMPLOYEE]);

$user = current_user();
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = post_string('action');

        if ($action === 'add') {
            $categoryName = trim(post_string('category_name'));
            if ($categoryName === '') {
                throw new RuntimeException('Category name is required.');
            }
            if (fetch_one('SELECT id FROM inventory_categories WHERE LOWER(category_name) = LOWER(?) LIMIT 1', 's', [$categoryName])) {
                throw new RuntimeException('Category name already exists.');
            }

            $allowsWireLength = isset($_POST['allows_wire_length']) ? 1 : 0;
            $stmt = db()->prepare('INSERT INTO inventory_categories (category_name, allows_wire_length) VALUES (?, ?)');
            $stmt->bind_param('si', $categoryName, $allowsWireLength);
            $stmt->execute();

            log_activity($userId, 'Create Category', 'Categories', "Created category {$categoryName}.");
            set_flash('success', 'Category created.');
        }

        if ($action === 'edit') {
            $id = post_int('id');
            $category = find_inventory_category($id);
            if (!$category) {
                throw new RuntimeException('Category not found.');
            }

            $categoryName = trim(post_string('category_name'));
            if ($categoryName === '') {
                throw new RuntimeException('Category name is required.');
            }
            if (fetch_one('SELECT id FROM inventory_categories WHERE LOWER(category_name) = LOWER(?) AND id != ? LIMIT 1', 'si', [$categoryName, $id])) {
                throw new RuntimeException('Category name already exists.');
            }

            $allowsWireLength = isset($_POST['allows_wire_length']) ? 1 : 0;
            $stmt = db()->prepare('UPDATE inventory_categories SET category_name = ?, allows_wire_length = ? WHERE id = ?');
            $stmt->bind_param('sii', $categoryName, $allowsWireLength, $id);
            $stmt->execute();
            update_inventory_category_cache($id, $categoryName);

            log_activity($userId, 'Update Category', 'Categories', "Updated category {$categoryName}.");
            set_flash('success', 'Category updated.');
        }

        if ($action === 'delete') {
            $id = post_int('id');
            $category = find_inventory_category($id);
            if (!$category) {
                throw new RuntimeException('Category not found.');
            }

            $usage = fetch_one('SELECT COUNT(*) AS total FROM inventory_items WHERE category_id = ?', 'i', [$id]);
            if ((int) ($usage['total'] ?? 0) > 0) {
                throw new RuntimeException('This category is already used by inventory items and cannot be deleted.');
            }

            $stmt = db()->prepare('DELETE FROM inventory_categories WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();

            log_activity($userId, 'Delete Category', 'Categories', "Deleted category {$category['category_name']}.");
            set_flash('success', 'Category deleted.');
        }
    } catch (Throwable $exception) {
        set_flash('danger', $exception->getMessage());
    }

    redirect('categories.php');
}

$categories = fetch_inventory_categories(false);
$usageMap = [];
foreach (fetch_all('SELECT category_id, COUNT(*) AS total FROM inventory_items WHERE category_id IS NOT NULL GROUP BY category_id') as $row) {
    $usageMap[(int) $row['category_id']] = (int) $row['total'];
}

$pageTitle = 'Categories';
$pageKey = 'categories';
require BASE_PATH . '/partials/layout_top.php';
?>
<div class="content-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="section-title">Inventory Categories</div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">Add Category</button>
    </div>
    <div class="table-responsive">
        <table class="table datatable align-middle">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Wire Length Field</th>
                    <th>Used By</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $category): ?>
                <tr>
                    <td class="fw-semibold"><?= e($category['category_name']) ?></td>
                    <td><?= (int) $category['allows_wire_length'] === 1 ? 'Enabled' : 'Disabled' ?></td>
                    <td><?= e((string) ($usageMap[(int) $category['id']] ?? 0)) ?> inventory item(s)</td>
                    <td><?= e(format_display_datetime($category['created_at'])) ?></td>
                    <td class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCategory<?= (int) $category['id'] ?>">Edit</button>
                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteCategory<?= (int) $category['id'] ?>" <?= (($usageMap[(int) $category['id']] ?? 0) > 0) ? 'disabled' : '' ?>>Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><form method="post">
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Add Category</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Category Name</label>
                <input class="form-control" name="category_name" required>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="allows_wire_length" id="addCategoryWire">
                <label class="form-check-label" for="addCategoryWire">Show wire length / meters field for this category</label>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Save Category</button></div>
    </form></div></div>
</div>

<?php foreach ($categories as $category): ?>
<div class="modal fade" id="editCategory<?= (int) $category['id'] ?>" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><form method="post">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Edit Category</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Category Name</label>
                <input class="form-control" name="category_name" value="<?= e($category['category_name']) ?>" required>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="allows_wire_length" id="editCategoryWire<?= (int) $category['id'] ?>" <?= (int) $category['allows_wire_length'] === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="editCategoryWire<?= (int) $category['id'] ?>">Show wire length / meters field for this category</label>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Update Category</button></div>
    </form></div></div>
</div>
<div class="modal fade" id="deleteCategory<?= (int) $category['id'] ?>" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><form method="post">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Delete Category</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            Delete category <strong><?= e($category['category_name']) ?></strong>?
            <?php if (($usageMap[(int) $category['id']] ?? 0) > 0): ?>
                <div class="text-danger mt-2">This category is still used by inventory items.</div>
            <?php endif; ?>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger" <?= (($usageMap[(int) $category['id']] ?? 0) > 0) ? 'disabled' : '' ?>>Delete</button></div>
    </form></div></div>
</div>
<?php endforeach; ?>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>
