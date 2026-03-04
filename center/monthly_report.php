<?php
// File: center/monthly_report.php
// Monthly Report - COMPLETE FIXED VERSION WITH PDF & INR

require_once __DIR__ . '/../config/init.php';
requireCenterAdmin();

$conn = getDBConnection();
$centerId = $_SESSION['center_id'];
$centerName = getCenterName($centerId);

// Selected month/year
$selMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$selYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Handle Submit Report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $rMonth = (int)$_POST['report_month'];
    $rYear = (int)$_POST['report_year'];

    // Calculate totals from transactions table
    $totals = getCenterMonthlyTotals($centerId, $rMonth, $rYear);
    $netProfit = $totals['income'] - $totals['expense'];

    // Get all transactions for the report
    $txnStmt = $conn->prepare("SELECT * FROM transactions WHERE center_id = ? AND month = ? AND year = ? ORDER BY date ASC, type ASC");
    $txnStmt->bind_param("iii", $centerId, $rMonth, $rYear);
    $txnStmt->execute();
    $reportTransactions = $txnStmt->get_result();
    $txnStmt->close();

    // Generate PDF
    $pdfFileName = 'report_' . $centerId . '_' . $rMonth . '_' . $rYear . '_' . time() . '.pdf';

    // Create reports directory if not exists
    if (!is_dir(REPORTS_PATH)) {
        mkdir(REPORTS_PATH, 0755, true);
    }

    // Generate the PDF file
    $pdfGenerated = generateMonthlyPDF($centerId, $centerName, $rMonth, $rYear, $totals, $reportTransactions, $pdfFileName);

    // Insert/Update monthly report in database
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO monthly_reports (center_id, month, year, total_income, total_expense, net_profit, status, pdf_path, submitted_at)
                            VALUES (?, ?, ?, ?, ?, ?, 'submitted', ?, ?)
                            ON DUPLICATE KEY UPDATE
                            total_income = VALUES(total_income),
                            total_expense = VALUES(total_expense),
                            net_profit = VALUES(net_profit),
                            status = 'submitted',
                            pdf_path = VALUES(pdf_path),
                            submitted_at = VALUES(submitted_at)");
    $stmt->bind_param("iiidddss", $centerId, $rMonth, $rYear, $totals['income'], $totals['expense'], $netProfit, $pdfFileName, $now);

    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'Report Submitted', "Monthly report for " . getMonthName($rMonth) . " $rYear");

        // Try to send email (will silently fail if PHPMailer not installed)
        sendReportEmail($centerId, $centerName, $rMonth, $rYear, $pdfFileName);

        setFlash('success', 'Monthly report submitted successfully! PDF has been generated.');
    } else {
        setFlash('danger', 'Failed to submit report: ' . $stmt->error);
    }
    $stmt->close();
    redirect(APP_URL . '/center/monthly_report.php?month=' . $rMonth . '&year=' . $rYear);
}

// Handle PDF Download
if (isset($_GET['download_pdf'])) {
    $dlMonth = (int)$_GET['dl_month'];
    $dlYear = (int)$_GET['dl_year'];

    // Get totals
    $totals = getCenterMonthlyTotals($centerId, $dlMonth, $dlYear);

    // Get transactions
    $txnStmt = $conn->prepare("SELECT * FROM transactions WHERE center_id = ? AND month = ? AND year = ? ORDER BY date ASC");
    $txnStmt->bind_param("iii", $centerId, $dlMonth, $dlYear);
    $txnStmt->execute();
    $dlTransactions = $txnStmt->get_result();
    $txnStmt->close();

    $pdfFileName = 'report_' . $centerId . '_' . $dlMonth . '_' . $dlYear . '_download.pdf';
    generateMonthlyPDF($centerId, $centerName, $dlMonth, $dlYear, $totals, $dlTransactions, $pdfFileName, true);
    exit;
}

// Get current report status
$stmt = $conn->prepare("SELECT * FROM monthly_reports WHERE center_id = ? AND month = ? AND year = ?");
$stmt->bind_param("iii", $centerId, $selMonth, $selYear);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get totals
$totals = getCenterMonthlyTotals($centerId, $selMonth, $selYear);

// Get transactions for display
$stmt = $conn->prepare("SELECT * FROM transactions WHERE center_id = ? AND month = ? AND year = ? ORDER BY date ASC");
$stmt->bind_param("iii", $centerId, $selMonth, $selYear);
$stmt->execute();
$transactions = $stmt->get_result();
$stmt->close();

$conn->close();

/**
 * ============================================
 * GENERATE PDF REPORT
 * Supports TCPDF if available, otherwise uses
 * built-in HTML to PDF conversion
 * ============================================
 */
function generateMonthlyPDF($centerId, $centerName, $month, $year, $totals, $transactions, $fileName, $directDownload = false) {

    $tcpdfPath = BASE_PATH . '/tcpdf/tcpdf.php';
    $savePath = REPORTS_PATH . $fileName;

    // Format amounts in INR
    $incomeFormatted = formatINRForPDF($totals['income']);
    $expenseFormatted = formatINRForPDF($totals['expense']);
    $profit = $totals['income'] - $totals['expense'];
    $profitFormatted = formatINRForPDF(abs($profit));
    $profitLabel = $profit >= 0 ? 'Net Profit' : 'Net Loss';
    $monthName = getMonthName($month);
    $generatedDate = date('d M, Y h:i A');

    // ==========================================
    // METHOD 1: Using TCPDF (if available)
    // ==========================================
    if (file_exists($tcpdfPath)) {
        require_once $tcpdfPath;

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(APP_NAME);
        $pdf->SetAuthor(APP_NAME);
        $pdf->SetTitle("Monthly Report - $centerName - $monthName $year");
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();

        // ---- HEADER ----
        $pdf->SetFont('helvetica', 'B', 28);
        $pdf->SetTextColor(37, 150, 190);
        $pdf->Cell(0, 12, APP_NAME, 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(108, 117, 125);
        $pdf->Cell(0, 6, APP_TAGLINE, 0, 1, 'C');

        $pdf->Ln(3);
        $pdf->SetDrawColor(37, 150, 190);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(8);

        // ---- REPORT TITLE ----
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetTextColor(30, 58, 95);
        $pdf->Cell(0, 10, 'Monthly Financial Report', 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 13);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->Cell(0, 8, $monthName . ' ' . $year, 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 11);
        $pdf->SetTextColor(108, 117, 125);
        $pdf->Cell(0, 7, 'Center: ' . $centerName, 0, 1, 'C');
        $pdf->Ln(10);

        // ---- SUMMARY TABLE ----
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFillColor(37, 150, 190);
        $pdf->Cell(90, 10, '  Description', 1, 0, 'L', true);
        $pdf->Cell(90, 10, 'Amount (INR)  ', 1, 1, 'R', true);

        // Total Income Row
        $pdf->SetFont('helvetica', '', 11);
        $pdf->SetTextColor(40, 167, 69);
        $pdf->SetFillColor(245, 255, 245);
        $pdf->Cell(90, 10, '  Total Income', 1, 0, 'L', true);
        $pdf->Cell(90, 10, $incomeFormatted . '  ', 1, 1, 'R', true);

        // Total Expense Row
        $pdf->SetTextColor(220, 53, 69);
        $pdf->SetFillColor(255, 245, 245);
        $pdf->Cell(90, 10, '  Total Expense', 1, 0, 'L', true);
        $pdf->Cell(90, 10, $expenseFormatted . '  ', 1, 1, 'R', true);

        // Net Profit/Loss Row
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFillColor(30, 58, 95);
        $pdf->Cell(90, 12, '  ' . $profitLabel, 1, 0, 'L', true);
        $profitSign = $profit >= 0 ? '' : '-';
        $pdf->Cell(90, 12, $profitSign . $profitFormatted . '  ', 1, 1, 'R', true);

        $pdf->Ln(8);

        // ---- TRANSACTIONS TABLE ----
        if ($transactions && $transactions->num_rows > 0) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(30, 58, 95);
            $pdf->Cell(0, 10, 'Transaction Details', 0, 1, 'L');
            $pdf->Ln(2);

            // Table Header
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFillColor(30, 58, 95);
            $pdf->Cell(10, 8, 'SR', 1, 0, 'C', true);
            $pdf->Cell(25, 8, 'Date', 1, 0, 'C', true);
            $pdf->Cell(22, 8, 'Type', 1, 0, 'C', true);
            $pdf->Cell(28, 8, 'Category', 1, 0, 'C', true);
            $pdf->Cell(60, 8, 'Description', 1, 0, 'C', true);
            $pdf->Cell(35, 8, 'Amount', 1, 1, 'C', true);

            // Table Rows
            $pdf->SetFont('helvetica', '', 9);
            $sr = 1;
            $transactions->data_seek(0);

            while ($txn = $transactions->fetch_assoc()) {
                // Alternate row colors
                if ($sr % 2 == 0) {
                    $pdf->SetFillColor(248, 249, 250);
                } else {
                    $pdf->SetFillColor(255, 255, 255);
                }

                $pdf->SetTextColor(51, 51, 51);
                $pdf->Cell(10, 7, $sr, 1, 0, 'C', true);
                $pdf->Cell(25, 7, date('d/m/Y', strtotime($txn['date'])), 1, 0, 'C', true);

                // Type with color
                if ($txn['type'] === 'income') {
                    $pdf->SetTextColor(40, 167, 69);
                } else {
                    $pdf->SetTextColor(220, 53, 69);
                }
                $pdf->Cell(22, 7, ucfirst($txn['type']), 1, 0, 'C', true);

                $pdf->SetTextColor(51, 51, 51);
                $pdf->Cell(28, 7, $txn['category'] ?? '-', 1, 0, 'C', true);
                $pdf->Cell(60, 7, substr($txn['description'] ?? '-', 0, 35), 1, 0, 'L', true);

                // Amount with color
                if ($txn['type'] === 'income') {
                    $pdf->SetTextColor(40, 167, 69);
                } else {
                    $pdf->SetTextColor(220, 53, 69);
                }
                $pdf->Cell(35, 7, formatINRForPDF($txn['amount']), 1, 1, 'R', true);

                $sr++;

                // Add new page if needed
                if ($pdf->GetY() > 260) {
                    $pdf->AddPage();
                }
            }
        }

        $pdf->Ln(10);

        // ---- FOOTER ----
        $pdf->SetDrawColor(37, 150, 190);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(5);

        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->SetTextColor(108, 117, 125);
        $pdf->Cell(0, 6, 'Generated on ' . $generatedDate, 0, 1, 'C');
        $pdf->Cell(0, 6, APP_NAME . ' - ' . APP_TAGLINE, 0, 1, 'C');
        $pdf->Cell(0, 6, 'This is a computer generated report.', 0, 1, 'C');

        // Output
        if ($directDownload) {
            $pdf->Output($monthName . '_' . $year . '_' . $centerName . '.pdf', 'D');
        } else {
            $pdf->Output($savePath, 'F');
        }

        return true;
    }

    // ==========================================
    // METHOD 2: Generate PDF without TCPDF
    // Using HTML + Browser Print
    // ==========================================
    else {
        // Reset transactions pointer
        if ($transactions) {
            $transactions->data_seek(0);
        }

        $html = generateHTMLReport($centerName, $month, $year, $totals, $transactions, $generatedDate);

        if ($directDownload) {
            // Output as HTML that auto-prints as PDF
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            echo '<script>window.onload = function() { window.print(); }</script>';
            return true;
        } else {
            // Save as HTML file (rename to .pdf for storage)
            file_put_contents($savePath, $html);
            return true;
        }
    }
}

/**
 * Generate HTML Report (fallback when TCPDF not available)
 */
function generateHTMLReport($centerName, $month, $year, $totals, $transactions, $generatedDate) {
    $monthName = getMonthName($month);
    $profit = $totals['income'] - $totals['expense'];
    $profitLabel = $profit >= 0 ? 'Net Profit' : 'Net Loss';

    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report - ' . $centerName . ' - ' . $monthName . ' ' . $year . '</title>
    <style>
        @page { size: A4; margin: 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #333; padding: 30px; background: #fff; }

        .report-header { text-align: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 3px solid #2596be; }
        .report-header h1 { font-size: 32px; color: #2596be; letter-spacing: 4px; margin-bottom: 5px; }
        .report-header .tagline { font-size: 12px; color: #6c757d; }

        .report-title { text-align: center; margin: 25px 0; }
        .report-title h2 { font-size: 22px; color: #1e3a5f; margin-bottom: 8px; }
        .report-title p { font-size: 14px; color: #555; }
        .report-title .center-name { font-size: 15px; color: #2596be; font-weight: bold; }

        .summary-table { width: 100%; border-collapse: collapse; margin: 25px 0; }
        .summary-table th { background: #2596be; color: #fff; padding: 12px 15px; text-align: left; font-size: 13px; }
        .summary-table th:last-child { text-align: right; }
        .summary-table td { padding: 12px 15px; border-bottom: 1px solid #e0e0e0; font-size: 14px; }
        .summary-table td:last-child { text-align: right; font-weight: bold; }
        .summary-table .income-row td { color: #28a745; background: #f6fff6; }
        .summary-table .expense-row td { color: #dc3545; background: #fff6f6; }
        .summary-table .total-row td { background: #1e3a5f; color: #fff; font-size: 15px; font-weight: bold; }

        .txn-section h3 { font-size: 16px; color: #1e3a5f; margin: 30px 0 15px; padding-bottom: 8px; border-bottom: 2px solid #e0e0e0; }

        .txn-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .txn-table thead th { background: #1e3a5f; color: #fff; padding: 10px 8px; text-align: center; font-size: 11px; text-transform: uppercase; }
        .txn-table tbody td { padding: 8px; border-bottom: 1px solid #eee; text-align: center; }
        .txn-table tbody tr:nth-child(even) { background: #f8f9fa; }
        .txn-table .amount-col { text-align: right; font-weight: bold; }
        .txn-table .desc-col { text-align: left; }
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }

        .report-footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #2596be; color: #999; font-size: 11px; }
        .report-footer p { margin: 3px 0; }

        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="report-header">
    <h1>' . APP_NAME . '</h1>
    <p class="tagline">' . APP_TAGLINE . '</p>
</div>

<div class="report-title">
    <h2>Monthly Financial Report</h2>
    <p>' . $monthName . ' ' . $year . '</p>
    <p class="center-name">Center: ' . htmlspecialchars($centerName) . '</p>
</div>

<table class="summary-table">
    <thead>
        <tr>
            <th>Description</th>
            <th>Amount (₹)</th>
        </tr>
    </thead>
    <tbody>
        <tr class="income-row">
            <td>📈 Total Income</td>
            <td>' . formatINRForPDF($totals['income']) . '</td>
        </tr>
        <tr class="expense-row">
            <td>📉 Total Expense</td>
            <td>' . formatINRForPDF($totals['expense']) . '</td>
        </tr>
        <tr class="total-row">
            <td>' . ($profit >= 0 ? '✅' : '❌') . ' ' . $profitLabel . '</td>
            <td>' . ($profit < 0 ? '-' : '') . formatINRForPDF(abs($profit)) . '</td>
        </tr>
    </tbody>
</table>';

    // Add transaction details
    if ($transactions && $transactions->num_rows > 0) {
        $html .= '<div class="txn-section"><h3>📋 Transaction Details</h3>';
        $html .= '<table class="txn-table"><thead><tr>';
        $html .= '<th>SR#</th><th>Date</th><th>Type</th><th>Category</th><th>Description</th><th>Amount (₹)</th>';
        $html .= '</tr></thead><tbody>';

        $sr = 1;
        while ($txn = $transactions->fetch_assoc()) {
            $typeClass = $txn['type'] === 'income' ? 'text-success' : 'text-danger';
            $html .= '<tr>';
            $html .= '<td>' . $sr++ . '</td>';
            $html .= '<td>' . date('d/m/Y', strtotime($txn['date'])) . '</td>';
            $html .= '<td class="' . $typeClass . '"><strong>' . ucfirst($txn['type']) . '</strong></td>';
            $html .= '<td>' . ($txn['category'] ?? '-') . '</td>';
            $html .= '<td class="desc-col">' . htmlspecialchars($txn['description'] ?? '-') . '</td>';
            $html .= '<td class="amount-col ' . $typeClass . '">' . formatINRForPDF($txn['amount']) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
    } else {
        $html .= '<p style="text-align:center; color:#999; margin:30px 0;">No transactions recorded for this period.</p>';
    }

    $html .= '
<div class="report-footer">
    <p>Generated on ' . $generatedDate . '</p>
    <p><strong>' . APP_NAME . '</strong> - ' . APP_TAGLINE . '</p>
    <p>This is a computer generated report</p>
</div>

<div class="no-print" style="text-align:center; margin-top:30px;">
    <button onclick="window.print()" style="background:#2596be; color:#fff; border:none; padding:12px 30px; border-radius:8px; font-size:16px; cursor:pointer;">
        🖨️ Print / Save as PDF
    </button>
    <button onclick="window.close()" style="background:#6c757d; color:#fff; border:none; padding:12px 30px; border-radius:8px; font-size:16px; cursor:pointer; margin-left:10px;">
        ✖ Close
    </button>
</div>

</body>
</html>';

    return $html;
}

/**
 * Format INR for PDF (plain text without HTML entities)
 */
function formatINRForPDF($amount) {
    $amount = (float)$amount;
    $whole = floor(abs($amount));
    $decimal = sprintf('%02d', round((abs($amount) - $whole) * 100));
    $wholeStr = (string)$whole;
    $length = strlen($wholeStr);

    if ($length > 3) {
        $lastThree = substr($wholeStr, -3);
        $remaining = substr($wholeStr, 0, $length - 3);
        $formatted = '';
        $remLen = strlen($remaining);
        for ($i = 0; $i < $remLen; $i++) {
            if ($i > 0 && ($remLen - $i) % 2 === 0) {
                $formatted .= ',';
            }
            $formatted .= $remaining[$i];
        }
        $result = $formatted . ',' . $lastThree . '.' . $decimal;
    } else {
        $result = $wholeStr . '.' . $decimal;
    }

    return 'Rs.' . $result;
}

/**
 * Send report via email
 */
function sendReportEmail($centerId, $centerName, $month, $year, $pdfFileName) {
    // Check if PHPMailer exists
    $mailerAutoload = BASE_PATH . '/vendor/autoload.php';
    $mailerDirect = BASE_PATH . '/mailer/PHPMailer.php';

    $mailerAvailable = false;

    if (file_exists($mailerAutoload)) {
        require_once $mailerAutoload;
        $mailerAvailable = true;
    } elseif (file_exists($mailerDirect)) {
        require_once $mailerDirect;
        require_once BASE_PATH . '/mailer/SMTP.php';
        require_once BASE_PATH . '/mailer/Exception.php';
        $mailerAvailable = true;
    }

    if (!$mailerAvailable) {
        return false;
    }

    $settings = getSettings();
    $monthName = getMonthName($month);

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $settings['email'];
        $mail->Password = ''; // Add your app password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom($settings['email'], APP_NAME);
        $mail->addAddress($settings['email']);

        $mail->isHTML(true);
        $mail->Subject = "Monthly Report - $centerName - $monthName $year";
        $mail->Body = "<h2>" . APP_NAME . "</h2>
            <p>Monthly report for <strong>$centerName</strong> has been submitted.</p>
            <p>Period: <strong>$monthName $year</strong></p>
            <p>Please find the report attached.</p>";

        $attachPath = REPORTS_PATH . $pdfFileName;
        if (file_exists($attachPath)) {
            $mail->addAttachment($attachPath);
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email failed: " . $e->getMessage());
        return false;
    }
}

$pageTitle = 'Monthly Report';
$currentPage = 'monthly_report';
include __DIR__ . '/../includes/header.php';
?>

<!-- Period Selector -->
<div class="card-custom mb-4">
    <div class="card-custom-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-600" style="font-size:12px;">MONTH</label>
                <select name="month" class="form-control-custom">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $selMonth == $m ? 'selected' : ''; ?>>
                        <?php echo getMonthName($m); ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-600" style="font-size:12px;">YEAR</label>
                <select name="year" class="form-control-custom">
                    <?php for ($y = date('Y') + 1; $y >= date('Y') - 5; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $selYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-6 d-flex gap-2">
                <button type="submit" class="btn-primary-custom"><i class="fas fa-search"></i> View Report</button>
                <a href="<?php echo APP_URL; ?>/center/monthly_report.php?download_pdf=1&dl_month=<?php echo $selMonth; ?>&dl_year=<?php echo $selYear; ?>"
                   class="btn-outline-custom" target="_blank">
                    <i class="fas fa-file-pdf"></i> Preview PDF
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Report Content -->
<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <!-- Transactions List -->
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-list"></i> Transactions - <?php echo getMonthName($selMonth) . ' ' . $selYear; ?></h5>
                <span class="badge bg-primary"><?php echo $transactions->num_rows; ?> records</span>
            </div>
            <div class="card-custom-body p-0">
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
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
                            <tr><td colspan="6" class="text-center py-4 text-muted-custom">No transactions for this period</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <!-- Summary Box -->
        <div class="summary-box mb-4">
            <h4><i class="fas fa-calculator me-2"></i>Monthly Summary</h4>
            <div class="summary-item">
                <span>📈 Total Income</span>
                <span><?php echo formatCurrency($totals['income']); ?></span>
            </div>
            <div class="summary-item">
                <span>📉 Total Expense</span>
                <span><?php echo formatCurrency($totals['expense']); ?></span>
            </div>
            <div class="summary-item">
                <span><?php echo $totals['profit'] >= 0 ? '✅ Net Profit' : '❌ Net Loss'; ?></span>
                <span><?php echo formatCurrency(abs($totals['profit'])); ?></span>
            </div>
        </div>

        <!-- Report Status -->
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fas fa-file-alt"></i> Report Status</h5>
            </div>
            <div class="card-custom-body text-center">
                <?php if ($report && $report['status'] === 'submitted'): ?>
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success-custom" style="font-size:52px;"></i>
                    </div>
                    <h6 class="fw-700 text-success-custom mb-2">Report Submitted ✅</h6>
                    <p class="text-muted-custom" style="font-size:13px;">
                        Submitted on <?php echo formatDate($report['submitted_at']); ?>
                    </p>

                    <!-- Download PDF Button -->
                    <a href="<?php echo APP_URL; ?>/center/monthly_report.php?download_pdf=1&dl_month=<?php echo $selMonth; ?>&dl_year=<?php echo $selYear; ?>"
                       class="btn-primary-custom w-100 justify-content-center mt-3" target="_blank">
                        <i class="fas fa-file-pdf"></i> Download / Print PDF
                    </a>

                    <?php if ($report['pdf_path'] && file_exists(REPORTS_PATH . $report['pdf_path'])): ?>
                    <a href="<?php echo APP_URL; ?>/reports/<?php echo $report['pdf_path']; ?>"
                       class="btn-outline-custom w-100 justify-content-center mt-2" target="_blank">
                        <i class="fas fa-external-link-alt"></i> View Saved Report
                    </a>
                    <?php endif; ?>

                    <!-- Re-submit option -->
                    <hr class="my-3">
                    <form method="POST">
                        <input type="hidden" name="report_month" value="<?php echo $selMonth; ?>">
                        <input type="hidden" name="report_year" value="<?php echo $selYear; ?>">
                        <button type="submit" name="submit_report" class="btn-outline-custom w-100 justify-content-center btn-sm-custom"
                                onclick="return confirm('Re-submit this report with updated data?')">
                            <i class="fas fa-redo"></i> Re-Submit Report
                        </button>
                    </form>

                <?php else: ?>
                    <div class="mb-3">
                        <i class="fas fa-clock" style="font-size:52px; color:#ffc107;"></i>
                    </div>
                    <h6 class="fw-700" style="color:#ffc107;">Pending Submission ⏳</h6>
                    <p class="text-muted-custom" style="font-size:13px;">
                        Report for <?php echo getMonthName($selMonth) . ' ' . $selYear; ?> has not been submitted.
                    </p>

                    <!-- Preview PDF first -->
                    <a href="<?php echo APP_URL; ?>/center/monthly_report.php?download_pdf=1&dl_month=<?php echo $selMonth; ?>&dl_year=<?php echo $selYear; ?>"
                       class="btn-outline-custom w-100 justify-content-center mb-2" target="_blank">
                        <i class="fas fa-eye"></i> Preview Report
                    </a>

                    <!-- Submit Button -->
                    <form method="POST">
                        <input type="hidden" name="report_month" value="<?php echo $selMonth; ?>">
                        <input type="hidden" name="report_year" value="<?php echo $selYear; ?>">
                        <button type="submit" name="submit_report" class="btn-success-custom w-100 justify-content-center"
                                onclick="return confirm('Submit this monthly report to Super Admin?')">
                            <i class="fas fa-paper-plane"></i> Submit Report
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>