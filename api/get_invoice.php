<?php
require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/json');

$db = new Database();
$conn = $db->getConnection();

try {
    $invoiceId = $_GET['id'] ?? null;
    
    if (empty($invoiceId)) {
        throw new Exception("Invoice ID is required");
    }

    // Get invoice header
    $stmt = $conn->prepare("
        SELECT i.*, c.name as customer_name, c.gstin as customer_gstin, 
               c.state_code as customer_state, c.billing_address as customer_address
        FROM invoices i
        JOIN customers c ON i.customer_id = c.id
        WHERE i.id = :id
    ");
    $stmt->execute([':id' => $invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        throw new Exception("Invoice not found");
    }

    // Get invoice items
    $stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = :invoice_id");
    $stmt->execute([':invoice_id' => $invoiceId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'invoice' => $invoice,
            'customer' => [
                'id' => $invoice['customer_id'],
                'name' => $invoice['customer_name'],
                'gstin' => $invoice['customer_gstin'],
                'state' => $invoice['customer_state'],
                'billing_address' => $invoice['customer_address']
            ],
            'items' => $items
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}