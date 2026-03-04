<?php
// File: includes/sidebar.php
// Sidebar navigation component - UPDATED WITH EXPORT MENU

$role = $_SESSION['role'] ?? '';
$centerName = '';
if (isCenterAdmin() && isset($_SESSION['center_id'])) {
    $centerName = getCenterName($_SESSION['center_id']);
}
?>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-logo">O</div>
        <div>
            <h2><?php echo APP_NAME; ?></h2>
            <small><?php echo isSuperAdmin() ? 'Super Admin' : sanitize($centerName); ?></small>
        </div>
    </div>

    <nav class="sidebar-menu">
        <?php if (isSuperAdmin()): ?>
        <!-- Super Admin Menu -->
        <div class="sidebar-menu-label">Main</div>
        <a href="<?php echo APP_URL; ?>/superadmin/dashboard.php" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>

        <div class="sidebar-menu-label">Management</div>
        <a href="<?php echo APP_URL; ?>/superadmin/centers.php" class="<?php echo $currentPage === 'centers' ? 'active' : ''; ?>">
            <i class="fas fa-building"></i>
            <span>Centers</span>
        </a>
        <a href="<?php echo APP_URL; ?>/superadmin/users.php" class="<?php echo $currentPage === 'users' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Center Admins</span>
        </a>
        <a href="<?php echo APP_URL; ?>/superadmin/transactions.php" class="<?php echo $currentPage === 'transactions' ? 'active' : ''; ?>">
            <i class="fas fa-exchange-alt"></i>
            <span>Transactions</span>
        </a>

        <div class="sidebar-menu-label">Reports & Export</div>
        <a href="<?php echo APP_URL; ?>/superadmin/reports.php" class="<?php echo $currentPage === 'reports' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i>
            <span>Monthly Reports</span>
        </a>
        <a href="<?php echo APP_URL; ?>/superadmin/analytics.php" class="<?php echo $currentPage === 'analytics' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Analytics</span>
        </a>
        <a href="<?php echo APP_URL; ?>/superadmin/export.php" class="<?php echo $currentPage === 'export' ? 'active' : ''; ?>">
            <i class="fas fa-download"></i>
            <span>Export Data</span>
        </a>

        <div class="sidebar-menu-label">System</div>
        <a href="<?php echo APP_URL; ?>/superadmin/settings.php" class="<?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
        <a href="<?php echo APP_URL; ?>/superadmin/profile.php" class="<?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i>
            <span>My Profile</span>
        </a>

        <?php else: ?>
        <!-- Center Admin Menu -->
        <div class="sidebar-menu-label">Main</div>
        <a href="<?php echo APP_URL; ?>/center/dashboard.php" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>

        <div class="sidebar-menu-label">Transactions</div>
        <a href="<?php echo APP_URL; ?>/center/add_transaction.php" class="<?php echo $currentPage === 'add_transaction' ? 'active' : ''; ?>">
            <i class="fas fa-plus-circle"></i>
            <span>Add Transaction</span>
        </a>
        <a href="<?php echo APP_URL; ?>/center/transactions.php" class="<?php echo $currentPage === 'transactions' ? 'active' : ''; ?>">
            <i class="fas fa-list-alt"></i>
            <span>View Transactions</span>
        </a>

        <div class="sidebar-menu-label">Reports & Export</div>
        <a href="<?php echo APP_URL; ?>/center/monthly_report.php" class="<?php echo $currentPage === 'monthly_report' ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Monthly Report</span>
        </a>
        <a href="<?php echo APP_URL; ?>/center/export.php" class="<?php echo $currentPage === 'export' ? 'active' : ''; ?>">
            <i class="fas fa-download"></i>
            <span>Export Data</span>
        </a>

        <div class="sidebar-menu-label">Account</div>
        <a href="<?php echo APP_URL; ?>/center/profile.php" class="<?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i>
            <span>My Profile</span>
        </a>
        <?php endif; ?>

        <div class="sidebar-menu-label" style="margin-top: 20px;"></div>
        <a href="<?php echo APP_URL; ?>/logout.php" style="color: var(--danger);">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>
</aside>