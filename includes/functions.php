<?php
/**
 * GST Invoice Generator - Core Functions
 */

/**
 * DATABASE HELPER FUNCTIONS
 */

/**
 * Get database connection
 */
function getDB() {
    static $db = null;
    if ($db === null) {
        require_once __DIR__ . '/db_connect.php';
        $database = new Database();
        $db = $database->getConnection();
    }
    return $db;
}

/**
 * Execute a query with parameters
 */
function executeQuery($sql, $params = []) {
    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * INVOICE FUNCTIONS
 */

/**
 * Generate a unique invoice number
 */
function generateInvoiceNumber($prefix = 'INV') {
    $year = date('Y');
    $month = date('m');
    $random = strtoupper(substr(md5(uniqid()), 0, 6));
    
    return sprintf("%s-%s%s-%s", $prefix, $year, $month, $random);
}

/**
 * Calculate GST taxes (CGST/SGST/IGST)
 */
function calculateGST($amount, $customerState, $businessState = '24' /* Maharashtra */) {
    if ($customerState === $businessState) {
        // Intra-state (CGST + SGST)
        $cgst = $amount * 0.09; // 9%
        $sgst = $amount * 0.09; // 9%
        return [
            'cgst' => $cgst,
            'sgst' => $sgst,
            'igst' => 0,
            'total_tax' => $cgst + $sgst,
            'total_amount' => $amount + $cgst + $sgst
        ];
    } else {
        // Inter-state (IGST)
        $igst = $amount * 0.18; // 18%
        return [
            'cgst' => 0,
            'sgst' => 0,
            'igst' => $igst,
            'total_tax' => $igst,
            'total_amount' => $amount + $igst
        ];
    }
}

/**
 * Format currency for display
 */
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

/**
 * CUSTOMER FUNCTIONS
 */

/**
 * Find or create customer
 */
function findOrCreateCustomer($data) {
    $db = getDB();
    
    // Try to find by GSTIN
    if (!empty($data['gstin'])) {
        $stmt = $db->prepare("SELECT id FROM customers WHERE gstin = ?");
        $stmt->execute([$data['gstin']]);
        $customer = $stmt->fetch();
        
        if ($customer) {
            return $customer['id'];
        }
    }
    
    // Create new customer
    $stmt = $db->prepare("
        INSERT INTO customers (name, gstin, email, phone, billing_address, state_code)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['name'],
        $data['gstin'] ?? null,
        $data['email'] ?? null,
        $data['phone'] ?? null,
        $data['billing_address'] ?? null,
        $data['state'] ?? null
    ]);
    
    return $db->lastInsertId();
}

/**
 * PRODUCT FUNCTIONS
 */

/**
 * Get product by ID
 */
function getProduct($id) {
    $stmt = executeQuery("SELECT * FROM products WHERE id = ?", [$id]);
    return $stmt->fetch();
}

/**
 * Get all products
 */
function getAllProducts() {
    $stmt = executeQuery("SELECT id, name, price FROM products ORDER BY name");
    return $stmt->fetchAll();
}

/**
 * UTILITY FUNCTIONS
 */

/**
 * Sanitize user input
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Redirect with message
 */
function redirect($url, $message = null, $type = 'success') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: $url");
    exit();
}

/**
 * Display flash message
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'success';
        
        echo '<div class="alert alert-' . htmlspecialchars($type) . '">' 
             . htmlspecialchars($message) . '</div>';
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}

/**
 * Get Indian state name by code
 */
function getStateName($code) {
    $states = [
        '01' => 'Jammu and Kashmir',
        '02' => 'Himachal Pradesh',
        '03' => 'Punjab',
        '04' => 'Chandigarh',
        '05' => 'Uttarakhand',
        '06' => 'Haryana',
        '07' => 'Delhi',
        '08' => 'Rajasthan',
        '09' => 'Uttar Pradesh',
        '10' => 'Bihar',
        '11' => 'Sikkim',
        '12' => 'Arunachal Pradesh',
        '13' => 'Nagaland',
        '14' => 'Manipur',
        '15' => 'Mizoram',
        '16' => 'Tripura',
        '17' => 'Meghalaya',
        '18' => 'Assam',
        '19' => 'West Bengal',
        '20' => 'Jharkhand',
        '21' => 'Odisha',
        '22' => 'Chattisgarh',
        '23' => 'Madhya Pradesh',
        '24' => 'Gujarat',
        '25' => 'Daman and Diu',
        '26' => 'Dadra and Nagar Haveli',
        '27' => 'Maharashtra',
        '29' => 'Karnataka',
        '30' => 'Goa',
        '31' => 'Lakshadweep',
        '32' => 'Kerala',
        '33' => 'Tamil Nadu',
        '34' => 'Puducherry',
        '35' => 'Andaman and Nicobar Islands',
        '36' => 'Telangana',
        '37' => 'Andhra Pradesh',
        '38' => 'Ladakh',
        '97' => 'Other Territory',
        '99' => 'Centre Jurisdiction'
    ];
    return $states[$code] ?? 'Unknown State';
}

/**
 * Validate GSTIN
 */
function isValidGSTIN($gstin) {
    return preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin);
}

/**
 * FILE HANDLING FUNCTIONS
 */

/**
 * Generate PDF and return path
 */
function generateInvoicePDF($invoiceId) {
    require_once __DIR__ . '/../vendor/autoload.php';
    
    // Get invoice data
    $db = getDB();
    $stmt = $db->prepare("
        SELECT i.*, c.name AS customer_name, c.gstin AS customer_gstin
        FROM invoices i
        JOIN customers c ON i.customer_id = c.id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();
    
    // Generate PDF
    $mpdf = new \Mpdf\Mpdf();
    $html = '<h1>Invoice #' . $invoice['invoice_number'] . '</h1>';
    // ... add more HTML content ...
    
    $mpdf->WriteHTML($html);
    $filename = 'invoices/invoice_' . $invoiceId . '.pdf';
    $mpdf->Output($filename, \Mpdf\Output\Destination::FILE);
    
    return $filename;
}

/**
 * BITRIX24 INTEGRATION FUNCTIONS
 */

/**
 * Sync invoice with Bitrix24
 */
function syncInvoiceWithBitrix($invoiceId) {
    require_once __DIR__ . '/bitrix_api.php';
    
    // Get invoice data
    $db = getDB();
    $stmt = $db->prepare("
        SELECT i.*, c.bitrix_company_id, c.bitrix_contact_id
        FROM invoices i
        JOIN customers c ON i.customer_id = c.id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();
    
    // Prepare Bitrix data
    $bitrixData = [
        'company_id' => $invoice['bitrix_company_id'],
        'contact_id' => $invoice['bitrix_contact_id'],
        'invoice_number' => $invoice['invoice_number'],
        'date' => $invoice['invoice_date'],
        'due_date' => $invoice['due_date'],
        'total' => $invoice['total'],
        'items' => getInvoiceItems($invoiceId)
    ];
    
    // Sync with Bitrix
    $bitrix = new BitrixAPI();
    return $bitrix->createInvoice($bitrixData);
}

/**
 * Get invoice items
 */
function getInvoiceItems($invoiceId) {
    $stmt = executeQuery("
        SELECT description, quantity, rate, amount 
        FROM invoice_items 
        WHERE invoice_id = ?
    ", [$invoiceId]);
    
    return $stmt->fetchAll();
}