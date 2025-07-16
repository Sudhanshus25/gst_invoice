<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Create a response array
$response = [
    'success' => false,
    'message' => '',
    'data' => [],
    'count' => 0
];

try {
    // Create database connection
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Check if customers table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'customers'")->rowCount() > 0;
    
    if (!$tableExists) {
        throw new Exception("Customers table does not exist");
    }

    // Get customers from database
    $stmt = $pdo->query("
        SELECT 
            id,
            name,
            gstin,
            phone,
            email,
            address,
            state_code,
            created_at,
            updated_at
        FROM customers
        WHERE deleted_at IS NULL
        ORDER BY name ASC
    ");
    
    $customers = $stmt->fetchAll();

    // Format the response
    $response = [
        'success' => true,
        'data' => $customers,
        'count' => count($customers),
        'message' => 'Customers loaded successfully'
    ];

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    http_response_code(500);
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    http_response_code(400);
}

echo json_encode($response);