<?php
declare(strict_types=1);

function db(): mysqli
{
    global $mysqli;

    if (!$mysqli instanceof mysqli) {
        throw new RuntimeException('Database connection is not available. Import the SQL file and update config/app.php first.');
    }

    return $mysqli;
}

function app_url(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . ($path ? '/' . ltrim($path, '/') : '');
}

function asset_url(string $path): string
{
    return app_url($path);
}

function redirect(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function refresh_current_user_session(): void
{
    if (!isset($_SESSION['user']['id'])) {
        return;
    }

    $user = fetch_one('SELECT * FROM users WHERE id = ? LIMIT 1', 'i', [(int) $_SESSION['user']['id']]);
    if ($user) {
        $_SESSION['user'] = $user;
    }
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function require_auth(array $roles = []): void
{
    if (!is_logged_in()) {
        set_flash('danger', 'Please sign in first.');
        redirect('auth/login.php');
    }

    if ($roles && !in_array($_SESSION['user']['account_type'], $roles, true)) {
        set_flash('danger', 'You do not have access to that page.');
        redirect('dashboard.php');
    }
}

function role_label(string $role): string
{
    return match ($role) {
        ACCOUNT_ADMIN => 'Administrator',
        ACCOUNT_EMPLOYEE => 'Employee',
        ACCOUNT_RENTAL => 'Rental User',
        default => ucfirst($role),
    };
}

function dashboard_path_for_role(string $role): string
{
    return match ($role) {
        ACCOUNT_ADMIN => 'admin/dashboard.php',
        ACCOUNT_EMPLOYEE => 'employee/dashboard.php',
        ACCOUNT_RENTAL => 'rental/dashboard.php',
        default => 'auth/login.php',
    };
}

function log_activity(?int $userId, string $action, string $module, string $details): void
{
    if (!$userId) {
        return;
    }

    $stmt = db()->prepare('INSERT INTO activity_logs (user_id, action_name, module_name, details) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('isss', $userId, $action, $module, $details);
    $stmt->execute();
}

function create_notification(int $userId, string $title, string $message): void
{
    $stmt = db()->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $userId, $title, $message);
    $stmt->execute();
}

function fetch_all_assoc(mysqli_stmt $stmt): array
{
    $result = $stmt->get_result();

    return $result->fetch_all(MYSQLI_ASSOC);
}

function fetch_one(string $sql, string $types = '', array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    return $result ?: null;
}

function fetch_all(string $sql, string $types = '', array $params = []): array
{
    $stmt = db()->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function count_table(string $table, string $where = '1=1'): int
{
    $result = db()->query("SELECT COUNT(*) AS total FROM {$table} WHERE {$where}");
    $row = $result->fetch_assoc();

    return (int) ($row['total'] ?? 0);
}

function upload_image(array $file, string $directory, array $allowedTypes = ['image/jpeg', 'image/png', 'image/webp']): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload failed.');
    }

    if (!in_array($file['type'], $allowedTypes, true)) {
        throw new RuntimeException('Invalid image type. Use JPG, PNG, or WEBP.');
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_', true) . '.' . strtolower($extension);
    $targetDir = UPLOADS_PATH . '/' . trim($directory, '/');

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $targetPath = $targetDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Unable to save the uploaded image.');
    }

    return $filename;
}

function page_title(string $title): string
{
    return $title . ' | ' . APP_NAME;
}

function user_avatar(?string $filename): string
{
    if ($filename && is_file(UPLOADS_PATH . '/profiles/' . $filename)) {
        return app_url('uploads/profiles/' . $filename);
    }

    return app_url('assets/img/logo-placeholder.svg');
}

function inventory_image(?string $filename): string
{
    if ($filename && is_file(UPLOADS_PATH . '/inventory/' . $filename)) {
        return app_url('uploads/inventory/' . $filename);
    }

    return app_url('assets/img/logo-placeholder.svg');
}

function money(float $value): string
{
    return 'PHP ' . number_format($value, 2);
}

function post_string(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function post_float(string $key): float
{
    return (float) ($_POST[$key] ?? 0);
}

function post_int(string $key): int
{
    return (int) ($_POST[$key] ?? 0);
}

function validate_required(array $fields): array
{
    $errors = [];
    foreach ($fields as $field => $label) {
        if (trim((string) ($_POST[$field] ?? '')) === '') {
            $errors[] = "{$label} is required.";
        }
    }

    return $errors;
}

function ensure_database(): void
{
    global $dbError;

    if (isset($dbError)) {
        http_response_code(500);
        echo '<h2>Database connection error</h2>';
        echo '<p>' . e($dbError) . '</p>';
        echo '<p>Update <code>config/app.php</code> and import <code>sql/schema.sql</code>.</p>';
        exit;
    }
}

function repair_seed_passwords(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }
    $checked = true;

    global $mysqli;
    if (!$mysqli instanceof mysqli) {
        return;
    }

    $legacySeedHash = '$2y$10$rfvVjlwmq5d8czxjTj4AbuZUtGFd6eCzVGOnwGTrSYm1g7mM75l1y';
    $seedUsernames = ['admin', 'soundtech', 'rentaluser'];
    $newHash = password_hash('password123', PASSWORD_DEFAULT);

    $select = db()->prepare('SELECT id, username FROM users WHERE username = ? AND password_hash = ? LIMIT 1');
    $update = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');

    foreach ($seedUsernames as $username) {
        $select->bind_param('ss', $username, $legacySeedHash);
        $select->execute();
        $row = $select->get_result()->fetch_assoc();

        if ($row) {
            $id = (int) $row['id'];
            $update->bind_param('si', $newHash, $id);
            $update->execute();
        }
    }
}

function ensure_runtime_schema(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }
    $checked = true;

    db()->query("
        CREATE TABLE IF NOT EXISTS event_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            employee_id INT NOT NULL,
            assigned_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_event_employee (event_id, employee_id),
            CONSTRAINT fk_event_assignments_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            CONSTRAINT fk_event_assignments_employee FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_event_assignments_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function package_items_available(?string $equipmentIds): bool
{
    if (!$equipmentIds) {
        return true;
    }

    $ids = array_values(array_filter(array_map('intval', explode(',', $equipmentIds))));
    if (!$ids) {
        return true;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = db()->prepare("SELECT COUNT(*) AS total FROM inventory_items WHERE id IN ($placeholders) AND quantity > 0 AND status = 'Available'");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $available = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    return $available === count($ids);
}

function event_has_conflict(int $packageId, string $eventDate, string $startTime, string $endTime, int $ignoreEventId = 0): bool
{
    $sql = "
        SELECT COUNT(*) AS total
        FROM events
        WHERE package_id = ?
          AND event_date = ?
          AND event_status IN ('Pending', 'Approved')
          AND id != ?
          AND (
            (start_time < ? AND end_time > ?)
            OR (start_time < ? AND end_time > ?)
            OR (start_time >= ? AND end_time <= ?)
          )
    ";
    $stmt = db()->prepare($sql);
    $stmt->bind_param('isissssss', $packageId, $eventDate, $ignoreEventId, $endTime, $startTime, $endTime, $startTime, $startTime, $endTime);
    $stmt->execute();

    return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0) > 0;
}
