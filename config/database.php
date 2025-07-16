<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'gst_invoice');
define('DB_USER', 'root');
define('DB_PASS', '');

// Test connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Bitrix24 Configuration
define('BITRIX_WEBHOOK_URL', 'https://yourdomain.bitrix24.com/rest/');
define('BITRIX_AUTH_TOKEN', 'your_oauth_token');
define('BITRIX_INVOICE_TEMPLATE_ID', '123'); // Your Bitrix invoice template ID