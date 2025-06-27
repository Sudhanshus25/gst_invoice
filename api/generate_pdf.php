<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/db_connect.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$invoiceId = $_GET['invoice_id'] ?? null;

if (!$invoiceId) {
    die('Invoice ID is required');
}

$db = new Database();
$conn = $db->getConnection();

try {
    // Get invoice data
    $stmt = $conn->prepare("
        SELECT i.*, c.name AS customer_name, c.gstin AS customer_gstin, 
               c.billing_address AS customer_address, c.state_code AS customer_state
        FROM invoices i
        JOIN customers c ON i.customer_id = c.id
        WHERE i.id = :invoice_id
    ");
    $stmt->execute([':invoice_id' => $invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        die('Invoice not found');
    }
    
    // Get items
    $stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = :invoice_id");
    $stmt->execute([':invoice_id' => $invoiceId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate HTML
    ob_start();
    include __DIR__ . '/../templates/invoice_pdf.php';
    $html = ob_get_clean();
    
    // Generate PDF
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Output PDF
    $dompdf->stream("invoice_{$invoice['invoice_number']}.pdf", [
        'Attachment' => 1
    ]);
    
} catch (Exception $e) {
    die('Error generating PDF: ' . $e->getMessage());
}