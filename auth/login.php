<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

if (is_logged_in()) {
    redirect(dashboard_path_for_role(current_user()['account_type']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post_string('username');
    $password = post_string('password');

    $user = fetch_one('SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1', 's', [$username]);

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = $user;
        log_activity((int) $user['id'], 'Login', 'Authentication', 'User logged in.');
        set_flash('success', 'Welcome back, ' . $user['full_name'] . '.');
        redirect(dashboard_path_for_role($user['account_type']));
    }

    set_flash('danger', 'Invalid username or password.');
    redirect('auth/login.php');
}

$pageTitle = 'Login';
$pageKey = 'login';
require BASE_PATH . '/partials/layout_top.php';
?>
<div class="auth-wrap">
    <div class="auth-card row g-0">
        <div class="col-lg-5 auth-side d-flex flex-column justify-content-between">
            <div>
                <div class="hero-badge">Larry R. Bajet</div>
                <h2 class="display-6 fw-bold">LCB LIGHTS AND SOUND</h2>
                <p class="mt-3 text-white-50">Rental operations, inventory, and event tracking.</p>
            </div>
            <div>
                <div class="small text-white-50">Default accounts after SQL import</div>
                <div class="mt-2">admin / password123</div>
                <div>soundtech / password123</div>
                <div>rentaluser / password123</div>
            </div>
        </div>
        <div class="col-lg-7 auth-form bg-white">
            <h3 class="fw-bold mb-1">Sign In</h3>
            <p class="text-muted mb-4">Use your account to access the system dashboard.</p>
            <form method="post" class="row g-3">
                <div class="col-12">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Login</button>
                    
                </div>
            </form>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>

