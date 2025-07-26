<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$response = [
    'success' => false,
    'message' => '',
    'data' => [],
    'count' => 0
];

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $tableExists = $pdo->query("SHOW TABLES LIKE 'customers'")->rowCount() > 0;
    if (!$tableExists) {
        throw new Exception("Customers table does not exist");
    }

    $id = $_GET['id'] ?? null;

    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        $customer = $stmt->fetch();

        if ($customer) {
            $response['success'] = true;
            $response['data'] = $customer;
            $response['message'] = 'Customer loaded successfully';
            $response['count'] = 1;
        } else {
            http_response_code(404);
            $response['message'] = 'Customer not found';
        }
    } else {
        $stmt = $pdo->query("SELECT * FROM customers ORDER BY name ASC");
        $customers = $stmt->fetchAll();

        $response['success'] = true;
        $response['data'] = $customers;
        $response['count'] = count($customers);
        $response['message'] = 'Customers loaded successfully';
    }

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
