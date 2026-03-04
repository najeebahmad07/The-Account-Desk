<?php
// File: center/profile.php
// Center Admin Profile

require_once __DIR__ . '/../config/init.php';
requireCenterAdmin();

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';

    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $email, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
    $_SESSION['user_name'] = $name;

    if (!empty($newPassword)) {
        if (strlen($newPassword) < 6) {
            setFlash('danger', 'New password must be at least 6 characters.');
            redirect(APP_URL . '/center/profile.php');
        }

        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!password_verify($currentPassword, $user['password'])) {
            setFlash('danger', 'Current password is incorrect.');
            redirect(APP_URL . '/center/profile.php');
        }

        changePassword($_SESSION['user_id'], $newPassword);
        setFlash('success', 'Profile and password updated.');
    } else {
        setFlash('success', 'Profile updated successfully.');
    }
    redirect(APP_URL . '/center/profile.php');
}

$stmt = $conn->prepare("SELECT u.*, c.center_name, c.location FROM users u LEFT JOIN centers c ON u.center_id = c.id WHERE u.id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

$pageTitle = 'My Profile';
$currentPage = 'profile';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-user-circle"></i> My Profile</h5>
            </div>
            <div class="card-custom-body">
                <div class="text-center mb-4">
                    <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 32px;">
                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                    </div>
                    <h5 class="fw-700"><?php echo sanitize($user['name']); ?></h5>
                    <span class="badge-type badge-active">Center Admin</span>
                    <p class="text-muted-custom mt-2 mb-0">
                        <i class="fas fa-building me-1"></i> <?php echo sanitize($user['center_name'] ?? 'N/A'); ?>
                        <br><small><?php echo sanitize($user['location'] ?? ''); ?></small>
                    </p>
                </div>

                <form method="POST">
                    <div class="form-group-custom">
                        <label>Full Name</label>
                        <input type="text" name="name" class="form-control-custom" required
                               value="<?php echo sanitize($user['name']); ?>">
                    </div>
                    <div class="form-group-custom">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control-custom"
                               value="<?php echo sanitize($user['email'] ?? ''); ?>">
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-600 mb-3 text-muted-custom">Change Password</h6>

                    <div class="form-group-custom">
                        <label>Current Password</label>
                        <input type="password" name="current_password" class="form-control-custom">
                    </div>
                    <div class="form-group-custom">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control-custom" minlength="6">
                    </div>

                    <button type="submit" class="btn-primary-custom w-100">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>