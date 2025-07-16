<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/database.php';

class BitrixProductManager {
    private $webhookUrl;
    private $authToken;
    private $pdo;
    
    public function __construct() {
        $this->webhookUrl = BITRIX_WEBHOOK_URL;
        $this->authToken = BITRIX_AUTH_TOKEN;
        
        // Initialize database connection
        $this->pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    }
    
    /**
     * Make API call to Bitrix24
     */
    private function callBitrixAPI($method, $params = []) {
        $queryUrl = $this->webhookUrl . $method . '.json';
        $queryData = http_build_query(array_merge($params, ['auth' => $this->authToken]));
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $queryUrl,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $queryData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HEADER => false
        ]);
        
        $result = curl_exec($curl);
        curl_close($curl);
        
        return json_decode($result, true);
    }
    
    /**
     * Get products from Bitrix24
     */
    public function getBitrixProducts() {
        $response = $this->callBitrixAPI('crm.product.list', [
            'order' => ['NAME' => 'ASC'],
            'select' => [
                'ID', 'NAME', 'CODE', 'DESCRIPTION', 
                'PRICE', 'CURRENCY_ID', 'VAT_INCLUDED'
            ]
        ]);
        
        return $response['result'] ?? [];
    }
    
    /**
     * Get single product from Bitrix24
     */
    public function getBitrixProduct($productId) {
        $response = $this->callBitrixAPI('crm.product.get', [
            'id' => $productId
        ]);
        
        return $response['result'] ?? null;
    }
    
    /**
     * Sync products from Bitrix24 to local cache
     */
    public function syncProducts() {
        $bitrixProducts = $this->getBitrixProducts();
        
        // Store in cache file (could also store in database)
        $cacheFile = __DIR__ . '/bitrix_products_cache.json';
        file_put_contents($cacheFile, json_encode($bitrixProducts));
        
        return $bitrixProducts;
    }
    
    /**
     * Import product from Bitrix24 to local database
     */
    public function importProduct($productId, $additionalData = []) {
        $product = $this->getBitrixProduct($productId);
        if (!$product) {
            throw new Exception('Product not found in Bitrix24');
        }
        
        // Prepare data for local database
        $productData = [
            'name' => $product['NAME'],
            'code' => $product['CODE'],
            'description' => $product['DESCRIPTION'],
            'price' => $product['PRICE'],
            'currency' => $product['CURRENCY_ID'],
            'vat_included' => $product['VAT_INCLUDED'] === 'Y' ? 1 : 0,
            'hsn_sac' => $additionalData['hsn_sac'] ?? '',
            'type' => $additionalData['type'] ?? 'service',
            'source' => 'bitrix',
            'bitrix_id' => $product['ID']
        ];
        
        // Check if product already exists
        $stmt = $this->pdo->prepare("SELECT id FROM products WHERE bitrix_id = ?");
        $stmt->execute([$product['ID']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing product
            $query = "UPDATE products SET 
                name = :name, code = :code, description = :description,
                price = :price, currency = :currency, vat_included = :vat_included,
                hsn_sac = :hsn_sac, type = :type, updated_at = NOW()
                WHERE bitrix_id = :bitrix_id";
        } else {
            // Insert new product
            $query = "INSERT INTO products (
                name, code, description, price, currency, 
                vat_included, hsn_sac, type, source, bitrix_id
            ) VALUES (
                :name, :code, :description, :price, :currency,
                :vat_included, :hsn_sac, :type, :source, :bitrix_id
            )";
        }
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($productData);
        
        return [
            'id' => $existing ? $existing['id'] : $this->pdo->lastInsertId(),
            'action' => $existing ? 'updated' : 'created'
        ];
    }
    
    /**
     * Get combined products (local + Bitrix)
     */
    public function getCombinedProducts() {
        // Get local products
        $stmt = $this->pdo->query("SELECT * FROM products WHERE deleted_at IS NULL");
        $localProducts = $stmt->fetchAll();
        
        // Get Bitrix products from cache
        $cacheFile = __DIR__ . '/bitrix_products_cache.json';
        $bitrixProducts = file_exists($cacheFile) ? 
            json_decode(file_get_contents($cacheFile), true) : [];
        
        // Transform Bitrix products to match local format
        $transformedBitrixProducts = array_map(function($product) {
            return [
                'id' => 'bitrix_' . $product['ID'],
                'name' => $product['NAME'],
                'code' => $product['CODE'],
                'description' => $product['DESCRIPTION'],
                'price' => $product['PRICE'],
                'currency' => $product['CURRENCY_ID'],
                'vat_included' => $product['VAT_INCLUDED'] === 'Y',
                'hsn_sac' => '',
                'type' => 'service',
                'source' => 'bitrix',
                'bitrix_id' => $product['ID']
            ];
        }, $bitrixProducts);
        
        return array_merge($localProducts, $transformedBitrixProducts);
    }
}

// Handle the request
try {
    $manager = new BitrixProductManager();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'sync':
            if ($method !== 'POST') throw new Exception('Invalid method');
            $products = $manager->syncProducts();
            echo json_encode([
                'success' => true,
                'count' => count($products),
                'message' => 'Products synced successfully'
            ]);
            break;
            
        case 'import':
            if ($method !== 'POST') throw new Exception('Invalid method');
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input['product_id'])) {
                throw new Exception('Product ID required');
            }
            $result = $manager->importProduct(
                $input['product_id'],
                [
                    'hsn_sac' => $input['hsn_sac'] ?? '',
                    'type' => $input['type'] ?? 'service'
                ]
            );
            echo json_encode([
                'success' => true,
                'product_id' => $result['id'],
                'action' => $result['action'],
                'message' => 'Product ' . $result['action'] . ' successfully'
            ]);
            break;
            
        case 'get':
        default:
            if ($method !== 'GET') throw new Exception('Invalid method');
            $products = $manager->getCombinedProducts();
            echo json_encode([
                'success' => true,
                'data' => $products,
                'count' => count($products)
            ]);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(in_array($e->getCode(), [400, 401, 403, 404]) ? $e->getCode() : 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}