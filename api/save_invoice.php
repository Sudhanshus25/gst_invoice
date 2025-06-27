<?php
require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/json');

$db = new Database();
$conn = $db->getConnection();

$data = json_decode(file_get_contents('php://input'), true);

try {
    // Begin transaction
    $conn->beginTransaction();
    
    // Save invoice
    $stmt = $conn->prepare("
        INSERT INTO invoices (
            bitrix_deal_id, invoice_number, invoice_date, due_date, customer_id,
            subtotal, tax_amount, total, status, notes, terms
        ) VALUES (
            :bitrix_deal_id, :invoice_number, :invoice_date, :due_date, :customer_id,
            :subtotal, :tax_amount, :total, :status, :notes, :terms
        )
    ");
    
    $stmt->execute([
        ':bitrix_deal_id' => null, // Will be set when syncing with Bitrix
        ':invoice_number' => $data['invoice']['number'],
        ':invoice_date' => $data['invoice']['date'],
        ':due_date' => $data['invoice']['due_date'],
        ':customer_id' => $this->findOrCreateCustomer($conn, $data['customer']),
        ':subtotal' => $data['invoice']['subtotal'],
        ':tax_amount' => $data['invoice']['total'] - $data['invoice']['subtotal'],
        ':total' => $data['invoice']['total'],
        ':status' => $data['status'],
        ':notes' => $data['invoice']['notes'],
        ':terms' => $data['invoice']['terms']
    ]);
    
    $invoiceId = $conn->lastInsertId();
    
    // Save items
    foreach ($data['items'] as $item) {
        $stmt = $conn->prepare("
            INSERT INTO invoice_items (
                invoice_id, hsn_sac_code, description, quantity, rate, amount, tax_rate, tax_type
            ) VALUES (
                :invoice_id, :hsn_sac_code, :description, :quantity, :rate, :amount, :tax_rate, :tax_type
            )
        ");
        
        $taxType = $data['customer']['state'] === '24' ? 'cgst_sgst' : 'igst';
        $taxRate = $taxType === 'cgst_sgst' ? 9 : 18; // Simplified for example
        
        $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':hsn_sac_code' => $item['hsn_sac'],
            ':description' => $item['description'],
            ':quantity' => $item['quantity'],
            ':rate' => $item['rate'],
            ':amount' => $item['amount'],
            ':tax_rate' => $taxRate,
            ':tax_type' => $taxType
        ]);
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'invoice_id' => $invoiceId]);
} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function findOrCreateCustomer($conn, $customerData) {
    // Check if customer exists by GSTIN
    $stmt = $conn->prepare("SELECT id FROM customers WHERE gstin = :gstin");
    $stmt->execute([':gstin' => $customerData['gstin']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        return $existing['id'];
    }
    
    // Create new customer
    $stmt = $conn->prepare("
        INSERT INTO customers (
            bitrix_contact_id, name, gstin, pan, billing_address, state_code
        ) VALUES (
            :bitrix_contact_id, :name, :gstin, :pan, :billing_address, :state_code
        )
    ");
    
    $stmt->execute([
        ':bitrix_contact_id' => null,
        ':name' => $customerData['name'],
        ':gstin' => $customerData['gstin'],
        ':pan' => substr($customerData['gstin'], 2, 10), // Extract PAN from GSTIN
        ':billing_address' => $customerData['address'],
        ':state_code' => $customerData['state']
    ]);
    
    return $conn->lastInsertId();
}