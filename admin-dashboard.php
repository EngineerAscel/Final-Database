<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== "Admin") {
    header("Location: login.php");
    exit();
}

include "db.php";

$lowStockQuery = $conn->query("SELECT productName, stockQuantity FROM products WHERE stockQuantity <= 3 ORDER BY stockQuantity ASC");
$lowStockItems = [];
if ($lowStockQuery) {
    while ($row = $lowStockQuery->fetch_assoc()) {
        $lowStockItems[] = $row;
    }
}
$lowStockCount = count($lowStockItems);

$inventoryResult = $conn->query("SELECT productName, stockQuantity FROM products ORDER BY stockQuantity DESC LIMIT 7");
$inventoryData = [];
$maxStock = 0;
if ($inventoryResult) {
    while ($row = $inventoryResult->fetch_assoc()) {
        $inventoryData[] = $row;
        $maxStock = max($maxStock, (int)$row['stockQuantity']);
    }
}
$maxStock = max($maxStock, 1);

$productCountResult = $conn->query("SELECT COUNT(*) AS total FROM products");
$totalProducts = $productCountResult ? (int)$productCountResult->fetch_assoc()['total'] : 0;

$supplierCountResult = $conn->query("SELECT COUNT(*) AS total FROM supplier");
$totalSuppliers = $supplierCountResult ? (int)$supplierCountResult->fetch_assoc()['total'] : 0;

$clientCountResult = $conn->query("SELECT COUNT(*) AS total FROM clientinfo");
$totalClients = $clientCountResult ? (int)$clientCountResult->fetch_assoc()['total'] : 0;

// ✅ Fetch clients
$clientsResult = $conn->query("SELECT * FROM clientinfo ORDER BY clientID ASC");
$clients = [];
$clientsById = [];
if ($clientsResult) {
    while ($row = $clientsResult->fetch_assoc()) {
        $clients[] = $row;
        $key = $row['clientID'] ?? null;
        if ($key !== null) {
            $clientsById[$key] = $row['clientName'] ?? '';
        }
    }
}

// ✅ Fetch Sales
$salesResult = $conn->query("SELECT * FROM sales ORDER BY saleDate DESC");
$sales = [];
if ($salesResult) {
    while ($row = $salesResult->fetch_assoc()) {
        $sales[] = $row;
    }
}

$totalTransactions = count($sales);
$totalRevenue = 0;
$today = date('Y-m-d');
$todayRevenue = 0;
$todayCount = 0;

foreach ($sales as $sale) {
    $amount = isset($sale['totalAmount']) ? (float)$sale['totalAmount'] : 0;
    $totalRevenue += $amount;

    $saleDateRaw = $sale['saleDate'] ?? null;
    if ($saleDateRaw && date('Y-m-d', strtotime($saleDateRaw)) === $today) {
        $todayRevenue += $amount;
        $todayCount++;
    }
}

$averageOrder = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;
$latestSaleDate = $sales[0]['saleDate'] ?? null;
$lastActivity = $latestSaleDate ? date('M d, Y', strtotime($latestSaleDate)) : date('M d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Motorcycle Garage Dashboard</title>
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
    </style>
</head>
<body class="app-bg text-gray-100 min-h-screen">
    <div class="flex">
        <?php
        if (!defined('ADMIN_SIDEBAR_VARIANT')) {
            define('ADMIN_SIDEBAR_VARIANT', 'tailwind');
        }
        include 'admin-sidebar.php';
        ?>

        <main class="flex-1 md:ml-64 p-6 md:p-10 w-full">
            <header class="mb-10 flex flex-col md:flex-row justify-between items-start md:items-center border-b border-gray-800 pb-6">
                <h1 class="text-4xl font-light tracking-tight">Welcome, <span class="font-bold text-red-500"><?= htmlspecialchars($_SESSION['username']); ?></span> 👋</h1>
                <div class="mt-4 md:mt-0 text-gray-500">
                    <span class="text-sm">Last wrench turned: <?= $lastActivity; ?></span>
                </div>
            </header>

            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-12">
                <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl transition duration-300 hover:ring-2 hover:ring-blue-600/50 border border-gray-800">
                    <div class="flex items-center justify-between text-gray-400 text-xs uppercase tracking-widest">
                        <span>Total Products</span>
                        <i data-lucide="package" class="text-blue-400"></i>
                    </div>
                    <p class="text-5xl font-bold text-white mt-4"><?= number_format($totalProducts); ?></p>
                    <p class="text-sm text-gray-500 mt-2">In inventory</p>
                </div>

                <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl transition duration-300 hover:ring-2 hover:ring-gray-400/50 border border-gray-800">
                    <div class="flex items-center justify-between text-gray-400 text-xs uppercase tracking-widest">
                        <span>Trusted Suppliers</span>
                        <i data-lucide="truck" class="text-gray-400"></i>
                    </div>
                    <p class="text-5xl font-bold text-white mt-4"><?= number_format($totalSuppliers); ?></p>
                    <p class="text-sm text-gray-500 mt-2">Verified partners</p>
                </div>

                <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl border border-gray-800 hover:ring-1 hover:ring-blue-600/40 transition">
                    <div class="flex items-center justify-between text-gray-400 text-xs uppercase tracking-widest">
                        <span>Total Transactions</span>
                        <i data-lucide="refresh-ccw" class="text-blue-400"></i>
                    </div>
                    <p class="text-5xl font-bold text-white mt-4"><?= number_format($totalTransactions); ?></p>
                    <p class="text-sm text-gray-500 mt-2">All recorded sales</p>
                </div>

                <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl border border-gray-800 hover:ring-1 hover:ring-green-600/40 transition">
                    <div class="flex items-center justify-between text-gray-400 text-xs uppercase tracking-widest">
                        <span>Total Revenue</span>
                        <i data-lucide="banknote" class="text-green-400"></i>
                    </div>
                    <p class="text-5xl font-bold text-white mt-4">₱<?= number_format($totalRevenue, 2); ?></p>
                    <p class="text-sm text-gray-500 mt-2">Lifetime earnings</p>
                </div>

                <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl border border-gray-800 hover:ring-1 hover:ring-yellow-500/40 transition">
                    <div class="flex items-center justify-between text-gray-400 text-xs uppercase tracking-widest">
                        <span>Average Order</span>
                        <i data-lucide="chart-bar" class="text-yellow-400"></i>
                    </div>
                    <p class="text-5xl font-bold text-white mt-4">₱<?= number_format($averageOrder, 2); ?></p>
                    <p class="text-sm text-gray-500 mt-2">Per transaction</p>
                </div>

                <div class="bg-red-900/50 p-6 rounded-2xl shadow-2xl transition duration-300 ring-4 ring-red-500/70 border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-medium text-red-300 uppercase">Stock Alerts</div>
                        <i data-lucide="alert-triangle" class="text-red-500"></i>
                    </div>
                    <div class="mt-2">
                        <span class="text-5xl font-extrabold text-white"><?= $lowStockCount; ?></span>
                    </div>
                    <?php if ($lowStockCount > 0): ?>
                        <p class="mt-2 text-sm text-red-200">Critical items:</p>
                        <ul class="mt-2 space-y-1 text-sm text-red-100 max-h-24 overflow-y-auto pr-2">
                            <?php foreach ($lowStockItems as $item): ?>
                                <li>⚠️ <?= htmlspecialchars($item['productName']); ?> — <?= (int)$item['stockQuantity']; ?> left</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="mt-2 text-sm text-red-200">All products above safety stock.</p>
                    <?php endif; ?>
                </div>

                <div class="bg-red-900/50 p-6 rounded-2xl shadow-2xl border border-red-700/40 hover:ring-1 hover:ring-red-500/40 transition">
                    <div class="flex items-center justify-between text-red-200 text-xs uppercase tracking-widest">
                        <span>Today's Revenue</span>
                        <i data-lucide="flame" class="text-red-300"></i>
                    </div>
                    <p class="text-4xl font-bold text-white mt-4">₱<?= number_format($todayRevenue, 2); ?></p>
                    <p class="text-sm text-red-200 mt-2"><?= number_format($todayCount); ?> sales today</p>
                </div>
            </section>

            <section class="bg-gray-900 rounded-3xl p-8 shadow-2xl border border-gray-800 mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-2xl font-semibold flex items-center gap-2">
                            <i data-lucide="line-chart" class="text-red-500 w-7 h-7"></i>
                            Recorded Sales
                        </h2>
                        <p class="text-sm text-gray-500">Track every approved sale in real time.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="text" id="salesSearch" placeholder="Search by client or #ID" class="bg-gray-800 border border-gray-700 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500/50">
                    </div>
                </div>

                <?php if (!empty($sales)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-800" id="salesTable">
                            <thead class="bg-gray-800/60">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Sale #</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Product ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Quantity</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Unit Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-gray-900 divide-y divide-gray-800 text-sm">
                                <?php foreach ($sales as $sale): 
                                    $clientId = $sale['clientID'] ?? null;
                                    $clientLabel = $clientId && isset($clientsById[$clientId])
                                        ? htmlspecialchars($clientsById[$clientId])
                                        : 'Client #' . ($clientId !== null ? (int)$clientId : '—');
                                    $productId = $sale['productID'] ?? null;
                                    $userId = $sale['userID'] ?? null;
                                    $quantity = $sale['quantity'] ?? null;
                                    $unitPrice = isset($sale['unitPrice']) ? (float)$sale['unitPrice'] : null;
                                    $totalAmount = isset($sale['totalAmount']) ? (float)$sale['totalAmount'] : null;
                                    $saleDate = !empty($sale['saleDate']) ? date('M d, Y g:i A', strtotime($sale['saleDate'])) : '—';
                                ?>
                                    <tr class="hover:bg-gray-800/50 transition">
                                        <td class="px-6 py-4 font-semibold text-gray-200">#<?= (int)($sale['saleID'] ?? 0); ?></td>
                                        <td class="px-6 py-4 text-gray-300"><?= $clientLabel; ?></td>
                                        <td class="px-6 py-4 text-gray-400"><?= $productId !== null ? (int)$productId : '—'; ?></td>
                                        <td class="px-6 py-4 text-gray-400"><?= $userId !== null ? (int)$userId : '—'; ?></td>
                                        <td class="px-6 py-4 text-gray-200"><?= $quantity !== null ? (int)$quantity : '—'; ?></td>
                                        <td class="px-6 py-4 text-gray-300"><?= $unitPrice !== null ? '₱' . number_format($unitPrice, 2) : '—'; ?></td>
                                        <td class="px-6 py-4 text-green-400 font-semibold"><?= $totalAmount !== null ? '₱' . number_format($totalAmount, 2) : '—'; ?></td>
                                        <td class="px-6 py-4 text-gray-400"><?= $saleDate; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-16">
                        <i data-lucide="inbox" class="w-16 h-16 text-gray-700 mx-auto mb-4"></i>
                        <p class="text-gray-500">No sales recorded yet.</p>
                    </div>
                <?php endif; ?>
            </section>

            <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-gray-900 rounded-3xl p-6 border border-gray-800 shadow-2xl space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-semibold flex items-center gap-2">
                            <i data-lucide="users" class="text-red-500"></i>
                            Recent Clients
                        </h3>
                        <span class="text-xs text-gray-500 uppercase tracking-widest"><?= count($clients); ?> on record</span>
                    </div>
                    <div class="space-y-4 max-h-72 overflow-y-auto pr-2">
                        <?php if (!empty($clients)): ?>
                            <?php foreach (array_slice($clients, 0, 6) as $client): ?>
                                <div class="bg-gray-800/60 rounded-2xl p-4 flex items-center justify-between">
                                    <div>
                                        <p class="font-semibold text-gray-100"><?= htmlspecialchars($client['clientName']); ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($client['email']); ?></p>
                                    </div>
                                    <span class="text-xs text-gray-400">#<?= (int)$client['clientID']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-gray-500 text-sm">No clients yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide && typeof lucide.createIcons === 'function') {
                lucide.createIcons();
            }

            const searchInput = document.getElementById('salesSearch');
            const table = document.getElementById('salesTable');

            if (searchInput && table) {
                searchInput.addEventListener('input', () => {
                    const term = searchInput.value.toLowerCase();
                    table.querySelectorAll('tbody tr').forEach(row => {
                        const text = row.innerText.toLowerCase();
                        row.style.display = text.includes(term) ? '' : 'none';
                    });
                });
            }
        });
    </script>
</body>
</html>
