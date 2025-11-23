<?php
session_start();
require 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
$is_sales = isset($_SESSION['role']) && $_SESSION['role'] === 'sales';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_product'])) {

    $name = $_POST['productName'] ?? '';
    $category = $_POST['category'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $qty = intval($_POST['stockQuantity'] ?? 0);
    $supplier = intval($_POST['supplierID'] ?? 0);

    // --- IMAGE UPLOAD ---
    $imgPath = "";
    if (isset($_FILES['productsImg']) && $_FILES['productsImg']['error'] === 0) {
        $target = "uploads/";
        if (!is_dir($target)) mkdir($target, 0777, true);

        $filename = time() . "_" . basename($_FILES['productsImg']['name']);
        $imgPath = $target . $filename;

        move_uploaded_file($_FILES['productsImg']['tmp_name'], $imgPath);
    }

    // ------------------------------
    // SALES: SEND REQUEST TO ADMIN
    // ------------------------------
    if ($is_sales) {
        $stmt = $conn->prepare("
            INSERT INTO product_add_requests 
            (productName, category, price, stockQuantity, supplierID, imagePath, requestedBy, status, dateRequested)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");

        $requestedBy = $_SESSION['username'];

        $stmt->bind_param("ssdisss", 
            $name, 
            $category, 
            $price, 
            $qty, 
            $supplier, 
            $imgPath, 
            $requestedBy
        );

        $stmt->execute();
        $stmt->close();

        $_SESSION['addProductRequest'] = "Your add product request was sent to admin.";
        header("Location: sales-add-requests.php");
        exit();
    }

    // ------------------------------
    // ADMIN: DIRECT INSERT
    // ------------------------------
    if ($is_admin) {
        $stmt = $conn->prepare("
            INSERT INTO products 
            (productName, category, price, stockQuantity, supplierID, productsImg, dateAdded)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param("ssdiss",
            $name,
            $category,
            $price,
            $qty,
            $supplier,
            $imgPath
        );

        $stmt->execute();
        $stmt->close();

        header("Location: sales-products.php");
        exit();
    }
}

// fallback
header("Location: sales-products.php");
exit();
