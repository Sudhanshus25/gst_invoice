<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$db = new Database();
$conn = $db->getConnection();

$query = $_GET['q'] ?? '';

try {
    $stmt = $conn->prepare("
        SELECT id, name, hsn_sac_code, rate, tax_rate 
        FROM products 
        WHERE name LIKE :query OR hsn_sac_code LIKE :query
        ORDER BY name
        LIMIT 10
    ");
    
    $searchQuery = "%$query%";
    $stmt->bindParam(':query', $searchQuery);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($products);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}