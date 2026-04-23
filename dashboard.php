<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_auth([ACCOUNT_ADMIN, ACCOUNT_EMPLOYEE, ACCOUNT_RENTAL]);

redirect(dashboard_path_for_role(current_user()['account_type']));

