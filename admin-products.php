<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

// ✅ Add Product
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_product'])) {
    $name = $_POST['productName'];
    $cat = $_POST['category'];
    $price = $_POST['price'];
    $qty = $_POST['stockQuantity'];
    $supplier = $_POST['supplierID'];
    $imgPath = "";

    if (isset($_FILES['productsImg']) && $_FILES['productsImg']['error'] == 0) {
        $target = "uploads/";
        if (!is_dir($target)) mkdir($target, 0777, true);
        $imgPath = $target . basename($_FILES['productsImg']['name']);
        move_uploaded_file($_FILES['productsImg']['tmp_name'], $imgPath);
    }

    $stmt = $conn->prepare("INSERT INTO products (productsImg, productName, category, price, stockQuantity, supplierID, dateAdded)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssdis", $imgPath, $name, $cat, $price, $qty, $supplier);
    $stmt->execute();
    header("Location: admin-products.php");
    exit();
}

// ✅ Delete Product
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_product'])) {
    $id = intval($_POST['deleteID']);
    $stmt = $conn->prepare("DELETE FROM products WHERE productID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: admin-products.php");
    exit();
}

// ✅ Update Product
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_product'])) {
    $id = intval($_POST['updateID']);
    $name = $_POST['updateName'];
    $cat = $_POST['updateCategory'];
    $price = $_POST['updatePrice'];
    $qty = $_POST['updateStock'];
    $supplier = $_POST['updateSupplier'];

    $imgPath = "";
    if (isset($_FILES['updateImg']) && $_FILES['updateImg']['error'] == 0) {
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
    header("Location: admin-products.php");
    exit();
}

// ✅ Pagination & filters
$limit = 5;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$category = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

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

// ✅ Category list (with "All Items")
$categories = [
    "All Items",
    "Engine & Transmission",
    "Breaking System",
    "Suspension & Steering",
    "Electrical & Lightning",
    "Tires and Wheels"
];
?>



<!DOCTYPE html>
<html lang="en">
<head>

    <meta http-equiv="Cache-Control" content="max-age=86400">


    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Products</title>
    <link rel="stylesheet" type="text/css" href="css/products.css">

    <script src="https://cdn.tailwindcss.com" defer></script>
    
    <script src="https://unpkg.com/lucide@latest"></script>
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

        .product-table tbody tr {
            background-color: #1f2937;
            border-bottom: 1px solid #374151;
            transition: background-color 0.2s;
        }

        .product-table tbody tr:hover {
            background-color: #2c3e50;
        }
    </style>
</head>
<body class="app-bg text-gray-100 min-h-screen">
<div class="flex w-full min-h-screen">
    <?php include "admin-sidebar.php"; ?>
    <main class="flex-1 md:ml-64 p-6 md:p-10 w-full">

    <div class="space-y-8">
        <header class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-gray-800 pb-4">
            <div class="flex items-center space-x-3">
                <i data-lucide="package" class="text-red-500 w-10 h-10"></i>
                <div>
                    <p class="text-sm uppercase tracking-[0.3em] text-gray-500">Inventory Suite</p>
                    <h1 class="text-4xl font-bold tracking-tight text-white">Product Management</h1>
                </div>
            </div>
            <button class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl transition duration-200 shadow-lg flex items-center space-x-2 self-start lg:self-auto"
                onclick="document.getElementById('modal').style.display='flex'">
                <i data-lucide="plus" class="w-5 h-5"></i>
                <span>Add Product</span>
            </button>
        </header>

        <section class="bg-gray-900 p-6 rounded-2xl shadow-2xl">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <form method="GET" class="w-full lg:w-1/3">
                    <label class="text-sm text-gray-400 mb-2 block uppercase tracking-widest">Filter</label>
                    <select name="category"
                            onchange="this.form.submit()"
                            class="input-style p-3 rounded-xl w-full cursor-pointer">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"
                                <?= ($cat === $category) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($search)): ?>
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <?php endif; ?>
                </form>

                <form method="GET" class="flex flex-col sm:flex-row w-full lg:w-2/3 gap-3" id="searchForm">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
                    <div class="flex-1">
                        <label class="text-sm text-gray-400 mb-2 block uppercase tracking-widest">Search</label>
                        <input type="text"
                               name="search"
                               id="searchInput"
                               value="<?= htmlspecialchars($search) ?>"
                               placeholder="Search by product name..."
                               class="input-style w-full p-3 rounded-xl"
                               autocomplete="off">
                    </div>
                    <div class="flex items-end">
                        <button type="submit"
                                class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl transition duration-200 shadow-lg flex items-center space-x-2">
                            <i data-lucide="search" class="w-5 h-5"></i>
                            <span>Find</span>
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="bg-gray-900 p-8 rounded-2xl shadow-2xl overflow-x-auto">
            <div class="rounded-xl border border-gray-800">
                <table class="min-w-full divide-y divide-gray-800 product-table">
                    <thead class="bg-gray-800">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Image</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Price</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">QTY</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Supplier ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Date Added</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-200">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800" id="productTableBody">
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr class="product-row" data-product-name="<?= strtolower(htmlspecialchars($row['productName'])); ?>">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-300"><?= $row['productID']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php if(!empty($row['productsImg'])): ?>
                                            <img src="<?= $row['productsImg']; ?>" alt="<?= htmlspecialchars($row['productName']); ?>" class="w-12 h-12 object-cover rounded">
                                        <?php else: ?>
                                            <div class="w-12 h-12 rounded flex items-center justify-center bg-gray-700 text-xs text-gray-300">IMG</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?= htmlspecialchars($row['productName']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-400">₱<?= number_format($row['price'], 2); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm <?= $row['stockQuantity'] <= 3 ? 'text-red-400 font-bold' : 'text-gray-300' ?>">
                                        <?= $row['stockQuantity']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400"><?= $row['supplierID']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $row['dateAdded']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-3">
                                        <button type="button"
                                            class="text-blue-400 hover:text-blue-500 transition duration-150"
                                               onclick="openUpdateModal(
                                            '<?= $row['productID'] ?>',
                                            '<?= htmlspecialchars($row['productName'], ENT_QUOTES) ?>',
                                            '<?= htmlspecialchars($row['category'], ENT_QUOTES) ?>',
                                            '<?= $row['price'] ?>',
                                            '<?= $row['stockQuantity'] ?>',
                                            '<?= $row['supplierID'] ?>', )">
                                            Update
                                        </button>
                                        <form method="POST" class="inline" onsubmit="return confirm('Delete this product?')">
                                            <input type="hidden" name="deleteID" value="<?= $row['productID']; ?>">
                                            <button type="submit" name="delete_product" class="text-red-400 hover:text-red-500 transition duration-150">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr id="noResultsRow" style="display: none;">
                                <td colspan="8" class="px-6 py-8 text-center text-gray-400">No products found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between text-sm text-gray-500">
                <span>Page <?= $page ?> of <?= $totalPages ?></span>
                <div class="space-x-2">
                    <?php if ($page > 1): ?>
                        <a class="px-3 py-1 bg-gray-800 hover:bg-gray-700 text-white rounded-lg transition" href="?page=<?= $page - 1 ?>&category=<?= urlencode($category) ?>&search=<?= urlencode($search) ?>">Previous</a>
                    <?php else: ?>
                        <button class="px-3 py-1 bg-gray-700 rounded-lg opacity-50 cursor-not-allowed" disabled>Previous</button>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                        <a class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded-lg transition" href="?page=<?= $page + 1 ?>&category=<?= urlencode($category) ?>&search=<?= urlencode($search) ?>">Next</a>
                    <?php else: ?>
                        <button class="px-3 py-1 bg-gray-700 rounded-lg opacity-50 cursor-not-allowed" disabled>Next</button>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <!-- Add Product Modal -->
    <div class="modal" id="modal">
        <div class="modal-content">
            <h3>Add Product</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="productsImg" accept="image/*" required><br>
                <input type="text" name="productName" placeholder="Name" required><br>
                <select name="category" required>
                    <option value="">-- Select Category --</option>
                    <?php foreach (array_slice($categories, 1) as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select><br>
                <input type="number" step="0.01" name="price" placeholder="Price" required><br>
                <input type="number" name="stockQuantity" placeholder="Quantity" required><br>
                <input type="number" name="supplierID" placeholder="Supplier ID"><br>
                <button type="submit" name="add_product">Save</button>
                <button type="button" onclick="document.getElementById('modal').style.display='none'">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Update Product Modal -->
    <div class="modal" id="updateModal">
        <div class="modal-content">
            <h3>Update Product</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="updateID" id="updateID">
                <input type="file" name="updateImg" accept="image/*"><br>
                <input type="text" name="updateName" id="updateName" placeholder="Name"><br>
                <select name="updateCategory" id="updateCategory">
                    <option value="">-- Select Category --</option>
                    <?php foreach (array_slice($categories, 1) as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select><br>
                <input type="number" step="0.01" name="updatePrice" id="updatePrice" placeholder="Price"><br>
                <input type="number" name="updateStock" id="updateStock" placeholder="Quantity"><br>
                <input type="number" name="updateSupplier" id="updateSupplier" placeholder="Supplier ID"><br>
                <button type="submit" name="update_product">Update</button>
                <button type="button" onclick="document.getElementById('updateModal').style.display='none'">Cancel</button>
            </form>
        </div>
    </div>

    </main>
</div>

    <script>
    function openUpdateModal(id, name, cat, price, qty, sup) {
        document.getElementById('updateModal').style.display = 'flex';
        document.getElementById('updateID').value = id;
        document.getElementById('updateName').value = name;
        document.getElementById('updateCategory').value = cat;
        document.getElementById('updatePrice').value = price;
        document.getElementById('updateStock').value = qty;
        document.getElementById('updateSupplier').value = sup;
    }

    // Real-time search functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const productRows = document.querySelectorAll('.product-row');
        const noResultsRow = document.getElementById('noResultsRow');
        const searchForm = document.getElementById('searchForm');
        let searchTimeout;

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                
                // Clear previous timeout
                clearTimeout(searchTimeout);
                
                // Debounce the search to avoid too many updates
                searchTimeout = setTimeout(function() {
                    let visibleCount = 0;
                    
                    productRows.forEach(function(row) {
                        const productName = row.getAttribute('data-product-name') || '';
                        
                        if (productName.includes(searchTerm)) {
                            row.style.display = '';
                            visibleCount++;
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    // Show/hide "no results" message
                    if (visibleCount === 0 && searchTerm !== '') {
                        if (noResultsRow) {
                            noResultsRow.style.display = '';
                        } else {
                            // Create no results row if it doesn't exist
                            const tbody = document.getElementById('productTableBody');
                            const newRow = document.createElement('tr');
                            newRow.id = 'noResultsRow';
                            newRow.innerHTML = '<td colspan="8" class="px-6 py-8 text-center text-gray-400">No products found matching "' + searchTerm + '".</td>';
                            tbody.appendChild(newRow);
                        }
                    } else {
                        if (noResultsRow) {
                            noResultsRow.style.display = 'none';
                        }
                    }
                }, 150); // 150ms debounce delay
            });

            // Prevent form submission on Enter if user is typing (optional)
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    // Allow form submission for server-side search if needed
                    // Or prevent it for instant search only
                    // e.preventDefault(); // Uncomment to disable Enter submission
                }
            });
        }
    });
    </script>
    <script defer src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>