<?php
// File: includes/header.php
// No whitespace before this line
$pageTitle = $pageTitle ?? 'Dashboard';
$currentPage = $currentPage ?? 'dashboard';
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($pageTitle); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link href="<?php echo APP_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>
<div class="page-loader"><div class="spinner"></div></div>
<div class="sidebar-overlay"></div>
<?php include __DIR__ . '/sidebar.php'; ?>
<header class="top-header">
    <div class="header-left">
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <div class="breadcrumb-section">
            <span class="page-title"><?php echo sanitize($pageTitle); ?></span>
        </div>
    </div>
    <div class="header-right">
        <button class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i><span>Dark</span></button>
        <div class="dropdown">
            <div class="user-dropdown" data-bs-toggle="dropdown">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?></div>
                <div class="user-info d-none d-md-flex">
                    <span class="user-name"><?php echo sanitize($_SESSION['user_name'] ?? 'User'); ?></span>
                    <span class="user-role"><?php echo $_SESSION['role'] === 'super_admin' ? 'Super Admin' : 'Center Admin'; ?></span>
                </div>
                <i class="fas fa-chevron-down ms-1" style="font-size:10px;color:var(--text-muted-light);"></i>
            </div>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/<?php echo isSuperAdmin() ? 'superadmin' : 'center'; ?>/profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</header>
<main class="main-content">
    <div class="content-wrapper">
        <?php if ($flash): ?>
        <div class="alert-custom alert-<?php echo sanitize($flash['type']); ?>">
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <?php echo sanitize($flash['message']); ?>
            <button class="alert-close">&times;</button>
        </div>
        <?php endif; ?>