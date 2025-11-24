<?php
ob_start();
session_start();

// ✅ Protect page
if (!isset($_SESSION['username']) || ($_SESSION['role'] !== "Cashier")) {
    header("Location: login.php");
    exit();
}

// ✅ Check if this is an AJAX request
$isAjax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) || isset($_POST['add_to_cart']) || isset($_POST['remove_cart']) || isset($_POST['update_qty']) || isset($_POST['submit_sale']);

// Initialize cart session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ✅ Handle AJAX requests
if ($isAjax) {
    include "db.php";

    // 🔹 Add to Cart
    if (isset($_POST['add_to_cart'])) {
        $productID = intval($_POST['productID']);
        $quantity = intval($_POST['quantity']);

        $stmt = $conn->prepare("SELECT productID, productName, price, stockQuantity FROM products WHERE productID = ?");
        $stmt->bind_param("i", $productID);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($product = $result->fetch_assoc()) {
            $stock = $product['stockQuantity'];
            $found = false;

            foreach ($_SESSION['cart'] as &$item) {
                if ($item['productID'] == $productID) {
                    $item['quantity'] = min($item['quantity'] + $quantity, $stock);
                    $found = true;
                    break;
                }
            }
            unset($item);

            if (!$found) {
                $_SESSION['cart'][] = [
                    'productID' => $product['productID'],
                    'productName' => $product['productName'],
                    'price' => $product['price'],
                    'quantity' => min($quantity, $stock),
                    'stockQuantity' => $stock
                ];
            }
        }

        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'added']);
        exit();
    }

    // 🔹 Remove from Cart
    if (isset($_POST['remove_cart'])) {
        $removeID = intval($_POST['removeID']);
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['productID'] == $removeID) {
                unset($_SESSION['cart'][$key]);
                break;
            }
        }
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'removed']);
        exit();
    }

    // 🔹 Update Quantity
    if (isset($_POST['update_qty'])) {
        $updateID = intval($_POST['updateID']);
        $newQty = intval($_POST['newQty']);
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['productID'] == $updateID) {
                $item['quantity'] = min($newQty, $item['stockQuantity']);
                break;
            }
        }
        unset($item);
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'updated']);
        exit();
    }

    // 🔹 Submit Sale
    if (isset($_POST['submit_sale'])) {
        // ---------------------------
        // Collect POST data (including new client fields)
        // ---------------------------
        $clientName     = isset($_POST['clientName']) ? trim($_POST['clientName']) : '';
        $contactNumber  = isset($_POST['contactNumber']) ? trim($_POST['contactNumber']) : '';
        $email          = isset($_POST['email']) ? trim($_POST['email']) : '';
        $address        = isset($_POST['address']) ? trim($_POST['address']) : '';

        // Keep backward-compatible variables expected by the rest of your logic
        $customerName   = $clientName; // we'll use this as the customerName in sales table
        $paymentType    = isset($_POST['paymentType']) ? $_POST['paymentType'] : 'cash';
        $totalAmount    = isset($_POST['totalAmount']) ? floatval($_POST['totalAmount']) : 0;
        $cashier        = isset($_POST['cashier']) ? $_POST['cashier'] : '';
        $salesAccount   = isset($_POST['salesAccount']) ? $_POST['salesAccount'] : '';

        // Basic validation
        if ($clientName === '' || $contactNumber === '' || $email === '' || $address === '') {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Client information incomplete.']);
            exit();
        }

        if (count($_SESSION['cart']) > 0) {

            // --- Insert client into clientinfo if not exists ---
            $checkClient = $conn->prepare("SELECT clientID FROM clientinfo WHERE clientName = ? AND contactNumber = ?");
            $checkClient->bind_param("ss", $clientName, $contactNumber);
            $checkClient->execute();
            $checkClient->store_result();

            if ($checkClient->num_rows === 0) {
                $ins = $conn->prepare("INSERT INTO clientinfo (clientName, contactNumber, email, address, registeredDate) VALUES (?, ?, ?, ?, NOW())");
                $ins->bind_param("ssss", $clientName, $contactNumber, $email, $address);
                $ins->execute();
                $ins->close();
            }
            $checkClient->close();

            // --- Get cashier ID ---
            $stmt = $conn->prepare("SELECT userID FROM usermanagement WHERE username = ?");
            $stmt->bind_param("s", $cashier);
            $stmt->execute();
            $cashierID = $stmt->get_result()->fetch_assoc()['userID'] ?? 0;
            $stmt->close();

            // --- Get sales account ID ---
            $stmt = $conn->prepare("SELECT userID FROM usermanagement WHERE username = ?");
            $stmt->bind_param("s", $salesAccount);
            $stmt->execute();
            $salesAccountID = $stmt->get_result()->fetch_assoc()['userID'] ?? 0;
            $stmt->close();

            // --- Insert Sale Record (Header) ---
            $status = 'pending';
            $stmt = $conn->prepare(
                "INSERT INTO sales (salesAccountID, cashierID, customerName, totalAmount, saleDate, status) 
                 VALUES (?, ?, ?, ?, NOW(), ?)"
            );
            // types: i (salesAccountID), i (cashierID), s (customerName), d (totalAmount), s (status)
            $stmt->bind_param("iisds", $salesAccountID, $cashierID, $customerName, $totalAmount, $status);

            if (!$stmt->execute()) {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Failed to insert sale: ' . $stmt->error]);
                exit();
            }

            $saleID = $stmt->insert_id;
            $stmt->close();

            // --- Insert Sale Items (Details with Product Snapshot) ---
            $stmt_get = $conn->prepare("SELECT productName, category FROM products WHERE productID = ?");
            $stmt_items = $conn->prepare(
                "INSERT INTO sale_items (saleID, productID, quantity, unitPrice, lineTotal, productName, category)
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            foreach ($_SESSION['cart'] as $item) {
                $productID = $item['productID'];
                $quantity  = $item['quantity'];
                $unitPrice = $item['price'];
                $lineTotal = $unitPrice * $quantity;

                // Fetch product name and category for snapshot
                $stmt_get->bind_param("i", $productID);
                $stmt_get->execute();
                $result = $stmt_get->get_result();
                $product = $result->fetch_assoc();
                $productName = $product['productName'] ?? 'Unknown';
                $category = $product['category'] ?? 'Uncategorized';

                $stmt_items->bind_param("iiddsss", $saleID, $productID, $quantity, $unitPrice, $lineTotal, $productName, $category);
                $stmt_items->execute();
            }

            $stmt_get->close();
            $stmt_items->close();

            // --- Clear Cart & Return JSON ---
            $_SESSION['cart'] = [];
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'submitted', 'saleID' => $saleID]);
            exit();

        } else {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'empty']);
            exit();
        }
    }

    // --- Fallback for invalid AJAX request ---
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid AJAX request']);
    exit();

}

// ✅ Non-AJAX: render HTML (keeps your exact UI and listing logic)
include "db.php";
include "cashier-sidebar.php";

$categories = ["All Items", "Engine & Transmission", "Breaking System", "Suspension & Steering", "Electrical & Lightning", "Tires and Wheels"];
$selectedCategory = $_GET['category'] ?? 'All Items';
$limit = 8;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

if ($selectedCategory != 'All Items') {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE category=?");
    $stmt->bind_param("s", $selectedCategory);
    $stmt->execute();
    $stmt->bind_result($totalProducts);
    $stmt->fetch();
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM products WHERE category=? ORDER BY productName ASC LIMIT ?, ?");
    $stmt->bind_param("sii", $selectedCategory, $offset, $limit);
    $stmt->execute();
    $items = $stmt->get_result();
} else {
    $totalProducts = $conn->query("SELECT COUNT(*) FROM products")->fetch_row()[0];
    $stmt = $conn->prepare("SELECT * FROM products ORDER BY productName ASC LIMIT ?, ?");
    $stmt->bind_param("ii", $offset, $limit);
    $stmt->execute();
    $items = $stmt->get_result();
}

$totalPages = ceil($totalProducts / $limit);
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Items - Cashier</title>
    <script src="https://cdn.tailwindcss.com" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');

        :root {
            font-family: 'Inter', sans-serif;
        }

        .app-bg { background-color: #121212; }
        .sidebar-bg { background-color: #0d0d0d; }

        [data-lucide] {
            width: 1.5rem;
            height: 1.5rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background: #1f2937;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 1rem;
            width: 90%;
            max-width: 900px;
            position: relative;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
        }

        .close {
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            color: #9ca3af;
            transition: color 0.2s;
        }

        .close:hover {
            color: #ef4444;
        }

        .input-style {
            background-color: #1f2937;
            color: #d1d5db;
            border: 2px solid #374151;
            transition: all 0.3s ease;
        }

        .input-style:focus {
            outline: none;
            border-color: #ef4444;
        }
    </style>
</head>
<body class="app-bg text-gray-100 min-h-screen">
<div class="flex w-full min-h-screen">
    <?php include 'cashier-sidebar.php'; ?>
    <main class="flex-1 md:ml-64 p-6 md:p-10 w-full">
        <div class="space-y-8">
            <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-gray-800 pb-4">
                <div class="flex items-center space-x-3">
                    <i data-lucide="package" class="text-red-500 w-10 h-10"></i>
                    <div>
                        <p class="text-sm uppercase tracking-[0.3em] text-gray-500">Product Catalog</p>
                        <h1 class="text-4xl font-bold tracking-tight text-white">Available Items</h1>
                    </div>
                </div>
                <button id="openCartBtn" class="relative px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl transition duration-200 shadow-lg flex items-center space-x-2">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                    <span>View Cart</span>
                    <span id="cartCountBadge" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full px-2 py-1 text-xs font-bold"><?= count($_SESSION['cart']) ?></span>
                </button>
            </header>

            <section class="bg-gray-900 p-6 rounded-2xl shadow-2xl">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-6">
                    <div class="flex-1">
                        <label class="text-sm text-gray-400 mb-2 block uppercase tracking-widest">Search</label>
                        <input type="text" id="searchBox" placeholder="Search item..." class="input-style w-full p-3 rounded-xl">
                    </div>
                </div>

                <div class="mb-6">
                    <label class="text-sm text-gray-400 mb-2 block uppercase tracking-widest">Category</label>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($categories as $cat): ?>
                            <a href="?category=<?= urlencode($cat) ?>" 
                               class="px-4 py-2 rounded-xl transition duration-200 <?= $selectedCategory == $cat ? 'bg-red-600 text-white font-semibold' : 'bg-gray-800 text-gray-300 hover:bg-gray-700' ?>">
                                <?= htmlspecialchars($cat) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table id="itemTable" class="min-w-full divide-y divide-gray-800">
                        <thead class="bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Image</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Price</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Stock</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-200">Add to Cart</th>
                            </tr>
                        </thead>
                        <tbody class="bg-gray-900 divide-y divide-gray-800">
                            <?php if ($items && $items->num_rows > 0): ?>
                                <?php while ($row = $items->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-800/50 transition">
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-300"><?= $row['productID'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if (!empty($row['productsImg'])): ?>
                                                <img src="<?= htmlspecialchars($row['productsImg']) ?>" alt="<?= htmlspecialchars($row['productName']) ?>" class="w-16 h-16 object-cover rounded-lg">
                                            <?php else: ?>
                                                <div class="w-16 h-16 rounded-lg flex items-center justify-center bg-gray-800 text-xs text-gray-500">No Image</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300 font-medium"><?= htmlspecialchars($row['productName']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400"><?= htmlspecialchars($row['category']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-400">₱<?= number_format($row['price'], 2) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm <?= $row['stockQuantity'] <= 3 ? 'text-red-400 font-bold' : 'text-gray-300' ?>">
                                            <?= $row['stockQuantity'] ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <div class="flex items-center justify-center space-x-2">
                                                <input type="number" class="cart-qty input-style w-20 p-2 rounded-lg text-center" value="1" min="1" max="<?= $row['stockQuantity'] ?>">
                                                <button type="button" class="add-to-cart-btn px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition duration-150 flex items-center space-x-1" data-id="<?= $row['productID'] ?>">
                                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                                    <span>Add</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                                        <i data-lucide="package-x" class="w-16 h-16 mx-auto mb-4 text-gray-600"></i>
                                        <p>No products found.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between text-sm text-gray-500">
                    <span>Page <?= $page ?> of <?= $totalPages ?></span>
                    <div class="space-x-2">
                        <?php if ($page > 1): ?>
                            <a class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg transition flex items-center space-x-1" href="?page=<?= $page - 1 ?>&category=<?= urlencode($selectedCategory) ?>">
                                <i data-lucide="chevron-left" class="w-4 h-4"></i>
                                <span>Previous</span>
                            </a>
                        <?php else: ?>
                            <button class="px-4 py-2 bg-gray-700 rounded-lg opacity-50 cursor-not-allowed" disabled>Previous</button>
                        <?php endif; ?>

                        <?php if ($page < $totalPages): ?>
                            <a class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition flex items-center space-x-1" href="?page=<?= $page + 1 ?>&category=<?= urlencode($selectedCategory) ?>">
                                <span>Next</span>
                                <i data-lucide="chevron-right" class="w-4 h-4"></i>
                            </a>
                        <?php else: ?>
                            <button class="px-4 py-2 bg-gray-700 rounded-lg opacity-50 cursor-not-allowed" disabled>Next</button>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </main>
</div>

<div id="cartModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2 class="text-2xl font-bold text-white mb-4 flex items-center space-x-2">
            <i data-lucide="shopping-cart" class="text-red-500"></i>
            <span>Cart</span>
        </h2>
        <div id="cartContent" class="text-gray-300"></div>
    </div>
</div>

<script>
$(document).ready(function(){
    // Initialize Lucide icons
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        lucide.createIcons();
    }

    const modal = $("#cartModal");
    $("#openCartBtn").click(function(){ 
        loadCart(); 
        modal.show(); 
        // Reinitialize icons after modal opens
        setTimeout(() => { if (window.lucide) lucide.createIcons(); }, 100);
    });
    $(".close").click(function(){ modal.hide(); });
    $(window).click(function(e){ if(e.target.id=="cartModal") modal.hide(); });

    function loadCart(){ 
        $.get('get_cart.php', function(data){ 
            $('#cartContent').html(data); 
            // Reinitialize icons after cart content loads
            if (window.lucide) lucide.createIcons();
        }); 
    }
    function updateBadge(){ 
        $.get('get_count.php', function(count){ 
            $('#cartCountBadge').text(count); 
        }); 
    }

    $(".add-to-cart-btn").click(function(){
        const productID = $(this).data("id");
        const quantity = $(this).siblings(".cart-qty").val();
        $.post("cashier-items.php", { add_to_cart: 1, productID, quantity }, function(){
            loadCart();
            updateBadge();
        });
    });

    $(document).on('submit', '.update_qty_form', function(e){
        e.preventDefault();
        $.post('cashier-items.php', $(this).serialize(), function(){ 
            loadCart(); 
            updateBadge(); 
        });
    });

    $(document).on('submit', '.remove_cart_form', function(e){
        e.preventDefault();
        $.post('cashier-items.php', $(this).serialize(), function(){ 
            loadCart(); 
            updateBadge(); 
        });
    });

    // Search functionality
    $("#searchBox").on("keyup", function() {
        const value = $(this).val().toLowerCase();
        $("#itemTable tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
});
</script>
</body>
</html>
