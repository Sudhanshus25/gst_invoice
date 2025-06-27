<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/bitrix_api.php';
require_once __DIR__ . '/../src/Invoice.php';

header('Content-Type: application/json');

try {
    $invoiceId = $_POST['invoice_id'] ?? null;
    
    if (!$invoiceId) {
        throw new \Exception('Invoice ID is required');
    }
    
    // Get invoice data
    $invoice = new \GSTInvoice\Invoice();
    $invoiceData = $invoice->getById($invoiceId);
    
    if (!$invoiceData) {
        throw new \Exception('Invoice not found');
    }
    
    // Get customer data
    $customer = new \GSTInvoice\Customer();
    $customerData = $customer->getById($invoiceData['customer_id']);
    
    // Prepare Bitrix data
    $bitrixData = [
        'company_id' => $customerData['bitrix_company_id'],
        'contact_id' => $customerData['bitrix_contact_id'],
        'deal_id' => $invoiceData['bitrix_deal_id'],
        'invoice_number' => $invoiceData['invoice_number'],
        'date' => $invoiceData['invoice_date'],
        'due_date' => $invoiceData['due_date'],
        'total' => $invoiceData['total'],
        'notes' => $invoiceData['notes'],
        'items' => $invoice->getItems($invoiceId)
    ];
    
    // Sync with Bitrix
    $bitrix = new \BitrixAPI();
    $result = $bitrix->createInvoice($bitrixData);
    
    // Update local invoice with Bitrix ID
    $invoice->updateBitrixId($invoiceId, $result['ID']);
    
    echo json_encode([
        'success' => true,
        'bitrix_id' => $result['ID']
    ]);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}