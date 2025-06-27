<?php
// require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

// Get current settings
$settings = $conn->query("SELECT * FROM settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $businessName = sanitizeInput($_POST['business_name']);
        $businessGSTIN = sanitizeInput($_POST['business_gstin']);
        $businessAddress = sanitizeInput($_POST['business_address']);
        $businessState = sanitizeInput($_POST['business_state']);
        $businessEmail = sanitizeInput($_POST['business_email']);
        $businessPhone = sanitizeInput($_POST['business_phone']);
        $invoicePrefix = sanitizeInput($_POST['invoice_prefix']);
        $bitrixWebhook = sanitizeInput($_POST['bitrix_webhook']);
        $bitrixAuthToken = sanitizeInput($_POST['bitrix_auth_token']);
        $bitrixInvoiceTemplate = sanitizeInput($_POST['bitrix_invoice_template']);
        
        $stmt = $conn->prepare("
            INSERT INTO settings 
            (id, business_name, business_gstin, business_address, business_state, business_email, business_phone, 
             invoice_prefix, bitrix_webhook, bitrix_auth_token, bitrix_invoice_template)
            VALUES 
            (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            business_name = VALUES(business_name),
            business_gstin = VALUES(business_gstin),
            business_address = VALUES(business_address),
            business_state = VALUES(business_state),
            business_email = VALUES(business_email),
            business_phone = VALUES(business_phone),
            invoice_prefix = VALUES(invoice_prefix),
            bitrix_webhook = VALUES(bitrix_webhook),
            bitrix_auth_token = VALUES(bitrix_auth_token),
            bitrix_invoice_template = VALUES(bitrix_invoice_template)
        ");
        
        $stmt->execute([
            $businessName, $businessGSTIN, $businessAddress, $businessState, $businessEmail, $businessPhone,
            $invoicePrefix, $bitrixWebhook, $bitrixAuthToken, $bitrixInvoiceTemplate
        ]);
        
        $message = "Settings updated successfully!";
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Get Indian states
$states = [
    '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', /* ... all states ... */
    '24' => 'Maharashtra', /* ... remaining states ... */
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4">
        <h2>System Settings</h2>
        
        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="post">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Business Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Business Name</label>
                            <input type="text" class="form-control" name="business_name" 
                                   value="<?= htmlspecialchars($settings['business_name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">GSTIN</label>
                            <input type="text" class="form-control" name="business_gstin" 
                                   value="<?= htmlspecialchars($settings['business_gstin'] ?? '') ?>" 
                                   pattern="[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}" 
                                   title="Enter valid GSTIN">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Business Address</label>
                        <textarea class="form-control" name="business_address" rows="3" required><?= htmlspecialchars($settings['business_address'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">State</label>
                            <select class="form-select" name="business_state" required>
                                <option value="">Select State</option>
                                <?php foreach ($states as $code => $name): ?>
                                    <option value="<?= $code ?>" <?= ($settings['business_state'] ?? '') == $code ? 'selected' : '' ?>>
                                        <?= $name ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="business_email" 
                                   value="<?= htmlspecialchars($settings['business_email'] ?? '') ?>" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="business_phone" 
                                   value="<?= htmlspecialchars($settings['business_phone'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Invoice Prefix</label>
                        <input type="text" class="form-control" name="invoice_prefix" 
                               value="<?= htmlspecialchars($settings['invoice_prefix'] ?? 'INV') ?>">
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Bitrix24 Integration</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Webhook URL</label>
                        <input type="url" class="form-control" name="bitrix_webhook" 
                               value="<?= htmlspecialchars($settings['bitrix_webhook'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Auth Token</label>
                        <input type="text" class="form-control" name="bitrix_auth_token" 
                               value="<?= htmlspecialchars($settings['bitrix_auth_token'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Invoice Template ID</label>
                        <input type="text" class="form-control" name="bitrix_invoice_template" 
                               value="<?= htmlspecialchars($settings['bitrix_invoice_template'] ?? '') ?>">
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Email Settings</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SMTP Host</label>
                            <input type="text" class="form-control" name="email_host" 
                                   value="<?= htmlspecialchars($settings['email_host'] ?? '') ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SMTP Port</label>
                            <input type="number" class="form-control" name="email_port" 
                                   value="<?= htmlspecialchars($settings['email_port'] ?? '587') ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SMTP Username</label>
                            <input type="text" class="form-control" name="email_username" 
                                   value="<?= htmlspecialchars($settings['email_username'] ?? '') ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SMTP Password</label>
                            <input type="password" class="form-control" name="email_password" 
                                   value="<?= htmlspecialchars($settings['email_password'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Encryption</label>
                        <select class="form-select" name="email_encryption">
                            <option value="tls" <?= ($settings['email_encryption'] ?? 'tls') == 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= ($settings['email_encryption'] ?? '') == 'ssl' ? 'selected' : '' ?>>SSL</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">From Email</label>
                        <input type="email" class="form-control" name="email_from" 
                               value="<?= htmlspecialchars($settings['email_from'] ?? '') ?>">
                    </div>
                </div>
            </div>
            
            <div class="text-end">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>