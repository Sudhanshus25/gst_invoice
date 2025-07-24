<?php
// Load Composer autoloader — same logic as in test_mpdf.php
$autoloadPath = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    die("Autoloader not found at: $autoloadPath<br>
         Run: <code>composer require mpdf/mpdf</code> in your project root.");
}

require_once $autoloadPath;

// Check if mPDF is available
if (!class_exists('\Mpdf\Mpdf')) {
    die("MPDF class not found. Installation incomplete.<br>
         Run: <code>composer require mpdf/mpdf</code>");
}

// Load DB connection
require_once __DIR__ . '/../includes/db_connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get invoice ID from query string
$invoiceId = $_GET['invoice_id'] ?? null;
if (!$invoiceId) {
    die("Invoice ID is required.");
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Fetch invoice with customer info
    $stmt = $conn->prepare("
        SELECT i.*, 
               c.name AS customer_name, 
               c.gstin AS customer_gstin, 
               c.billing_address AS customer_address, 
               c.state_code AS customer_state
        FROM invoices i
        JOIN customers c ON i.customer_id = c.id
        WHERE i.id = :invoice_id
    ");
    $stmt->execute([':invoice_id' => $invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        die("Invoice not found for ID: $invoiceId");
    }

    // Fetch invoice items
    $stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = :invoice_id");
    $stmt->execute([':invoice_id' => $invoiceId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate HTML
    ob_start();
    include __DIR__ . '/../templates/invoice_pdf.php';
    $html = ob_get_clean();

    // Optionally save HTML for debugging
    // file_put_contents(__DIR__ . '/../tmp/debug_invoice.html', $html);

    // Setup temp directory
    $tempDir = __DIR__ . '/../tmp';
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    // Create PDF
    $mpdf = new \Mpdf\Mpdf([
        'tempDir' => $tempDir,
        'default_font' => 'dejavusans'
    ]);

    $mpdf->WriteHTML($html);
    $pdfFileName = 'invoice_' . $invoice['invoice_number'] . '.pdf';

    // Output to browser (download)
    $mpdf->Output($pdfFileName, \Mpdf\Output\Destination::DOWNLOAD);

} catch (Exception $e) {
    echo "<h2>Error Generating PDF</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}
