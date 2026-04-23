<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

if (is_logged_in()) {
    log_activity((int) current_user()['id'], 'Logout', 'Authentication', 'User logged out.');
}

session_destroy();
session_start();
set_flash('success', 'You have been logged out.');
redirect('auth/login.php');

