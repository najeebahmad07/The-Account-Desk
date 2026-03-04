<?php
// File: center/dashboard.php
// Center Admin Dashboard

require_once __DIR__ . '/../config/init.php';
requireCenterAdmin();

$conn = getDBConnection();
$centerId = $_SESSION['center_id'];
$currentMonth = (int)date('n');
$currentYear = (int)date('Y');

// Current month totals
$totals = getCenterMonthlyTotals($centerId, $currentMonth, $currentYear);

// Monthly data for chart (current year)
$monthlyData = [];
for ($m = 1; $m <= 12; $m++) {
    $mt = getCenterMonthlyTotals($centerId, $m, $currentYear);
    $monthlyData[] = [
        'month' => getMonthName($m),
        'income' => (float)$mt['income'],
        'expense' => (float)$mt['expense']
    ];
}

// Recent transactions
$stmt = $conn->prepare("SELECT * FROM transactions WHERE center_id = ? ORDER BY date DESC, created_at DESC LIMIT 10");
$stmt->bind_param("i", $centerId);
$stmt->execute();
$recentTxns = $stmt->get_result();
$stmt->close();

// Report status for current month
$stmt = $conn->prepare("SELECT * FROM monthly_reports WHERE center_id = ? AND month = ? AND year = ?");
$stmt->bind_param("iii", $centerId, $currentMonth, $currentYear);
$stmt->execute();
$currentReport = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conn->close();

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';


// File: center/dashboard.php
// Find the Chart.js code section and replace with this extraJS:

$extraJS = '<script>
const monthlyData = ' . json_encode($monthlyData) . ';
const isDark = document.body.classList.contains("dark-mode");

function formatINR(value) {
    var val = Math.abs(value);
    if (val >= 10000000) return "₹" + (val / 10000000).toFixed(1) + " Cr";
    if (val >= 100000) return "₹" + (val / 100000).toFixed(1) + " L";
    if (val >= 1000) return "₹" + (val / 1000).toFixed(1) + " K";
    return "₹" + val.toLocaleString("en-IN");
}

new Chart(document.getElementById("incomeExpenseChart"), {
    type: "bar",
    data: {
        labels: monthlyData.map(d => d.month.substr(0,3)),
        datasets: [
            { label: "Income", data: monthlyData.map(d => d.income), backgroundColor: "rgba(40,167,69,0.7)", borderRadius: 4 },
            { label: "Expense", data: monthlyData.map(d => d.expense), backgroundColor: "rgba(220,53,69,0.7)", borderRadius: 4 }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: isDark ? "#f1f1f1" : "#333", usePointStyle: true } },
            tooltip: {
                callbacks: {
                    label: function(ctx) { return ctx.dataset.label + ": ₹" + ctx.parsed.y.toLocaleString("en-IN"); }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: v => formatINR(v), color: isDark ? "#9a9fbf" : "#6c757d" },
                grid: { color: isDark ? "rgba(255,255,255,0.05)" : "rgba(0,0,0,0.05)" }
            },
            x: { ticks: { color: isDark ? "#9a9fbf" : "#6c757d" }, grid: { display: false } }
        }
    }
});
</script>';


include __DIR__ . '/../includes/header.php';
?>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="stat-card income-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-value"><?php echo formatCurrency($totals['income']); ?></div>
                    <div class="stat-card-label">Income (<?php echo getMonthName($currentMonth); ?>)</div>
                </div>
                <div class="stat-card-icon bg-success"><i class="fas fa-arrow-up"></i></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="stat-card expense-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-value"><?php echo formatCurrency($totals['expense']); ?></div>
                    <div class="stat-card-label">Expense (<?php echo getMonthName($currentMonth); ?>)</div>
                </div>
                <div class="stat-card-icon bg-danger"><i class="fas fa-arrow-down"></i></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="stat-card profit-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-value <?php echo $totals['profit'] >= 0 ? 'text-success-custom' : 'text-danger-custom'; ?>">
                        <?php echo formatCurrency($totals['profit']); ?>
                    </div>
                    <div class="stat-card-label"><?php echo $totals['profit'] >= 0 ? 'Profit' : 'Loss'; ?> (<?php echo getMonthName($currentMonth); ?>)</div>
                </div>
                <div class="stat-card-icon <?php echo $totals['profit'] >= 0 ? 'bg-info' : 'bg-danger'; ?>">
                    <i class="fas fa-<?php echo $totals['profit'] >= 0 ? 'chart-line' : 'chart-line'; ?>"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Status Alert -->
<?php if (!$currentReport || $currentReport['status'] !== 'submitted'): ?>
<div class="alert-custom alert-warning">
    <i class="fas fa-exclamation-triangle"></i>
    Monthly report for <?php echo getMonthName($currentMonth) . ' ' . $currentYear; ?> has not been submitted yet.
    <a href="<?php echo APP_URL; ?>/center/monthly_report.php" class="ms-2 fw-600">Submit Now →</a>
</div>
<?php endif; ?>

<!-- Chart and Summary -->
<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-chart-bar"></i> Income vs Expense - <?php echo $currentYear; ?></h5>
            </div>
            <div class="card-custom-body">
                <div class="chart-container">
                    <canvas id="incomeExpenseChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="summary-box">
            <h4><i class="fas fa-calculator me-2"></i>Monthly Summary</h4>
            <div class="summary-item">
                <span>Total Income</span>
                <span><?php echo formatCurrency($totals['income']); ?></span>
            </div>
            <div class="summary-item">
                <span>Total Expense</span>
                <span><?php echo formatCurrency($totals['expense']); ?></span>
            </div>
            <div class="summary-item">
                <span><?php echo $totals['profit'] >= 0 ? '✅ Net Profit' : '❌ Net Loss'; ?></span>
                <span><?php echo formatCurrency(abs($totals['profit'])); ?></span>
            </div>
        </div>

        <div class="d-grid gap-2">
            <a href="<?php echo APP_URL; ?>/center/add_transaction.php" class="btn-success-custom justify-content-center">
                <i class="fas fa-plus"></i> Add Transaction
            </a>
            <a href="<?php echo APP_URL; ?>/center/monthly_report.php" class="btn-primary-custom justify-content-center">
                <i class="fas fa-file-alt"></i> Monthly Report
            </a>
        </div>
    </div>
</div>

<!-- Recent Transactions -->
<div class="card-custom">
    <div class="card-custom-header">
        <h5><i class="fas fa-history"></i> Recent Transactions</h5>
        <a href="<?php echo APP_URL; ?>/center/transactions.php" class="btn-outline-custom btn-sm-custom">View All</a>
    </div>
    <div class="card-custom-body p-0">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentTxns->num_rows > 0): ?>
                    <?php while ($t = $recentTxns->fetch_assoc()): ?>
                    <tr>
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
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr><td colspan="5" class="text-center py-4 text-muted-custom">No transactions yet this month</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>