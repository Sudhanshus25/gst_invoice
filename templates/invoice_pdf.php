<?php
require_once __DIR__ . '/../includes/tax_calculator.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></title>
    <link rel="stylesheet" href="../assets/css/pdf-template.css">
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <div class="company-name"><?= htmlspecialchars($invoice['business_name'] ?? 'Instloo Private Limited') ?></div>
            <div class="company-address"><?= nl2br(htmlspecialchars($invoice['business_address'] ?? 'A 2402, Prateeek Edifice Sector 107 Noida 201304')) ?></div>
            <div class="gstin">GSTIN: <?= htmlspecialchars($invoice['business_gstin'] ?? '09AAECI8350C1ZN') ?></div>
        </div>

        <div class="invoice-info">
            <div>
                <strong>Invoice No.:</strong> <span><?= htmlspecialchars($invoice['invoice_number']) ?></span>
            </div>
            <div>
                <strong>Invoice Date:</strong> <span><?= htmlspecialchars($invoice['invoice_date']) ?></span>
            </div>
            <div>
                <strong>Order#:</strong> <span><?= htmlspecialchars($invoice['order_number'] ?? '') ?></span>
            </div>
        </div>

        <div class="bill-to">
            <h2>Bill To</h2>
            <div><strong><?= htmlspecialchars($invoice['customer_name']) ?></strong></div>
            <div>
                <?php if (!empty($invoice['customer_phone'])): ?>
                Tel.: <?= htmlspecialchars($invoice['customer_phone']) ?><br>
                <?php endif; ?>
                <?php if (!empty($invoice['customer_email'])): ?>
                Email: <?= htmlspecialchars($invoice['customer_email']) ?><br>
                <?php endif; ?>
                <?php if (!empty($invoice['customer_website'])): ?>
                Website: <?= htmlspecialchars($invoice['customer_website']) ?><br>
                <?php endif; ?>
            </div>
        </div>

        <div class="delivery-info">
            <strong>Place of Delivery</strong><br>
            GSTIN: <?= htmlspecialchars($invoice['customer_gstin']) ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Item</th>
                    <th>HSN</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Discount</th>
                    <th>Tax</th>
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
                    <td class="text-right"><?= $item['discount_amount'] > 0 ? number_format($item['discount_percentage'], 0).'%' : '0%' ?></td>
                    <td class="text-right"><?= number_format($item['tax_rate'] ?? 14, 0) ?>%</td>
                    <td class="text-right">₹<?= number_format($item['total_amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="amount-summary">
            <div>
                <span>Net Amount</span>
                <span>₹<?= number_format($invoice['subtotal'], 2) ?></span>
            </div>
            <div>
                <span>Discount</span>
                <span>₹<?= number_format($invoice['discount_amount'] ?? 0, 2) ?></span>
            </div>
            <div>
                <span>GST <?= number_format($invoice['tax_rate'] ?? 14, 0) ?>%</span>
                <span>₹<?= number_format($invoice['tax_amount'], 2) ?></span>
            </div>
            <?php if ($invoice['customer_state'] === TaxCalculator::BUSINESS_STATE): ?>
                <div>
                    <span>CGST</span>
                    <span>₹<?= number_format($invoice['tax_amount'] / 2, 2) ?></span>
                </div>
                <div>
                    <span>SGST</span>
                    <span>₹<?= number_format($invoice['tax_amount'] / 2, 2) ?></span>
                </div>
            <?php endif; ?>
            <div>
                <span>Tax Total</span>
                <span>₹<?= number_format($invoice['tax_amount'], 2) ?></span>
            </div>
            <div class="total-amount">
                <span>Amount Due</span>
                <span>₹<?= number_format($invoice['total'], 2) ?></span>
            </div>
        </div>

        <div class="terms">
            <strong>Terms & Conditions</strong><br>
            <?= nl2br(htmlspecialchars($invoice['terms'] ?? '*Total Payment due before delivery.
*Goods once sold will not be taken back
*Please include the invoice number on your Cheque
*Cheque bounce charges Rs. 500 will be charged.
*warranty/guarantee is responsible to Principal company')) ?>
        </div>

        <div class="signature">
            Authorized Signature
        </div>

        <div class="footer">
            <?= htmlspecialchars($invoice['notes'] ?? 'Thank you for your business!') ?>
        </div>
    </div>
</body>
</html>