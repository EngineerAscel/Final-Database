<?php
session_start();

if (!isset($_SESSION['username']) || ($_SESSION['role'] !== "sales")) {
    header("Location: login.php");
    exit();
}

include "db.php";

$today = date('Y-m-d');
$monthStart = date('Y-m-01');

$totalRevenueQuery = $conn->query("SELECT COALESCE(SUM(totalAmount), 0) AS total FROM sales WHERE status='approved'");
$totalRevenue = $totalRevenueQuery ? (float)$totalRevenueQuery->fetch_assoc()['total'] : 0;

$monthlyRevenueQuery = $conn->query("SELECT COALESCE(SUM(totalAmount), 0) AS total FROM sales WHERE status='approved' AND saleDate >= '$monthStart'");
$monthlyRevenue = $monthlyRevenueQuery ? (float)$monthlyRevenueQuery->fetch_assoc()['total'] : 0;

$monthlySalesQuery = $conn->query("SELECT COUNT(*) AS total FROM sales WHERE status='approved' AND saleDate >= '$monthStart'");
$monthlySalesCount = $monthlySalesQuery ? (int)$monthlySalesQuery->fetch_assoc()['total'] : 0;

$pendingSalesQuery = $conn->query("SELECT COUNT(*) AS pending FROM sales WHERE status='pending'");
$pendingSales = $pendingSalesQuery ? (int)$pendingSalesQuery->fetch_assoc()['pending'] : 0;

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

$recentSalesQuery = $conn->query("SELECT saleID, customerName, totalAmount, saleDate, status FROM sales ORDER BY saleDate DESC LIMIT 5");
$recentSales = [];
if ($recentSalesQuery) {
    while ($row = $recentSalesQuery->fetch_assoc()) {
        $recentSales[] = $row;
    }
}

$lastActivity = date('M d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Dashboard - 1 GARAGE</title>
    <script src="https://cdn.tailwindcss.com" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');

        :root {
            font-family: 'Inter', sans-serif;
        }

        .app-bg { background-color: #121212; }
        .sidebar-bg { background-color: #0d0d0d; }

        .bar-chart-container {
            display: flex;
            align-items: flex-end;
            height: 300px;
            gap: 16px;
        }
        .bar-item {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            width: 100%;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .bar {
            background: linear-gradient(180deg, #F97316 0%, #DC2626 100%);
            border-radius: 4px 4px 0 0;
            width: 100%;
            transition: height 0.5s ease;
            position: relative;
        }
        .bar:hover {
            box-shadow: 0 0 30px rgba(249, 115, 22, 0.9);
        }
        .tooltip-text {
            visibility: hidden;
            width: 140px;
            background-color: rgba(40, 40, 40, 0.95);
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px 8px;
            position: absolute;
            z-index: 10;
            bottom: calc(100% + 10px);
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
            font-size: 0.8rem;
        }
        .bar-item:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
        [data-lucide] {
            width: 1.5rem;
            height: 1.5rem;
        }
    </style>
</head>
<body class="app-bg text-gray-100 min-h-screen">
    <div class="flex">
        <?php include 'sales-sidebar.php'; ?>

        <main class="flex-1 md:ml-64 p-6 md:p-10 w-full space-y-10">
            <header class="flex flex-col md:flex-row justify-between items-start md:items-center border-b border-gray-800 pb-6">
                <div>
                    <p class="text-sm uppercase tracking-[0.3em] text-gray-500">Sales Control</p>
                    <h1 class="text-4xl font-light tracking-tight mt-2">
                        Welcome, <span class="font-bold text-red-500"><?= htmlspecialchars($_SESSION['username']); ?></span> 👋
                    </h1>
                </div>
                <div class="mt-4 md:mt-0 text-gray-500">
                    <span class="text-sm">Last sync: <?= $lastActivity; ?></span>
                </div>
            </header>

            <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">
                <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl transition hover:ring-2 hover:ring-red-500/50">
                    <div class="flex items-center justify-between">
                        <span class="text-xs uppercase tracking-widest text-gray-400">Total Revenue</span>
                        <i data-lucide="wallet" class="text-red-400"></i>
                    </div>
                    <div class="mt-4">
                        <p class="text-4xl font-extrabold text-white">₱<?= number_format($totalRevenue, 2); ?></p>
                        <p class="text-sm text-gray-500 mt-2">Approved sales, all time</p>
                    </div>
                </div>

                <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl transition hover:ring-2 hover:ring-blue-500/50">
                    <div class="flex items-center justify-between">
                        <span class="text-xs uppercase tracking-widest text-gray-400">This Month</span>
                        <i data-lucide="calendar" class="text-blue-400"></i>
                    </div>
                    <div class="mt-4">
                        <p class="text-4xl font-extrabold text-white">₱<?= number_format($monthlyRevenue, 2); ?></p>
                        <p class="text-sm text-gray-500 mt-2"><?= $monthlySalesCount; ?> tickets since <?= date('M 1, Y'); ?></p>
                    </div>
                </div>

                <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl transition hover:ring-2 hover:ring-yellow-400/50">
                    <div class="flex items-center justify-between">
                        <span class="text-xs uppercase tracking-widest text-gray-400">Pending</span>
                        <i data-lucide="clock" class="text-yellow-400"></i>
                    </div>
                    <div class="mt-4">
                        <p class="text-4xl font-extrabold text-white"><?= $pendingSales; ?></p>
                        <p class="text-sm text-gray-500 mt-2">Awaiting approval</p>
                    </div>
                </div>

                <div class="bg-red-900/50 p-6 rounded-2xl shadow-2xl transition ring-4 ring-red-500/70 border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <span class="text-xs uppercase tracking-widest text-red-100">Stock Alerts</span>
                        <i data-lucide="alert-triangle" class="text-red-300"></i>
                    </div>
                    <div class="mt-4">
                        <p class="text-4xl font-extrabold text-white"><?= $lowStockCount; ?></p>
                        <p class="text-sm text-red-100 mt-2">Products under threshold</p>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-2 bg-gray-900 p-8 rounded-2xl shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-800 pb-4 mb-6">
                        <h2 class="text-2xl font-semibold flex items-center space-x-2">
                            <i data-lucide="bar-chart-2" class="text-red-500 w-7 h-7"></i>
                            <span>Inventory Snapshot</span>
                        </h2>
                        <span class="text-xs text-gray-500">Updated <?= $today; ?></span>
                    </div>
                    <?php if (!empty($inventoryData)): ?>
                        <div class="bar-chart-container">
                            <?php foreach ($inventoryData as $item):
                                $percent = (int)ceil(($item['stockQuantity'] / $maxStock) * 100);
                            ?>
                                <div class="bar-item" style="height: 100%;">
                                    <div class="bar" style="height: <?= $percent; ?>%;">
                                        <span class="tooltip-text"><?= htmlspecialchars($item['productName']); ?>: <?= (int)$item['stockQuantity']; ?> units</span>
                                    </div>
                                    <span class="text-xs text-gray-400 mt-2 text-center truncate"><?= htmlspecialchars($item['productName']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-400">No inventory data available yet.</p>
                    <?php endif; ?>
                    <p class="mt-6 text-sm text-gray-500">Hover bars to see exact unit counts.</p>
                </div>

                <div class="bg-gray-900 p-8 rounded-2xl shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-800 pb-4 mb-6">
                        <h2 class="text-xl font-semibold flex items-center space-x-2">
                            <i data-lucide="activity" class="text-yellow-400 w-6 h-6"></i>
                            <span>Critical Items</span>
                        </h2>
                        <span class="text-xs text-gray-500"><?= $lowStockCount; ?> alerts</span>
                    </div>
                    <div class="space-y-4 max-h-72 overflow-y-auto pr-1">
                        <?php if ($lowStockCount === 0): ?>
                            <p class="text-gray-500 text-sm">All products above safety levels.</p>
                        <?php else: ?>
                            <?php foreach ($lowStockItems as $item): ?>
                                <div class="bg-gray-950/40 border border-gray-800 rounded-xl p-4 flex items-center justify-between">
                                    <div>
                                        <p class="font-semibold text-white"><?= htmlspecialchars($item['productName']); ?></p>
                                        <p class="text-xs uppercase tracking-widest text-gray-500">Stock Alert</p>
                                    </div>
                                    <span class="text-lg font-bold text-red-400"><?= (int)$item['stockQuantity']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="bg-gray-900 p-8 rounded-2xl shadow-2xl">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-semibold flex items-center space-x-2">
                        <i data-lucide="receipt" class="text-blue-400 w-7 h-7"></i>
                        <span>Recent Sales</span>
                    </h2>
                    <a href="sales-salesrecords.php" class="text-sm text-red-400 hover:text-red-300 transition flex items-center space-x-1">
                        <span>Open records</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                </div>

            <?php if (!empty($recentSales)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-800">
                        <thead class="bg-gray-800/60">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Sale ID</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-gray-900 divide-y divide-gray-800">
                            <?php foreach ($recentSales as $sale): ?>
                                <tr class="hover:bg-gray-800/60 transition">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-200">#<?= $sale['saleID']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?= htmlspecialchars($sale['customerName']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-400">₱<?= number_format((float)$sale['totalAmount'], 2); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400"><?= date('M d, Y', strtotime($sale['saleDate'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php
                                            $statusColors = [
                                                'approved' => 'bg-green-500/20 text-green-400',
                                                'pending' => 'bg-yellow-500/20 text-yellow-400',
                                                'rejected' => 'bg-red-500/20 text-red-400'
                                            ];
                                            $status = strtolower($sale['status']);
                                            $badgeClass = $statusColors[$status] ?? 'bg-gray-500/20 text-gray-300';
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $badgeClass; ?>">
                                            <?= ucfirst($status); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <i data-lucide="inbox" class="w-16 h-16 text-gray-700 mx-auto mb-4"></i>
                    <p class="text-gray-500">No recent sales recorded.</p>
                </div>
            <?php endif; ?>
            </section>

            <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <a href="sales-salesrecords.php" class="bg-gray-900 p-6 rounded-2xl shadow-2xl transition duration-300 hover:ring-2 hover:ring-blue-600/50 group">
                    <div class="flex items-center space-x-4">
                        <div class="bg-blue-900/30 p-4 rounded-xl group-hover:bg-blue-900/50 transition">
                            <i data-lucide="notebook-text" class="text-blue-400 w-8 h-8"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-white">Sales Records</h3>
                            <p class="text-sm text-gray-400">Review historical performance</p>
                        </div>
                    </div>
                </a>

                <a href="sales-orders.php" class="bg-gray-900 p-6 rounded-2xl shadow-2xl transition duration-300 hover:ring-2 hover:ring-green-600/50 group">
                    <div class="flex items-center space-x-4">
                        <div class="bg-green-900/30 p-4 rounded-xl group-hover:bg-green-900/50 transition">
                            <i data-lucide="file-plus-2" class="text-green-400 w-8 h-8"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-white">View Orders</h3>
                            <p class="text-sm text-gray-400">View customer transaction</p>
                        </div>
                    </div>
                </a>
            </section>
        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
