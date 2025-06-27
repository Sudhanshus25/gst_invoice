<?php
// This template is used by the PDF generator
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; margin: 0; padding: 20px; color: #333; }
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
        .signature { margin-top: 50px; text-align: right; }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <div class="company-info">
                <h2><?= htmlspecialchars($invoice['business_name'] ?? 'Your Business Name') ?></h2>
                <p><?= nl2br(htmlspecialchars($invoice['business_address'] ?? '123 Business Street, City, State - 123456')) ?></p>
                <p>GSTIN: <?= htmlspecialchars($invoice['business_gstin'] ?? '22AAAAA0000A1Z5') ?></p>
            </div>
            <div class="invoice-info">
                <h3>TAX INVOICE</h3>
                <p><strong>Invoice #:</strong> <?= htmlspecialchars($invoice['invoice_number']) ?></p>
                <p><strong>Date:</strong> <?= htmlspecialchars($invoice['invoice_date']) ?></p>
                <p><strong>Due Date:</strong> <?= htmlspecialchars($invoice['due_date']) ?></p>
            </div>
        </div>
        
        <div class="customer-info">
            <h4>Bill To:</h4>
            <p><strong><?= htmlspecialchars($invoice['customer_name']) ?></strong></p>
            <p>GSTIN: <?= htmlspecialchars($invoice['customer_gstin']) ?></p>
            <p><?= nl2br(htmlspecialchars($invoice['customer_address'])) ?></p>
            <p>State: <?= TaxCalculator::getStateName($invoice['customer_state']) ?></p>
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
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($item['description']) ?></td>
                    <td><?= htmlspecialchars($item['hsn_sac_code']) ?></td>
                    <td><?= htmlspecialchars($item['quantity']) ?></td>
                    <td class="text-right">₹<?= number_format($item['rate'], 2) ?></td>
                    <td class="text-right">₹<?= number_format($item['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="totals">
            <p><strong>Subtotal:</strong> ₹<?= number_format($invoice['subtotal'], 2) ?></p>
            <?php if ($invoice['customer_state'] === TaxCalculator::BUSINESS_STATE): ?>
                <p><strong>CGST (9%):</strong> ₹<?= number_format($invoice['tax_amount'] / 2, 2) ?></p>
                <p><strong>SGST (9%):</strong> ₹<?= number_format($invoice['tax_amount'] / 2, 2) ?></p>
            <?php else: ?>
                <p><strong>IGST (18%):</strong> ₹<?= number_format($invoice['tax_amount'], 2) ?></p>
            <?php endif; ?>
            <p><strong>Total:</strong> ₹<?= number_format($invoice['total'], 2) ?></p>
        </div>
        
        <div class="signature">
            <p>Authorized Signatory</p>
            <p><strong><?= htmlspecialchars($invoice['business_name'] ?? 'Your Business Name') ?></strong></p>
        </div>
        
        <div class="footer">
            <p><?= nl2br(htmlspecialchars($invoice['terms'])) ?></p>
            <p><?= nl2br(htmlspecialchars($invoice['notes'])) ?></p>
        </div>
    </div>
</body>
</html>