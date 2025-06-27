<?php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/bitrix_api.php';

try {
    $bitrix = new BitrixAPI();
    $result = $bitrix->testConnection();
    
    echo json_encode([
        'success' => true,
        'version' => $result['result']['server_version']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}