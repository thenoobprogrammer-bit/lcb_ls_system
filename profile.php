<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_auth([ACCOUNT_ADMIN, ACCOUNT_EMPLOYEE, ACCOUNT_RENTAL]);

$user = current_user();
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $fullName = $user['account_type'] === ACCOUNT_RENTAL ? (string) $user['full_name'] : post_string('full_name');
        $address = post_string('address');
        $contactNumber = post_string('contact_number');
        $employeeRole = $user['account_type'] === ACCOUNT_EMPLOYEE ? (post_string('employee_role') ?: null) : null;

        if ($user['account_type'] !== ACCOUNT_RENTAL && $fullName === '') {
            throw new RuntimeException('Full name is required.');
        }

        $profileImage = $user['profile_image'];
        if (!empty($_FILES['profile_image']['name'])) {
            $profileImage = upload_image($_FILES['profile_image'], 'profiles');
        }

        $stmt = db()->prepare('UPDATE users SET full_name = ?, address = ?, contact_number = ?, employee_role = ?, profile_image = ? WHERE id = ?');
        $stmt->bind_param('sssssi', $fullName, $address, $contactNumber, $employeeRole, $profileImage, $userId);
        $stmt->execute();

        log_activity($userId, 'Update Profile', 'Profile', 'User updated their own profile.');
        refresh_current_user_session();
        set_flash('success', 'Profile updated successfully.');
    } catch (Throwable $exception) {
        set_flash('danger', $exception->getMessage());
    }

    redirect('profile.php');
}

$user = current_user();
$pageTitle = 'My Profile';
$pageKey = 'profile';
require BASE_PATH . '/partials/layout_top.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="content-card text-center">
            <img class="rounded-4 mb-3" src="<?= e(user_avatar($user['profile_image'] ?? null)) ?>" alt="Profile" style="width: 180px; height: 180px; object-fit: cover;">
            <div class="section-title"><?= e($user['full_name']) ?></div>
            <div class="text-muted"><?= e(role_label($user['account_type'])) ?></div>
            <?php if (!empty($user['employee_role'])): ?>
                <div class="mt-2"><span class="status-pill status-approved"><?= e($user['employee_role']) ?></span></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="content-card">
            <div class="section-title mb-3">Edit Profile</div>
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Username</label>
                    <input class="form-control" value="<?= e($user['username']) ?>" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Account Type</label>
                    <input class="form-control" value="<?= e(role_label($user['account_type'])) ?>" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Full Name</label>
                    <input class="form-control" name="full_name" value="<?= e($user['full_name']) ?>" <?= $user['account_type'] === ACCOUNT_RENTAL ? 'disabled' : 'required' ?>>
                    <?php if ($user['account_type'] === ACCOUNT_RENTAL): ?>
                        <div class="form-text">Rental users cannot change their full name to protect booking integrity.</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact Number</label>
                    <input class="form-control" name="contact_number" value="<?= e((string) $user['contact_number']) ?>">
                </div>
                <?php if ($user['account_type'] === ACCOUNT_EMPLOYEE): ?>
                    <div class="col-md-6">
                        <label class="form-label">Employee Role</label>
                        <select class="form-select" name="employee_role">
                            <option value="">Select role</option>
                            <?php foreach (['Sound Technician', 'Light Technician', 'Crew'] as $role): ?>
                                <option value="<?= e($role) ?>" <?= ($user['employee_role'] ?? '') === $role ? 'selected' : '' ?>><?= e($role) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="col-md-6">
                    <label class="form-label">Profile Picture</label>
                    <input type="file" class="form-control" name="profile_image" accept="image/*">
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea class="form-control" name="address" rows="3"><?= e((string) $user['address']) ?></textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary">Save Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>
