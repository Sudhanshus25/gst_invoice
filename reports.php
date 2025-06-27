<?php
// require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/tax_calculator.php';

$db = new Database();
$conn = $db->getConnection();

// Default to current month
$month = $_GET['month'] ?? date('Y-m');
$reportType = $_GET['report'] ?? 'gstr1';

// Get report data
$startDate = date('Y-m-01', strtotime($month));
$endDate = date('Y-m-t', strtotime($month));

$invoices = $conn->prepare("
    SELECT i.*, c.name AS customer_name, c.gstin AS customer_gstin, c.state_code AS customer_state
    FROM invoices i
    JOIN customers c ON i.customer_id = c.id
    WHERE i.invoice_date BETWEEN ? AND ?
    ORDER BY i.invoice_date
");
$invoices->execute([$startDate, $endDate]);
$invoices = $invoices->fetchAll(PDO::FETCH_ASSOC);

// Calculate report totals
$totals = [
    'total_invoices' => 0,
    'total_value' => 0,
    'total_tax' => 0,
    'cgst' => 0,
    'sgst' => 0,
    'igst' => 0
];

foreach ($invoices as $invoice) {
    $totals['total_invoices']++;
    $totals['total_value'] += $invoice['subtotal'];
    $totals['total_tax'] += $invoice['tax_amount'];
    
    if ($invoice['customer_state'] === TaxCalculator::BUSINESS_STATE) {
        $totals['cgst'] += $invoice['tax_amount'] / 2;
        $totals['sgst'] += $invoice['tax_amount'] / 2;
    } else {
        $totals['igst'] += $invoice['tax_amount'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GST Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4">
        <h2>GST Reports</h2>
        
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Report Type</label>
                        <select name="report" class="form-select">
                            <option value="gstr1" <?= $reportType === 'gstr1' ? 'selected' : '' ?>>GSTR-1 (Outward Supplies)</option>
                            <option value="gstr2" <?= $reportType === 'gstr2' ? 'selected' : '' ?>>GSTR-2 (Inward Supplies)</option>
                            <option value="gstr3b" <?= $reportType === 'gstr3b' ? 'selected' : '' ?>>GSTR-3B (Summary)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Month</label>
                        <input type="month" name="month" class="form-control" value="<?= $month ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Generate</button>
                        <button type="button" class="btn btn-success ms-2" id="exportExcel">
                            <i class="bi bi-file-earmark-excel"></i> Export
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <?= strtoupper($reportType) ?> Report for <?= date('F Y', strtotime($month)) ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Invoice No</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>GSTIN</th>
                                <th>State</th>
                                <th>Taxable Value</th>
                                <th>CGST</th>
                                <th>SGST</th>
                                <th>IGST</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td><?= $invoice['invoice_number'] ?></td>
                                    <td><?= date('d-m-Y', strtotime($invoice['invoice_date'])) ?></td>
                                    <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
                                    <td><?= $invoice['customer_gstin'] ?></td>
                                    <td><?= TaxCalculator::getStateName($invoice['customer_state']) ?></td>
                                    <td>₹<?= number_format($invoice['subtotal'], 2) ?></td>
                                    <td>
                                        <?php if ($invoice['customer_state'] === TaxCalculator::BUSINESS_STATE): ?>
                                            ₹<?= number_format($invoice['tax_amount'] / 2, 2) ?>
                                        <?php else: ?>
                                            ₹0.00
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($invoice['customer_state'] === TaxCalculator::BUSINESS_STATE): ?>
                                            ₹<?= number_format($invoice['tax_amount'] / 2, 2) ?>
                                        <?php else: ?>
                                            ₹0.00
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($invoice['customer_state'] !== TaxCalculator::BUSINESS_STATE): ?>
                                            ₹<?= number_format($invoice['tax_amount'], 2) ?>
                                        <?php else: ?>
                                            ₹0.00
                                        <?php endif; ?>
                                    </td>
                                    <td>₹<?= number_format($invoice['total'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-active">
                                <th colspan="5">Total</th>
                                <th>₹<?= number_format($totals['total_value'], 2) ?></th>
                                <th>₹<?= number_format($totals['cgst'], 2) ?></th>
                                <th>₹<?= number_format($totals['sgst'], 2) ?></th>
                                <th>₹<?= number_format($totals['igst'], 2) ?></th>
                                <th>₹<?= number_format($totals['total_value'] + $totals['total_tax'], 2) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="mt-4">
                    <h5>Summary</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card text-white bg-primary mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Total Invoices</h6>
                                    <p class="card-text h4"><?= $totals['total_invoices'] ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-white bg-success mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Taxable Value</h6>
                                    <p class="card-text h4">₹<?= number_format($totals['total_value'], 2) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-white bg-info mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Total Tax</h6>
                                    <p class="card-text h4">₹<?= number_format($totals['total_tax'], 2) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
    <script>
        // Export to Excel
        document.getElementById('exportExcel').addEventListener('click', function() {
            // Get table data
            const table = document.querySelector('table');
            const workbook = XLSX.utils.table_to_book(table);
            
            // Generate file name
            const reportType = document.querySelector('select[name="report"]').value;
            const month = document.querySelector('input[name="month"]').value;
            const fileName = `${reportType}_${month}.xlsx`;
            
            // Export
            XLSX.writeFile(workbook, fileName);
        });
    </script>
</body>
</html>