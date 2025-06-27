<?php
require_once __DIR__ . '/../includes/db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

// Generate HTML preview
$html = '
<!DOCTYPE html>
<html>
<head>
    <title>Invoice ' . htmlspecialchars($data['invoice']['number']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #333; }
        .invoice-container { max-width: 800px; margin: 0 auto; background: #fff; padding: 20px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .company-info { flex: 1; }
        .invoice-info { flex: 1; text-align: right; }
        .title { text-align: center; margin: 20px 0; font-size: 24px; font-weight: bold; }
        .customer-info { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .text-right { text-align: right; }
        .totals { float: right; width: 300px; }
        .footer { margin-top: 50px; font-size: 12px; text-align: center; }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <div class="company-info">
                <h2>' . htmlspecialchars($data['business']['name']) . '</h2>
                <p>' . nl2br(htmlspecialchars($data['business']['address'])) . '</p>
                <p>GSTIN: ' . htmlspecialchars($data['business']['gstin']) . '</p>
            </div>
            <div class="invoice-info">
                <h3>TAX INVOICE</h3>
                <p><strong>Invoice #:</strong> ' . htmlspecialchars($data['invoice']['number']) . '</p>
                <p><strong>Date:</strong> ' . htmlspecialchars($data['invoice']['date']) . '</p>
                <p><strong>Due Date:</strong> ' . htmlspecialchars($data['invoice']['due_date']) . '</p>
            </div>
        </div>
        
        <div class="customer-info">
            <h4>Bill To:</h4>
            <p><strong>' . htmlspecialchars($data['customer']['name']) . '</strong></p>
            <p>GSTIN: ' . htmlspecialchars($data['customer']['gstin']) . '</p>
            <p>' . nl2br(htmlspecialchars($data['customer']['address'])) . '</p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th>HSN/SAC</th>
                    <th>Qty</th>
                    <th>Rate</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>';

$counter = 1;
foreach ($data['items'] as $item) {
    $html .= '
                <tr>
                    <td>' . $counter++ . '</td>
                    <td>' . htmlspecialchars($item['description']) . '</td>
                    <td>' . htmlspecialchars($item['hsn_sac']) . '</td>
                    <td>' . htmlspecialchars($item['quantity']) . '</td>
                    <td class="text-right">₹' . number_format($item['rate'], 2) . '</td>
                    <td class="text-right">₹' . number_format($item['amount'], 2) . '</td>
                </tr>';
}

$html .= '
            </tbody>
        </table>
        
        <div class="totals">
            <p><strong>Subtotal:</strong> ₹' . number_format($data['invoice']['subtotal'], 2) . '</p>';

if ($data['customer']['state'] === '24') {
    $html .= '
            <p><strong>CGST (9%):</strong> ₹' . number_format($data['invoice']['subtotal'] * 0.09, 2) . '</p>
            <p><strong>SGST (9%):</strong> ₹' . number_format($data['invoice']['subtotal'] * 0.09, 2) . '</p>';
} else {
    $html .= '
            <p><strong>IGST (18%):</strong> ₹' . number_format($data['invoice']['subtotal'] * 0.18, 2) . '</p>';
}

$html .= '
            <p><strong>Total:</strong> ₹' . number_format($data['invoice']['total'], 2) . '</p>
        </div>
        
        <div class="footer">
            <p>' . nl2br(htmlspecialchars($data['invoice']['terms'])) . '</p>
            <p>' . nl2br(htmlspecialchars($data['invoice']['notes'])) . '</p>
        </div>
    </div>
</body>
</html>';

echo $html;