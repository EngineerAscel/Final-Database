<?php
session_start();

// ✅ Protect page
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

// ✅ Add Client + Sale
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_client_sale'])) {
    $clientName    = $_POST['clientName'];
    $contactNumber = $_POST['contactNumber'];
    $email         = $_POST['email'];
    $address       = $_POST['address'];

    $stmt = $conn->prepare("INSERT INTO clientinfo (clientName, contactNumber, email, address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $clientName, $contactNumber, $email, $address);
    $stmt->execute();
    $clientID = $stmt->insert_id;
    $stmt->close();

    $productID = intval($_POST['productID']);
    $userID    = intval($_POST['userID']);
    $quantity  = intval($_POST['quantity']);
    $unitPrice = floatval($_POST['unitPrice']);
    $totalAmount = $quantity * $unitPrice;

    $stmt = $conn->prepare("INSERT INTO sales (clientID, productID, userID, quantity, unitPrice, totalAmount, saleDate) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiiidd", $clientID, $productID, $userID, $quantity, $unitPrice, $totalAmount);
    $stmt->execute();
    $stmt->close();

    header("Location: sales-sales.php");
    exit();
}

// ✅ Update Sale
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_sale'])) {
    $saleID = intval($_POST['saleID']);
    $clientID = intval($_POST['clientID']);
    $productID = intval($_POST['productID']);
    $userID = intval($_POST['userID']);
    $quantity = intval($_POST['quantity']);
    $unitPrice = floatval($_POST['unitPrice']);
    $totalAmount = $quantity * $unitPrice;

    $stmt = $conn->prepare("UPDATE sales SET clientID=?, productID=?, userID=?, quantity=?, unitPrice=?, totalAmount=? WHERE saleID=?");
    $stmt->bind_param("iiiiddi", $clientID, $productID, $userID, $quantity, $unitPrice, $totalAmount, $saleID);
    $stmt->execute();
    $stmt->close();

    header("Location: sales.php");
    exit();
}

// ✅ Delete Sale
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_sale'])) {
    $saleID = intval($_POST['saleID']);
    $stmt = $conn->prepare("DELETE FROM sales WHERE saleID=?");
    $stmt->bind_param("i", $saleID);
    $stmt->execute();
    $stmt->close();

    header("Location: sales-sales.php");
    exit();
}

// ✅ Fetch Sales
$salesResult = $conn->query("SELECT * FROM sales ORDER BY saleID ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Management</title>
    <link rel="stylesheet" href="css/Sales.css">
</head>
<body>

<?php include("sales-sidebar.php") ?>

<div class="main-content">
    <header class="topbar">
        <h1>💰 Sales</h1>
        <div class="settings-menu">
            <button class="settings-btn">&#9776;</button>
            <div class="settings-dropdown">
                <button class="add-btn" onclick="document.getElementById('addModal').style.display='flex'">+ Add Sale</button>
            </div>
        </div>
    </header>

    <!-- ✅ Sales Table -->
    <table class="sales-table">
        <thead>
            <tr>
                <th>Sale ID</th>
                <th>Client ID</th>
                <th>Product ID</th>
                <th>User ID</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Total Amount</th>
                <th>Sale Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $salesResult->fetch_assoc()): ?>
            <tr>
                <td><?= $row['saleID'] ?></td>
                <td><?= $row['clientID'] ?></td>
                <td><?= $row['productID'] ?></td>
                <td><?= $row['userID'] ?></td>
                <td><?= $row['quantity'] ?></td>
                <td><?= $row['unitPrice'] ?></td>
                <td><?= $row['totalAmount'] ?></td>
                <td><?= $row['saleDate'] ?></td>
                <td>
                    <div class="action">
                        <button class="update-btn"
                            onclick="openUpdateModal(
                                '<?= $row['saleID'] ?>',
                                '<?= $row['clientID'] ?>',
                                '<?= $row['productID'] ?>',
                                '<?= $row['userID'] ?>',
                                '<?= $row['quantity'] ?>',
                                '<?= $row['unitPrice'] ?>'
                            )">Update</button>

                        <button class="delete-btn" onclick="openDeleteModal(<?= $row['saleID'] ?>)">Delete</button>
                    </div>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- ✅ Add Modal -->
<div class="modal" id="addModal">
    <div class="modal-content">
        <h3>Add New Client & Sale</h3>
        <form method="POST" action="sales.php">
            <label>Client Name:</label>
            <input type="text" name="clientName" required><br>
            <label>Contact Number:</label>
            <input type="text" name="contactNumber"><br>
            <label>Email:</label>
            <input type="email" name="email"><br>
            <label>Address:</label>
            <input type="text" name="address"><br><br>

            <label>Product ID:</label>
            <input type="number" name="productID" required><br>
            <label>User ID:</label>
            <input type="number" name="userID" required><br>
            <label>Quantity:</label>
            <input type="number" name="quantity" id="quantity"><br>
            <label>Unit Price:</label>
            <input type="number" step="0.01" name="unitPrice" id="unitPrice"><br>
            <label>Total Amount:</label>
            <input type="text" name="totalAmount" id="totalAmount" readonly><br>

            <button type="submit" name="add_client_sale">Save</button>
            <button type="button" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
        </form>
    </div>
</div>

<!-- ✅ Update Modal -->
<div class="modal" id="updateModal">
    <div class="modal-content">
        <h3>Update Sale</h3>
        <form method="POST" action="sales.php">
            <input type="hidden" name="saleID" id="updateSaleID">

            <label>Client ID:</label>
            <input type="number" name="clientID" id="updateClientID" required><br>

            <label>Product ID:</label>
            <input type="number" name="productID" id="updateProductID" required><br>

            <label>User ID:</label>
            <input type="number" name="userID" id="updateUserID" required><br>

            <label>Quantity:</label>
            <input type="number" name="quantity" id="updateQuantity" required><br>

            <label>Unit Price:</label>
            <input type="number" step="0.01" name="unitPrice" id="updateUnitPrice" required><br>

            <div style="margin-top:10px;">
                <button type="submit" name="update_sale">Update</button>
                <button type="button" onclick="document.getElementById('updateModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ✅ Delete Modal -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete this sale?</p>
        <form method="POST" action="sales.php">
            <input type="hidden" name="saleID" id="deleteSaleID">
            <button type="submit" name="delete_sale">Yes, Delete</button>
            <button type="button" onclick="document.getElementById('deleteModal').style.display='none'">Cancel</button>
        </form>
    </div>
</div>

<script>
// ✅ Auto calculate totalAmount
document.getElementById("quantity")?.addEventListener("input", calcTotal);
document.getElementById("unitPrice")?.addEventListener("input", calcTotal);
function calcTotal() {
    let qty = parseFloat(document.getElementById("quantity").value) || 0;
    let price = parseFloat(document.getElementById("unitPrice").value) || 0;
    document.getElementById("totalAmount").value = (qty * price).toFixed(2);
}

// ✅ Open Update Modal with Data
function openUpdateModal(saleID, clientID, productID, userID, quantity, unitPrice) {
    document.getElementById("updateSaleID").value = saleID;
    document.getElementById("updateClientID").value = clientID;
    document.getElementById("updateProductID").value = productID;
    document.getElementById("updateUserID").value = userID;
    document.getElementById("updateQuantity").value = quantity;
    document.getElementById("updateUnitPrice").value = unitPrice;
    document.getElementById("updateModal").style.display = "flex";
}

// ✅ Open Delete Modal
function openDeleteModal(saleID) {
    document.getElementById("deleteSaleID").value = saleID;
    document.getElementById("deleteModal").style.display = "flex";
}

// ✅ Toggle Settings Menu
document.querySelector(".settings-btn").addEventListener("click", function() {
    document.querySelector(".settings-menu").classList.toggle("show");
});
</script>

</body>
</html>
