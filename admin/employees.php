<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_auth([ACCOUNT_ADMIN]);

$userId = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = post_string('action');
        if ($action === 'add') {
            $profileImage = upload_image($_FILES['profile_image'] ?? [], 'profiles');
            $stmt = db()->prepare('INSERT INTO users (username, password_hash, account_type, full_name, address, contact_number, employee_role, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $username = post_string('username');
            if (fetch_one('SELECT id FROM users WHERE username = ?', 's', [$username])) {
                throw new RuntimeException('Username already exists.');
            }
            $passwordHash = password_hash(post_string('password'), PASSWORD_DEFAULT);
            $accountType = post_string('account_type');
            $fullName = post_string('full_name');
            $address = post_string('address');
            $contactNumber = post_string('contact_number');
            $employeeRole = $accountType === ACCOUNT_EMPLOYEE ? (post_string('employee_role') ?: null) : null;
            $stmt->bind_param('ssssssss', $username, $passwordHash, $accountType, $fullName, $address, $contactNumber, $employeeRole, $profileImage);
            $stmt->execute();
            log_activity($userId, 'Create User', 'Employees', "Created {$accountType} account for {$fullName}.");
            set_flash('success', 'User account created.');
        }
        if ($action === 'edit') {
            $id = post_int('id');
            $existing = fetch_one('SELECT * FROM users WHERE id = ?', 'i', [$id]);
            if (!$existing) {
                throw new RuntimeException('User account not found.');
            }

            $username = post_string('username');
            $duplicate = fetch_one('SELECT id FROM users WHERE username = ? AND id != ?', 'si', [$username, $id]);
            if ($duplicate) {
                throw new RuntimeException('Username already exists.');
            }

            $profileImage = $existing['profile_image'];
            if (!empty($_FILES['profile_image']['name'])) {
                $profileImage = upload_image($_FILES['profile_image'], 'profiles');
            }

            $accountType = post_string('account_type');
            $employeeRole = $accountType === ACCOUNT_EMPLOYEE ? (post_string('employee_role') ?: null) : null;
            $password = post_string('password');

            if ($password !== '') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = db()->prepare('UPDATE users SET username = ?, password_hash = ?, account_type = ?, full_name = ?, address = ?, contact_number = ?, employee_role = ?, profile_image = ? WHERE id = ?');
                $fullName = post_string('full_name');
                $address = post_string('address');
                $contactNumber = post_string('contact_number');
                $stmt->bind_param('ssssssssi', $username, $passwordHash, $accountType, $fullName, $address, $contactNumber, $employeeRole, $profileImage, $id);
            } else {
                $stmt = db()->prepare('UPDATE users SET username = ?, account_type = ?, full_name = ?, address = ?, contact_number = ?, employee_role = ?, profile_image = ? WHERE id = ?');
                $fullName = post_string('full_name');
                $address = post_string('address');
                $contactNumber = post_string('contact_number');
                $stmt->bind_param('sssssssi', $username, $accountType, $fullName, $address, $contactNumber, $employeeRole, $profileImage, $id);
            }

            $stmt->execute();
            if ($id === $userId) {
                refresh_current_user_session();
            }
            log_activity($userId, 'Update User', 'Employees', "Updated account {$fullName} to {$accountType}.");
            set_flash('success', 'User account updated.');
        }
        if ($action === 'delete') {
            $id = post_int('id');
            if ($id === $userId) {
                throw new RuntimeException('You cannot delete your own account.');
            }
            $target = fetch_one('SELECT full_name FROM users WHERE id = ?', 'i', [$id]);
            $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            log_activity($userId, 'Delete User', 'Employees', "Deleted user {$target['full_name']}.");
            set_flash('success', 'User account deleted.');
        }
    } catch (Throwable $exception) {
        set_flash('danger', $exception->getMessage());
    }
    redirect('admin/employees.php');
}

$users = fetch_all('SELECT * FROM users ORDER BY created_at DESC');
$pageTitle = 'Employees and Users';
$pageKey = 'admin-employees';
require BASE_PATH . '/partials/layout_top.php';
?>
<div class="content-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="section-title">Account Management</div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">Add User</button>
    </div>
    <div class="table-responsive">
        <table class="table datatable align-middle">
            <thead><tr><th>Profile</th><th>Name</th><th>Username</th><th>Type</th><th>Role</th><th>Contact</th><th>Address</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $row): ?>
                <tr>
                    <td><img class="table-thumb" src="<?= e(user_avatar($row['profile_image'])) ?>" alt=""></td>
                    <td><?= e($row['full_name']) ?></td>
                    <td><?= e($row['username']) ?></td>
                    <td><?= e(role_label($row['account_type'])) ?></td>
                    <td><?= e($row['employee_role'] ?: '-') ?></td>
                    <td><?= e($row['contact_number'] ?: '-') ?></td>
                    <td><?= e($row['address'] ?: '-') ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUser<?= (int) $row['id'] ?>">Edit</button>
                        <?php if ((int) $row['id'] !== $userId): ?>
                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteUser<?= (int) $row['id'] ?>">Delete</button>
                        <?php else: ?>
                            <span class="text-muted small">Current account</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content"><form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Add User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" name="full_name" required></div>
            <div class="col-md-6"><label class="form-label">Username</label><input class="form-control" name="username" required></div>
            <div class="col-md-6"><label class="form-label">Password</label><input type="password" class="form-control" name="password" required></div>
            <div class="col-md-6"><label class="form-label">Account Type</label><select class="form-select js-account-type" name="account_type"><option value="employee">Employee</option><option value="rental">Rental User</option><option value="administrator">Administrator</option></select></div>
            <div class="col-md-6 js-employee-role-wrap"><label class="form-label">Employee Role</label><select class="form-select js-employee-role" name="employee_role"><option value="">N/A</option><option>Sound Technician</option><option>Light Technician</option><option>Crew</option></select><div class="form-text">Only employees should have an employee role.</div></div>
            <div class="col-md-6"><label class="form-label">Contact Number</label><input class="form-control" name="contact_number"></div>
            <div class="col-md-6"><label class="form-label">Profile Picture</label><input type="file" class="form-control" name="profile_image" accept="image/*"></div>
            <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Create Account</button></div>
    </form></div></div>
</div>
<?php foreach ($users as $row): ?>
<div class="modal fade" id="editUser<?= (int) $row['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content"><form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" name="full_name" value="<?= e($row['full_name']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Username</label><input class="form-control" name="username" value="<?= e($row['username']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">New Password</label><input type="password" class="form-control" name="password" placeholder="Leave blank to keep current password"></div>
            <div class="col-md-6"><label class="form-label">Account Type</label><select class="form-select js-account-type" name="account_type"><option value="employee" <?= $row['account_type'] === ACCOUNT_EMPLOYEE ? 'selected' : '' ?>>Employee</option><option value="rental" <?= $row['account_type'] === ACCOUNT_RENTAL ? 'selected' : '' ?>>Rental User</option><option value="administrator" <?= $row['account_type'] === ACCOUNT_ADMIN ? 'selected' : '' ?>>Administrator</option></select></div>
            <div class="col-md-6 js-employee-role-wrap"><label class="form-label">Employee Role</label><select class="form-select js-employee-role" name="employee_role"><option value="">N/A</option><?php foreach (['Sound Technician', 'Light Technician', 'Crew'] as $role): ?><option value="<?= e($role) ?>" <?= ($row['employee_role'] ?? '') === $role ? 'selected' : '' ?>><?= e($role) ?></option><?php endforeach; ?></select><div class="form-text">Only employees should have an employee role.</div></div>
            <div class="col-md-6"><label class="form-label">Contact Number</label><input class="form-control" name="contact_number" value="<?= e((string) $row['contact_number']) ?>"></div>
            <div class="col-md-6"><label class="form-label">Profile Picture</label><input type="file" class="form-control" name="profile_image" accept="image/*"></div>
            <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2"><?= e((string) $row['address']) ?></textarea></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save Changes</button></div>
    </form></div></div>
</div>
<?php endforeach; ?>
<?php foreach ($users as $row): if ((int) $row['id'] === $userId) continue; ?>
<div class="modal fade" id="deleteUser<?= (int) $row['id'] ?>" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><form method="post">
        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Delete User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">Delete account for <strong><?= e($row['full_name']) ?></strong>?</div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger" type="submit">Delete</button></div>
    </form></div></div>
</div>
<?php endforeach; ?>
<script>
document.querySelectorAll('.modal form').forEach((form) => {
    const accountType = form.querySelector('.js-account-type');
    const roleWrap = form.querySelector('.js-employee-role-wrap');
    const roleSelect = form.querySelector('.js-employee-role');

    if (!accountType || !roleWrap || !roleSelect) {
        return;
    }

    const syncRoleVisibility = () => {
        const isEmployee = accountType.value === 'employee';
        roleWrap.style.display = isEmployee ? '' : 'none';
        if (!isEmployee) {
            roleSelect.value = '';
        }
    };

    accountType.addEventListener('change', syncRoleVisibility);
    syncRoleVisibility();
});
</script>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>
