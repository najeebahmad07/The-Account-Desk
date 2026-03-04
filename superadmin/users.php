<?php
// File: superadmin/users.php
// Manage Center Admin Users - BLOCK/UNBLOCK BUG FIXED

require_once __DIR__ . '/../config/init.php';
requireSuperAdmin();

$conn = getDBConnection();

// ============================================
// Handle Create User
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = sanitize($_POST['name'] ?? '');
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $centerId = (int)($_POST['center_id'] ?? 0);
        
        if (empty($name) || empty($username) || empty($password) || $centerId === 0) {
            setFlash('danger', 'All fields are required.');
        } elseif (strlen($password) < 6) {
            setFlash('danger', 'Password must be at least 6 characters.');
        } else {
            $result = createUser($name, $username, $email, $password, 'center_admin', $centerId);
            if ($result['success']) {
                setFlash('success', 'Center Admin created successfully.');
            } else {
                setFlash('danger', $result['message']);
            }
        }
        redirect(APP_URL . '/superadmin/users.php');
    }
    
    if ($action === 'update') {
        $userId = (int)$_POST['user_id'];
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $centerId = (int)$_POST['center_id'];
        
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, center_id = ? WHERE id = ? AND role = 'center_admin'");
        $stmt->bind_param("ssii", $name, $email, $centerId, $userId);
        $stmt->execute();
        $stmt->close();
        
        if (!empty($_POST['password'])) {
            changePassword($userId, $_POST['password']);
        }
        
        logActivity($_SESSION['user_id'], 'User Updated', "Updated user ID: $userId");
        setFlash('success', 'User updated successfully.');
        redirect(APP_URL . '/superadmin/users.php');
    }
}

// ============================================
// FIXED: Handle Block/Unblock Toggle
// ============================================
if (isset($_GET['toggle_status'])) {
    $userId = (int)$_GET['toggle_status'];
    
    // Get current user status
    $userStmt = $conn->prepare("SELECT id, username, status FROM users WHERE id = ? AND role = 'center_admin'");
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();
    
    if ($user) {
        $newStatus = $user['status'] === 'active' ? 'blocked' : 'active';
        
        // Update user status
        $updateStmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $updateStmt->bind_param("si", $newStatus, $userId);
        $updateStmt->execute();
        $updateStmt->close();
        
        // ============================================
        // KEY FIX: When Super Admin UNBLOCKS a user,
        // log it as 'Manual Unblock' so autoBlockCheck()
        // will NOT re-block this user for 30 days
        // ============================================
        if ($newStatus === 'active') {
            // Log manual unblock - THIS PREVENTS AUTO RE-BLOCKING
            $detail = "Manually unblocked by Super Admin (User: {$user['username']})";
            $logStmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, 'Manual Unblock', ?, ?)");
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $logStmt->bind_param("iss", $userId, $detail, $ip);
            $logStmt->execute();
            $logStmt->close();
            
            logActivity($_SESSION['user_id'], 'User Unblocked', "Unblocked user: {$user['username']} (ID: $userId)");
            setFlash('success', "✅ User '{$user['username']}' has been UNBLOCKED successfully. They will NOT be auto-blocked again for 30 days.");
        } else {
            // Log manual block
            logActivity($_SESSION['user_id'], 'User Blocked', "Blocked user: {$user['username']} (ID: $userId)");
            setFlash('warning', "🚫 User '{$user['username']}' has been BLOCKED.");
        }
    } else {
        setFlash('danger', 'User not found.');
    }
    
    redirect(APP_URL . '/superadmin/users.php');
}

// ============================================
// Handle Delete
// ============================================
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    
    // Get username for log
    $delStmt = $conn->prepare("SELECT username FROM users WHERE id = ? AND role = 'center_admin'");
    $delStmt->bind_param("i", $deleteId);
    $delStmt->execute();
    $delUser = $delStmt->get_result()->fetch_assoc();
    $delStmt->close();
    
    if ($delUser) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'center_admin'");
        $stmt->bind_param("i", $deleteId);
        $stmt->execute();
        $stmt->close();
        logActivity($_SESSION['user_id'], 'User Deleted', "Deleted user: {$delUser['username']} (ID: $deleteId)");
        setFlash('success', "User '{$delUser['username']}' deleted successfully.");
    }
    redirect(APP_URL . '/superadmin/users.php');
}

// ============================================
// Fetch all users with details
// ============================================
$users = $conn->query("SELECT u.*, c.center_name,
    (SELECT al.created_at FROM activity_log al WHERE al.user_id = u.id AND al.action = 'Manual Unblock' ORDER BY al.created_at DESC LIMIT 1) as last_unblocked,
    (SELECT al.created_at FROM activity_log al WHERE al.user_id = u.id AND al.action = 'Auto-Blocked' ORDER BY al.created_at DESC LIMIT 1) as last_auto_blocked
    FROM users u 
    LEFT JOIN centers c ON u.center_id = c.id 
    WHERE u.role = 'center_admin' 
    ORDER BY u.created_at DESC");

// Fetch centers for dropdown
$centers = $conn->query("SELECT * FROM centers ORDER BY center_name");

// Edit user data
$editUser = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editStmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'center_admin'");
    $editStmt->bind_param("i", $editId);
    $editStmt->execute();
    $editUser = $editStmt->get_result()->fetch_assoc();
    $editStmt->close();
}

$conn->close();

$pageTitle = 'Manage Center Admins';
$currentPage = 'users';
include __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <!-- Add/Edit User Form -->
    <div class="col-lg-4">
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-<?php echo $editUser ? 'edit' : 'user-plus'; ?>"></i>
                    <?php echo $editUser ? 'Edit Admin' : 'Create Center Admin'; ?>
                </h5>
            </div>
            <div class="card-custom-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="<?php echo $editUser ? 'update' : 'create'; ?>">
                    <?php if ($editUser): ?>
                    <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group-custom">
                        <label>Full Name</label>
                        <input type="text" name="name" class="form-control-custom" required
                               placeholder="Enter full name"
                               value="<?php echo $editUser ? sanitize($editUser['name']) : ''; ?>">
                    </div>
                    
                    <?php if (!$editUser): ?>
                    <div class="form-group-custom">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control-custom" required
                               placeholder="Enter username" pattern="[a-zA-Z0-9_.]+">
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group-custom">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control-custom"
                               placeholder="Enter email"
                               value="<?php echo $editUser ? sanitize($editUser['email'] ?? '') : ''; ?>">
                    </div>
                    
                    <div class="form-group-custom">
                        <label>Password <?php echo $editUser ? '(leave blank to keep)' : ''; ?></label>
                        <input type="password" name="password" class="form-control-custom"
                               placeholder="Enter password" <?php echo $editUser ? '' : 'required'; ?> minlength="6">
                    </div>
                    
                    <div class="form-group-custom">
                        <label>Assign Center</label>
                        <select name="center_id" class="form-control-custom" required>
                            <option value="">-- Select Center --</option>
                            <?php 
                            $centers->data_seek(0);
                            while ($c = $centers->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>" 
                                <?php echo ($editUser && $editUser['center_id'] == $c['id']) ? 'selected' : ''; ?>>
                                <?php echo sanitize($c['center_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-primary-custom w-100">
                        <i class="fas fa-<?php echo $editUser ? 'save' : 'plus'; ?>"></i>
                        <?php echo $editUser ? 'Update Admin' : 'Create Admin'; ?>
                    </button>
                    
                    <?php if ($editUser): ?>
                    <a href="<?php echo APP_URL; ?>/superadmin/users.php" class="btn-outline-custom w-100 mt-2 justify-content-center">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Users List -->
    <div class="col-lg-8">
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-users"></i> All Center Admins</h5>
            </div>
            <div class="card-custom-body p-0">
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Center</th>
                                <th>Status</th>
                                <th>Info</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $sr = 1; while ($u = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $sr++; ?></td>
                                <td class="fw-600"><?php echo sanitize($u['name']); ?></td>
                                <td class="text-muted-custom"><code><?php echo sanitize($u['username']); ?></code></td>
                                <td><?php echo sanitize($u['center_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($u['status'] === 'active'): ?>
                                        <span class="badge-type badge-active">
                                            <i class="fas fa-check-circle me-1"></i>Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-type badge-blocked">
                                            <i class="fas fa-ban me-1"></i>Blocked
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 11px;">
                                    <?php if ($u['last_auto_blocked']): ?>
                                        <span class="text-danger-custom">
                                            <i class="fas fa-robot"></i> Auto-blocked: <?php echo formatDate($u['last_auto_blocked']); ?>
                                        </span><br>
                                    <?php endif; ?>
                                    <?php if ($u['last_unblocked']): ?>
                                        <span class="text-success-custom">
                                            <i class="fas fa-user-check"></i> Unblocked: <?php echo formatDate($u['last_unblocked']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!$u['last_auto_blocked'] && !$u['last_unblocked']): ?>
                                        <span class="text-muted-custom">
                                            Created: <?php echo formatDate($u['created_at']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <!-- Edit Button -->
                                        <a href="?edit=<?php echo $u['id']; ?>" class="btn-icon btn-icon-edit" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        
                                        <!-- Block/Unblock Button -->
                                        <?php if ($u['status'] === 'active'): ?>
                                            <a href="?toggle_status=<?php echo $u['id']; ?>" 
                                               class="btn-icon btn-icon-delete" 
                                               title="Block this user"
                                               onclick="return confirm('⚠️ Block user \'<?php echo sanitize($u['username']); ?>\'?\n\nThey will NOT be able to login.')">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="?toggle_status=<?php echo $u['id']; ?>" 
                                               class="btn-icon btn-icon-view" 
                                               title="Unblock this user"
                                               onclick="return confirm('✅ Unblock user \'<?php echo sanitize($u['username']); ?>\'?\n\nThey will be able to login again.\nThey will NOT be auto-blocked for 30 days.')">
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- Delete Button -->
                                        <a href="?delete=<?php echo $u['id']; ?>" 
                                           class="btn-icon btn-icon-delete" title="Delete" 
                                           onclick="return confirm('🗑️ Permanently delete user \'<?php echo sanitize($u['username']); ?>\'?\n\nThis cannot be undone!')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if ($sr === 1): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-users d-block"></i>
                                        <h5>No Center Admins Found</h5>
                                        <p>Create your first center admin using the form.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Info Box -->
        <div class="card-custom mt-3">
            <div class="card-custom-body" style="padding: 16px 24px;">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="fw-700 mb-2" style="font-size: 13px;"><i class="fas fa-robot text-primary-custom me-2"></i>Auto-Block System</h6>
                        <p class="text-muted-custom mb-0" style="font-size: 12px;">
                            Users are automatically blocked if they don't submit their monthly report within 
                            <strong><?php echo AUTO_BLOCK_DAYS; ?> days</strong> of the new month.
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-700 mb-2" style="font-size: 13px;"><i class="fas fa-shield-alt text-success-custom me-2"></i>Manual Unblock Protection</h6>
                        <p class="text-muted-custom mb-0" style="font-size: 12px;">
                            When you manually unblock a user, they are protected from auto-blocking for 
                            <strong>30 days</strong>. This gives them time to submit pending reports.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>