<?php
// File: superadmin/transactions.php
// View all transactions (Super Admin)

require_once __DIR__ . '/../config/init.php';
requireSuperAdmin();

$conn = getDBConnection();

// Filters
$filterCenter = isset($_GET['center']) ? (int)$_GET['center'] : 0;
$filterMonth = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$filterType = isset($_GET['type']) ? sanitize($_GET['type']) : '';

// Build query
$query = "SELECT t.*, c.center_name FROM transactions t JOIN centers c ON t.center_id = c.id WHERE 1=1";
$params = [];
$types = '';

if ($filterCenter > 0) {
    $query .= " AND t.center_id = ?";
    $params[] = $filterCenter;
    $types .= 'i';
}
if ($filterMonth > 0) {
    $query .= " AND t.month = ?";
    $params[] = $filterMonth;
    $types .= 'i';
}
if ($filterYear > 0) {
    $query .= " AND t.year = ?";
    $params[] = $filterYear;
    $types .= 'i';
}
if (!empty($filterType) && in_array($filterType, ['income', 'expense'])) {
    $query .= " AND t.type = ?";
    $params[] = $filterType;
    $types .= 's';
}

$query .= " ORDER BY t.date DESC, t.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$transactions = $stmt->get_result();
$stmt->close();

// Get centers for filter
$centers = $conn->query("SELECT * FROM centers ORDER BY center_name");

// Calculate totals for filtered results
$totalQuery = str_replace("SELECT t.*, c.center_name", "SELECT
    COALESCE(SUM(CASE WHEN t.type='income' THEN t.amount ELSE 0 END), 0) as total_income,
    COALESCE(SUM(CASE WHEN t.type='expense' THEN t.amount ELSE 0 END), 0) as total_expense", $query);
$totalQuery = str_replace("ORDER BY t.date DESC, t.created_at DESC", "", $totalQuery);

$totalStmt = $conn->prepare($totalQuery);
if (!empty($params)) {
    $totalStmt->bind_param($types, ...$params);
}
$totalStmt->execute();
$totals = $totalStmt->get_result()->fetch_assoc();
$totalStmt->close();

$conn->close();

$pageTitle = 'All Transactions';
$currentPage = 'transactions';
include __DIR__ . '/../includes/header.php';
?>

<!-- Filters -->
<div class="card-custom mb-4">
    <div class="card-custom-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-600" style="font-size: 12px;">CENTER</label>
                <select name="center" class="form-control-custom">
                    <option value="0">All Centers</option>
                    <?php $centers->data_seek(0); while ($c = $centers->fetch_assoc()): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $filterCenter == $c['id'] ? 'selected' : ''; ?>>
                        <?php echo sanitize($c['center_name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-600" style="font-size: 12px;">MONTH</label>
                <select name="month" class="form-control-custom">
                    <option value="0">All Months</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $filterMonth == $m ? 'selected' : ''; ?>>
                        <?php echo getMonthName($m); ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-600" style="font-size: 12px;">YEAR</label>
                <select name="year" class="form-control-custom">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $filterYear == $y ? 'selected' : ''; ?>>
                        <?php echo $y; ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-600" style="font-size: 12px;">TYPE</label>
                <select name="type" class="form-control-custom">
                    <option value="">All Types</option>
                    <option value="income" <?php echo $filterType === 'income' ? 'selected' : ''; ?>>Income</option>
                    <option value="expense" <?php echo $filterType === 'expense' ? 'selected' : ''; ?>>Expense</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn-primary-custom">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="<?php echo APP_URL; ?>/superadmin/transactions.php" class="btn-outline-custom ms-2">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Summary -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="stat-card income-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-value"><?php echo formatCurrency($totals['total_income']); ?></div>
                    <div class="stat-card-label">Total Income (Filtered)</div>
                </div>
                <div class="stat-card-icon bg-success"><i class="fas fa-arrow-up"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card expense-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-value"><?php echo formatCurrency($totals['total_expense']); ?></div>
                    <div class="stat-card-label">Total Expense (Filtered)</div>
                </div>
                <div class="stat-card-icon bg-danger"><i class="fas fa-arrow-down"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <?php $profit = $totals['total_income'] - $totals['total_expense']; ?>
        <div class="stat-card profit-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-value <?php echo $profit >= 0 ? 'text-success-custom' : 'text-danger-custom'; ?>">
                        <?php echo formatCurrency($profit); ?>
                    </div>
                    <div class="stat-card-label">Net Profit / Loss</div>
                </div>
                <div class="stat-card-icon <?php echo $profit >= 0 ? 'bg-info' : 'bg-danger'; ?>">
                    <i class="fas fa-calculator"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transactions Table -->
<div class="card-custom">
    <div class="card-custom-header">
        <h5><i class="fas fa-exchange-alt"></i> Transactions (<?php echo $transactions->num_rows; ?> records)</h5>
    </div>
    <div class="card-custom-body p-0">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>SR#</th>
                        <th>Date</th>
                        <th>Center</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $sr = 1; while ($t = $transactions->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $sr++; ?></td>
                        <td><?php echo formatDate($t['date']); ?></td>
                        <td class="fw-600"><?php echo sanitize($t['center_name']); ?></td>
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
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($sr === 1): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="fas fa-inbox d-block"></i>
                                <h5>No Transactions Found</h5>
                                <p>Try adjusting your filters or wait for centers to add transactions.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>