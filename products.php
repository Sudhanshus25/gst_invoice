<?php
// require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

$bitrixProducts = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $id = $_POST['id'] ?? 0;
        
        if ($action === 'add' || $action === 'edit') {
            $name = sanitizeInput($_POST['name']);
            $description = sanitizeInput($_POST['description']);
            $hsn_sac = sanitizeInput($_POST['hsn_sac']);
            $rate = (float)$_POST['rate'];
            $tax_rate = (float)$_POST['tax_rate'];
            $discount = isset($_POST['discount']) ? (float)$_POST['discount'] : 0;
            $is_service = isset($_POST['is_service']) ? 1 : 0;
            
            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO products (name, description, hsn_sac_code, rate, tax_rate, discount, is_service) 
                                      VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description, $hsn_sac, $rate, $tax_rate, $discount, $is_service]);
                $message = "Product added successfully!";
            } else {
                $stmt = $conn->prepare("UPDATE products SET name=?, description=?, hsn_sac_code=?, rate=?, tax_rate=?, discount=?, is_service=? 
                                      WHERE id=?");
                $stmt->execute([$name, $description, $hsn_sac, $rate, $tax_rate, $discount, $is_service, $id]);
                $message = "Product updated successfully!";
            }
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM products WHERE id=?");
            $stmt->execute([$id]);
            $message = "Product deleted successfully!";
        } elseif ($action === 'sync_bitrix') {
            // Handle Bitrix sync with POST request
            $url = 'http://localhost/gst_invoice/api/bitrix_products.php?action=sync';
            $options = [
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-type: application/x-www-form-urlencoded',
                ]
            ];
            $context = stream_context_create($options);
            $response = file_get_contents($url, false, $context);
            
            if ($response === FALSE) {
                throw new Exception("Failed to connect to Bitrix API");
            }
            
            $result = json_decode($response, true);
            
            if ($result && $result['success']) {
                $message = "Successfully synced {$result['count']} products from Bitrix24";
            } else {
                $error = "Failed to sync products from Bitrix24: " . ($result['message'] ?? 'Unknown error');
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all products
$products = $conn->query("SELECT * FROM products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Catalog</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4">
         <h2>Product Catalog</h2>
        
        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <div class="d-flex gap-2 mb-3">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal">
                <i class="bi bi-plus"></i> Add Product
            </button>
            
            <form method="post" class="d-inline" id="sync-bitrix-form">
                <input type="hidden" name="action" value="sync_bitrix">
                <button type="submit" class="btn btn-success" id="sync-bitrix-btn">
                    <i class="bi bi-arrow-repeat"></i> Sync Products from Bitrix24
                </button>
            </form>
        </div>

        
        <table class="table table-striped">
            <thead>
                    <tr>
                        <th>Name</th>
                        <th>HSN/SAC</th>
                        <th>Price</th>
                        <th>Discount</th>
                        <th>Tax Rate</th>
                        <th>Final Price</th>
                        <th>Type</th>
                        <th>Tax</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            <tbody>
                <?php foreach ($products as $product): 
                    $discount = $product['discount'] ?? 0;
                    $discountedPrice = $product['rate'] * (1 - $discount / 100);
                    $finalPrice = $discountedPrice * (1 + $product['tax_rate'] / 100);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($product['name']) ?></td>
                        <td><?= htmlspecialchars($product['hsn_sac_code']) ?></td>
                        <td>
                            <?php if ($discount > 0): ?>
                                <span class="discounted-price">₹<?= number_format($product['rate'], 2) ?></span>
                                ₹<?= number_format($discountedPrice, 2) ?>
                            <?php else: ?>
                                ₹<?= number_format($product['rate'], 2) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= $discount ?>%</td>
                        <td><?= $product['tax_rate'] ?>%</td>
                        <td>₹<?= number_format($finalPrice, 2) ?></td>
                        <td><?= $product['is_service'] ? 'Service' : 'Goods' ?></td>
                        <td class="tax-included">Included</td>
                        <!-- <td>
                            <span class="badge local-badge">Local</span>
                        </td> -->
                        <td>
                            <button class="btn btn-sm btn-warning edit-product" 
                                    data-id="<?= $product['id'] ?>"
                                    data-name="<?= htmlspecialchars($product['name']) ?>"
                                    data-description="<?= htmlspecialchars($product['description']) ?>"
                                    data-hsn="<?= htmlspecialchars($product['hsn_sac_code']) ?>"
                                    data-rate="<?= $product['rate'] ?>"
                                    data-tax="<?= $product['tax_rate'] ?>"
                                    data-service="<?= $product['is_service'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-product" 
                                    data-id="<?= $product['id'] ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Bitrix Products -->
                    <?php foreach ($bitrixProducts as $product): ?>
                        <tr class="product-row bitrix-product">
                            <td><?= htmlspecialchars($product['name']) ?></td>
                            <td><?= htmlspecialchars($product['hsn_sac']) ?></td>
                            <td>₹<?= number_format($product['price'], 2) ?></td>
                            <td>18%</td>
                            <td><?= ucfirst($product['type']) ?></td>
                            <td class="<?= $product['vat_included'] ? 'tax-included' : 'tax-excluded' ?>">
                                <?= $product['vat_included'] ? 'Included' : 'Excluded' ?>
                            </td>
                            <td>
                                <span class="badge bitrix-badge">Bitrix24</span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-success import-product" 
                                        data-bitrix-id="<?= $product['bitrix_id'] ?>">
                                    <i class="bi bi-download"></i> Import
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Add Product</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="id" id="productId" value="">
                        
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" id="productName" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="productDescription"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">HSN/SAC Code</label>
                            <input type="text" class="form-control" name="hsn_sac" id="productHSN">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Base Rate (₹) *</label>
                            <input type="number" class="form-control" name="rate" id="productRate" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Discount (%)</label>
                            <input type="number" class="form-control" name="discount" id="productDiscount" step="0.01" min="0" max="100" value="0">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tax Rate (%)</label>
                            <input type="number" class="form-control" name="tax_rate" id="productTax" step="0.01" min="0" max="100" value="18" required>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="is_service" id="productIsService">
                            <label class="form-check-label" for="productIsService">This is a service</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Bitrix Product Modal -->
    <div class="modal fade" id="importBitrixModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="import_bitrix">
                    <input type="hidden" name="bitrix_id" id="importBitrixId" value="">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Import Product from Bitrix24</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="importProductName" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Price</label>
                            <input type="text" class="form-control" id="importProductPrice" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tax Status</label>
                            <input type="text" class="form-control" id="importProductTax" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">HSN/SAC Code</label>
                            <input type="text" class="form-control" name="hsn_sac" required>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="is_service" id="importIsService">
                            <label class="form-check-label" for="importIsService">This is a service</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteId" value="">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete this product?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Edit product handler
            $('.edit-product').click(function() {
                $('#modalTitle').text('Edit Product');
                $('#formAction').val('edit');
                $('#productId').val($(this).data('id'));
                $('#productName').val($(this).data('name'));
                $('#productDescription').val($(this).data('description'));
                $('#productHSN').val($(this).data('hsn'));
                $('#productRate').val($(this).data('rate'));
                $('#productTax').val($(this).data('tax'));
                $('productDiscount').val($(this).data('discount'));
                $('#productIsService').prop('checked', $(this).data('service') === '1');
                
                new bootstrap.Modal($('#productModal')).show();
            });
            
            // Delete product handler
            $('.delete-product').click(function() {
                $('#deleteId').val($(this).data('id'));
                new bootstrap.Modal($('#deleteModal')).show();
            });
            
            // Import Bitrix product handler
            $('.import-product').click(function() {
                const bitrixId = $(this).data('bitrix-id');
                const productRow = $(this).closest('tr');
                
                $('#importBitrixId').val(bitrixId);
                $('#importProductName').val(productRow.find('td:eq(0)').text());
                $('#importProductPrice').val(productRow.find('td:eq(2)').text());
                $('#importProductTax').val(productRow.find('td:eq(5)').text());
                
                new bootstrap.Modal($('#importBitrixModal')).show();
            });
            
            // Sync Bitrix products
            $('#syncBitrix').click(function() {
                const $btn = $(this);
                $btn.html('<span class="spinner-border spinner-border-sm"></span> Syncing...');
                
                $.post('api/bitrix_products.php?action=sync', function(response) {
                    if (response.success) {
                        alert('Successfully synced ' + response.count + ' products from Bitrix24');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                }).always(function() {
                    $btn.html('<i class="bi bi-cloud-arrow-down"></i> Sync Bitrix Products');
                });
            });
            
            // View toggle
            $('.view-toggle').click(function() {
                const view = $(this).data('view');
                $('.view-toggle').removeClass('active');
                $(this).addClass('active');
                
                if (view === 'all') {
                    $('.product-row').show();
                } else if (view === 'local') {
                    $('.product-row').hide();
                    $('.local-product').show();
                } else if (view === 'bitrix') {
                    $('.product-row').hide();
                    $('.bitrix-product').show();
                }
            });
        });
    </script>
</body>
</html>