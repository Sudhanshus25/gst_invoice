<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        INSERT INTO settings 
        (id, bitrix_webhook, bitrix_auth_token, bitrix_invoice_template) 
        VALUES 
        (1, :webhook, :token, :template)
        ON DUPLICATE KEY UPDATE
        bitrix_webhook = VALUES(bitrix_webhook),
        bitrix_auth_token = VALUES(bitrix_auth_token),
        bitrix_invoice_template = VALUES(bitrix_invoice_template)
    ");
    
    $stmt->execute([
        ':webhook' => sanitizeInput($data['webhook_url']),
        ':token' => sanitizeInput($data['auth_token']),
        ':template' => sanitizeInput($data['invoice_template_id'])
    ]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}