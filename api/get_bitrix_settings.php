<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db_connect.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->query("SELECT * FROM settings WHERE id = 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        $settings = [
            'webhook_url' => '',
            'auth_token' => '',
            'invoice_template_id' => ''
        ];
    }
    
    // Get sync status (example - implement your own logic)
    $syncStatus = [
        'customers' => ['last_sync' => 'Never', 'status' => 'Not synced'],
        'products' => ['last_sync' => 'Never', 'status' => 'Not synced'],
        'invoices' => ['last_sync' => 'Never', 'status' => 'Not synced']
    ];
    
    echo json_encode([
        'webhook_url' => $settings['bitrix_webhook'] ?? '',
        'auth_token' => $settings['bitrix_auth_token'] ?? '',
        'invoice_template_id' => $settings['bitrix_invoice_template'] ?? '',
        'sync_status' => $syncStatus
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}