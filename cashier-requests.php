<?php
session_start();

// Protect page
if (!isset($_SESSION['username']) || 
   ($_SESSION['role'] !== "Cashier" && $_SESSION['role'] !== "Admin")) {
    header("Location: login.php");
    exit();
}

require "db.php";

// Fetch pending sales + cashier name using JOIN
$query = "
    SELECT 
        sales.saleID,
        sales.customerName,
        sales.totalAmount,
        sales.saleDate,
        sales.status,
        usermanagement.fullName AS cashierName
    FROM sales
    LEFT JOIN usermanagement ON sales.cashierID = usermanagement.userID
    WHERE sales.status = 'pending'
    ORDER BY sales.saleDate DESC
";

$result = $conn->query($query);
$pendingSales = [];
while ($row = $result->fetch_assoc()) {
    $pendingSales[] = $row;
}

$lastActivity = date('M d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Requests - 1 GARAGE</title>
    <script src="https://cdn.tailwindcss.com" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');

:root { font-family: 'Inter', sans-serif; }

.app-bg { background-color: #121212; }
.sidebar-bg { background-color: #0d0d0d; }

[data-lucide] { width: 1.5rem; height: 1.5rem; }
</style>
</head>

<body class="app-bg text-gray-100 min-h-screen">
<div class="flex">

    <?php include 'cashier-sidebar.php'; ?>

    <main class="flex-1 md:ml-64 p-6 md:p-10 w-full">

        <!-- Header -->
        <header class="mb-10 flex flex-col md:flex-row justify-between items-start md:items-center border-b border-gray-800 pb-6">
            <h1 class="text-4xl font-light tracking-tight flex items-center space-x-3">
                <i data-lucide="list-checks" class="text-yellow-500 w-10 h-10"></i>
                <span class="font-bold text-white">Pending Sales Requests</span>
            </h1>

            <div class="mt-4 md:mt-0 text-gray-500">
                <span class="text-sm">Last activity: <?= $lastActivity; ?></span>
            </div>
        </header>

        <!-- Section -->
        <section class="bg-gray-900 p-8 rounded-2xl shadow-2xl">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold flex items-center space-x-2">
                    <i data-lucide="clock" class="text-yellow-400 w-6 h-6"></i>
                    <span>Requests Awaiting Approval</span>
                </h2>
            </div>

            <?php if (!empty($pendingSales)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-800">
                    <thead class="bg-gray-800">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Sale</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Cashier</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Date</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-200">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-200">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="bg-gray-900 divide-y divide-gray-800">
                        <?php foreach ($pendingSales as $sale): ?>
                        <tr class="hover:bg-gray-800/50 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-300">
                                #<?= $sale['saleID']; ?>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                <?= htmlspecialchars($sale['cashierName']); ?>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                <?= htmlspecialchars($sale['customerName']); ?>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-400">
                                ₱<?= number_format($sale['totalAmount'], 2); ?>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                                <?= date('M d, Y g:i A', strtotime($sale['saleDate'])); ?>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-3 py-1.5 rounded-lg text-xs bg-yellow-600/30 text-yellow-400">
                                    Pending
                                </span>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm flex items-center justify-center gap-2">

                                <!-- View -->
                                <form action="generate-receipt.php" method="GET" target="_blank">
                                    <input type="hidden" name="saleID" value="<?= $sale['saleID']; ?>">
                                    <button class="bg-blue-900/40 hover:bg-blue-900/60 transition px-3 py-1.5 rounded-lg text-blue-400 flex items-center gap-1">
                                        <i data-lucide="file-text" class="w-4 h-4"></i> View
                                    </button>
                                </form>

                                <!-- Delete -->
                                <form action="delete-sale.php" method="POST" onsubmit="return confirm('Delete this request?');">
                                    <input type="hidden" name="saleID" value="<?= $sale['saleID']; ?>">
                                    <button class="bg-red-900/40 hover:bg-red-900/60 transition px-3 py-1.5 rounded-lg text-red-400 flex items-center gap-1">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
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
                <p class="text-gray-400">No pending requests at the moment.</p>
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
