<?php
session_start();

if (!isset($_SESSION['username']) || ($_SESSION['role'] !== "Cashier")) {
    header("Location: login.php");
    exit();
}

include "db.php";

// Today's sales
$today = date('Y-m-d');
$todaySalesQuery = $conn->query("SELECT COUNT(*) AS count, COALESCE(SUM(totalAmount), 0) AS total FROM sales WHERE DATE(saleDate) = '$today' AND status='approved'");
$todaySales = $todaySalesQuery ? $todaySalesQuery->fetch_assoc() : ['count' => 0, 'total' => 0];

// This week's sales
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekSalesQuery = $conn->query("SELECT COUNT(*) AS count, COALESCE(SUM(totalAmount), 0) AS total FROM sales WHERE DATE(saleDate) >= '$weekStart' AND status='approved'");
$weekSales = $weekSalesQuery ? $weekSalesQuery->fetch_assoc() : ['count' => 0, 'total' => 0];

// Recent transactions (last 5)
$recentTransactionsQuery = $conn->query("SELECT saleID, customerName, totalAmount, saleDate FROM sales WHERE status='approved' ORDER BY saleDate DESC LIMIT 5");
$recentTransactions = [];
if ($recentTransactionsQuery) {
    while ($row = $recentTransactionsQuery->fetch_assoc()) {
        $recentTransactions[] = $row;
    }
}

// Total products available
$productCountQuery = $conn->query("SELECT COUNT(*) AS total FROM products");
$totalProducts = $productCountQuery ? (int)$productCountQuery->fetch_assoc()['total'] : 0;

$lastActivity = date('M d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Dashboard - 1 GARAGE</title>
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
        <?php include 'cashier-sidebar.php'; ?>

        <main class="flex-1 md:ml-64 p-6 md:p-10 w-full">
            <header class="mb-10 flex flex-col md:flex-row justify-between items-start md:items-center border-b border-gray-800 pb-6">
                <h1 class="text-4xl font-light tracking-tight">Welcome, <span class="font-bold text-red-500"><?= htmlspecialchars($_SESSION['username']); ?></span> 👋</h1>
                <div class="mt-4 md:mt-0 text-gray-500">
                    <span class="text-sm">Last activity: <?= $lastActivity; ?></span>
                </div>
            </header>

            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
                <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl transition duration-300 hover:ring-2 hover:ring-green-600/50">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-medium text-green-400 uppercase">Today's Sales</div>
                        <i data-lucide="dollar-sign" class="text-green-400"></i>
                    </div>
                    <div class="mt-2 flex items-end justify-between">
                        <span class="text-5xl font-extrabold text-white">₱<?= number_format((float)$todaySales['total'], 2); ?></span>
                        <div class="text-green-500 flex items-center text-sm ml-4">
                            <i data-lucide="arrow-up" class="w-4 h-4 mr-1"></i>
                            <span><?= (int)$todaySales['count']; ?> transactions</span>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl transition duration-300 hover:ring-2 hover:ring-blue-600/50">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-medium text-blue-400 uppercase">This Week</div>
                        <i data-lucide="trending-up" class="text-blue-400"></i>
                    </div>
                    <div class="mt-2 flex items-end justify-between">
                        <span class="text-5xl font-extrabold text-white">₱<?= number_format((float)$weekSales['total'], 2); ?></span>
                        <div class="text-blue-500 flex items-center text-sm ml-4">
                            <i data-lucide="calendar" class="w-4 h-4 mr-1"></i>
                            <span><?= (int)$weekSales['count']; ?> sales</span>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl transition duration-300 hover:ring-2 hover:ring-purple-600/50">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-medium text-purple-400 uppercase">Available Products</div>
                        <i data-lucide="package" class="text-purple-400"></i>
                    </div>
                    <div class="mt-2 flex items-end justify-between">
                        <span class="text-5xl font-extrabold text-white"><?= number_format($totalProducts); ?></span>
                        <div class="text-purple-500 flex items-center text-sm ml-4">
                            <i data-lucide="check-circle" class="w-4 h-4 mr-1"></i>
                            <span>In stock</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-gray-900 p-8 rounded-2xl shadow-2xl">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold flex items-center space-x-2">
                        <i data-lucide="receipt" class="text-red-500 w-8 h-8"></i>
                        <span>Recent Transactions</span>
                    </h2>
                    <a href="cashier-payments.php" class="text-sm text-blue-400 hover:text-blue-500 transition flex items-center space-x-1">
                        <span>View All</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                </div>

                <?php if (!empty($recentTransactions)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-800">
                            <thead class="bg-gray-800">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Transaction ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Date</th>
                                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-200">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-gray-900 divide-y divide-gray-800">
                                <?php foreach ($recentTransactions as $transaction): ?>
                                    <tr class="hover:bg-gray-800/50 transition">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-300">#<?= $transaction['saleID']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?= htmlspecialchars($transaction['customerName']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-400">₱<?= number_format((float)$transaction['totalAmount'], 2); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400"><?= date('M d, Y', strtotime($transaction['saleDate'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                            <form method="GET" action="generate-receipt.php" target="_blank" class="inline">
                                                <input type="hidden" name="saleID" value="<?= $transaction['saleID']; ?>">
                                                <button type="submit" class="text-blue-400 hover:text-blue-500 transition duration-150 flex items-center space-x-1 mx-auto">
                                                    <i data-lucide="file-text" class="w-4 h-4"></i>
                                                    <span>Receipt</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i data-lucide="inbox" class="w-16 h-16 text-gray-600 mx-auto mb-4"></i>
                        <p class="text-gray-400">No recent transactions found.</p>
                    </div>
                <?php endif; ?>
            </section>

            <section class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                <a href="cashier-items.php" class="bg-gray-900 p-6 rounded-2xl shadow-2xl transition duration-300 hover:ring-2 hover:ring-blue-600/50 cursor-pointer group">
                    <div class="flex items-center space-x-4">
                        <div class="bg-blue-900/30 p-4 rounded-xl group-hover:bg-blue-900/50 transition">
                            <i data-lucide="package" class="text-blue-400 w-8 h-8"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-white">View Products</h3>
                            <p class="text-sm text-gray-400">Browse available inventory</p>
                        </div>
                    </div>
                </a>

                <a href="cashier-payments.php" class="bg-gray-900 p-6 rounded-2xl shadow-2xl transition duration-300 hover:ring-2 hover:ring-green-600/50 cursor-pointer group">
                    <div class="flex items-center space-x-4">
                        <div class="bg-green-900/30 p-4 rounded-xl group-hover:bg-green-900/50 transition">
                            <i data-lucide="credit-card" class="text-green-400 w-8 h-8"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-white">Sales Records</h3>
                            <p class="text-sm text-gray-400">View all transaction history</p>
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