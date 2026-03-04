<?php
// File: superadmin/dashboard.php
// Super Admin Dashboard - UPDATED WITH INR

require_once __DIR__ . '/../config/init.php';
requireSuperAdmin();

$conn = getDBConnection();

// Total Centers
$totalCenters = $conn->query("SELECT COUNT(*) as count FROM centers")->fetch_assoc()['count'];

// Total Income
$totalIncome = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'income'")->fetch_assoc()['total'];

// Total Expense
$totalExpense = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'expense'")->fetch_assoc()['total'];

// Net Profit
$netProfit = $totalIncome - $totalExpense;

// Current month/year
$currentMonth = (int)date('n');
$currentYear = (int)date('Y');

// Pending Reports
$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

$pendingReports = $conn->query("SELECT COUNT(*) as count FROM centers c
    WHERE c.id NOT IN (
        SELECT mr.center_id FROM monthly_reports mr
        WHERE mr.month = $prevMonth AND mr.year = $prevYear AND mr.status = 'submitted'
    )")->fetch_assoc()['count'];

// Active/Blocked users
$activeUsers = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='center_admin' AND status='active'")->fetch_assoc()['c'];
$blockedUsers = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='center_admin' AND status='blocked'")->fetch_assoc()['c'];

// Monthly data for chart
$monthlyData = [];
for ($m = 1; $m <= 12; $m++) {
    $incStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE type='income' AND month=? AND year=?");
    $incStmt->bind_param("ii", $m, $currentYear);
    $incStmt->execute();
    $incomeVal = $incStmt->get_result()->fetch_assoc()['total'];
    $incStmt->close();

    $expStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE type='expense' AND month=? AND year=?");
    $expStmt->bind_param("ii", $m, $currentYear);
    $expStmt->execute();
    $expenseVal = $expStmt->get_result()->fetch_assoc()['total'];
    $expStmt->close();

    $monthlyData[] = [
        'month' => getMonthName($m),
        'income' => (float)$incomeVal,
        'expense' => (float)$expenseVal
    ];
}

// Recent transactions
$recentTransactions = $conn->query("SELECT t.*, c.center_name FROM transactions t
    JOIN centers c ON t.center_id = c.id ORDER BY t.created_at DESC LIMIT 10");

// Center-wise totals
$centerTotals = $conn->query("SELECT c.id, c.center_name, c.location,
    COALESCE((SELECT SUM(amount) FROM transactions WHERE center_id=c.id AND type='income'), 0) as total_income,
    COALESCE((SELECT SUM(amount) FROM transactions WHERE center_id=c.id AND type='expense'), 0) as total_expense
    FROM centers c ORDER BY c.center_name");

$conn->close();

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

// CHART JS WITH ₹ SYMBOL
$extraJS = '<script>
const monthlyChartCtx = document.getElementById("monthlyChart").getContext("2d");
const monthlyData = ' . json_encode($monthlyData) . ';

// Indian Number Format Function for Charts
function formatINR(value) {
    var val = Math.abs(value);
    if (val >= 10000000) {
        return "₹" + (val / 10000000).toFixed(1) + " Cr";
    } else if (val >= 100000) {
        return "₹" + (val / 100000).toFixed(1) + " L";
    } else if (val >= 1000) {
        return "₹" + (val / 1000).toFixed(1) + " K";
    }
    return "₹" + val.toLocaleString("en-IN");
}

const monthlyChart = new Chart(monthlyChartCtx, {
    type: "bar",
    data: {
        labels: monthlyData.map(d => d.month.substr(0, 3)),
        datasets: [
            {
                label: "Income",
                data: monthlyData.map(d => d.income),
                backgroundColor: "rgba(40, 167, 69, 0.7)",
                borderColor: "#28a745",
                borderWidth: 1,
                borderRadius: 4
            },
            {
                label: "Expense",
                data: monthlyData.map(d => d.expense),
                backgroundColor: "rgba(220, 53, 69, 0.7)",
                borderColor: "#dc3545",
                borderWidth: 1,
                borderRadius: 4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: "top",
                labels: {
                    usePointStyle: true,
                    padding: 20,
                    color: document.body.classList.contains("dark-mode") ? "#f1f1f1" : "#333"
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ": ₹" + context.parsed.y.toLocaleString("en-IN");
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) { return formatINR(value); },
                    color: document.body.classList.contains("dark-mode") ? "#9a9fbf" : "#6c757d"
                },
                grid: { color: document.body.classList.contains("dark-mode") ? "rgba(255,255,255,0.05)" : "rgba(0,0,0,0.05)" }
            },
            x: {
                ticks: { color: document.body.classList.contains("dark-mode") ? "#9a9fbf" : "#6c757d" },
                grid: { display: false }
            }
        }
    }
});

// Profit Doughnut Chart
const profitCtx = document.getElementById("profitChart").getContext("2d");
new Chart(profitCtx, {
    type: "doughnut",
    data: {
        labels: ["Income", "Expense"],
        datasets: [{
            data: [' . $totalIncome . ', ' . $totalExpense . '],
            backgroundColor: ["rgba(40, 167, 69, 0.8)", "rgba(220, 53, 69, 0.8)"],
            borderWidth: 0,
            cutout: "70%"
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: "bottom",
                labels: {
                    usePointStyle: true,
                    padding: 20,
                    color: document.body.classList.contains("dark-mode") ? "#f1f1f1" : "#333"
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ": ₹" + context.parsed.toLocaleString("en-IN");
                    }
                }
            }
        }
    }
});

function updateChartColors(isDark) {
    const textColor = isDark ? "#f1f1f1" : "#333";
    const mutedColor = isDark ? "#9a9fbf" : "#6c757d";
    const gridColor = isDark ? "rgba(255,255,255,0.05)" : "rgba(0,0,0,0.05)";
    monthlyChart.options.scales.y.ticks.color = mutedColor;
    monthlyChart.options.scales.x.ticks.color = mutedColor;
    monthlyChart.options.scales.y.grid.color = gridColor;
    monthlyChart.options.plugins.legend.labels.color = textColor;
    monthlyChart.update();
}
</script>';

include __DIR__ . '/../includes/header.php';
?>

<!-- Dashboard Stats -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-value"><?php echo $totalCenters; ?></div>
                    <div class="stat-card-label">Total Centers</div>
                </div>
                <div class="stat-card-icon bg-primary"><i class="fas fa-building"></i></div>
            </div>
            <div class="stat-card-change positive">
                <i class="fas fa-users"></i> <?php echo $activeUsers; ?> active, <?php echo $blockedUsers; ?> blocked
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card income-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-value"><?php echo formatCurrency($totalIncome); ?></div>
                    <div class="stat-card-label">Total Income</div>
                </div>
                <div class="stat-card-icon bg-success"><i class="fas fa-arrow-up"></i></div>
            </div>
            <div class="stat-card-change positive">
                <i class="fas fa-chart-line"></i> All centers combined
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card expense-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-value"><?php echo formatCurrency($totalExpense); ?></div>
                    <div class="stat-card-label">Total Expense</div>
                </div>
                <div class="stat-card-icon bg-danger"><i class="fas fa-arrow-down"></i></div>
            </div>
            <div class="stat-card-change negative">
                <i class="fas fa-chart-line"></i> All centers combined
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card profit-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-value <?php echo $netProfit >= 0 ? 'text-success-custom' : 'text-danger-custom'; ?>">
                        <?php echo formatCurrency($netProfit); ?>
                    </div>
                    <div class="stat-card-label">Net Profit / Loss</div>
                </div>
                <div class="stat-card-icon <?php echo $netProfit >= 0 ? 'bg-info' : 'bg-danger'; ?>">
                    <i class="fas fa-wallet"></i>
                </div>
            </div>
            <div class="stat-card-change <?php echo $pendingReports > 0 ? 'negative' : 'positive'; ?>">
                <i class="fas fa-clock"></i> <?php echo $pendingReports; ?> pending reports
            </div>
        </div>
    </div>
</div>


</div>

<!-- Charts -->
<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-chart-bar"></i> Income vs Expense - <?php echo $currentYear; ?></h5>
            </div>
            <div class="card-custom-body">
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-chart-pie"></i> Overall Distribution</h5>
            </div>
            <div class="card-custom-body">
                <div class="chart-container" style="height: 280px;">
                    <canvas id="profitChart"></canvas>
                </div>
                <div class="text-center mt-3">
                    <span class="badge-type badge-income px-3 py-2 me-2">
                        <i class="fas fa-circle me-1"></i> Income: <?php echo formatCurrency($totalIncome); ?>
                    </span>
                    <span class="badge-type badge-expense px-3 py-2">
                        <i class="fas fa-circle me-1"></i> Expense: <?php echo formatCurrency($totalExpense); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Center Totals & Recent Transactions -->
<div class="row g-4">
    <div class="col-xl-7">
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-building"></i> Center-wise Summary</h5>
                <a href="<?php echo APP_URL; ?>/superadmin/centers.php" class="btn-outline-custom btn-sm-custom">View All</a>
            </div>
            <div class="card-custom-body p-0">
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Center</th>
                                <th>Location</th>
                                <th>Income</th>
                                <th>Expense</th>
                                <th>Profit/Loss</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($center = $centerTotals->fetch_assoc()): ?>
                            <?php $profit = $center['total_income'] - $center['total_expense']; ?>
                            <tr>
                                <td class="fw-600"><?php echo sanitize($center['center_name']); ?></td>
                                <td class="text-muted-custom" style="font-size:12px;"><?php echo sanitize($center['location']); ?></td>
                                <td class="text-success-custom fw-600"><?php echo formatCurrency($center['total_income']); ?></td>
                                <td class="text-danger-custom fw-600"><?php echo formatCurrency($center['total_expense']); ?></td>
                                <td class="<?php echo $profit >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                                    <?php echo formatCurrency($profit); ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-history"></i> Recent Transactions</h5>
            </div>
            <div class="card-custom-body p-0">
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Center</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentTransactions->num_rows > 0): ?>
                            <?php while ($txn = $recentTransactions->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-600" style="font-size:12px;"><?php echo sanitize($txn['center_name']); ?></td>
                                <td>
                                    <span class="badge-type <?php echo $txn['type'] === 'income' ? 'badge-income' : 'badge-expense'; ?>">
                                        <?php echo ucfirst($txn['type']); ?>
                                    </span>
                                </td>
                                <td class="fw-600"><?php echo formatCurrency($txn['amount']); ?></td>
                                <td class="text-muted-custom" style="font-size:11px;"><?php echo formatDate($txn['date']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted-custom">No transactions yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>