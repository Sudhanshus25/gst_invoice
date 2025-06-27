<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../vendor/autoload.php'; // For PHPMailer

header('Content-Type: application/json');

$db = new Database();
$conn = $db->getConnection();

$invoiceId = $_POST['invoice_id'] ?? null;
$email = $_POST['email'] ?? null;

if (!$invoiceId || !$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Invoice ID and email are required']);
    exit;
}

try {
    // Get invoice data
    $stmt = $conn->prepare("
        SELECT i.*, c.name AS customer_name, c.email AS customer_email
        FROM invoices i
        JOIN customers c ON i.customer_id = c.id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        throw new Exception('Invoice not found');
    }
    
    // Generate PDF if not exists
    if (empty($invoice['pdf_path']) || !file_exists($invoice['pdf_path'])) {
        require_once __DIR__ . '/generate_pdf.php';
        $pdfPath = generate_invoice_pdf($invoiceId);
        $stmt = $conn->prepare("UPDATE invoices SET pdf_path = ? WHERE id = ?");
        $stmt->execute([$pdfPath, $invoiceId]);
    } else {
        $pdfPath = $invoice['pdf_path'];
    }
    
    // Send email
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = EMAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = EMAIL_USERNAME;
        $mail->Password = EMAIL_PASSWORD;
        $mail->SMTPSecure = EMAIL_ENCRYPTION;
        $mail->Port = EMAIL_PORT;
        
        // Recipients
        $mail->setFrom(EMAIL_FROM, 'Your Company Name');
        $mail->addAddress($email, $invoice['customer_name']);
        
        // Attachments
        $mail->addAttachment($pdfPath, 'Invoice_' . $invoice['invoice_number'] . '.pdf');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Invoice #' . $invoice['invoice_number'];
        
        $mail->Body = '
            <h2>Invoice #' . $invoice['invoice_number'] . '</h2>
            <p>Dear ' . $invoice['customer_name'] . ',</p>
            <p>Please find attached your invoice for your records.</p>
            <p><strong>Invoice Date:</strong> ' . $invoice['invoice_date'] . '</p>
            <p><strong>Due Date:</strong> ' . $invoice['due_date'] . '</p>
            <p><strong>Total Amount:</strong> ₹' . number_format($invoice['total'], 2) . '</p>
            <p>Thank you for your business!</p>
            <p>Best regards,<br>Your Company Name</p>
        ';
        
        $mail->AltBody = 'Invoice #' . $invoice['invoice_number'] . "\n\n" .
                         'Dear ' . $invoice['customer_name'] . ",\n\n" .
                         'Please find attached your invoice for your records.' . "\n\n" .
                         'Invoice Date: ' . $invoice['invoice_date'] . "\n" .
                         'Due Date: ' . $invoice['due_date'] . "\n" .
                         'Total Amount: ₹' . number_format($invoice['total'], 2) . "\n\n" .
                         'Thank you for your business!' . "\n\n" .
                         'Best regards,' . "\n" .
                         'Your Company Name';
        
        $mail->send();
        
        // Update invoice status
        $stmt = $conn->prepare("UPDATE invoices SET status = 'sent' WHERE id = ?");
        $stmt->execute([$invoiceId]);
        
        echo json_encode(['success' => true, 'message' => 'Email sent successfully']);
    } catch (Exception $e) {
        throw new Exception("Email could not be sent. Error: {$mail->ErrorInfo}");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}