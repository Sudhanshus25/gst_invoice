<?php
require_once __DIR__ . '/../config/database.php';

class BitrixAPI {
    private $webhookUrl;
    private $authToken;
    
    public function __construct() {
        $this->webhookUrl = BITRIX_WEBHOOK_URL;
        $this->authToken = BITRIX_AUTH_TOKEN;
    }
    
    public function createInvoice(array $invoiceData) {
        $method = 'crm.invoice.add';
        
        $bitrixData = [
            'fields' => [
                'UF_COMPANY_ID' => $invoiceData['company_id'],
                'UF_CONTACT_ID' => $invoiceData['contact_id'],
                'UF_DEAL_ID' => $invoiceData['deal_id'],
                'ACCOUNT_NUMBER' => $invoiceData['invoice_number'],
                'STATUS_ID' => 'N',
                'DATE_INSERT' => date('Y-m-d H:i:s'),
                'DATE_BILL' => $invoiceData['date'],
                'DATE_PAY_BEFORE' => $invoiceData['due_date'],
                'PRICE' => $invoiceData['total'],
                'CURRENCY' => 'INR',
                'COMMENTS' => $invoiceData['notes'],
                'TEMPLATE_ID' => BITRIX_INVOICE_TEMPLATE_ID
            ]
        ];
        
        // Add products
        foreach ($invoiceData['items'] as $item) {
            $bitrixData['fields']['PRODUCT_ROWS'][] = [
                'PRODUCT_NAME' => $item['description'],
                'PRICE' => $item['rate'],
                'QUANTITY' => $item['quantity'],
                'TAX_RATE' => $item['tax_rate']
            ];
        }
        
        return $this->call($method, $bitrixData);
    }
    
    private function call($method, $params = []) {
        $queryUrl = $this->webhookUrl . $method;
        $queryData = http_build_query(array_merge($params, ['auth' => $this->authToken]));
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST => true,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $queryUrl,
            CURLOPT_POSTFIELDS => $queryData,
        ]);
        
        $result = curl_exec($curl);
        curl_close($curl);
        
        $response = json_decode($result, true);
        
        if (isset($response['error'])) {
            throw new \Exception($response['error_description']);
        }
        
        return $response['result'];
    }
}