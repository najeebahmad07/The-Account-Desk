<?php
// File: superadmin/settings.php
// System Settings

require_once __DIR__ . '/../config/init.php';
requireSuperAdmin();

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName = sanitize($_POST['company_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $autoBlockDays = (int)($_POST['auto_block_days'] ?? 5);

    $stmt = $conn->prepare("UPDATE settings SET company_name = ?, email = ?, auto_block_days = ? WHERE id = 1");
    $stmt->bind_param("ssi", $companyName, $email, $autoBlockDays);
    $stmt->execute();
    $stmt->close();

    logActivity($_SESSION['user_id'], 'Settings Updated', 'System settings updated');
    setFlash('success', 'Settings updated successfully.');
    redirect(APP_URL . '/superadmin/settings.php');
}

$settings = getSettings();
$conn->close();

$pageTitle = 'Settings';
$currentPage = 'settings';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-cog"></i> System Settings</h5>
            </div>
            <div class="card-custom-body">
                <form method="POST">
                    <div class="form-group-custom">
                        <label>Company Name</label>
                        <input type="text" name="company_name" class="form-control-custom" required
                               value="<?php echo sanitize($settings['company_name']); ?>">
                    </div>

                    <div class="form-group-custom">
                        <label>System Email</label>
                        <input type="email" name="email" class="form-control-custom"
                               value="<?php echo sanitize($settings['email']); ?>">
                    </div>

                    <div class="form-group-custom">
                        <label>Auto-Block Days (days after month end)</label>
                        <input type="number" name="auto_block_days" class="form-control-custom"
                               min="1" max="30" value="<?php echo $settings['auto_block_days']; ?>">
                        <small class="text-muted-custom d-block mt-1">
                            Center admins will be auto-blocked if they don't submit their monthly report within this many days of the new month.
                        </small>
                    </div>

                    <button type="submit" class="btn-primary-custom">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>