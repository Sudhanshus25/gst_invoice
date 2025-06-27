<?php
function generate_invoice_from_deal($deal, $company_id) {
    global $conn;

    // Basic info
    $date = date('Y-m-d');
    $total = (float)$deal['OPPORTUNITY'];

    // Auto-invoice number
    $prefix = get_fy_prefix();
    $res = $conn->query("SELECT invoice_no FROM invoices WHERE invoice_no LIKE '$prefix%' ORDER BY id DESC LIMIT 1");
    $next = "001";
    if ($res->num_rows > 0) {
        $last = $res->fetch_assoc();
        $last_no = (int)explode("/", $last['invoice_no'])[2];
        $next = str_pad($last_no + 1, 3, '0', STR_PAD_LEFT);
    }
    $invoice_no = $prefix . $next;

    // GST Tax Logic
    $buyer_state = get_buyer_state($company_id, $conn);
    $seller_state = "Karnataka";
    $cgst = $sgst = $igst = 0;
    $tax_rate = 18;

    if (strtolower($seller_state) == strtolower($buyer_state)) {
        $cgst = $total * ($tax_rate / 2) / 100;
        $sgst = $total * ($tax_rate / 2) / 100;
    } else {
        $igst = $total * $tax_rate / 100;
    }

    $grand_total = $total + $cgst + $sgst + $igst;

    // Save invoice
    $stmt = $conn->prepare("INSERT INTO invoices (invoice_no, company_id, date, cgst, sgst, igst, total, pdf_path) VALUES (?, ?, ?, ?, ?, ?, ?, '')");
    $stmt->bind_param("sisssssd", $invoice_no, $company_id, $date, $cgst, $sgst, $igst, $grand_total);
    $stmt->execute();
    $invoice_id = $stmt->insert_id;

    // Generate PDF
    require_once 'mpdf/vendor/autoload.php';
    $mpdf = new \Mpdf\Mpdf();
    $html = "<h2>GST Invoice</h2><p>Deal: {$deal['TITLE']}<br>Invoice No: $invoice_no</p><p>Total: ₹$grand_total</p>";
    $mpdf->WriteHTML($html);
    $pdf_path = "pdfs/$invoice_no.pdf";
    $mpdf->Output($pdf_path, \Mpdf\Output\Destination::FILE);

    // Update invoice with PDF path
    $conn->query("UPDATE invoices SET pdf_path = '$pdf_path' WHERE id = $invoice_id");
}
