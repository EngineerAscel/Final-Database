<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$requestedBy = $_SESSION['username'];

// ------------------- POST: Handle stock request -------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_existing_stock'])) {

    $supplierID    = isset($_POST['supplierID']) ? intval($_POST['supplierID']) : 0;
    $stockQuantity = isset($_POST['stockQuantity']) ? intval($_POST['stockQuantity']) : 0;
    $productName   = $_POST['productName'] ?? '';
    $category      = $_POST['category'] ?? '';
    $price         = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $imagePath     = $_POST['productsImg'] ?? ''; // optional

    if ($supplierID <= 0 || empty($productName) || $stockQuantity <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request data']);
        exit();
    }

    // ------------------- Check if product already exists -------------------
    $check = $conn->prepare("
        SELECT id, stockQuantity, imagePath, category, price, status
        FROM product_add_requests 
        WHERE productName=? AND supplierID=? AND fromSupplier=1
        ORDER BY id DESC
        LIMIT 1
    ");
    $check->bind_param("si", $productName, $supplierID);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        // Update existing request only if pending
        $row = $result->fetch_assoc();
        if ($row['status'] === 'pending') {
            $newQty = $row['stockQuantity'] + $stockQuantity;
            $newCategory = !empty($category) ? $category : $row['category'];
            $newPrice = $price > 0 ? $price : $row['price'];
            $newImage = !empty($imagePath) ? $imagePath : $row['imagePath'];

            $update = $conn->prepare("
                UPDATE product_add_requests 
                SET stockQuantity=?, category=?, price=?, imagePath=?, dateRequested=NOW() 
                WHERE id=?
            ");
            $update->bind_param("isdsi", $newQty, $newCategory, $newPrice, $newImage, $row['id']);

            if ($update->execute()) {
                echo json_encode(['status'=>'success','message'=>"Existing request updated (new qty: $newQty)"]);
            } else {
                echo json_encode(['status'=>'error','message'=>'Failed to update: '.$update->error]);
            }
            $update->close();
        } else {
            // Approved request exists, insert a new pending request
            $stmt = $conn->prepare("
                INSERT INTO product_add_requests 
                (productName, category, price, stockQuantity, supplierID, requestedBy, fromSupplier, status, dateRequested, imagePath) 
                VALUES (?, ?, ?, ?, ?, ?, 1, 'pending', NOW(), ?)
            ");
            $stmt->bind_param("ssdiiss", $productName, $category, $price, $stockQuantity, $supplierID, $requestedBy, $imagePath);

            if ($stmt->execute()) {
                echo json_encode(['status'=>'success','message'=>"New request added"]);
            } else {
                echo json_encode(['status'=>'error','message'=>'Failed to insert: '.$stmt->error]);
            }
            $stmt->close();
        }
    } else {
        // No existing request, insert new
        $stmt = $conn->prepare("
            INSERT INTO product_add_requests 
            (productName, category, price, stockQuantity, supplierID, requestedBy, fromSupplier, status, dateRequested, imagePath) 
            VALUES (?, ?, ?, ?, ?, ?, 1, 'pending', NOW(), ?)
        ");
        $stmt->bind_param("ssdiiss", $productName, $category, $price, $stockQuantity, $supplierID, $requestedBy, $imagePath);

        if ($stmt->execute()) {
            echo json_encode(['status'=>'success','message'=>"New request added"]);
        } else {
            echo json_encode(['status'=>'error','message'=>'Failed to insert: '.$stmt->error]);
        }
        $stmt->close();
    }

    $check->close();
    exit();
}

// ------------------- GET: Fetch supplier items -------------------
$supplierID = isset($_GET['supplierID']) ? intval($_GET['supplierID']) : 0;
if ($supplierID <= 0) {
    echo json_encode([]);
    exit();
}

$stmt = $conn->prepare("
    SELECT itemID, itemName, category, unitPrice, imagePath 
    FROM supplier_items 
    WHERE supplierID=?
");
$stmt->bind_param("i", $supplierID);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'productID'     => $row['itemID'],
        'productName'   => $row['itemName'],
        'category'      => $row['category'],
        'price'         => floatval($row['unitPrice']),
        'productsImg'   => $row['imagePath'],
        'stockQuantity' => 0
    ];
}

$stmt->close();
echo json_encode($items);
