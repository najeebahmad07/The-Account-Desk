<?php
// File: includes/export_sidebar.php
// Reusable export sidebar component
// Usage: include with $exportConfig variable set

$exportConfig = $exportConfig ?? [];
$exportPage = $exportConfig['page'] ?? '';
$exportFilters = $exportConfig['filters'] ?? [];
?>

<!-- Export Sidebar Panel -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="exportPanel" style="width: 380px;">
    <div class="offcanvas-header" style="background: var(--accent); color: #fff;">
        <h5 class="offcanvas-title"><i class="fas fa-download me-2"></i>Export Data</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body" style="background: var(--bg-light);">

        <form method="GET" action="" id="exportForm">
            <input type="hidden" name="export" id="exportType" value="">

            <!-- Export Format Selection -->
            <div class="card-custom mb-3">
                <div class="card-custom-body" style="padding: 16px;">
                    <h6 class="fw-700 mb-3" style="font-size: 13px;">
                        <i class="fas fa-file-export text-primary-custom me-2"></i>Export Format
                    </h6>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn-export-option" onclick="setExportType('excel')" id="btnExcel">
                            <div class="d-flex align-items-center gap-3">
                                <div class="export-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
                                    <i class="fas fa-file-excel"></i>
                                </div>
                                <div>
                                    <strong>Excel (CSV)</strong>
                                    <small class="d-block text-muted">Download as spreadsheet</small>
                                </div>
                            </div>
                        </button>
                        <button type="button" class="btn-export-option" onclick="setExportType('pdf')" id="btnPdf">
                            <div class="d-flex align-items-center gap-3">
                                <div class="export-icon" style="background: rgba(220,53,69,0.1); color: #dc3545;">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <div>
                                    <strong>PDF Report</strong>
                                    <small class="d-block text-muted">Download as PDF document</small>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Data Type Selection (Super Admin only) -->
            <?php if (isSuperAdmin()): ?>
            <div class="card-custom mb-3">
                <div class="card-custom-body" style="padding: 16px;">
                    <h6 class="fw-700 mb-3" style="font-size: 13px;">
                        <i class="fas fa-database text-primary-custom me-2"></i>Data to Export
                    </h6>
                    <select name="export_data" class="form-control-custom" id="exportDataType" onchange="toggleExportFilters()">
                        <option value="transactions">All Transactions</option>
                        <option value="income_only">Income Only</option>
                        <option value="expense_only">Expense Only</option>
                        <option value="center_summary">Center-wise Summary</option>
                        <option value="monthly_summary">Monthly Summary</option>
                        <option value="category_breakdown">Expense by Category</option>
                        <option value="profit_loss">Profit & Loss Statement</option>
                        <option value="user_list">User List</option>
                        <option value="center_list">Center List</option>
                        <option value="pending_reports">Pending Reports</option>
                    </select>
                </div>
            </div>
            <?php else: ?>
            <input type="hidden" name="export_data" value="transactions">
            <?php endif; ?>

            <!-- Filters -->
            <div class="card-custom mb-3">
                <div class="card-custom-body" style="padding: 16px;">
                    <h6 class="fw-700 mb-3" style="font-size: 13px;">
                        <i class="fas fa-filter text-primary-custom me-2"></i>Filters
                    </h6>

                    <?php if (isSuperAdmin()): ?>
                    <!-- Center Filter -->
                    <div class="form-group-custom" id="filterCenter">
                        <label>Center</label>
                        <select name="export_center" class="form-control-custom">
                            <option value="0">All Centers</option>
                            <?php
                            $expConn = getDBConnection();
                            $expCenters = $expConn->query("SELECT id, center_name FROM centers ORDER BY center_name");
                            while ($ec = $expCenters->fetch_assoc()):
                            ?>
                            <option value="<?php echo $ec['id']; ?>"><?php echo sanitize($ec['center_name']); ?></option>
                            <?php endwhile; $expConn->close(); ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Month Filter -->
                    <div class="form-group-custom" id="filterMonth">
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

                    <!-- Year Filter -->
                    <div class="form-group-custom" id="filterYear">
                        <label>Year</label>
                        <select name="export_year" class="form-control-custom">
                            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Date Range -->
                    <div class="form-group-custom" id="filterDateRange" style="display:none;">
                        <label>From Date</label>
                        <input type="date" name="export_from" class="form-control-custom" value="<?php echo date('Y-m-01'); ?>">
                    </div>
                    <div class="form-group-custom" id="filterDateRange2" style="display:none;">
                        <label>To Date</label>
                        <input type="date" name="export_to" class="form-control-custom" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
            </div>

            <!-- Export Button -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn-success-custom justify-content-center py-3" id="exportBtn" disabled>
                    <i class="fas fa-download"></i>
                    <span id="exportBtnText">Select Format to Export</span>
                </button>
            </div>

        </form>

        <!-- Export Info -->
        <div class="card-custom mt-3">
            <div class="card-custom-body" style="padding: 12px 16px;">
                <small class="text-muted-custom">
                    <i class="fas fa-info-circle me-1"></i>
                    Excel exports can be opened in Microsoft Excel, Google Sheets, or LibreOffice.
                    PDF exports are formatted reports ready for printing.
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Export Sidebar CSS -->
<style>
.btn-export-option {
    background: var(--bg-card-light);
    border: 2px solid var(--border-light);
    border-radius: 8px;
    padding: 12px 16px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: left;
    width: 100%;
    color: var(--text-light);
}

.btn-export-option:hover {
    border-color: var(--primary);
    background: rgba(37, 150, 190, 0.05);
}

.btn-export-option.active {
    border-color: var(--primary);
    background: rgba(37, 150, 190, 0.08);
    box-shadow: 0 0 0 3px rgba(37, 150, 190, 0.1);
}

.export-icon {
    width: 42px;
    height: 42px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.btn-export-option small {
    font-size: 11px;
    color: var(--text-muted-light);
}

.btn-export-option strong {
    font-size: 14px;
}
</style>

<!-- Export Sidebar JS -->
<script>
function setExportType(type) {
    document.getElementById('exportType').value = type;
    document.getElementById('exportBtn').disabled = false;

    // Toggle active class
    document.getElementById('btnExcel').classList.remove('active');
    document.getElementById('btnPdf').classList.remove('active');

    if (type === 'excel') {
        document.getElementById('btnExcel').classList.add('active');
        document.getElementById('exportBtnText').textContent = 'Export as Excel (CSV)';
    } else {
        document.getElementById('btnPdf').classList.add('active');
        document.getElementById('exportBtnText').textContent = 'Export as PDF';
    }
}

function toggleExportFilters() {
    var dataType = document.getElementById('exportDataType');
    if (!dataType) return;

    var val = dataType.value;
    var filterCenter = document.getElementById('filterCenter');
    var filterMonth = document.getElementById('filterMonth');
    var filterYear = document.getElementById('filterYear');

    // Show/hide filters based on data type
    if (val === 'user_list' || val === 'center_list') {
        if (filterCenter) filterCenter.style.display = 'none';
        filterMonth.style.display = 'none';
        filterYear.style.display = 'none';
    } else {
        if (filterCenter) filterCenter.style.display = 'block';
        filterMonth.style.display = 'block';
        filterYear.style.display = 'block';
    }
}
</script>