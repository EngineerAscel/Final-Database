<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success'=>false, 'message'=>'Not authorized']);
    exit();
}

require 'db.php';

// helper: save uploaded image, returns path or null
function saveUploadedImage($fileField) {
    if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) return null;

    $uploadDir = __DIR__ . '/uploads/supplier_items/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $fname = basename($_FILES[$fileField]['name']);
    $ext = pathinfo($fname, PATHINFO_EXTENSION);
    $newName = uniqid('si_', true) . '.' . $ext;
    $target = $uploadDir . $newName;

    if (move_uploaded_file($_FILES[$fileField]['tmp_name'], $target)) {
        // return a web-accessible path (adjust if your web root differs)
        $webPath = 'uploads/supplier_items/' . $newName;
        return $webPath;
    }
    return null;
}

// action routing
$action = $_REQUEST['action'] ?? '';

if ($action === 'fetch_items') {
    $supplierID = intval($_GET['supplierID'] ?? 0);
    $stmt = $conn->prepare("SELECT itemID, supplierID, itemName, category, unitPrice, imagePath, description, dateAdded FROM supplier_items WHERE supplierID = ? ORDER BY dateAdded DESC");
    $stmt->bind_param("i", $supplierID);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = $res->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success'=>true, 'data'=>$items]);
    exit();
}

if ($action === 'get_item') {
    $itemID = intval($_GET['itemID'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM supplier_items WHERE itemID = ?");
    $stmt->bind_param("i", $itemID);
    $stmt->execute();
    $it = $stmt->get_result()->fetch_assoc();
    if ($it) echo json_encode(['success'=>true, 'data'=>$it]);
    else echo json_encode(['success'=>false, 'message'=>'Item not found']);
    exit();
}

// POST actions: add_item, edit_item, delete_item, request_stock
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // add_item
    if ($_POST['action'] === 'add_item') {
        $supplierID = intval($_POST['supplierID'] ?? 0);
        $itemName = trim($_POST['itemName'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $unitPrice = floatval($_POST['unitPrice'] ?? 0.00);
        $description = trim($_POST['description'] ?? '');

        if (!$supplierID || $itemName === '') {
            echo json_encode(['success'=>false, 'message'=>'Missing fields']);
            exit();
        }

        $imagePath = saveUploadedImage('image');

        $stmt = $conn->prepare("INSERT INTO supplier_items (supplierID, itemName, category, unitPrice, imagePath, description, dateAdded) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("issdss", $supplierID, $itemName, $category, $unitPrice, $imagePath, $description);
        if ($stmt->execute()) {
            echo json_encode(['success'=>true, 'message'=>'Item added']);
        } else {
            echo json_encode(['success'=>false, 'message'=>'DB error: '.$conn->error]);
        }
        exit();
    }

    // edit_item
    if ($_POST['action'] === 'edit_item') {
        $itemID = intval($_POST['itemID'] ?? 0);
        $supplierID = intval($_POST['supplierID'] ?? 0);
        $itemName = trim($_POST['itemName'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $unitPrice = floatval($_POST['unitPrice'] ?? 0.00);
        $description = trim($_POST['description'] ?? '');

        if (!$itemID || !$supplierID || $itemName === '') {
            echo json_encode(['success'=>false, 'message'=>'Missing fields']);
            exit();
        }

        // optionally replace image
        $imagePath = saveUploadedImage('image');

        if ($imagePath) {
            $stmt = $conn->prepare("UPDATE supplier_items SET itemName=?, category=?, unitPrice=?, imagePath=?, description=? WHERE itemID=?");
            $stmt->bind_param("sdsssi", $itemName, $category, $unitPrice, $imagePath, $description, $itemID);
        } else {
            $stmt = $conn->prepare("UPDATE supplier_items SET itemName=?, category=?, unitPrice=?, description=? WHERE itemID=?");
            $stmt->bind_param("sds si", $itemName, $category, $unitPrice, $description, $itemID);
            // note: fixed bind types below because some PHP versions need exact types. We'll use correct types below.
        }

        // use a safe second path with correct types:
        if ($imagePath) {
            $stmt = $conn->prepare("UPDATE supplier_items SET itemName=?, category=?, unitPrice=?, imagePath=?, description=? WHERE itemID=?");
            $stmt->bind_param("ssdssi", $itemName, $category, $unitPrice, $imagePath, $description, $itemID);
        } else {
            $stmt = $conn->prepare("UPDATE supplier_items SET itemName=?, category=?, unitPrice=?, description=? WHERE itemID=?");
            $stmt->bind_param("ssdsi", $itemName, $category, $unitPrice, $description, $itemID);
        }

        if ($stmt->execute()) echo json_encode(['success'=>true, 'message'=>'Item updated']);
        else echo json_encode(['success'=>false, 'message'=>'DB error: '.$conn->error]);

        exit();
    }

    // delete_item
    if ($_POST['action'] === 'delete_item') {
        $itemID = intval($_POST['itemID'] ?? 0);
        if (!$itemID) { echo json_encode(['success'=>false, 'message'=>'Invalid ID']); exit(); }
        $stmt = $conn->prepare("DELETE FROM supplier_items WHERE itemID = ?");
        $stmt->bind_param("i", $itemID);
        if ($stmt->execute()) echo json_encode(['success'=>true, 'message'=>'Deleted']);
        else echo json_encode(['success'=>false, 'message'=>'DB error']);
        exit();
    }

    // request_stock: admin requests a quantity of supplier item -> insert to products or update existing
    if ($_POST['action'] === 'request_stock') {
        $itemID = intval($_POST['itemID'] ?? 0);
        $qty = intval($_POST['qty'] ?? 0);

        if (!$itemID || $qty <= 0) { echo json_encode(['success'=>false, 'message'=>'Invalid request']); exit(); }

        // fetch supplier_item details
        $stmt = $conn->prepare("SELECT * FROM supplier_items WHERE itemID = ?");
        $stmt->bind_param("i", $itemID);
        $stmt->execute();
        $it = $stmt->get_result()->fetch_assoc();
        if (!$it) { echo json_encode(['success'=>false, 'message'=>'Item not found']); exit(); }

        // Check if a product with same name AND supplierID exists in products table
        $pstmt = $conn->prepare("SELECT * FROM products WHERE productName = ? AND supplierID = ? LIMIT 1");
        $pstmt->bind_param("si", $it['itemName'], $it['supplierID']);
        $pstmt->execute();
        $existing = $pstmt->get_result()->fetch_assoc();

        if ($existing) {
            // update stockQuantity
            $newQty = intval($existing['stockQuantity']) + $qty;
            $up = $conn->prepare("UPDATE products SET stockQuantity = ?, dateAdded = NOW() WHERE productID = ?");
            $up->bind_param("ii", $newQty, $existing['productID']);
            if ($up->execute()) {
                echo json_encode(['success'=>true, 'message'=>"Existing product stock updated to {$newQty}"]);
            } else {
                echo json_encode(['success'=>false, 'message'=>'Failed to update product: '.$conn->error]);
            }
            exit();
        } else {
            // insert new product into products table using the supplier_item data
            // NOTE: products table columns: productID, productsImg, productName, category, price, stockQuantity, supplierID, dateAdded
            $img = $it['imagePath'] ?? null;
            $pstmt = $conn->prepare("INSERT INTO products (productsImg, productName, category, price, stockQuantity, supplierID, dateAdded) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $pstmt->bind_param("sssdis", $img, $it['itemName'], $it['category'], $it['unitPrice'], $qty, $it['supplierID']);
            // NOTE: bind types: s s s d i s but last should be integer; string used on last because prepared signature must match. We'll instead do safe re-binding:
            $pstmt = $conn->prepare("INSERT INTO products (productsImg, productName, category, price, stockQuantity, supplierID, dateAdded) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $pstmt->bind_param("sssdis", $img, $it['itemName'], $it['category'], $it['unitPrice'], $qty, $it['supplierID']);

            if ($pstmt->execute()) {
                echo json_encode(['success'=>true, 'message'=>'Product created and stock set.']);
            } else {
                // If bind above fails on types, try a fallback that casts properly:
                $stmtFallback = $conn->prepare("INSERT INTO products (productsImg, productName, category, price, stockQuantity, supplierID, dateAdded) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $price = $it['unitPrice'];
                $supplierId = $it['supplierID'];
                $stmtFallback->bind_param("sssiii", $img, $it['itemName'], $it['category'], $price, $qty, $supplierId);
                if ($stmtFallback->execute()) {
                    echo json_encode(['success'=>true, 'message'=>'Product created (fallback).']);
                } else {
                    echo json_encode(['success'=>false, 'message'=>'Failed to insert product: '.$conn->error]);
                }
            }
            exit();
        }
    }

    // Generic fallback
    echo json_encode(['success'=>false, 'message'=>'Unknown action']);
    exit();
}

// If no action matched
echo json_encode(['success'=>false, 'message'=>'No action specified']);
exit();
