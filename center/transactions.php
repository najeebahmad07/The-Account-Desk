<?php
// File: center/transactions.php
// View/Manage Transactions (Center Admin)

require_once __DIR__ . '/../config/init.php';
requireCenterAdmin();

$conn = getDBConnection();
$centerId = $_SESSION['center_id'];

// Handle Delete
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ? AND center_id = ?");
    $stmt->bind_param("ii", $deleteId, $centerId);
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'Transaction Deleted', "Deleted transaction ID: $deleteId");
        setFlash('success', 'Transaction deleted successfully.');
    }
    $stmt->close();
    redirect(APP_URL . '/center/transactions.php' . (isset($_GET['month']) ? '?month=' . $_GET['month'] . '&year=' . $_GET['year'] : ''));
}

// Handle Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $txnId = (int)$_POST['txn_id'];
    $type = sanitize($_POST['type'] ?? '');
    $category = ($type === 'expense') ? sanitize($_POST['category'] ?? '') : null;
    $amount = floatval($_POST['amount'] ?? 0);
    $date = sanitize($_POST['date'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $month = (int)date('n', strtotime($date));
    $year = (int)date('Y', strtotime($date));

    if ($amount > 0 && !empty($date) && in_array($type, ['income', 'expense'])) {
        $stmt = $conn->prepare("UPDATE transactions SET type=?, category=?, description=?, amount=?, date=?, month=?, year=? WHERE id=? AND center_id=?");
        $stmt->bind_param("sssdsiiis", $type, $category, $description, $amount, $date, $month, $year, $txnId, $centerId);
        $stmt->execute();
        $stmt->close();
        logActivity($_SESSION['user_id'], 'Transaction Updated', "Updated transaction ID: $txnId");
        setFlash('success', 'Transaction updated successfully.');
    } else {
        setFlash('danger', 'Invalid input. Please check your data.');
    }
    redirect(APP_URL . '/center/transactions.php');
}

// Filters
$filterMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Fetch transactions
$stmt = $conn->prepare("SELECT * FROM transactions WHERE center_id = ? AND month = ? AND year = ? ORDER BY date DESC, created_at DESC");
$stmt->bind_param("iii", $centerId, $filterMonth, $filterYear);
$stmt->execute();
$transactions = $stmt->get_result();
$stmt->close();

// Monthly totals
$totals = getCenterMonthlyTotals($centerId, $filterMonth, $filterYear);

$conn->close();

$pageTitle = 'Transactions';
$currentPage = 'transactions';
include __DIR__ . '/../includes/header.php';
?>

<!-- Filter -->
<div class="card-custom mb-4">
    <div class="card-custom-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-600" style="font-size: 12px;">MONTH</label>
                <select name="month" class="form-control-custom">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $filterMonth == $m ? 'selected' : ''; ?>>
                        <?php echo getMonthName($m); ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-600" style="font-size: 12px;">YEAR</label>
                <select name="year" class="form-control-custom">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $filterYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn-primary-custom"><i class="fas fa-filter"></i> Filter</button>
            </div>
            <div class="col-md-3 text-end">
                <a href="<?php echo APP_URL; ?>/center/add_transaction.php" class="btn-success-custom">
                    <i class="fas fa-plus"></i> Add New
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Monthly Summary Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="stat-card income-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-value"><?php echo formatCurrency($totals['income']); ?></div>
                    <div class="stat-card-label">Total Income</div>
                </div>
                <div class="stat-card-icon bg-success"><i class="fas fa-arrow-up"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card expense-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-value"><?php echo formatCurrency($totals['expense']); ?></div>
                    <div class="stat-card-label">Total Expense</div>
                </div>
                <div class="stat-card-icon bg-danger"><i class="fas fa-arrow-down"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card profit-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-value <?php echo $totals['profit'] >= 0 ? 'text-success-custom' : 'text-danger-custom'; ?>">
                        <?php echo formatCurrency($totals['profit']); ?>
                    </div>
                    <div class="stat-card-label">Net <?php echo $totals['profit'] >= 0 ? 'Profit' : 'Loss'; ?></div>
                </div>
                <div class="stat-card-icon <?php echo $totals['profit'] >= 0 ? 'bg-info' : 'bg-danger'; ?>">
                    <i class="fas fa-calculator"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transactions Table -->
<div class="card-custom">
    <div class="card-custom-header">
        <h5><i class="fas fa-list-alt"></i> <?php echo getMonthName($filterMonth) . ' ' . $filterYear; ?> Transactions</h5>
        <span class="badge bg-primary"><?php echo $transactions->num_rows; ?> records</span>
    </div>
    <div class="card-custom-body p-0">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>SR#</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $sr = 1; while ($t = $transactions->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $sr++; ?></td>
                        <td><?php echo formatDate($t['date']); ?></td>
                        <td>
                            <span class="badge-type <?php echo $t['type'] === 'income' ? 'badge-income' : 'badge-expense'; ?>">
                                <?php echo ucfirst($t['type']); ?>
                            </span>
                        </td>
                        <td><?php echo $t['category'] ? sanitize($t['category']) : '-'; ?></td>
                        <td class="text-muted-custom"><?php echo $t['description'] ? sanitize($t['description']) : '-'; ?></td>
                        <td class="fw-700 <?php echo $t['type'] === 'income' ? 'text-success-custom' : 'text-danger-custom'; ?>">
                            <?php echo formatCurrency($t['amount']); ?>
                        </td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-icon btn-icon-edit" title="Edit"
                                        data-bs-toggle="modal" data-bs-target="#editModal"
                                        onclick="fillEditForm(<?php echo htmlspecialchars(json_encode($t)); ?>)">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <a href="?delete=<?php echo $t['id']; ?>&month=<?php echo $filterMonth; ?>&year=<?php echo $filterYear; ?>"
                                   class="btn-icon btn-icon-delete" title="Delete" data-delete>
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
                                <i class="fas fa-inbox d-block"></i>
                                <h5>No Transactions Found</h5>
                                <p>No transactions recorded for this period.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="txn_id" id="edit_txn_id">
                <div class="modal-body">
                    <div class="form-group-custom">
                        <label>Type</label>
                        <select name="type" id="edit_type" class="form-control-custom" required onchange="toggleEditCategory()">
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    <div class="form-group-custom" id="editCategoryGroup">
                        <label>Category</label>
                        <select name="category" id="edit_category" class="form-control-custom">
                            <option value="">-- Select --</option>
                            <?php foreach (EXPENSE_CATEGORIES as $cat): ?>
                            <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-custom">
                        <label>Amount</label>
                        <input type="number" name="amount" id="edit_amount" class="form-control-custom" required min="0.01" step="0.01">
                    </div>
                    <div class="form-group-custom">
                        <label>Date</label>
                        <input type="date" name="date" id="edit_date" class="form-control-custom" required>
                    </div>
                    <div class="form-group-custom">
                        <label>Description</label>
                        <input type="text" name="description" id="edit_description" class="form-control-custom">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-custom"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function fillEditForm(txn) {
    document.getElementById('edit_txn_id').value = txn.id;
    document.getElementById('edit_type').value = txn.type;
    document.getElementById('edit_category').value = txn.category || '';
    document.getElementById('edit_amount').value = txn.amount;
    document.getElementById('edit_date').value = txn.date;
    document.getElementById('edit_description').value = txn.description || '';
    toggleEditCategory();
}

function toggleEditCategory() {
    const type = document.getElementById('edit_type').value;
    const group = document.getElementById('editCategoryGroup');
    group.style.display = type === 'expense' ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>