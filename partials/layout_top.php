<?php
declare(strict_types=1);

require_once BASE_PATH . '/config/app.php';
ensure_database();
$user = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
$pageKey = $pageKey ?? '';
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(page_title($pageTitle)) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="<?= e(asset_url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body data-page="<?= e($pageKey) ?>">
<div class="app-shell">
    <?php if ($user): ?>
        <?php require BASE_PATH . '/partials/sidebar.php'; ?>
    <?php endif; ?>
    <main class="app-main <?= $user ? '' : 'app-auth' ?>">
        <?php if ($user): ?>
            <?php require BASE_PATH . '/partials/topbar.php'; ?>
        <?php endif; ?>
        <div class="container-fluid py-4">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

