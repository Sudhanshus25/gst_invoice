<?php
require_once __DIR__ . '/mpdf/vendor/autoload.php';
include 'db.php';

$date = $_POST['invoice_date'];
$company_id = $_POST['company_id'];
$items = $_POST['item_names'];
$qtys = $_POST['quantities'];
$rates = $_POST['rates'];

$html = "<h2>GST Invoice</h2><p>Date: $date</p><table><tr><th>Item</th><th>Qty</th><th>Rate</th><th>Total</th></tr>";

$total = 0;
foreach ($items as $i => $name) {
    $qty = $qtys[$i];
    $rate = $rates[$i];
    $item_total = $qty * $rate;
    $total += $item_total;
    $html .= "<tr><td>$name</td><td>$qty</td><td>$rate</td><td>$item_total</td></tr>";
}
$html .= "</table><p>Total: ₹$total</p>";

$mpdf = new \Mpdf\Mpdf();
$mpdf->WriteHTML($html);
$pdfPath = "invoice_" . time() . ".pdf";
$mpdf->Output("pdfs/$pdfPath", \Mpdf\Output\Destination::FILE);

echo "Invoice generated! <a href='pdfs/$pdfPath'>Download PDF</a>";
