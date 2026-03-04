<?php
// File: center/add_transaction.php
// Add Income/Expense Transaction

require_once __DIR__ . '/../config/init.php';
requireCenterAdmin();

$conn = getDBConnection();
$centerId = $_SESSION['center_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = sanitize($_POST['type'] ?? '');
    $category = ($type === 'expense') ? sanitize($_POST['category'] ?? '') : null;
    $amount = floatval($_POST['amount'] ?? 0);
    $date = sanitize($_POST['date'] ?? '');
    $description = sanitize($_POST['description'] ?? '');

    // Validation
    $errors = [];
    if (!in_array($type, ['income', 'expense'])) $errors[] = 'Invalid transaction type.';
    if ($amount <= 0) $errors[] = 'Amount must be greater than zero.';
    if (empty($date)) $errors[] = 'Date is required.';
    if ($type === 'expense' && empty($category)) $errors[] = 'Category is required for expenses.';

    if (empty($errors)) {
        $month = (int)date('n', strtotime($date));
        $year = (int)date('Y', strtotime($date));

        $stmt = $conn->prepare("INSERT INTO transactions (center_id, type, category, description, amount, date, month, year, created_by)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssdsiis", $centerId, $type, $category, $description, $amount, $date, $month, $year, $_SESSION['user_id']);

        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'Transaction Added', "$type of $amount on $date");
            setFlash('success', ucfirst($type) . ' of ' . formatCurrency($amount) . ' added successfully.');
            redirect(APP_URL . '/center/transactions.php');
        } else {
            setFlash('danger', 'Failed to add transaction.');
        }
        $stmt->close();
    } else {
        setFlash('danger', implode(' ', $errors));
    }
}

$conn->close();

$pageTitle = 'Add Transaction';
$currentPage = 'add_transaction';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-plus-circle"></i> Add New Transaction</h5>
            </div>
            <div class="card-custom-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group-custom">
                                <label>Transaction Type</label>
                                <select name="type" id="type" class="form-control-custom" required onchange="toggleCategory()">
                                    <option value="">-- Select Type --</option>
                                    <option value="income">Income</option>
                                    <option value="expense">Expense</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6" id="categoryGroup" style="display: none;">
                            <div class="form-group-custom">
                                <label>Expense Category</label>
                                <select name="category" class="form-control-custom">
                                    <option value="">-- Select Category --</option>
                                    <?php foreach (EXPENSE_CATEGORIES as $cat): ?>
                                    <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group-custom">
                                <label>Amount (₹)</label>
                                <input type="number" name="amount" class="form-control-custom"
                                       placeholder="0.00" required min="0.01" step="0.01">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group-custom">
                                <label>Date</label>
                                <input type="date" name="date" class="form-control-custom"
                                       required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group-custom">
                                <label>Description (Optional)</label>
                                <input type="text" name="description" class="form-control-custom"
                                       placeholder="Brief description of the transaction">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mt-3">
                        <button type="submit" class="btn-success-custom">
                            <i class="fas fa-check"></i> Save Transaction
                        </button>
                        <a href="<?php echo APP_URL; ?>/center/transactions.php" class="btn-outline-custom">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>