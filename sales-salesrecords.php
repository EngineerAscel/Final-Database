<?php
session_start();

// ✅ Protect page
if (!isset($_SESSION['username']) || 
    ($_SESSION['role'] !== "sales" && $_SESSION['role'] !== "Admin" )) {
    header("Location: login.php");
    exit();
}

include "db.php";

// Fetch all approved sales for stats
$allSalesQuery = $conn->query("SELECT * FROM sales WHERE status='approved' ORDER BY saleDate DESC");
$allSales = [];
if ($allSalesQuery) {
    while ($row = $allSalesQuery->fetch_assoc()) {
        $allSales[] = $row;
    }
}

// Calculate stats
$totalSales = count($allSales);
$totalRevenue = array_sum(array_column($allSales, 'totalAmount'));

$today = date('Y-m-d');
$todaySales = array_filter($allSales, fn($s) => date('Y-m-d', strtotime($s['saleDate'])) === $today);
$todayCount = count($todaySales);
$todayRevenue = array_sum(array_column($todaySales, 'totalAmount'));

$thisMonth = date('Y-m');
$monthSales = array_filter($allSales, fn($s) => date('Y-m', strtotime($s['saleDate'])) === $thisMonth);
$monthCount = count($monthSales);
$monthRevenue = array_sum(array_column($monthSales, 'totalAmount'));

$lastActivity = date('M d, Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales • Sales Records</title>
    <script src="https://cdn.tailwindcss.com" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        :root { font-family: 'Inter', sans-serif; }
        body { font-family: 'Inter', sans-serif; }
        .app-bg { background-color: #121212; }
        .sidebar-bg { background-color: #0d0d0d; }
    </style>
</head>
<body class="app-bg text-gray-100 min-h-screen">
<div class="flex">
    <?php include "sales-sidebar.php"; ?>

    <main class="flex-1 md:ml-64 p-6 md:p-10 space-y-10">
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center border-b border-gray-800 pb-6">
            <div>
                <p class="text-sm uppercase tracking-[0.35em] text-gray-500">Transaction History</p>
                <h1 class="text-4xl font-light tracking-tight mt-2 flex items-center space-x-3">
                    <span class="font-bold text-red-500">Sales Records</span>
                    <i data-lucide="receipt" class="w-8 h-8 text-red-500"></i>
                </h1>
            </div>
            <div class="mt-6 md:mt-0 text-gray-400 text-sm flex items-center space-x-2">
                <i data-lucide="refresh-ccw" class="w-4 h-4 text-red-400"></i>
                <span>Updated <?= $lastActivity; ?></span>
            </div>
        </header>

        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl transition duration-300 hover:ring-2 hover:ring-blue-600/50">
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wider text-gray-400">Total Sales</span>
                    <i data-lucide="shopping-bag" class="text-blue-400"></i>
                </div>
                <div class="mt-4">
                    <p class="text-4xl font-bold text-white"><?= number_format($totalSales); ?></p>
                    <p class="text-sm text-gray-500 mt-2">all approved transactions</p>
                </div>
            </div>

            <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl transition duration-300 hover:ring-2 hover:ring-green-600/50">
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wider text-gray-400">Total Revenue</span>
                    <i data-lucide="dollar-sign" class="text-green-400"></i>
                </div>
                <div class="mt-4">
                    <p class="text-4xl font-bold text-white">₱<?= number_format($totalRevenue, 2); ?></p>
                    <p class="text-sm text-gray-500 mt-2">lifetime earnings</p>
                </div>
            </div>

            <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl transition duration-300 hover:ring-2 hover:ring-yellow-500/50">
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wider text-gray-400">Today's Sales</span>
                    <i data-lucide="calendar" class="text-yellow-400"></i>
                </div>
                <div class="mt-4">
                    <p class="text-4xl font-bold text-white"><?= number_format($todayCount); ?></p>
                    <p class="text-sm text-gray-500 mt-2">₱<?= number_format($todayRevenue, 2); ?> revenue</p>
                </div>
            </div>

            <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl transition duration-300 hover:ring-2 hover:ring-purple-500/50">
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wider text-gray-400">This Month</span>
                    <i data-lucide="trending-up" class="text-purple-400"></i>
                </div>
                <div class="mt-4">
                    <p class="text-4xl font-bold text-white"><?= number_format($monthCount); ?></p>
                    <p class="text-sm text-gray-500 mt-2">₱<?= number_format($monthRevenue, 2); ?> revenue</p>
                </div>
            </div>
        </section>

        <section class="bg-gray-900 rounded-2xl p-8 shadow-2xl">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-semibold flex items-center space-x-2">
                    <i data-lucide="file-text" class="text-red-500 w-7 h-7"></i>
                    <span>Paid Records</span>
                </h2>
            </div>

            <?php if (!empty($allSales)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-800">
                        <thead class="bg-gray-800/60">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Transaction ID</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Total Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Payment Type</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Date</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-400">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-gray-900 divide-y divide-gray-800">
                            <?php foreach ($allSales as $r): ?>
                                <tr class="hover:bg-gray-800/60 transition">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-300">#<?= $r['saleID']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?= htmlspecialchars($r['customerName']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-400">₱<?= number_format($r['totalAmount'], 2); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-500/20 text-blue-400">Cash</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400"><?= date('M d, Y', strtotime($r['saleDate'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                        <form method="GET" action="generate-receipt.php" target="_blank" class="inline">
                                            <input type="hidden" name="saleID" value="<?= $r['saleID']; ?>">
                                            <button type="submit" class="text-blue-400 hover:text-blue-300 transition duration-150 flex items-center space-x-1 mx-auto">
                                                <i data-lucide="file-text" class="w-4 h-4"></i>
                                                <span>View Receipt</span>
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
                    <i data-lucide="inbox" class="w-16 h-16 text-gray-700 mx-auto mb-4"></i>
                    <p class="text-gray-500">No paid records found.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<script>
    lucide.createIcons();
</script>
</body>
</html>
