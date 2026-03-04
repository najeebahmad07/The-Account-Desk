<?php
// File: superadmin/reports.php
// View Monthly Reports - FIXED for PDF viewing

require_once __DIR__ . '/../config/init.php';
requireSuperAdmin();

$conn = getDBConnection();

$filterMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Handle PDF download for super admin
if (isset($_GET['view_report'])) {
    $viewCenterId = (int)$_GET['center_id'];
    $viewMonth = (int)$_GET['view_month'];
    $viewYear = (int)$_GET['view_year'];
    $viewCenterName = getCenterName($viewCenterId);

    $totals = getCenterMonthlyTotals($viewCenterId, $viewMonth, $viewYear);

    $txnStmt = $conn->prepare("SELECT * FROM transactions WHERE center_id = ? AND month = ? AND year = ? ORDER BY date ASC");
    $txnStmt->bind_param("iii", $viewCenterId, $viewMonth, $viewYear);
    $txnStmt->execute();
    $viewTxns = $txnStmt->get_result();
    $txnStmt->close();

    // Generate and display HTML report
    $monthName = getMonthName($viewMonth);
    $profit = $totals['income'] - $totals['expense'];
    $profitLabel = $profit >= 0 ? 'Net Profit' : 'Net Loss';
    $generatedDate = date('d M, Y h:i A');

    // Reuse the HTML report function
    require_once __DIR__ . '/../center/monthly_report.php';
    exit;
}

$query = "SELECT c.*,
    mr.id as report_id, mr.total_income, mr.total_expense, mr.net_profit, mr.status as report_status, mr.submitted_at, mr.pdf_path,
    COALESCE(mr.total_income, (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE center_id=c.id AND type='income' AND month=? AND year=?)) as calc_income,
    COALESCE(mr.total_expense, (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE center_id=c.id AND type='expense' AND month=? AND year=?)) as calc_expense
    FROM centers c
    LEFT JOIN monthly_reports mr ON c.id = mr.center_id AND mr.month = ? AND mr.year = ?
    ORDER BY c.center_name";

$stmt = $conn->prepare($query);
$stmt->bind_param("iiiiii", $filterMonth, $filterYear, $filterMonth, $filterYear, $filterMonth, $filterYear);
$stmt->execute();
$reports = $stmt->get_result();
$stmt->close();

// Calculate grand totals
$grandIncome = 0;
$grandExpense = 0;
$reportData = [];
while ($r = $reports->fetch_assoc()) {
    $grandIncome += $r['calc_income'];
    $grandExpense += $r['calc_expense'];
    $reportData[] = $r;
}
$grandProfit = $grandIncome - $grandExpense;

$conn->close();

$pageTitle = 'Monthly Reports';
$currentPage = 'reports';
include __DIR__ . '/../includes/header.php';
?>

<!-- Filter -->
<div class="card-custom mb-4">
    <div class="card-custom-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-600" style="font-size:12px;">MONTH</label>
                <select name="month" class="form-control-custom">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $filterMonth == $m ? 'selected' : ''; ?>>
                        <?php echo getMonthName($m); ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-600" style="font-size:12px;">YEAR</label>
                <select name="year" class="form-control-custom">
                    <?php for ($y = date('Y') + 1; $y >= date('Y') - 5; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $filterYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn-primary-custom"><i class="fas fa-search"></i> View Reports</button>
            </div>
        </form>
    </div>
</div>

<!-- Grand Summary -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="stat-card income-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-value"><?php echo formatCurrency($grandIncome); ?></div>
                    <div class="stat-card-label">Total Income - <?php echo getMonthName($filterMonth); ?></div>
                </div>
                <div class="stat-card-icon bg-success"><i class="fas fa-arrow-up"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card expense-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-value"><?php echo formatCurrency($grandExpense); ?></div>
                    <div class="stat-card-label">Total Expense - <?php echo getMonthName($filterMonth); ?></div>
                </div>
                <div class="stat-card-icon bg-danger"><i class="fas fa-arrow-down"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card profit-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-value <?php echo $grandProfit >= 0 ? 'text-success-custom' : 'text-danger-custom'; ?>">
                        <?php echo formatCurrency($grandProfit); ?>
                    </div>
                    <div class="stat-card-label"><?php echo $grandProfit >= 0 ? 'Net Profit' : 'Net Loss'; ?></div>
                </div>
                <div class="stat-card-icon <?php echo $grandProfit >= 0 ? 'bg-info' : 'bg-danger'; ?>">
                    <i class="fas fa-calculator"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reports Table -->
<div class="card-custom">
    <div class="card-custom-header">
        <h5><i class="fas fa-file-alt"></i> Reports - <?php echo getMonthName($filterMonth) . ' ' . $filterYear; ?></h5>
    </div>
    <div class="card-custom-body p-0">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Center</th>
                        <th>Total Income</th>
                        <th>Total Expense</th>
                        <th>Net Profit/Loss</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $sr = 1; foreach ($reportData as $r): ?>
                    <?php $calcProfit = $r['calc_income'] - $r['calc_expense']; ?>
                    <tr>
                        <td><?php echo $sr++; ?></td>
                        <td class="fw-600"><?php echo sanitize($r['center_name']); ?></td>
                        <td class="text-success-custom fw-600"><?php echo formatCurrency($r['calc_income']); ?></td>
                        <td class="text-danger-custom fw-600"><?php echo formatCurrency($r['calc_expense']); ?></td>
                        <td class="<?php echo $calcProfit >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                            <?php echo formatCurrency($calcProfit); ?>
                        </td>
                        <td>
                            <span class="badge-type <?php echo ($r['report_status'] === 'submitted') ? 'badge-submitted' : 'badge-pending'; ?>">
                                <?php echo $r['report_status'] ? ucfirst($r['report_status']) : 'Not Submitted'; ?>
                            </span>
                        </td>
                        <td class="text-muted-custom" style="font-size:12px;">
                            <?php echo $r['submitted_at'] ? formatDate($r['submitted_at']) : '-'; ?>
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="<?php echo APP_URL; ?>/superadmin/transactions.php?center=<?php echo $r['id']; ?>&month=<?php echo $filterMonth; ?>&year=<?php echo $filterYear; ?>"
                                   class="btn-icon btn-icon-view" title="View Transactions">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($r['pdf_path'] && file_exists(REPORTS_PATH . $r['pdf_path'])): ?>
                                <a href="<?php echo APP_URL; ?>/reports/<?php echo $r['pdf_path']; ?>"
                                   class="btn-icon btn-icon-edit" title="View PDF" target="_blank">
                                    <i class="fas fa-file-pdf"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>