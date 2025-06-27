<?php
// require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

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
            $is_service = isset($_POST['is_service']) ? 1 : 0;
            
            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO products (name, description, hsn_sac_code, rate, tax_rate, is_service) 
                                      VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description, $hsn_sac, $rate, $tax_rate, $is_service]);
                $message = "Product added successfully!";
            } else {
                $stmt = $conn->prepare("UPDATE products SET name=?, description=?, hsn_sac_code=?, rate=?, tax_rate=?, is_service=? 
                                      WHERE id=?");
                $stmt->execute([$name, $description, $hsn_sac, $rate, $tax_rate, $is_service, $id]);
                $message = "Product updated successfully!";
            }
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM products WHERE id=?");
            $stmt->execute([$id]);
            $message = "Product deleted successfully!";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
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
        
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#productModal">
            <i class="bi bi-plus"></i> Add Product
        </button>
        
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>HSN/SAC</th>
                    <th>Rate</th>
                    <th>Tax Rate</th>
                    <th>Type</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?= htmlspecialchars($product['name']) ?></td>
                        <td><?= htmlspecialchars($product['hsn_sac_code']) ?></td>
                        <td>₹<?= number_format($product['rate'], 2) ?></td>
                        <td><?= $product['tax_rate'] ?>%</td>
                        <td><?= $product['is_service'] ? 'Service' : 'Goods' ?></td>
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
                            <label class="form-label">Rate (₹)</label>
                            <input type="number" class="form-control" name="rate" id="productRate" step="0.01" min="0" required>
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
        // Edit product handler
        document.querySelectorAll('.edit-product').forEach(button => {
            button.addEventListener('click', function() {
                const modal = new bootstrap.Modal(document.getElementById('productModal'));
                document.getElementById('modalTitle').textContent = 'Edit Product';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('productId').value = this.dataset.id;
                document.getElementById('productName').value = this.dataset.name;
                document.getElementById('productDescription').value = this.dataset.description;
                document.getElementById('productHSN').value = this.dataset.hsn;
                document.getElementById('productRate').value = this.dataset.rate;
                document.getElementById('productTax').value = this.dataset.tax;
                document.getElementById('productIsService').checked = this.dataset.service === '1';
                modal.show();
            });
        });
        
        // Delete product handler
        document.querySelectorAll('.delete-product').forEach(button => {
            button.addEventListener('click', function() {
                const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
                document.getElementById('deleteId').value = this.dataset.id;
                modal.show();
            });
        });
    </script>
</body>
</html>