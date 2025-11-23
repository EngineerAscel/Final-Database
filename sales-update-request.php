<?php
session_start();
require 'db.php';

// -------------------
// Check login & role
// -------------------
if (!isset($_SESSION['username'], $_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'sales') {
    die("Error: Only Sales users can submit update requests.");
}

$user_id = $_SESSION['user_id'];

// -------------------
// Check for sales update data
// -------------------
if (!isset($_SESSION['salesUpdateProductID'], $_SESSION['salesUpdateData'])) {
    die("Error: No product update request found in session.");
}

$product_id = $_SESSION['salesUpdateProductID'];
$data = $_SESSION['salesUpdateData'];

// -------------------
// Fetch old product values from DB
// -------------------
$stmt = $conn->prepare("SELECT productName, category, price, stockQuantity, supplierID FROM products WHERE productID = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Error: Product not found.");
}
$old = $result->fetch_assoc();
$stmt->close();

// -------------------
// Prepare insert statements for each changed field
// -------------------
$fields = ['productName', 'category', 'price', 'stockQuantity', 'supplierID'];
foreach ($fields as $field) {
    if (isset($data[$field]) && $data[$field] != $old[$field]) {
        $stmt = $conn->prepare("
            INSERT INTO product_update_requests 
                (productID, salesID, fieldName, oldValue, newValue, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'Pending', NOW())
        ");
        $stmt->bind_param(
            "iisss",
            $product_id,
            $user_id,
            $field,
            $old[$field],
            $data[$field]
        );
        $stmt->execute();
        $stmt->close();
    }
}

// -------------------
// Clear session variables
// -------------------
unset($_SESSION['salesUpdateProductID'], $_SESSION['salesUpdateData'], $_SESSION['salesUpdateFile']);

// -------------------
// Redirect back to sales-products.php with success message
// -------------------
$_SESSION['success'] = "Update request submitted successfully!";
header("Location: sales-products.php");
exit();
?>
