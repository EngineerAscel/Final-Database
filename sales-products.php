<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
$is_sales = isset($_SESSION['role']) && $_SESSION['role'] === 'sales';

// --- Handle Add Product ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_product'])) {

    $name = $_POST['productName'] ?? '';
    $category = $_POST['category'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $qty = intval($_POST['stockQuantity'] ?? 0);
    $supplier = intval($_POST['supplierID'] ?? 0);

    // IMAGE UPLOAD
    $imgPath = "";
    if (isset($_FILES['productsImg']) && $_FILES['productsImg']['error'] === 0) {
        $target = "uploads/";
        if (!is_dir($target)) mkdir($target, 0777, true);

        $filename = time() . "_" . basename($_FILES['productsImg']['name']);
        $imgPath = $target . $filename;

        move_uploaded_file($_FILES['productsImg']['tmp_name'], $imgPath);
    }

    // SALES → CREATE ADD REQUEST
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

        $_SESSION['addProductRequest'] = "Request sent to admin.";
        header("Location: sales-add-requests.php");
        exit();
    }

    // ADMIN → DIRECT INSERT
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

// --- Handle Update Product ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_product'])) {
    $id = intval($_POST['updateID'] ?? 0);
    if ($id <= 0) {
        header("Location: sales-products.php");
        exit();
    }

    if ($is_sales) {
        // Sales submits a request instead of direct update
        $update_data = [
            'productName' => $_POST['updateName'] ?? '',
            'category' => $_POST['updateCategory'] ?? '',
            'price' => $_POST['updatePrice'] ?? '',
            'stockQuantity' => $_POST['updateStock'] ?? '',
            'supplierID' => $_POST['updateSupplier'] ?? ''
        ];

        $_SESSION['salesUpdateProductID'] = $id;
        $_SESSION['salesUpdateData'] = $update_data;

        if (isset($_FILES['updateImg']) && $_FILES['updateImg']['error'] === 0) {
            $_SESSION['salesUpdateFile'] = $_FILES['updateImg'];
        } else {
            $_SESSION['salesUpdateFile'] = null;
        }

        header("Location: sales-update-request.php");
        exit();
    }

    if ($is_admin) {
        // Admin direct update
        $name = $_POST['updateName'] ?? '';
        $cat = $_POST['updateCategory'] ?? '';
        $price = floatval($_POST['updatePrice'] ?? 0);
        $qty = intval($_POST['updateStock'] ?? 0);
        $supplier = intval($_POST['updateSupplier'] ?? 0);

        $imgPath = "";
        if (isset($_FILES['updateImg']) && $_FILES['updateImg']['error'] === 0) {
            $target = "uploads/";
            if (!is_dir($target)) mkdir($target, 0777, true);
            $imgPath = $target . basename($_FILES['updateImg']['name']);
            move_uploaded_file($_FILES['updateImg']['tmp_name'], $imgPath);
        }

        if (!empty($imgPath)) {
            $stmt = $conn->prepare("UPDATE products SET productsImg=?, productName=?, category=?, price=?, stockQuantity=?, supplierID=? WHERE productID=?");
            $stmt->bind_param("sssdisi", $imgPath, $name, $cat, $price, $qty, $supplier, $id);
        } else {
            $stmt = $conn->prepare("UPDATE products SET productName=?, category=?, price=?, stockQuantity=?, supplierID=? WHERE productID=?");
            $stmt->bind_param("ssdisi", $name, $cat, $price, $qty, $supplier, $id);
        }

        $stmt->execute();
        $stmt->close();
        header("Location: sales-products.php");
        exit();
    }
}

// --- Pagination & Filters ---
$limit = 5;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

$where = "WHERE 1";
$params = [];
$types = "";

if (!empty($category) && $category !== "All Items") {
    $where .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}
if (!empty($search)) {
    $where .= " AND productName LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

$sql = "SELECT * FROM products $where ORDER BY category ASC, dateAdded DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $limit;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$countQuery = "SELECT COUNT(*) as total FROM products $where";
$countStmt = $conn->prepare($countQuery);
if ($types !== "ii") {
    $bindTypes = substr($types, 0, -2);
    $countStmt->bind_param($bindTypes, ...array_slice($params, 0, -2));
}
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

$categories = [
    "All Items",
    "Engine & Transmission",
    "Braking System",
    "Suspension & Steering",
    "Electrical & Lighting",
    "Tires & Wheels"
];

$productCountQuery = $conn->query("SELECT COUNT(*) AS total FROM products");
$totalProducts = $productCountQuery ? (int)$productCountQuery->fetch_assoc()['total'] : 0;

$inventoryStatsQuery = $conn->query("SELECT COALESCE(SUM(stockQuantity), 0) AS total_qty, COALESCE(SUM(price * stockQuantity), 0) AS total_value FROM products");
$inventoryStats = $inventoryStatsQuery ? $inventoryStatsQuery->fetch_assoc() : ['total_qty' => 0, 'total_value' => 0];

$lowStockQuery = $conn->query("SELECT productName, stockQuantity FROM products WHERE stockQuantity <= 3 ORDER BY stockQuantity ASC LIMIT 5");
$lowStockItems = [];
if ($lowStockQuery) {
    while ($row = $lowStockQuery->fetch_assoc()) {
        $lowStockItems[] = $row;
    }
}
$lowStockCount = count($lowStockItems);

$lastUpdated = date('M d, Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales - Products</title>
    <script src="https://cdn.tailwindcss.com" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        :root { font-family: 'Inter', sans-serif; }
        body { font-family: 'Inter', sans-serif; }
        .app-bg { background-color: #121212; }
        .sidebar-bg { background-color: #0d0d0d; }
        .modal-backdrop { background-color: rgba(0, 0, 0, 0.65); }
    </style>
</head>
<body class="app-bg text-gray-100 min-h-screen">
<div class="flex">
    <?php include("sales-sidebar.php"); ?>

    <main class="flex-1 md:ml-64 p-6 md:p-10 space-y-10">
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center border-b border-gray-800 pb-6">
            <div>
                <p class="text-sm uppercase tracking-[0.35em] text-gray-500">Inventory Control</p>
                <h1 class="text-4xl font-light tracking-tight mt-2 flex items-center space-x-3">
                    <span class="font-bold text-red-500">Product Catalog</span>
                    <i data-lucide="boxes" class="w-8 h-8 text-red-500"></i>
                </h1>
            </div>
            <div class="mt-6 md:mt-0 text-gray-400 text-sm flex items-center space-x-2">
                <i data-lucide="refresh-ccw" class="w-4 h-4 text-red-400"></i>
                <span>Synced <?= $lastUpdated; ?></span>
            </div>
        </header>

        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">
            <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl hover:ring-2 hover:ring-red-500/40 transition">
                <div class="flex items-center justify-between text-gray-400 text-xs uppercase tracking-widest">
                    <span>Total Products</span>
                    <i data-lucide="layers" class="text-red-400"></i>
                </div>
                <p class="text-4xl font-extrabold text-white mt-4"><?= number_format($totalProducts); ?></p>
                <p class="text-sm text-gray-500 mt-2">Active SKUs in catalog</p>
            </div>

            <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl hover:ring-2 hover:ring-blue-500/40 transition">
                <div class="flex items-center justify-between text-gray-400 text-xs uppercase tracking-widest">
                    <span>Total Stock</span>
                    <i data-lucide="archive" class="text-blue-400"></i>
                </div>
                <p class="text-4xl font-extrabold text-white mt-4"><?= number_format((int)$inventoryStats['total_qty']); ?></p>
                <p class="text-sm text-gray-500 mt-2">Units across warehouse</p>
            </div>

            <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl hover:ring-2 hover:ring-green-500/40 transition">
                <div class="flex items-center justify-between text-gray-400 text-xs uppercase tracking-widest">
                    <span>Inventory Value</span>
                    <i data-lucide="wallet" class="text-green-400"></i>
                </div>
                <p class="text-4xl font-extrabold text-white mt-4">₱<?= number_format((float)$inventoryStats['total_value'], 2); ?></p>
                <p class="text-sm text-gray-500 mt-2">Retail valuation</p>
            </div>

            <div class="bg-red-900/40 p-6 rounded-2xl shadow-2xl ring-4 ring-red-500/60 border-l-4 border-red-500">
                <div class="flex items-center justify-between text-red-100 text-xs uppercase tracking-widest">
                    <span>Low Stock Alerts</span>
                    <i data-lucide="alert-octagon" class="text-red-300"></i>
                </div>
                <p class="text-4xl font-extrabold text-white mt-4"><?= $lowStockCount; ?></p>
                <p class="text-sm text-red-100 mt-2">Items ≤ 3 units</p>
            </div>
        </section>

        <section class="bg-gray-900 rounded-2xl p-6 shadow-2xl space-y-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold flex items-center space-x-2">
                        <i data-lucide="filter" class="text-red-500 w-6 h-6"></i>
                        <span>Filters & Search</span>
                    </h2>
                    <p class="text-sm text-gray-500">Refine results by category or keyword</p>
                </div>
                <button
                    class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-red-600 text-white font-semibold shadow-lg shadow-red-600/30 hover:bg-red-500 transition"
                    onclick="toggleModal('addProductModal', true)">
                    <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                    Add Product
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <form method="GET" class="col-span-1 lg:col-span-1">
                    <label class="text-xs uppercase tracking-widest text-gray-500 mb-2 block">Category</label>
                    <div class="relative">
                        <select
                            name="category"
                            onchange="this.form.submit()"
                            class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100 appearance-none focus:outline-none focus:ring-2 focus:ring-red-500">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat); ?>" <?= ($cat === $category) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i data-lucide="chevron-down" class="absolute right-4 top-3.5 text-gray-500 w-4 h-4 pointer-events-none"></i>
                        <?php if (!empty($search)): ?>
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search); ?>">
                        <?php endif; ?>
                    </div>
                </form>

                <form method="GET" class="col-span-1 lg:col-span-2 flex flex-col gap-2">
                    <label class="text-xs uppercase tracking-widest text-gray-500">Search</label>
                    <div class="flex flex-col md:flex-row gap-3">
                        <input type="hidden" name="category" value="<?= htmlspecialchars($category); ?>">
                        <div class="flex-1 relative">
                            <input
                                type="text"
                                name="search"
                                value="<?= htmlspecialchars($search); ?>"
                                placeholder="Search by product name"
                                class="w-full bg-gray-950/70 border border-gray-800 rounded-xl pl-12 pr-4 py-3 text-gray-100 placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-red-500">
                            <i data-lucide="search" class="absolute left-4 top-3.5 text-gray-500 w-5 h-5"></i>
                        </div>
                        <button type="submit" class="px-6 py-3 bg-gray-800 text-white rounded-xl font-semibold hover:bg-gray-700 transition">
                            Find
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="bg-gray-900 rounded-2xl shadow-2xl overflow-hidden">
            <?php if ($result->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-800">
                        <thead class="bg-gray-800/60">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Pricing</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Inventory</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Supplier</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-gray-900 divide-y divide-gray-800">
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-800/50 transition">
                                    <td class="px-6 py-4 flex items-center space-x-4">
                                        <div class="w-14 h-14 rounded-xl overflow-hidden bg-gray-800 flex items-center justify-center">
                                            <?php if(!empty($row['productsImg'])): ?>
                                                <img src="<?= $row['productsImg']; ?>" alt="<?= htmlspecialchars($row['productName']); ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <i data-lucide="image-off" class="text-gray-600 w-6 h-6"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <p class="text-white font-semibold"><?= htmlspecialchars($row['productName']); ?></p>
                                            <p class="text-sm text-gray-500">ID #<?= $row['productID']; ?></p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="px-3 py-1 rounded-full bg-gray-800 text-gray-200 text-xs font-semibold">
                                            <?= htmlspecialchars($row['category']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-green-400 font-semibold">₱<?= number_format($row['price'],2); ?></p>
                                        <p class="text-xs text-gray-500"><?= date('M d, Y', strtotime($row['dateAdded'])); ?></p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-2">
                                            <span class="font-semibold text-white"><?= $row['stockQuantity']; ?> units</span>
                                            <?php if ($row['stockQuantity'] <= 3): ?>
                                                <span class="text-xs text-red-400 flex items-center space-x-1">
                                                    <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
                                                    <span>Low</span>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-500">Healthy</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-300">#<?= $row['supplierID']; ?></td>
                                    <td class="px-6 py-4 text-right">
                                        <button
                                            type="button"
                                            class="inline-flex items-center space-x-2 text-blue-400 hover:text-blue-300 transition font-semibold"
                                            onclick="openUpdateModal(
                                                '<?= $row['productID']; ?>',
                                                '<?= htmlspecialchars($row['productName']); ?>',
                                                '<?= htmlspecialchars($row['category']); ?>',
                                                '<?= $row['price']; ?>',
                                                '<?= $row['stockQuantity']; ?>',
                                                '<?= $row['supplierID']; ?>'
                                            )">
                                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                                            <span><?= $is_sales ? 'Request Update' : 'Edit' ?></span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-16">
                    <i data-lucide="package-open" class="w-20 h-20 text-gray-700 mx-auto mb-4"></i>
                    <p class="text-gray-400 text-lg">No products match your filters.</p>
                </div>
            <?php endif; ?>
        </section>

        <section class="flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-gray-400">
            <div>
                Page <?= $page; ?> of <?= $totalPages; ?> · Showing <?= $totalRows > 0 ? min($limit, $totalRows - $offset) : 0; ?> of <?= $totalRows; ?> items
            </div>
            <div class="flex items-center space-x-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>&category=<?= urlencode($category) ?>&search=<?= urlencode($search) ?>"
                       class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 text-white transition flex items=center space-x-2">
                       <i data-lucide="chevron-left" class="w-4 h-4"></i>
                       <span>Previous</span>
                    </a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page+1 ?>&category=<?= urlencode($category) ?>&search=<?= urlencode($search) ?>"
                       class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 text-white transition flex items=center space-x-2">
                       <span>Next</span>
                       <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </a>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<!-- Add Product Modal -->
<div id="addProductModal" class="modal-backdrop fixed inset-0 hidden items-center justify-center p-4 z-40">
    <div class="bg-gray-900 rounded-2xl w-full max-w-2xl shadow-2xl border border-gray-800">
        <div class="flex items-center justify-between border-b border-gray-800 px-6 py-4">
            <div>
                <p class="text-xs uppercase tracking-[0.25em] text-gray-500">New Item</p>
                <h3 class="text-xl font-semibold text-white">Add Product</h3>
            </div>
            <button class="text-gray-400 hover:text-red-400" onclick="toggleModal('addProductModal', false)">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="px-6 py-6 space-y-4">
            <div class="space-y-2">
                <label class="text-sm text-gray-400">Product Image</label>
                <input type="file" name="productsImg" accept="image/*" required class="w-full text-sm text-gray-300">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-400">Name</label>
                    <input type="text" name="productName" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100" required>
                </div>
                <div>
                    <label class="text-sm text-gray-400">Category</label>
                    <select name="category" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach(array_slice($categories,1) as $cat): ?>
                            <option value="<?= htmlspecialchars($cat); ?>"><?= htmlspecialchars($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-400">Price</label>
                    <input type="number" step="0.01" name="price" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100" required>
                </div>
                <div>
                    <label class="text-sm text-gray-400">Quantity</label>
                    <input type="number" name="stockQuantity" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100" required>
                </div>
                  <label class="text-sm text-gray-400">Supplier ID</label>
                    <input type="number" name="supplierID" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100">
            </div>

            <div class="flex flex-col md:flex-row items-center justify-end gap-3 pt-4 border-t border-gray-800">
                <button type="button" class="w-full md:w-auto px-4 py-3 rounded-xl bg-gray-800 text-gray-200 hover:bg-gray-700 transition" onclick="toggleModal('addProductModal', false)">
                    Cancel
                </button>
                <button type="submit" name="add_product" class="w-full md:w-auto px-4 py-3 rounded-xl bg-red-600 text-white font-semibold hover:bg-red-500 transition">
                    Send Request
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Update Modal -->
<div id="updateProductModal" class="modal-backdrop fixed inset-0 hidden items-center justify-center p-4 z-40">
    <div class="bg-gray-900 rounded-2xl w-full max-w-2xl shadow-2xl border border-gray-800">
        <div class="flex items-center justify-between border-b border-gray-800 px-6 py-4">
            <div>
                <p class="text-xs uppercase tracking-[0.25em] text-gray-500"><?= $is_sales ? "Request" : "Update"; ?></p>
                <h3 class="text-xl font-semibold text-white"><?= $is_sales ? "Request Product Update" : "Edit Product"; ?></h3>
            </div>
            <button class="text-gray-400 hover:text-red-400" onclick="toggleModal('updateProductModal', false)">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data" onsubmit="return confirmChanges();" class="px-6 py-6 space-y-4">
            <input type="hidden" name="updateID" id="updateID">

            <div class="space-y-2">
                <label class="text-sm text-gray-400">Update Image</label>
                <input type="file" name="updateImg" id="updateImg" accept="image/*" class="w-full text-sm text-gray-300">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-400">Name</label>
                    <input type="text" name="updateName" id="updateName" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100">
                </div>
                <div>
                    <label class="text-sm text-gray-400">Category</label>
                    <select name="updateCategory" id="updateCategory" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100">
                        <option value="">-- Select Category --</option>
                        <?php foreach(array_slice($categories,1) as $cat): ?>
                            <option value="<?= htmlspecialchars($cat); ?>"><?= htmlspecialchars($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-400">Price</label>
                    <input type="number" step="0.01" name="updatePrice" id="updatePrice" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100">
                </div>
                <div>
                    <label class="text-sm text-gray-400">Quantity</label>
                    <input type="number" name="updateStock" id="updateStock" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100">
                </div>
                <div>
                    <label class="text-sm text-gray-400">Supplier ID</label>
                    <input type="number" name="updateSupplier" id="updateSupplier" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100">
                </div>
            </div>

            <div class="flex flex-col md:flex-row items-center justify-end gap-3 pt-4 border-t border-gray-800">
                <button type="button" class="w-full md:w-auto px-4 py-3 rounded-xl bg-gray-800 text-gray-200 hover:bg-gray-700 transition" onclick="toggleModal('updateProductModal', false)">
                    Cancel
                </button>
                <button type="submit" name="update_product" class="w-full md:w-auto px-4 py-3 rounded-xl bg-red-600 text-white font-semibold hover:bg-red-500 transition">
                    <?= $is_sales ? "Send Request" : "Save Changes"; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let originalData = {};

function toggleModal(id, show) {
    const modal = document.getElementById(id);
    if (!modal) return;
    if (show) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    } else {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function openUpdateModal(id, name, cat, price, qty, sup){
    toggleModal('updateProductModal', true);

    document.getElementById('updateID').value = id;
    document.getElementById('updateName').value = name;
    document.getElementById('updateCategory').value = cat;
    document.getElementById('updatePrice').value = price;
    document.getElementById('updateStock').value = qty;
    document.getElementById('updateSupplier').value = sup;

    originalData = { name, cat, price, qty, sup };
}

function confirmChanges(){
    let changes = "";
    const newName = document.getElementById('updateName').value;
    const newCat = document.getElementById('updateCategory').value;
    const newPrice = document.getElementById('updatePrice').value;
    const newQty = document.getElementById('updateStock').value;
    const newSup = document.getElementById('updateSupplier').value;
    const newImg = document.getElementById('updateImg').value;

    if(newName !== originalData.name) changes += `Name: ${originalData.name} → ${newName}\n`;
    if(newCat !== originalData.cat) changes += `Category: ${originalData.cat} → ${newCat}\n`;
    if(newPrice !== originalData.price) changes += `Price: ₱${originalData.price} → ₱${newPrice}\n`;
    if(newQty !== originalData.qty) changes += `Quantity: ${originalData.qty} → ${newQty}\n`;
    if(newSup !== originalData.sup) changes += `Supplier ID: ${originalData.sup} → ${newSup}\n`;
    if(newImg) changes += `New Image Selected: ${newImg.split("\\").pop()}\n`;

    if (changes === "") {
        return confirm("No changes detected. Submit anyway?");
    }
    return confirm("You are about to submit these changes:\n\n" + changes);
}

document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
});
</script>
</body>
</html>

