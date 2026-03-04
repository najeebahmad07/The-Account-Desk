<?php
// File: includes/export.php
// Export functions for Excel (CSV) and PDF

/**
 * Export data as CSV (Excel compatible)
 */
function exportCSV($filename, $headers, $data) {
    // Clean output buffer
    if (ob_get_length()) ob_end_clean();

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');

    // Add BOM for Excel to recognize UTF-8
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // Write headers
    fputcsv($output, $headers);

    // Write data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

/**
 * Export data as PDF
 */
function exportPDF($title, $subtitle, $headers, $data, $totals = null, $orientation = 'P') {
    // Clean output buffer
    if (ob_get_length()) ob_end_clean();

    $tcpdfPath = BASE_PATH . '/tcpdf/tcpdf.php';

    if (file_exists($tcpdfPath)) {
        require_once $tcpdfPath;
        exportPDFWithTCPDF($title, $subtitle, $headers, $data, $totals, $orientation);
    } else {
        exportPDFAsHTML($title, $subtitle, $headers, $data, $totals);
    }
    exit;
}

/**
 * Export PDF using TCPDF library
 */
function exportPDFWithTCPDF($title, $subtitle, $headers, $data, $totals, $orientation) {
    $pdf = new TCPDF($orientation, 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(APP_NAME);
    $pdf->SetAuthor(APP_NAME);
    $pdf->SetTitle($title);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

    // Header
    $pdf->SetFont('helvetica', 'B', 22);
    $pdf->SetTextColor(37, 150, 190);
    $pdf->Cell(0, 10, APP_NAME, 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(108, 117, 125);
    $pdf->Cell(0, 5, APP_TAGLINE, 0, 1, 'C');

    $pdf->Ln(3);
    $pdf->SetDrawColor(37, 150, 190);
    $pdf->SetLineWidth(0.5);
    $pageWidth = $orientation === 'L' ? 277 : 190;
    $pdf->Line(10, $pdf->GetY(), $pageWidth, $pdf->GetY());
    $pdf->Ln(6);

    // Title
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(30, 58, 95);
    $pdf->Cell(0, 8, $title, 0, 1, 'C');

    if ($subtitle) {
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(108, 117, 125);
        $pdf->Cell(0, 6, $subtitle, 0, 1, 'C');
    }
    $pdf->Ln(5);

    // Calculate column widths
    $colCount = count($headers);
    $availWidth = $pageWidth - 10;
    $colWidth = $availWidth / $colCount;

    // Custom widths based on column count
    $colWidths = array_fill(0, $colCount, $colWidth);

    // Table Header
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor(30, 58, 95);

    foreach ($headers as $i => $header) {
        $pdf->Cell($colWidths[$i], 8, $header, 1, 0, 'C', true);
    }
    $pdf->Ln();

    // Table Data
    $pdf->SetFont('helvetica', '', 8);
    $sr = 1;

    foreach ($data as $row) {
        // Alternate row color
        if ($sr % 2 == 0) {
            $pdf->SetFillColor(245, 247, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        $pdf->SetTextColor(51, 51, 51);

        foreach ($row as $i => $cell) {
            // Color for Income/Expense/Profit
            $cellStr = (string)$cell;
            if (strpos($cellStr, 'Income') !== false || strpos($cellStr, 'Profit') !== false) {
                $pdf->SetTextColor(40, 167, 69);
            } elseif (strpos($cellStr, 'Expense') !== false || strpos($cellStr, 'Loss') !== false) {
                $pdf->SetTextColor(220, 53, 69);
            } else {
                $pdf->SetTextColor(51, 51, 51);
            }

            $align = is_numeric(str_replace([',', '₹', 'Rs.', '-', ' '], '', $cellStr)) ? 'R' : 'L';
            $pdf->Cell($colWidths[$i], 7, $cellStr, 1, 0, $align, true);
        }
        $pdf->Ln();
        $sr++;

        // New page if needed
        if ($pdf->GetY() > ($orientation === 'L' ? 185 : 270)) {
            $pdf->AddPage();
            // Re-draw header
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFillColor(30, 58, 95);
            foreach ($headers as $i => $header) {
                $pdf->Cell($colWidths[$i], 8, $header, 1, 0, 'C', true);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 8);
        }
    }

    // Totals row
    if ($totals) {
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(37, 150, 190);
        $pdf->SetTextColor(255, 255, 255);

        foreach ($totals as $i => $cell) {
            $pdf->Cell($colWidths[$i], 9, $cell, 1, 0, is_numeric(str_replace([',', '₹', 'Rs.', '-', ' '], '', (string)$cell)) ? 'R' : 'L', true);
        }
        $pdf->Ln();
    }

    // Footer
    $pdf->Ln(8);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(108, 117, 125);
    $pdf->Cell(0, 5, 'Generated on ' . date('d M, Y h:i A') . ' | ' . APP_NAME, 0, 1, 'C');

    $pdf->Output($title . '.pdf', 'D');
}

/**
 * Export PDF as HTML (fallback without TCPDF)
 */
function exportPDFAsHTML($title, $subtitle, $headers, $data, $totals) {
    header('Content-Type: text/html; charset=utf-8');

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . $title . '</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; padding: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 3px solid #2596be; }
        .header h1 { color: #2596be; font-size: 24px; letter-spacing: 3px; }
        .header .tagline { color: #6c757d; font-size: 11px; }
        .header h2 { color: #1e3a5f; font-size: 16px; margin-top: 10px; }
        .header p { color: #666; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 11px; }
        th { background: #1e3a5f; color: #fff; padding: 8px 6px; text-align: center; font-size: 10px; text-transform: uppercase; }
        td { padding: 6px; border-bottom: 1px solid #eee; }
        tr:nth-child(even) { background: #f8f9fa; }
        .total-row td { background: #2596be; color: #fff; font-weight: bold; }
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
        .footer { text-align: center; margin-top: 20px; color: #999; font-size: 10px; border-top: 1px solid #ddd; padding-top: 10px; }
        .no-print { text-align: center; margin: 20px 0; }
        @media print { .no-print { display: none; } }
    </style></head><body>';

    echo '<div class="header">';
    echo '<h1>' . APP_NAME . '</h1>';
    echo '<p class="tagline">' . APP_TAGLINE . '</p>';
    echo '<h2>' . htmlspecialchars($title) . '</h2>';
    if ($subtitle) echo '<p>' . htmlspecialchars($subtitle) . '</p>';
    echo '</div>';

    echo '<table><thead><tr>';
    foreach ($headers as $h) {
        echo '<th>' . htmlspecialchars($h) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            $class = '';
            $cellStr = (string)$cell;
            if (strpos($cellStr, 'Income') !== false) $class = 'text-success';
            if (strpos($cellStr, 'Expense') !== false) $class = 'text-danger';
            echo '<td class="' . $class . '">' . htmlspecialchars($cellStr) . '</td>';
        }
        echo '</tr>';
    }

    if ($totals) {
        echo '<tr class="total-row">';
        foreach ($totals as $cell) {
            echo '<td>' . htmlspecialchars((string)$cell) . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<div class="footer">Generated on ' . date('d M, Y h:i A') . ' | ' . APP_NAME . '</div>';
    echo '<div class="no-print"><button onclick="window.print()" style="background:#2596be;color:#fff;border:none;padding:10px 30px;border-radius:6px;cursor:pointer;font-size:14px;">🖨️ Print / Save as PDF</button></div>';
    echo '</body></html>';
}

/**
 * Format amount for export (plain number without HTML)
 */
function formatExportAmount($amount) {
    $amount = (float)$amount;
    return 'Rs.' . number_format($amount, 2);
}