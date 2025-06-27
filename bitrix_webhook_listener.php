<?php
include 'db.php';

// Get incoming POST from Bitrix
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Extract deal ID
$dealId = $data['data']['FIELDS']['ID'] ?? null;
if (!$dealId) {
    http_response_code(400);
    echo "Missing deal ID";
    exit;
}

// Your Bitrix webhook base URL
$webhook = "https://yourdomain.bitrix24.com/rest/1/abcdef123456/";

// Step 1: Get Deal Info
$dealRes = file_get_contents($webhook . "crm.deal.get.json?id=$dealId");
$deal = json_decode($dealRes, true)['result'];

// Step 2: Get Company Info (GSTIN, state, etc.)
$companyId = $deal['COMPANY_ID'];
$companyRes = file_get_contents($webhook . "crm.company.get.json?id=$companyId");
$company = json_decode($companyRes, true)['result'];

// Step 3: Store Company to your DB if not exists
$name = $conn->real_escape_string($company['TITLE']);
$gstin = $company['UF_CRM_1700000000000']; // change field code to your GSTIN field
$billing_state = $company['UF_CRM_1700000000001']; // change to billing state
$email = $company['EMAIL'][0]['VALUE'];

$conn->query("INSERT INTO companies (name, gstin, billing_state, email)
              VALUES ('$name', '$gstin', '$billing_state', '$email')
              ON DUPLICATE KEY UPDATE email='$email'");

$companyLocalId = $conn->insert_id ?: $conn->query("SELECT id FROM companies WHERE gstin = '$gstin'")->fetch_assoc()['id'];

// Step 4: Create invoice using your existing logic
require 'generate_invoice_core.php';
generate_invoice_from_deal($deal, $companyLocalId);

echo "Invoice created successfully";
