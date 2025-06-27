<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db_connect.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get monthly invoice count
    $stmt = $conn->query("SELECT COUNT(*) FROM invoices WHERE MONTH(invoice_date) = MONTH(CURRENT_DATE())");
    $monthInvoices = $stmt->fetchColumn();
    
    // Get monthly revenue
    $stmt = $conn->query("SELECT SUM(total) FROM invoices WHERE MONTH(invoice_date) = MONTH(CURRENT_DATE())");
    $monthRevenue = $stmt->fetchColumn();
    
    // Get pending invoices
    $stmt = $conn->query("SELECT COUNT(*) FROM invoices WHERE status = 'pending'");
    $pendingInvoices = $stmt->fetchColumn();
    
    // Get recent invoices
    $stmt = $conn->prepare("
        SELECT i.*, c.name AS customer_name, c.gstin AS customer_gstin
        FROM invoices i
        JOIN customers c ON i.customer_id = c.id
        ORDER BY i.invoice_date DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'month_invoices' => $monthInvoices,
        'month_revenue' => $monthRevenue,
        'pending_invoices' => $pendingInvoices,
        'recent_invoices' => $recentInvoices
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}