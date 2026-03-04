<?php
// File: superadmin/analytics.php
// Analytics page - UPDATED WITH INR

require_once __DIR__ . '/../config/init.php';
requireSuperAdmin();

$conn = getDBConnection();

$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Monthly data
$monthlyData = [];
for ($m = 1; $m <= 12; $m++) {
    $stmt = $conn->prepare("SELECT
        COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END), 0) as income,
        COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END), 0) as expense
        FROM transactions WHERE month = ? AND year = ?");
    $stmt->bind_param("ii", $m, $selectedYear);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $monthlyData[] = [
        'month' => getMonthName($m),
        'income' => (float)$data['income'],
        'expense' => (float)$data['expense'],
        'profit' => (float)($data['income'] - $data['expense'])
    ];
}

// Category breakdown
$categoryData = $conn->query("SELECT category, SUM(amount) as total FROM transactions
    WHERE type='expense' AND year=$selectedYear AND category IS NOT NULL AND category != ''
    GROUP BY category ORDER BY total DESC");

$categories = [];
while ($cat = $categoryData->fetch_assoc()) {
    $categories[] = $cat;
}

// Center comparison
$centerComparison = $conn->query("SELECT c.center_name,
    COALESCE(SUM(CASE WHEN t.type='income' THEN t.amount ELSE 0 END), 0) as income,
    COALESCE(SUM(CASE WHEN t.type='expense' THEN t.amount ELSE 0 END), 0) as expense
    FROM centers c LEFT JOIN transactions t ON c.id = t.center_id AND t.year = $selectedYear
    GROUP BY c.id, c.center_name ORDER BY c.center_name");

$centerData = [];
while ($cd = $centerComparison->fetch_assoc()) {
    $centerData[] = $cd;
}

$conn->close();

$pageTitle = 'Analytics';
$currentPage = 'analytics';

$extraJS = '<script>
const monthlyData = ' . json_encode($monthlyData) . ';
const categoryData = ' . json_encode($categories) . ';
const centerData = ' . json_encode($centerData) . ';
const isDark = document.body.classList.contains("dark-mode");
const textColor = isDark ? "#f1f1f1" : "#333";
const gridColor = isDark ? "rgba(255,255,255,0.05)" : "rgba(0,0,0,0.05)";
const mutedColor = isDark ? "#9a9fbf" : "#6c757d";

// Indian Number Format for Charts
function formatINR(value) {
    var val = Math.abs(value);
    if (val >= 10000000) return "₹" + (val / 10000000).toFixed(1) + " Cr";
    if (val >= 100000) return "₹" + (val / 100000).toFixed(1) + " L";
    if (val >= 1000) return "₹" + (val / 1000).toFixed(1) + " K";
    return "₹" + val.toLocaleString("en-IN");
}

// Tooltip format
function inrTooltip(context) {
    return context.dataset.label + ": ₹" + context.parsed.y.toLocaleString("en-IN");
}

// Line Chart
new Chart(document.getElementById("trendChart"), {
    type: "line",
    data: {
        labels: monthlyData.map(d => d.month.substr(0,3)),
        datasets: [
            {
                label: "Income", data: monthlyData.map(d => d.income),
                borderColor: "#28a745", backgroundColor: "rgba(40,167,69,0.1)",
                fill: true, tension: 0.4, pointRadius: 4, pointHoverRadius: 6
            },
            {
                label: "Expense", data: monthlyData.map(d => d.expense),
                borderColor: "#dc3545", backgroundColor: "rgba(220,53,69,0.1)",
                fill: true, tension: 0.4, pointRadius: 4, pointHoverRadius: 6
            },
            {
                label: "Profit/Loss", data: monthlyData.map(d => d.profit),
                borderColor: "#2596be", backgroundColor: "rgba(37,150,190,0.1)",
                fill: true, tension: 0.4, pointRadius: 4, pointHoverRadius: 6, borderDash: [5,5]
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: textColor, usePointStyle: true, padding: 20 } },
            tooltip: { callbacks: { label: inrTooltip } }
        },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => formatINR(v), color: mutedColor }, grid: { color: gridColor } },
            x: { ticks: { color: mutedColor }, grid: { display: false } }
        }
    }
});

// Category Doughnut
const categoryColors = ["#2596be","#28a745","#dc3545","#ffc107","#17a2b8","#6f42c1","#fd7e14","#20c997","#e83e8c","#6c757d"];
new Chart(document.getElementById("categoryChart"), {
    type: "doughnut",
    data: {
        labels: categoryData.map(d => d.category),
        datasets: [{
            data: categoryData.map(d => parseFloat(d.total)),
            backgroundColor: categoryColors.slice(0, categoryData.length),
            borderWidth: 0, cutout: "60%"
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: "right", labels: { color: textColor, usePointStyle: true, padding: 12, font: { size: 12 } } },
            tooltip: {
                callbacks: {
                    label: function(ctx) { return ctx.label + ": ₹" + ctx.parsed.toLocaleString("en-IN"); }
                }
            }
        }
    }
});

// Center Comparison Bar
new Chart(document.getElementById("centerChart"), {
    type: "bar",
    data: {
        labels: centerData.map(d => d.center_name.replace(" Branch","").replace(" Center","")),
        datasets: [
            { label: "Income", data: centerData.map(d => parseFloat(d.income)), backgroundColor: "rgba(40,167,69,0.7)", borderRadius: 4 },
            { label: "Expense", data: centerData.map(d => parseFloat(d.expense)), backgroundColor: "rgba(220,53,69,0.7)", borderRadius: 4 }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: textColor, usePointStyle: true } },
            tooltip: { callbacks: { label: inrTooltip } }
        },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => formatINR(v), color: mutedColor }, grid: { color: gridColor } },
            x: { ticks: { color: mutedColor, font: { size: 10 } }, grid: { display: false } }
        }
    }
});
</script>';

include __DIR__ . '/../includes/header.php';
?>

<!-- Year Filter -->
<div class="card-custom mb-4">
    <div class="card-custom-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-600" style="font-size:12px;">SELECT YEAR</label>
                <select name="year" class="form-control-custom">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $selectedYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn-primary-custom"><i class="fas fa-chart-bar"></i> View Analytics</button>
            </div>
        </form>
    </div>
</div>

<!-- Trend Chart -->
<div class="card-custom mb-4">
    <div class="card-custom-header">
        <h5><i class="fas fa-chart-line"></i> Monthly Trends - <?php echo $selectedYear; ?></h5>
    </div>
    <div class="card-custom-body">
        <div class="chart-container" style="height:380px;">
            <canvas id="trendChart"></canvas>
        </div>
    </div>
</div>

<!-- Category & Center Charts -->
<div class="row g-4">
    <div class="col-xl-5">
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-chart-pie"></i> Expense by Category</h5>
            </div>
            <div class="card-custom-body">
                <div class="chart-container" style="height:320px;">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-building"></i> Center-wise Comparison</h5>
            </div>
            <div class="card-custom-body">
                <div class="chart-container" style="height:320px;">
                    <canvas id="centerChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>