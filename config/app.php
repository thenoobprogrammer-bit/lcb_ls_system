<?php
declare(strict_types=1);

session_start();

date_default_timezone_set('Asia/Manila');

define('APP_NAME', 'LCB LIGHTS AND SOUND');
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '/Inventory_Rental_Payroll_System');
define('UPLOADS_PATH', BASE_PATH . '/uploads');

const ACCOUNT_ADMIN = 'administrator';
const ACCOUNT_EMPLOYEE = 'employee';
const ACCOUNT_RENTAL = 'rental';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'lcb_lights_sound';

try {
    $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    $mysqli->set_charset('utf8mb4');
} catch (Throwable $exception) {
    $mysqli = null;
    $dbError = $exception->getMessage();
}

require_once BASE_PATH . '/config/helpers.php';

if ($mysqli instanceof mysqli) {
    ensure_runtime_schema();
    repair_seed_passwords();
}
