<?php
session_start();

// ✅ Protect page
if (!isset($_SESSION['username']) ||
    ($_SESSION['role'] !== "Cashier" && $_SESSION['role'] !== "Admin" )) {
    header("Location: login.php");
    exit();
}

include "db.php";

// Fetch all approved sales
$salesRecordsQuery = $conn->query("SELECT * FROM sales WHERE status='approved' ORDER BY saleDate DESC");
$salesRecords = [];
if ($salesRecordsQuery) {
    while ($row = $salesRecordsQuery->fetch_assoc()) {
        $salesRecords[] = $row;
    }
}

$lastActivity = date('M d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Records - 1 GARAGE</title>
    <script src="https://cdn.tailwindcss.com" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Import Inter font and set body styles from dashboard */
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
                <h1 class="text-4xl font-light tracking-tight flex items-center space-x-3">
                    <i data-lucide="receipt-text" class="text-red-500 w-10 h-10"></i>
                    <span class="font-bold text-white">Sales Records</span>
                </h1>
                <div class="mt-4 md:mt-0 text-gray-500">
                    <span class="text-sm">Last activity: <?= $lastActivity; ?></span>
                </div>
            </header>

            <section class="bg-gray-900 p-8 rounded-2xl shadow-2xl">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold flex items-center space-x-2">
                        <i data-lucide="credit-card" class="text-green-500 w-6 h-6"></i>
                        <span>Approved Transactions</span>
                    </h2>
                    </div>

                <?php if (!empty($salesRecords)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-800">
                            <thead class="bg-gray-800">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Transaction ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Total Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Payment Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Date</th>
                                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-200">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-gray-900 divide-y divide-gray-800">
                                <?php foreach ($salesRecords as $record): ?>
                                    <tr class="hover:bg-gray-800/50 transition">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-300">#<?= $record['saleID']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?= htmlspecialchars($record['customerName']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-400">₱<?= number_format((float)$record['totalAmount'], 2); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-300">Cash</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400"><?= date('M d, Y H:i', strtotime($record['saleDate'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                            <form method="GET" action="generate-receipt.php" target="_blank" class="inline">
                                                <input type="hidden" name="saleID" value="<?= $record['saleID']; ?>">
                                                <button type="submit" class="text-blue-400 hover:text-blue-500 transition duration-150 flex items-center space-x-1 mx-auto bg-blue-900/30 px-3 py-1.5 rounded-lg hover:bg-blue-900/50">
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
                        <i data-lucide="inbox" class="w-16 h-16 text-gray-600 mx-auto mb-4"></i>
                        <p class="text-gray-400">No approved sales records found.</p>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html>