<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

if (is_logged_in()) {
    redirect(dashboard_path_for_role(current_user()['account_type']));
}

redirect('auth/login.php');

