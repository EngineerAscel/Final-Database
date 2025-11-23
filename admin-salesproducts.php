<?php
session_start();
include "db.php";

if (!isset($_SESSION['username']) || ($_SESSION['role'] !== "Admin" && $_SESSION['role'] !== "sales")) {
    header("Location: login.php");
    exit();
}

$filter_type = $_GET['filter_type'] ?? 'all';
$saleID = trim($_GET['saleID'] ?? '');
$saleID = ctype_digit($saleID) ? $saleID : '';
$category = trim($_GET['category'] ?? '');
$order = strtolower($_GET['order'] ?? 'desc');
$order = $order === 'asc' ? 'ASC' : 'DESC';

$categoryCountResult = $conn->query("SELECT COUNT(DISTINCT category) AS total FROM products");
$categoryCount = $categoryCountResult ? (int)$categoryCountResult->fetch_assoc()['total'] : 0;

$totalSalesResult = $conn->query("SELECT SUM(si.lineTotal) AS totalSales 
                                  FROM sale_items si 
                                  JOIN sales s ON si.saleID = s.saleID 
                                  WHERE s.status='approved'");
$totalSalesAmount = $totalSalesResult ? (float)$totalSalesResult->fetch_assoc()['totalSales'] : 0;

$viewMode = ($filter_type === 'receipt' && $saleID !== '') ? 'receipt' : 'aggregate';
$rows = [];
$grandTotal = 0;

if ($viewMode === 'receipt') {
    $stmt = $conn->prepare("SELECT p.productName, p.category, si.quantity, si.unitPrice, si.lineTotal
                            FROM sale_items si
                            JOIN products p ON si.productID = p.productID
                            WHERE si.saleID = ?");
    $stmt->bind_param("i", $saleID);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
        $grandTotal += (float)$row['lineTotal'];
    }
    $stmt->close();
} else {
    $sql = "SELECT p.productName, p.category, SUM(si.quantity) AS totalQty, SUM(si.lineTotal) AS totalSales
            FROM sale_items si
            JOIN sales s ON si.saleID = s.saleID
            JOIN products p ON si.productID = p.productID
            WHERE s.status = 'approved'";
    $params = [];
    $types = '';
    if ($category !== '') {
        $sql .= " AND p.category = ?";
        $params[] = $category;
        $types .= 's';
    }
    $sql .= " GROUP BY si.productID ORDER BY totalQty $order";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
        $grandTotal += (float)$row['totalSales'];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Products - Admin</title>
    <link rel="stylesheet" href="dashboard.css">
    <script src="https://cdn.tailwindcss.com" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        :root { font-family: 'Inter', sans-serif; }
        .app-bg { background-color: #121212; }
        .sidebar-bg { background-color: #0d0d0d; }
        .filter-card select,
        .filter-card input {
            background-color: #1f2937;
            color: #d1d5db;
            border: 2px solid #374151;
            border-radius: 0.75rem;
            padding: 0.65rem 0.9rem;
            transition: border-color 0.3s ease;
        }
        .filter-card select:focus,
        .filter-card input:focus {
            border-color: #ef4444;
            outline: none;
        }
        .sales-table tbody tr {
            background-color: #1f2937;
            border-bottom: 1px solid #374151;
            transition: background-color 0.2s;
        }
        .sales-table tbody tr:hover {
            background-color: #2c3e50;
        }
        .sales-table th,
        .sales-table td {
            text-align: center;
        }
        .sales-table th:first-child,
        .sales-table td:first-child {
            text-align: left;
            padding-left: 2rem;
        }
    </style>
</head>
<body class="app-bg text-gray-100 min-h-screen">
    <div class="flex w-full min-h-screen">
    <?php include "admin-sidebar.php"; ?>
    <main class="flex-1 md:ml-64 p-6 md:p-10 w-full space-y-8">

    <div class="space-y-8">
        <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-gray-800 pb-4">
            <div class="flex items-center space-x-3">
                <i data-lucide="shopping-bag" class="text-red-500 w-10 h-10"></i>
                <div>
                    <p class="text-sm uppercase tracking-[0.3em] text-gray-500">Sales Insights</p>
                    <h1 class="text-4xl font-bold tracking-tight text-white">Sales Products</h1>
                </div>
            </div>
            <div class="flex gap-3">
                <div class="bg-gray-900 px-4 py-2 rounded-xl border border-gray-800 text-sm text-gray-400">
                    Categories <span class="text-white font-semibold ml-2"><?= $categoryCount ?></span>
                </div>
                <div class="bg-green-900/20 px-4 py-2 rounded-xl border border-green-600/40 text-sm text-green-200">
                    Total ₱ <span class="text-white font-semibold ml-2"><?= number_format($totalSalesAmount, 2) ?></span>
                </div>
            </div>
        </header>

        <form method="GET" class="filter-card bg-gray-900 p-6 rounded-2xl shadow-2xl flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between" id="filterForm">
            <div class="space-y-2">
                <label class="text-sm text-gray-400 uppercase tracking-widest">View Mode</label>
                <select name="filter_type" onchange="this.form.submit()">
                    <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Sold Products</option>
                    <option value="receipt" <?= $filter_type === 'receipt' ? 'selected' : '' ?>>By Receipt</option>
                </select>
            </div>

            <?php if ($filter_type === 'receipt'): ?>
                <?php $receiptRes = $conn->query("SELECT saleID FROM sales WHERE status = 'approved' ORDER BY saleID DESC"); ?>
                <div class="space-y-2 flex-1">
                    <label class="text-sm text-gray-400 uppercase tracking-widest">Receipt</label>
                    <div class="flex gap-3">
                        <select id="saleSelect" class="flex-1">
                            <option value="">-- Select a Receipt --</option>
                            <?php while ($r = $receiptRes->fetch_assoc()): ?>
                                <option value="<?= $r['saleID'] ?>" <?= ($saleID == $r['saleID']) ? 'selected' : '' ?>>
                                    Receipt #<?= $r['saleID'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <input type="number" id="saleIDInput" name="saleID" placeholder="Enter Sale ID..." value="<?= htmlspecialchars($saleID) ?>" class="flex-1">
                    </div>
                </div>
                <button type="submit" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl transition duration-200 shadow-lg flex items-center space-x-2">
                    <i data-lucide="search" class="w-5 h-5"></i>
                    <span>View Receipt</span>
                </button>
            <?php else: ?>
                <div class="space-y-2 flex-1">
                    <label class="text-sm text-gray-400 uppercase tracking-widest">Category</label>
                    <select name="category" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php
                        $catRes = $conn->query("SELECT DISTINCT category FROM products ORDER BY category ASC");
                        while ($cat = $catRes->fetch_assoc()):
                        ?>
                            <option value="<?= $cat['category'] ?>" <?= ($category === $cat['category']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="space-y-2 flex-1">
                    <label class="text-sm text-gray-400 uppercase tracking-widest">Sort</label>
                    <select name="order" onchange="this.form.submit()">
                        <option value="desc" <?= $order === 'DESC' ? 'selected' : '' ?>>Most Sold → Least Sold</option>
                        <option value="asc" <?= $order === 'ASC' ? 'selected' : '' ?>>Least Sold → Most Sold</option>
                    </select>
                </div>
            <?php endif; ?>
        </form>

        <section class="bg-gray-900 p-6 rounded-2xl shadow-2xl">
            <?php if ($viewMode === 'receipt'): ?>
                <?php if (!empty($rows)): ?>
                    <div class="rounded-xl border border-gray-800 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-800 sales-table">
                            <thead class="bg-gray-800">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Product Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Qty</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Unit Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Category</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800 text-gray-300">
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['productName']) ?></td>
                                        <td><?= (int)$row['quantity'] ?></td>
                                        <td>₱<?= number_format($row['unitPrice'], 2) ?></td>
                                        <td>₱<?= number_format($row['lineTotal'], 2) ?></td>
                                        <td><?= htmlspecialchars($row['category']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="grand-total mt-4 text-right text-gray-300">
                        Grand Total:
                        <span class="text-green-400 font-bold">₱<?= number_format($grandTotal, 2) ?></span>
                    </div>
                <?php else: ?>
                    <div class="no-data text-center text-gray-400">No products found for Sale ID #<?= htmlspecialchars($saleID) ?></div>
                <?php endif; ?>
            <?php else: ?>
                <?php if (!empty($rows)): ?>
                    <div class="rounded-xl border border-gray-800 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-800 sales-table">
                            <thead class="bg-gray-800">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Product Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Total Quantity Sold</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Total Sales Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800 text-gray-300">
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['productName']) ?></td>
                                        <td><?= htmlspecialchars($row['category']) ?></td>
                                        <td><?= (int)$row['totalQty'] ?></td>
                                        <td>₱<?= number_format($row['totalSales'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="grand-total mt-4 text-right text-gray-300">
                        Grand Total of All Sales:
                        <span class="text-green-400 font-bold">₱<?= number_format($grandTotal, 2) ?></span>
                    </div>
                <?php else: ?>
                    <div class="no-data text-center text-gray-400">No sold products found.</div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>

    </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const saleSelect = document.getElementById('saleSelect');
        const saleInput = document.getElementById('saleIDInput');
        const form = document.getElementById('filterForm');
        const filterType = document.querySelector('select[name="filter_type"]');

        if (saleSelect && saleInput) {
            saleSelect.addEventListener('change', function() {
                saleInput.value = this.value;
                form.submit();
            });
        }

        if (filterType) {
            filterType.addEventListener('change', function() {
                if (this.value === 'all') {
                    if (saleInput) saleInput.value = '';
                    if (saleSelect) saleSelect.selectedIndex = 0;
                }
                form.submit();
            });
        }

        lucide.createIcons();
    });
    </script>
</body>
</html>

