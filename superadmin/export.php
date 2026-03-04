<?php
// File: superadmin/export.php
// Super Admin Advanced Export Page

require_once __DIR__ . '/../config/init.php';
requireSuperAdmin();

$conn = getDBConnection();

// ============================================
// HANDLE EXPORT REQUEST
// ============================================
if (isset($_GET['export']) && !empty($_GET['export'])) {
    $exportFormat = sanitize($_GET['export']); // excel or pdf
    $exportData = sanitize($_GET['export_data'] ?? 'transactions');
    $exportCenter = (int)($_GET['export_center'] ?? 0);
    $exportMonth = (int)($_GET['export_month'] ?? 0);
    $exportYear = (int)($_GET['export_year'] ?? date('Y'));
    $exportFrom = sanitize($_GET['export_from'] ?? '');
    $exportTo = sanitize($_GET['export_to'] ?? '');

    $headers = [];
    $data = [];
    $totals = null;
    $title = '';
    $subtitle = '';

    switch ($exportData) {

        // ============================================
        // ALL TRANSACTIONS
        // ============================================
        case 'transactions':
        case 'income_only':
        case 'expense_only':
            $title = 'Transaction Report';
            $headers = ['SR#', 'Date', 'Center', 'Type', 'Category', 'Description', 'Amount'];

            $query = "SELECT t.*, c.center_name FROM transactions t JOIN centers c ON t.center_id = c.id WHERE 1=1";
            $params = [];
            $types = '';

            if ($exportData === 'income_only') {
                $query .= " AND t.type = 'income'";
                $title = 'Income Report';
            } elseif ($exportData === 'expense_only') {
                $query .= " AND t.type = 'expense'";
                $title = 'Expense Report';
            }

            if ($exportCenter > 0) {
                $query .= " AND t.center_id = ?";
                $params[] = $exportCenter;
                $types .= 'i';
            }
            if ($exportMonth > 0) {
                $query .= " AND t.month = ?";
                $params[] = $exportMonth;
                $types .= 'i';
            }
            if ($exportYear > 0) {
                $query .= " AND t.year = ?";
                $params[] = $exportYear;
                $types .= 'i';
            }

            $query .= " ORDER BY t.date DESC";
            $stmt = $conn->prepare($query);
            if (!empty($params)) $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            $sr = 1;
            $totalIncome = 0;
            $totalExpense = 0;

            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    $sr++,
                    date('d/m/Y', strtotime($row['date'])),
                    $row['center_name'],
                    ucfirst($row['type']),
                    $row['category'] ?? '-',
                    $row['description'] ?? '-',
                    formatExportAmount($row['amount'])
                ];

                if ($row['type'] === 'income') $totalIncome += $row['amount'];
                else $totalExpense += $row['amount'];
            }
            $stmt->close();

            $totals = ['', '', '', '', '', 'TOTALS:', ''];
            $subtitle = '';
            if ($exportMonth > 0) $subtitle .= getMonthName($exportMonth) . ' ';
            $subtitle .= $exportYear;
            if ($exportCenter > 0) $subtitle .= ' | ' . getCenterName($exportCenter);

            // Add summary rows
            $data[] = ['', '', '', '', '', 'Total Income:', formatExportAmount($totalIncome)];
            $data[] = ['', '', '', '', '', 'Total Expense:', formatExportAmount($totalExpense)];
            $data[] = ['', '', '', '', '', 'Net Profit/Loss:', formatExportAmount($totalIncome - $totalExpense)];
            break;

        // ============================================
        // CENTER-WISE SUMMARY
        // ============================================
        case 'center_summary':
            $title = 'Center-wise Financial Summary';
            $headers = ['SR#', 'Center', 'Location', 'Total Income', 'Total Expense', 'Net Profit/Loss'];

            $query = "SELECT c.center_name, c.location,
                COALESCE(SUM(CASE WHEN t.type='income' THEN t.amount ELSE 0 END), 0) as income,
                COALESCE(SUM(CASE WHEN t.type='expense' THEN t.amount ELSE 0 END), 0) as expense
                FROM centers c LEFT JOIN transactions t ON c.id = t.center_id";

            $conditions = [];
            $params = [];
            $types = '';

            if ($exportMonth > 0) {
                $conditions[] = "t.month = ?";
                $params[] = $exportMonth;
                $types .= 'i';
            }
            if ($exportYear > 0) {
                $conditions[] = "t.year = ?";
                $params[] = $exportYear;
                $types .= 'i';
            }

            if (!empty($conditions)) {
                $query .= " AND " . implode(" AND ", $conditions);
            }
            $query .= " GROUP BY c.id, c.center_name, c.location ORDER BY c.center_name";

            $stmt = $conn->prepare($query);
            if (!empty($params)) $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            $sr = 1;
            $grandIncome = 0;
            $grandExpense = 0;

            while ($row = $result->fetch_assoc()) {
                $profit = $row['income'] - $row['expense'];
                $data[] = [
                    $sr++,
                    $row['center_name'],
                    $row['location'],
                    formatExportAmount($row['income']),
                    formatExportAmount($row['expense']),
                    formatExportAmount($profit)
                ];
                $grandIncome += $row['income'];
                $grandExpense += $row['expense'];
            }
            $stmt->close();

            $totals = ['', 'GRAND TOTAL', '', formatExportAmount($grandIncome), formatExportAmount($grandExpense), formatExportAmount($grandIncome - $grandExpense)];
            $subtitle = ($exportMonth > 0 ? getMonthName($exportMonth) . ' ' : 'All Months ') . $exportYear;
            break;

        // ============================================
        // MONTHLY SUMMARY
        // ============================================
        case 'monthly_summary':
            $title = 'Monthly Financial Summary - ' . $exportYear;
            $headers = ['Month', 'Total Income', 'Total Expense', 'Net Profit/Loss', 'Margin %'];

            $yearlyIncome = 0;
            $yearlyExpense = 0;

            for ($m = 1; $m <= 12; $m++) {
                $stmt = $conn->prepare("SELECT
                    COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END), 0) as income,
                    COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END), 0) as expense
                    FROM transactions WHERE month = ? AND year = ?");
                $stmt->bind_param("ii", $m, $exportYear);
                $stmt->execute();
                $mData = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $profit = $mData['income'] - $mData['expense'];
                $margin = $mData['income'] > 0 ? round(($profit / $mData['income']) * 100, 1) : 0;

                $data[] = [
                    getMonthName($m),
                    formatExportAmount($mData['income']),
                    formatExportAmount($mData['expense']),
                    formatExportAmount($profit),
                    $margin . '%'
                ];

                $yearlyIncome += $mData['income'];
                $yearlyExpense += $mData['expense'];
            }

            $yearlyMargin = $yearlyIncome > 0 ? round((($yearlyIncome - $yearlyExpense) / $yearlyIncome) * 100, 1) : 0;
            $totals = ['YEARLY TOTAL', formatExportAmount($yearlyIncome), formatExportAmount($yearlyExpense), formatExportAmount($yearlyIncome - $yearlyExpense), $yearlyMargin . '%'];
            $subtitle = 'Year: ' . $exportYear;
            break;

        // ============================================
        // CATEGORY BREAKDOWN
        // ============================================
        case 'category_breakdown':
            $title = 'Expense Category Breakdown';
            $headers = ['SR#', 'Category', 'Total Amount', 'Transaction Count', 'Percentage'];

            $query = "SELECT category, SUM(amount) as total, COUNT(*) as cnt
                FROM transactions WHERE type='expense' AND category IS NOT NULL AND category != ''";
            $params = [];
            $types = '';

            if ($exportCenter > 0) {
                $query .= " AND center_id = ?";
                $params[] = $exportCenter;
                $types .= 'i';
            }
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

            $query .= " GROUP BY category ORDER BY total DESC";
            $stmt = $conn->prepare($query);
            if (!empty($params)) $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            // Calculate grand total first
            $grandTotal = 0;
            $catRows = [];
            while ($row = $result->fetch_assoc()) {
                $catRows[] = $row;
                $grandTotal += $row['total'];
            }

            $sr = 1;
            foreach ($catRows as $row) {
                $pct = $grandTotal > 0 ? round(($row['total'] / $grandTotal) * 100, 1) : 0;
                $data[] = [
                    $sr++,
                    $row['category'],
                    formatExportAmount($row['total']),
                    $row['cnt'],
                    $pct . '%'
                ];
            }
            $stmt->close();

            $totals = ['', 'TOTAL', formatExportAmount($grandTotal), '', '100%'];
            $subtitle = ($exportMonth > 0 ? getMonthName($exportMonth) . ' ' : '') . $exportYear;
            break;

        // ============================================
        // PROFIT & LOSS STATEMENT
        // ============================================
        case 'profit_loss':
            $title = 'Profit & Loss Statement';
            $headers = ['Center', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Total'];

            $centers = $conn->query("SELECT id, center_name FROM centers ORDER BY center_name");

            while ($c = $centers->fetch_assoc()) {
                $row = [$c['center_name']];
                $yearTotal = 0;

                for ($m = 1; $m <= 12; $m++) {
                    $t = getCenterMonthlyTotals($c['id'], $m, $exportYear);
                    $profit = $t['profit'];
                    $yearTotal += $profit;
                    $row[] = formatExportAmount($profit);
                }
                $row[] = formatExportAmount($yearTotal);
                $data[] = $row;
            }

            $subtitle = 'Year: ' . $exportYear;
            break;

        // ============================================
        // USER LIST
        // ============================================
        case 'user_list':
            $title = 'Center Admin Users List';
            $headers = ['SR#', 'Name', 'Username', 'Email', 'Center', 'Status', 'Created Date'];

            $result = $conn->query("SELECT u.*, c.center_name FROM users u LEFT JOIN centers c ON u.center_id = c.id WHERE u.role = 'center_admin' ORDER BY u.name");
            $sr = 1;
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    $sr++,
                    $row['name'],
                    $row['username'],
                    $row['email'] ?? '-',
                    $row['center_name'] ?? 'N/A',
                    ucfirst($row['status']),
                    date('d/m/Y', strtotime($row['created_at']))
                ];
            }
            $subtitle = 'Total: ' . ($sr - 1) . ' users';
            break;

        // ============================================
        // CENTER LIST
        // ============================================
        case 'center_list':
            $title = 'Centers List with Financial Summary';
            $headers = ['SR#', 'Center Name', 'Location', 'Admins', 'Total Income', 'Total Expense', 'Net Profit'];

            $result = $conn->query("SELECT c.*,
                (SELECT COUNT(*) FROM users WHERE center_id = c.id AND role = 'center_admin') as admin_count,
                COALESCE((SELECT SUM(amount) FROM transactions WHERE center_id = c.id AND type='income'), 0) as total_income,
                COALESCE((SELECT SUM(amount) FROM transactions WHERE center_id = c.id AND type='expense'), 0) as total_expense
                FROM centers c ORDER BY c.center_name");

            $sr = 1;
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    $sr++,
                    $row['center_name'],
                    $row['location'],
                    $row['admin_count'],
                    formatExportAmount($row['total_income']),
                    formatExportAmount($row['total_expense']),
                    formatExportAmount($row['total_income'] - $row['total_expense'])
                ];
            }
            $subtitle = 'Total: ' . ($sr - 1) . ' centers';
            break;

        // ============================================
        // PENDING REPORTS
        // ============================================
        case 'pending_reports':
            $title = 'Pending Monthly Reports';
            $headers = ['SR#', 'Center', 'Month', 'Year', 'Income', 'Expense', 'Status'];

            $checkMonth = $exportMonth > 0 ? $exportMonth : ((int)date('n') - 1);
            $checkYear = $exportYear;
            if ($checkMonth < 1) { $checkMonth = 12; $checkYear--; }

            $result = $conn->query("SELECT c.* FROM centers c WHERE c.id NOT IN (
                SELECT mr.center_id FROM monthly_reports mr WHERE mr.month = $checkMonth AND mr.year = $checkYear AND mr.status = 'submitted'
            ) ORDER BY c.center_name");

            $sr = 1;
            while ($row = $result->fetch_assoc()) {
                $t = getCenterMonthlyTotals($row['id'], $checkMonth, $checkYear);
                $data[] = [
                    $sr++,
                    $row['center_name'],
                    getMonthName($checkMonth),
                    $checkYear,
                    formatExportAmount($t['income']),
                    formatExportAmount($t['expense']),
                    'PENDING'
                ];
            }
            $subtitle = getMonthName($checkMonth) . ' ' . $checkYear;
            break;
    }

    $conn->close();

    // Execute export
    if ($exportFormat === 'excel') {
        exportCSV(APP_NAME . '_' . $exportData . '_' . date('Y-m-d'), $headers, $data);
    } else {
        $orientation = in_array($exportData, ['profit_loss', 'monthly_summary']) ? 'L' : 'P';
        exportPDF($title, $subtitle, $headers, $data, $totals, $orientation);
    }
    exit;
}

$conn->close();

$pageTitle = 'Export Data';
$currentPage = 'export';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/export_sidebar.php';
?>

<!-- Export Page Content -->
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-download"></i> Export Center</h5>
            </div>
            <div class="card-custom-body">
                <p class="text-muted-custom mb-4">Select the type of data you want to export and choose your preferred format.</p>

                <div class="row g-3">
                    <?php
                    $exportOptions = [
                        ['icon' => 'fas fa-exchange-alt', 'color' => '#2596be', 'title' => 'All Transactions', 'desc' => 'Complete transaction history with all details', 'value' => 'transactions'],
                        ['icon' => 'fas fa-arrow-up', 'color' => '#28a745', 'title' => 'Income Only', 'desc' => 'All income entries filtered', 'value' => 'income_only'],
                        ['icon' => 'fas fa-arrow-down', 'color' => '#dc3545', 'title' => 'Expense Only', 'desc' => 'All expense entries with categories', 'value' => 'expense_only'],
                        ['icon' => 'fas fa-building', 'color' => '#17a2b8', 'title' => 'Center Summary', 'desc' => 'Financial summary per center', 'value' => 'center_summary'],
                        ['icon' => 'fas fa-calendar-alt', 'color' => '#6f42c1', 'title' => 'Monthly Summary', 'desc' => 'Month-by-month breakdown', 'value' => 'monthly_summary'],
                        ['icon' => 'fas fa-tags', 'color' => '#fd7e14', 'title' => 'Category Breakdown', 'desc' => 'Expense categorization analysis', 'value' => 'category_breakdown'],
                        ['icon' => 'fas fa-chart-line', 'color' => '#20c997', 'title' => 'Profit & Loss', 'desc' => 'P&L statement across all centers', 'value' => 'profit_loss'],
                        ['icon' => 'fas fa-users', 'color' => '#e83e8c', 'title' => 'User List', 'desc' => 'All center admin accounts', 'value' => 'user_list'],
                        ['icon' => 'fas fa-map-marker-alt', 'color' => '#1e3a5f', 'title' => 'Center List', 'desc' => 'All centers with financial data', 'value' => 'center_list'],
                        ['icon' => 'fas fa-clock', 'color' => '#ffc107', 'title' => 'Pending Reports', 'desc' => 'Centers with pending submissions', 'value' => 'pending_reports'],
                    ];

                    foreach ($exportOptions as $opt):
                    ?>
                    <div class="col-md-6">
                        <div class="card-custom" style="cursor:pointer; transition: all 0.2s;"
                             onclick="quickExport('<?php echo $opt['value']; ?>')"
                             onmouseover="this.style.borderColor='<?php echo $opt['color']; ?>'"
                             onmouseout="this.style.borderColor='var(--border-light)'">
                            <div class="card-custom-body" style="padding: 16px;">
                                <div class="d-flex align-items-center gap-3">
                                    <div style="width:44px;height:44px;border-radius:10px;background:<?php echo $opt['color']; ?>15;color:<?php echo $opt['color']; ?>;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">
                                        <i class="<?php echo $opt['icon']; ?>"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-700 mb-0" style="font-size:14px;"><?php echo $opt['title']; ?></h6>
                                        <small class="text-muted-custom"><?php echo $opt['desc']; ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Quick Export -->
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-bolt"></i> Quick Export</h5>
            </div>
            <div class="card-custom-body">
                <form method="GET" id="quickExportForm">
                    <input type="hidden" name="export" value="" id="quickExportFormat">
                    <input type="hidden" name="export_data" value="" id="quickExportData">

                    <div class="form-group-custom">
                        <label>Data Type</label>
                        <select name="export_data_display" class="form-control-custom" id="quickDataType" onchange="document.getElementById('quickExportData').value=this.value">
                            <option value="transactions">All Transactions</option>
                            <option value="income_only">Income Only</option>
                            <option value="expense_only">Expense Only</option>
                            <option value="center_summary">Center Summary</option>
                            <option value="monthly_summary">Monthly Summary</option>
                            <option value="category_breakdown">Category Breakdown</option>
                            <option value="profit_loss">Profit & Loss</option>
                            <option value="user_list">User List</option>
                            <option value="center_list">Center List</option>
                        </select>
                    </div>

                    <div class="form-group-custom">
                        <label>Center</label>
                        <select name="export_center" class="form-control-custom">
                            <option value="0">All Centers</option>
                            <?php
                            $qConn = getDBConnection();
                            $qCenters = $qConn->query("SELECT id, center_name FROM centers ORDER BY center_name");
                            while ($qc = $qCenters->fetch_assoc()):
                            ?>
                            <option value="<?php echo $qc['id']; ?>"><?php echo sanitize($qc['center_name']); ?></option>
                            <?php endwhile; $qConn->close(); ?>
                        </select>
                    </div>

                    <div class="form-group-custom">
                        <label>Month</label>
                        <select name="export_month" class="form-control-custom">
                            <option value="0">All Months</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo (int)date('n') === $m ? 'selected' : ''; ?>>
                                <?php echo getMonthName($m); ?>
                            </option>
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
                        <button type="button" class="btn-success-custom justify-content-center" onclick="submitQuickExport('excel')">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                        <button type="button" class="btn-danger-custom justify-content-center" onclick="submitQuickExport('pdf')">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function quickExport(dataType) {
    document.getElementById('quickDataType').value = dataType;
    document.getElementById('quickExportData').value = dataType;
    // Scroll to quick export form
    document.querySelector('.col-lg-4').scrollIntoView({ behavior: 'smooth' });
}

function submitQuickExport(format) {
    var form = document.getElementById('quickExportForm');
    document.getElementById('quickExportFormat').value = format;
    document.getElementById('quickExportData').value = document.getElementById('quickDataType').value;
    form.submit();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>