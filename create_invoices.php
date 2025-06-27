<?php include 'db.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Create GST Invoice</title>
</head>
<body>
<h2>Create Invoice</h2>
<form action="generate_pdf.php" method="POST">
    <label>Invoice Date:</label>
    <input type="date" name="invoice_date" required><br>

    <label>Company:</label>
    <select name="company_id" required>
        <?php
        $res = $conn->query("SELECT id, name FROM companies");
        while ($row = $res->fetch_assoc()) {
            echo "<option value='{$row['id']}'>{$row['name']}</option>";
        }
        ?>
    </select><br>

    <label>Items:</label>
    <div id="items">
        <input type="text" name="item_names[]" placeholder="Item Name" required>
        <input type="number" name="quantities[]" placeholder="Qty" required>
        <input type="number" name="rates[]" step="0.01" placeholder="Rate" required>
    </div>
    <button type="submit">Generate Invoice</button>
</form>
</body>
</html>
