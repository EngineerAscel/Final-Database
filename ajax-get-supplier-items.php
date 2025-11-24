<?php
require 'db.php';

header('Content-Type: application/json');

// Input validation and sanitization
$supplierID = isset($_GET['supplierID']) ? intval($_GET['supplierID']) : 0;

if ($supplierID === 0) {
    echo json_encode(['error' => 'Invalid Supplier ID']);
    exit();
}

$sql = "SELECT productName, category, price, stockQuantity, productsImg 
        FROM products 
        WHERE supplierID = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    // Handle error if prepare fails
    echo json_encode(['error' => 'Database statement error: ' . $conn->error]);
    exit();
}

$stmt->bind_param("i", $supplierID);
$stmt->execute();
$result = $stmt->get_result();
$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

$stmt->close();
echo json_encode($items);
?>
