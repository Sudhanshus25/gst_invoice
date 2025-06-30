<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get business settings from database
    $stmt = $conn->query("SELECT * FROM settings WHERE id = 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        // Return default settings if none exist
        echo json_encode([
            'success' => false,
            'error' => 'Business settings not configured',
            'default_settings' => [
                'name' => 'Your Business Name',
                'gstin' => '',
                'address' => 'Your Business Address',
                'state_code' => '24', // Default to Maharashtra
                'email' => '',
                'phone' => '',
                'invoice_prefix' => 'INV'
            ]
        ]);
        exit;
    }

    // Return the business settings
    echo json_encode([
        'success' => true,
        'name' => $settings['business_name'],
        'gstin' => $settings['business_gstin'],
        'address' => $settings['business_address'],
        'state_code' => $settings['business_state'],
        'email' => $settings['business_email'],
        'phone' => $settings['business_phone'],
        'invoice_prefix' => $settings['invoice_prefix'] ?? 'INV',
        'bitrix_webhook' => $settings['bitrix_webhook'] ?? '',
        'bitrix_auth_token' => $settings['bitrix_auth_token'] ?? ''
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Application error: ' . $e->getMessage()
    ]);
}