<?php
require_once __DIR__ . '/../includes/db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set default empty data structure
$defaultData = [
    'business' => [
        'name' => 'Your Business Name',
        'address' => 'Business Address',
        'gstin' => 'GSTIN Number'
    ],
    'customer' => [
        'name' => 'Customer Name',
        'gstin' => '',
        'state' => '',
        'address' => 'Customer Address'
    ],
    'invoice' => [
        'number' => 'INV-0000',
        'date' => date('Y-m-d'),
        'due_date' => date('Y-m-d', strtotime('+15 days')),
        'subtotal' => 0,
        'total' => 0,
        'terms' => '',
        'notes' => ''
    ],
    'items' => []
];

// Function to parse input data
function parseInput() {
    $input = file_get_contents('php://input');
    
    // Check if input is JSON
    if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON: " . json_last_error_msg());
        }
        return $data;
    }
    
    // Handle URL-encoded form data
    parse_str($input, $formData);
    
    // Convert flat structure to nested array
    $data = [];
    foreach ($formData as $key => $value) {
        $keys = explode('[', str_replace(']', '', $key));
        $current = &$data;
        foreach ($keys as $k) {
            $current = &$current[$k];
        }
        $current = $value;
    }
    
    return $data;
}

try {
    // Get and parse input data
    $inputData = parseInput();
    
    // Merge received data with defaults
    $data = array_replace_recursive($defaultData, $inputData);

    // Generate HTML preview
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Invoice ' . htmlspecialchars($data['invoice']['number']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #333; }
            .invoice-container { max-width: 800px; margin: 0 auto; background: #fff; padding: 20px; }
            .header { display: flex; justify-content: space-between; margin-bottom: 20px; }
            .company-info { flex: 1; }
            .invoice-info { flex: 1; text-align: right; }
            .title { text-align: center; margin: 20px 0; font-size: 24px; font-weight: bold; }
            .customer-info { margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .text-right { text-align: right; }
            .totals { float: right; width: 300px; }
            .footer { margin-top: 50px; font-size: 12px; text-align: center; }
        </style>
    </head>
    <body>
        <div class="invoice-container">
            <div class="header">
                <div class="company-info">
                    <h2>' . htmlspecialchars($data['business']['name']) . '</h2>
                    <p>' . nl2br(htmlspecialchars($data['business']['address'])) . '</p>
                    <p>GSTIN: ' . htmlspecialchars($data['business']['gstin']) . '</p>
                </div>
                <div class="invoice-info">
                    <h3>TAX INVOICE</h3>
                    <p><strong>Invoice #:</strong> ' . htmlspecialchars($data['invoice']['number']) . '</p>
                    <p><strong>Date:</strong> ' . htmlspecialchars($data['invoice']['date']) . '</p>
                    <p><strong>Due Date:</strong> ' . htmlspecialchars($data['invoice']['due_date']) . '</p>
                </div>
            </div>
            
            <div class="customer-info">
                <h4>Bill To:</h4>
                <p><strong>' . htmlspecialchars($data['customer']['name']) . '</strong></p>
                <p>GSTIN: ' . htmlspecialchars($data['customer']['gstin']) . '</p>
                <p>' . nl2br(htmlspecialchars($data['customer']['address'])) . '</p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Description</th>
                        <th>HSN/SAC</th>
                        <th>Qty</th>
                        <th>Rate</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>';

    $counter = 1;
    foreach ($data['items'] as $item) {
        // Set default item values
        $item = array_replace([
            'description' => 'Item Description',
            'hsn_sac' => '',
            'quantity' => 1,
            'rate' => 0,
            'amount' => 0
        ], $item);

        $html .= '
                    <tr>
                        <td>' . $counter++ . '</td>
                        <td>' . htmlspecialchars($item['description']) . '</td>
                        <td>' . htmlspecialchars($item['hsn_sac']) . '</td>
                        <td>' . htmlspecialchars($item['quantity']) . '</td>
                        <td class="text-right">₹' . number_format($item['rate'], 2) . '</td>
                        <td class="text-right">₹' . number_format($item['amount'], 2) . '</td>
                    </tr>';
    }

    $html .= '
                </tbody>
            </table>
            
            <div class="totals">
                <p><strong>Subtotal:</strong> ₹' . number_format($data['invoice']['subtotal'], 2) . '</p>';

    if ($data['customer']['state'] === '24') {
        $html .= '
                <p><strong>CGST (9%):</strong> ₹' . number_format($data['invoice']['subtotal'] * 0.09, 2) . '</p>
                <p><strong>SGST (9%):</strong> ₹' . number_format($data['invoice']['subtotal'] * 0.09, 2) . '</p>';
    } else {
        $html .= '
                <p><strong>IGST (18%):</strong> ₹' . number_format($data['invoice']['subtotal'] * 0.18, 2) . '</p>';
    }

    $html .= '
                <p><strong>Total:</strong> ₹' . number_format($data['invoice']['total'], 2) . '</p>
            </div>
            
            <div class="footer">
                <p>' . nl2br(htmlspecialchars($data['invoice']['terms'])) . '</p>
                <p>' . nl2br(htmlspecialchars($data['invoice']['notes'])) . '</p>
            </div>
        </div>
    </body>
    </html>';

    echo $html;

} catch (Exception $e) {
    // Fallback error display
    echo '<div style="color: red; padding: 20px; border: 1px solid red; margin: 20px;">
            <h2>Error Generating Preview</h2>
            <p>' . htmlspecialchars($e->getMessage()) . '</p>
            <p>Received data: ' . htmlspecialchars(substr($input, 0, 1000)) . '</p>
          </div>';
}