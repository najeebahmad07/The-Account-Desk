<?php
// File: center/export.php
// Center Admin Export Page

require_once __DIR__ . '/../config/init.php';
requireCenterAdmin();

$conn = getDBConnection();
$centerId = $_SESSION['center_id'];
$centerName = getCenterName($centerId);

// HANDLE EXPORT
if (isset($_GET['export']) && !empty($_GET['export'])) {
    $exportFormat = sanitize($_GET['export']);
    $exportMonth = (int)($_GET['export_month'] ?? 0);
    $exportYear = (int)($_GET['export_year'] ?? date('Y'));
    $exportData = sanitize($_GET['export_data'] ?? 'transactions');

    $headers = [];
    $data = [];
    $totals = null;
    $title = '';
    $subtitle = $centerName;

    switch ($exportData) {
        case 'transactions':
            $title = 'Transaction Report - ' . $centerName;
            $headers = ['SR#', 'Date', 'Type', 'Category', 'Description', 'Amount'];

            $query = "SELECT * FROM transactions WHERE center_id = ?";
            $params = [$centerId];
            $types = 'i';

            if ($exportMonth > 0) {
                $query .= " AND month = ?";
                $params[] = $exportMonth;
                $types .= 'i';
            }
            if ($exportYear > 0) {
                $query .= " AND year = ?";
                $params[] = $exportYear;
                $types .= 'i';
            }

            $query .= " ORDER BY date DESC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            $sr = 1;
            $totalIncome = 0;
            $totalExpense = 0;

            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    $sr++,
                    date('d/m/Y', strtotime($row['date'])),
                    ucfirst($row['type']),
                    $row['category'] ?? '-',
                    $row['description'] ?? '-',
                    formatExportAmount($row['amount'])
                ];
                if ($row['type'] === 'income') $totalIncome += $row['amount'];
                else $totalExpense += $row['amount'];
            }
            $stmt->close();

            $data[] = ['', '', '', '', 'Total Income:', formatExportAmount($totalIncome)];
            $data[] = ['', '', '', '', 'Total Expense:', formatExportAmount($totalExpense)];
            $data[] = ['', '', '', '', 'Net Profit/Loss:', formatExportAmount($totalIncome - $totalExpense)];

            $subtitle .= ' | ' . ($exportMonth > 0 ? getMonthName($exportMonth) . ' ' : '') . $exportYear;
            break;

        case 'monthly_summary':
            $title = 'Monthly Summary - ' . $centerName;
            $headers = ['Month', 'Income', 'Expense', 'Profit/Loss'];

            $yearlyIncome = 0;
            $yearlyExpense = 0;

            for ($m = 1; $m <= 12; $m++) {
                $t = getCenterMonthlyTotals($centerId, $m, $exportYear);
                $data[] = [
                    getMonthName($m),
                    formatExportAmount($t['income']),
                    formatExportAmount($t['expense']),
                    formatExportAmount($t['profit'])
                ];
                $yearlyIncome += $t['income'];
                $yearlyExpense += $t['expense'];
            }

            $totals = ['TOTAL', formatExportAmount($yearlyIncome), formatExportAmount($yearlyExpense), formatExportAmount($yearlyIncome - $yearlyExpense)];
            $subtitle .= ' | Year ' . $exportYear;
            break;

        case 'category_breakdown':
            $title = 'Expense Categories - ' . $centerName;
            $headers = ['SR#', 'Category', 'Amount', 'Count', '%'];

            $query = "SELECT category, SUM(amount) as total, COUNT(*) as cnt FROM transactions
                WHERE center_id = ? AND type='expense' AND category IS NOT NULL";
            $params = [$centerId];
            $types = 'i';

            if ($exportYear > 0) {
                $query .= " AND year = ?";
                $params[] = $exportYear;
                $types .= 'i';
            }

            $query .= " GROUP BY category ORDER BY total DESC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            $catRows = [];
            $grandTotal = 0;
            while ($row = $result->fetch_assoc()) {
                $catRows[] = $row;
                $grandTotal += $row['total'];
            }
            $stmt->close();

            $sr = 1;
            foreach ($catRows as $row) {
                $pct = $grandTotal > 0 ? round(($row['total'] / $grandTotal) * 100, 1) : 0;
                $data[] = [$sr++, $row['category'], formatExportAmount($row['total']), $row['cnt'], $pct . '%'];
            }

            $totals = ['', 'TOTAL', formatExportAmount($grandTotal), '', '100%'];
            break;
    }

    $conn->close();

    if ($exportFormat === 'excel') {
        exportCSV(APP_NAME . '_' . $centerName . '_' . date('Y-m-d'), $headers, $data);
    } else {
        exportPDF($title, $subtitle, $headers, $data, $totals);
    }
    exit;
}

$conn->close();

$pageTitle = 'Export Data';
$currentPage = 'export';
include __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-download"></i> Export Your Data</h5>
            </div>
            <div class="card-custom-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card-custom" style="cursor:pointer;" onclick="selectExport('transactions')">
                            <div class="card-custom-body text-center" style="padding:24px;">
                                <div style="width:56px;height:56px;border-radius:12px;background:rgba(37,150,190,0.1);color:#2596be;display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 12px;">
                                    <i class="fas fa-list-alt"></i>
                                </div>
                                <h6 class="fw-700">Transactions</h6>
                                <small class="text-muted-custom">All income & expense entries</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-custom" style="cursor:pointer;" onclick="selectExport('monthly_summary')">
                            <div class="card-custom-body text-center" style="padding:24px;">
                                <div style="width:56px;height:56px;border-radius:12px;background:rgba(40,167,69,0.1);color:#28a745;display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 12px;">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <h6 class="fw-700">Monthly Summary</h6>
                                <small class="text-muted-custom">Month-wise breakdown</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-custom" style="cursor:pointer;" onclick="selectExport('category_breakdown')">
                            <div class="card-custom-body text-center" style="padding:24px;">
                                <div style="width:56px;height:56px;border-radius:12px;background:rgba(220,53,69,0.1);color:#dc3545;display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 12px;">
                                    <i class="fas fa-tags"></i>
                                </div>
                                <h6 class="fw-700">Categories</h6>
                                <small class="text-muted-custom">Expense breakdown</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-bolt"></i> Export</h5>
            </div>
            <div class="card-custom-body">
                <form method="GET" id="exportForm">
                    <input type="hidden" name="export" value="" id="exportFormat">

                    <div class="form-group-custom">
                        <label>Data Type</label>
                        <select name="export_data" class="form-control-custom" id="exportDataSelect">
                            <option value="transactions">Transactions</option>
                            <option value="monthly_summary">Monthly Summary</option>
                            <option value="category_breakdown">Category Breakdown</option>
                        </select>
                    </div>

                    <div class="form-group-custom">
                        <label>Month</label>
                        <select name="export_month" class="form-control-custom">
                            <option value="0">All Months</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo (int)date('n') === $m ? 'selected' : ''; ?>><?php echo getMonthName($m); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group-custom">
                        <label>Year</label>
                        <select name="export_year" class="form-control-custom">
                            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="button" class="btn-success-custom justify-content-center" onclick="doExport('excel')">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                        <button type="button" class="btn-danger-custom justify-content-center" onclick="doExport('pdf')">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function selectExport(type) {
    document.getElementById('exportDataSelect').value = type;
}
function doExport(format) {
    document.getElementById('exportFormat').value = format;
    document.getElementById('exportForm').submit();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>