<?php
// File: superadmin/centers.php
// Manage Centers

require_once __DIR__ . '/../config/init.php';
requireSuperAdmin();

$conn = getDBConnection();

// Handle Add/Edit Center
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $centerName = sanitize($_POST['center_name'] ?? '');
    $location = sanitize($_POST['location'] ?? '');

    if (empty($centerName) || empty($location)) {
        setFlash('danger', 'Center name and location are required.');
    } else {
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO centers (center_name, location) VALUES (?, ?)");
            $stmt->bind_param("ss", $centerName, $location);
            if ($stmt->execute()) {
                logActivity($_SESSION['user_id'], 'Center Created', "Created center: $centerName");
                setFlash('success', 'Center created successfully.');
            } else {
                setFlash('danger', 'Failed to create center.');
            }
            $stmt->close();
        } elseif ($action === 'edit') {
            $centerId = (int)$_POST['center_id'];
            $stmt = $conn->prepare("UPDATE centers SET center_name = ?, location = ? WHERE id = ?");
            $stmt->bind_param("ssi", $centerName, $location, $centerId);
            if ($stmt->execute()) {
                logActivity($_SESSION['user_id'], 'Center Updated', "Updated center ID: $centerId");
                setFlash('success', 'Center updated successfully.');
            } else {
                setFlash('danger', 'Failed to update center.');
            }
            $stmt->close();
        }
    }
    redirect(APP_URL . '/superadmin/centers.php');
}

// Handle Delete
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM centers WHERE id = ?");
    $stmt->bind_param("i", $deleteId);
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'Center Deleted', "Deleted center ID: $deleteId");
        setFlash('success', 'Center deleted successfully.');
    }
    $stmt->close();
    redirect(APP_URL . '/superadmin/centers.php');
}

// Fetch all centers with stats
$centers = $conn->query("SELECT c.*,
    (SELECT COUNT(*) FROM users WHERE center_id = c.id AND role = 'center_admin') as admin_count,
    COALESCE((SELECT SUM(amount) FROM transactions WHERE center_id = c.id AND type='income'), 0) as total_income,
    COALESCE((SELECT SUM(amount) FROM transactions WHERE center_id = c.id AND type='expense'), 0) as total_expense
    FROM centers c ORDER BY c.created_at DESC");

// Edit data
$editCenter = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editStmt = $conn->prepare("SELECT * FROM centers WHERE id = ?");
    $editStmt->bind_param("i", $editId);
    $editStmt->execute();
    $editCenter = $editStmt->get_result()->fetch_assoc();
    $editStmt->close();
}

$conn->close();

$pageTitle = 'Manage Centers';
$currentPage = 'centers';
include __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <!-- Add/Edit Center Form -->
    <div class="col-lg-4">
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-<?php echo $editCenter ? 'edit' : 'plus'; ?>"></i>
                    <?php echo $editCenter ? 'Edit Center' : 'Add New Center'; ?>
                </h5>
            </div>
            <div class="card-custom-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="<?php echo $editCenter ? 'edit' : 'add'; ?>">
                    <?php if ($editCenter): ?>
                    <input type="hidden" name="center_id" value="<?php echo $editCenter['id']; ?>">
                    <?php endif; ?>

                    <div class="form-group-custom">
                        <label>Center Name</label>
                        <input type="text" name="center_name" class="form-control-custom"
                               placeholder="Enter center name" required
                               value="<?php echo $editCenter ? sanitize($editCenter['center_name']) : ''; ?>">
                    </div>

                    <div class="form-group-custom">
                        <label>Location</label>
                        <input type="text" name="location" class="form-control-custom"
                               placeholder="Enter location" required
                               value="<?php echo $editCenter ? sanitize($editCenter['location']) : ''; ?>">
                    </div>

                    <button type="submit" class="btn-primary-custom w-100">
                        <i class="fas fa-<?php echo $editCenter ? 'save' : 'plus'; ?>"></i>
                        <?php echo $editCenter ? 'Update Center' : 'Add Center'; ?>
                    </button>

                    <?php if ($editCenter): ?>
                    <a href="<?php echo APP_URL; ?>/superadmin/centers.php" class="btn-outline-custom w-100 mt-2 justify-content-center">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Centers List -->
    <div class="col-lg-8">
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-building"></i> All Centers</h5>
                <span class="badge bg-primary"><?php echo $centers->num_rows; ?> Centers</span>
            </div>
            <div class="card-custom-body p-0">
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Center Name</th>
                                <th>Location</th>
                                <th>Admins</th>
                                <th>Income</th>
                                <th>Expense</th>
                                <th>Profit</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $sr = 1; while ($c = $centers->fetch_assoc()): ?>
                            <?php $profit = $c['total_income'] - $c['total_expense']; ?>
                            <tr>
                                <td><?php echo $sr++; ?></td>
                                <td class="fw-600"><?php echo sanitize($c['center_name']); ?></td>
                                <td class="text-muted-custom"><?php echo sanitize($c['location']); ?></td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary"><?php echo $c['admin_count']; ?></span>
                                </td>
                                <td class="text-success-custom fw-600"><?php echo formatCurrency($c['total_income']); ?></td>
                                <td class="text-danger-custom fw-600"><?php echo formatCurrency($c['total_expense']); ?></td>
                                <td class="<?php echo $profit >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                                    <?php echo formatCurrency($profit); ?>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="?edit=<?php echo $c['id']; ?>" class="btn-icon btn-icon-edit" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <a href="<?php echo APP_URL; ?>/superadmin/transactions.php?center=<?php echo $c['id']; ?>" class="btn-icon btn-icon-view" title="View Transactions">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="?delete=<?php echo $c['id']; ?>" class="btn-icon btn-icon-delete" title="Delete" data-delete>
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if ($sr === 1): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-building d-block"></i>
                                        <h5>No Centers Found</h5>
                                        <p>Create your first center using the form.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>