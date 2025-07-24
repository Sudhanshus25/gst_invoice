<?php
// require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/tax_calculator.php';

$db = new Database();
$conn = $db->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $id = $_POST['id'] ?? 0;
        
        if ($action === 'add' || $action === 'edit') {
            $name = sanitizeInput($_POST['name']);
            $gstin = sanitizeInput($_POST['gstin']);
            $email = sanitizeInput($_POST['email']);
            $phone = sanitizeInput($_POST['phone']);
            $billing_address = sanitizeInput($_POST['billing_address']);
            $shipping_address = sanitizeInput($_POST['shipping_address']);
            $state_code = sanitizeInput($_POST['state_code']);
            
            // Extract PAN from GSTIN (first 2 digits are state code, next 10 are PAN)
            $pan = substr($gstin, 2, 10);
            
            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO customers 
                    (name, gstin, pan, email, phone, billing_address, shipping_address, state_code) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $gstin, $pan, $email, $phone, $billing_address, $shipping_address, $state_code]);
                $message = "Customer added successfully!";
            } else {
                $stmt = $conn->prepare("UPDATE customers SET 
                    name=?, gstin=?, pan=?, email=?, phone=?, billing_address=?, shipping_address=?, state_code=? 
                    WHERE id=?");
                $stmt->execute([$name, $gstin, $pan, $email, $phone, $billing_address, $shipping_address, $state_code, $id]);
                $message = "Customer updated successfully!";
            }
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM customers WHERE id=?");
            $stmt->execute([$id]);
            $message = "Customer deleted successfully!";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Get all customers
$customers = $conn->query("SELECT * FROM customers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Customer Management</h2>
        
        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#customerModal">
            <i class="bi bi-plus"></i> Add Customer
        </button>
        
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>GSTIN</th>
                    <th>State</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><?= htmlspecialchars($customer['name']) ?></td>
                        <td><?= htmlspecialchars($customer['gstin']) ?></td>
                        <td><?= TaxCalculator::getStateName($customer['state_code']) ?></td>
                        <td><?= htmlspecialchars($customer['email']) ?></td>
                        <td><?= htmlspecialchars($customer['phone']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning edit-customer" 
                                    data-id="<?= $customer['id'] ?>"
                                    data-name="<?= htmlspecialchars($customer['name']) ?>"
                                    data-gstin="<?= htmlspecialchars($customer['gstin']) ?>"
                                    data-email="<?= htmlspecialchars($customer['email']) ?>"
                                    data-phone="<?= htmlspecialchars($customer['phone']) ?>"
                                    data-billing="<?= htmlspecialchars($customer['billing_address']) ?>"
                                    data-shipping="<?= htmlspecialchars($customer['shipping_address']) ?>"
                                    data-state="<?= $customer['state_code'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-customer" 
                                    data-id="<?= $customer['id'] ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Customer Modal -->
    <div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Add Customer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="id" id="customerId" value="">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" name="name" id="customerName" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">GSTIN</label>
                                <input type="text" class="form-control" name="gstin" id="customerGSTIN" pattern="[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}" title="Enter valid GSTIN">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="customerEmail">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" id="customerPhone">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Billing Address</label>
                            <textarea class="form-control" name="billing_address" id="customerBilling" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Shipping Address</label>
                            <textarea class="form-control" name="shipping_address" id="customerShipping" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">State</label>
                            <select class="form-select" name="state_code" id="customerState" required>
                                <option value="">Select State</option>
                                <?php 
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
                                    '22' => 'Chhattisgarh',
                                    '23' => 'Madhya Pradesh',
                                    '24' => 'Gujarat',
                                    '25' => 'Daman and Diu',
                                    '26' => 'Dadra and Nagar Haveli',
                                    '27' => 'Maharashtra',
                                    '28' => 'Andhra Pradesh (Old)',
                                    '29' => 'Karnataka',
                                    '30' => 'Goa',
                                    '31' => 'Lakshadweep',
                                    '32' => 'Kerala',
                                    '33' => 'Tamil Nadu',
                                    '34' => 'Puducherry',
                                    '35' => 'Andaman and Nicobar Islands',
                                    '36' => 'Telangana',
                                    '37' => 'Andhra Pradesh (New)',
                                    '97' => 'Other Territory'
                                ];

                            foreach ($states as $code => $name): ?>
                                <option value="<?= $code ?>"><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
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
                        Are you sure you want to delete this customer?
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
        // Edit customer handler
        document.querySelectorAll('.edit-customer').forEach(button => {
            button.addEventListener('click', function() {
                const modal = new bootstrap.Modal(document.getElementById('customerModal'));
                document.getElementById('modalTitle').textContent = 'Edit Customer';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('customerId').value = this.dataset.id;
                document.getElementById('customerName').value = this.dataset.name;
                document.getElementById('customerGSTIN').value = this.dataset.gstin;
                document.getElementById('customerEmail').value = this.dataset.email;
                document.getElementById('customerPhone').value = this.dataset.phone;
                document.getElementById('customerBilling').value = this.dataset.billing;
                document.getElementById('customerShipping').value = this.dataset.shipping;
                document.getElementById('customerState').value = this.dataset.state;
                modal.show();
            });
        });
        
        // Delete customer handler
        document.querySelectorAll('.delete-customer').forEach(button => {
            button.addEventListener('click', function() {
                const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
                document.getElementById('deleteId').value = this.dataset.id;
                modal.show();
            });
        });
    </script>
</body>
</html>