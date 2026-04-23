<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = validate_required([
        'full_name' => 'Full name',
        'username' => 'Username',
        'password' => 'Password',
        'contact_number' => 'Contact number',
        'address' => 'Address',
    ]);

    if (fetch_one('SELECT id FROM users WHERE username = ?', 's', [post_string('username')])) {
        $errors[] = 'Username is already taken.';
    }

    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO users (username, password_hash, account_type, full_name, address, contact_number) VALUES (?, ?, ?, ?, ?, ?)');
        $role = ACCOUNT_RENTAL;
        $passwordHash = password_hash(post_string('password'), PASSWORD_DEFAULT);
        $username = post_string('username');
        $fullName = post_string('full_name');
        $address = post_string('address');
        $contactNumber = post_string('contact_number');
        $stmt->bind_param('ssssss', $username, $passwordHash, $role, $fullName, $address, $contactNumber);
        $stmt->execute();

        set_flash('success', 'Registration completed. You can sign in now.');
        redirect('auth/login.php');
    }

    set_flash('danger', implode(' ', $errors));
    redirect('auth/register.php');
}

$pageTitle = 'Register';
$pageKey = 'register';
require BASE_PATH . '/partials/layout_top.php';
?>
<div class="auth-wrap">
    <div class="auth-card row g-0">
        <div class="col-lg-5 auth-side">
            <div class="hero-badge">Rental User Registration</div>
            <h2 class="display-6 fw-bold">Book packages and create event requests.</h2>
            <p class="text-white-50">Customer accounts can view approved packages, submit event bookings, and monitor approval updates.</p>
        </div>
        <div class="col-lg-7 auth-form bg-white">
            <h3 class="fw-bold mb-4">Create Rental Account</h3>
            <form method="post" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_number" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="3" required></textarea>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Register</button>
                    <a class="btn btn-outline-primary" href="<?= e(app_url('auth/login.php')) ?>">Back to Login</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>

