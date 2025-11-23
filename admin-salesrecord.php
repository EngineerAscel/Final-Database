<?php
session_start();

// ✅ Protect page (Admin or Sales)
if (!isset($_SESSION['username']) || ($_SESSION['role'] !== "Admin" && $_SESSION['role'] !== "sales")) {
    header("Location: login.php");
    exit();
}

include "db.php";

// ✅ Handle Delete
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_sale'])) {
    $delete_saleID = intval($_POST['delete_saleID']); // sanitize input

    $conn->query("DELETE FROM sale_items WHERE saleID = $delete_saleID");
    $conn->query("DELETE FROM sales WHERE saleID = $delete_saleID");

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$records = $conn->query("SELECT * FROM sales WHERE status='approved' ORDER BY saleDate DESC");
$sales = [];
if ($records) {
    while ($row = $records->fetch_assoc()) {
        $sales[] = $row;
    }
}
$totalSales = count($sales);
$grandTotal = array_reduce($sales, function($carry, $sale) {
    return $carry + (float)$sale['totalAmount'];
}, 0.0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paid Records - Admin</title>
    <link rel="stylesheet" href="css/cashier.css">
    <script src="https://cdn.tailwindcss.com" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        :root { font-family: 'Inter', sans-serif; }
        .records-table tbody tr { background-color: #1f2937; border-bottom: 1px solid #374151; transition: background-color 0.2s; }
        .records-table tbody tr:hover { background-color: #2c3e50; }
        .records-table th,
        .records-table td {
            text-align: center;
        }
        .records-table th:first-child,
        .records-table td:first-child {
            text-align: left;
            padding-left: 2rem;
        }
        .records-table th:nth-child(2),
        .records-table td:nth-child(2) {
            text-align: left;
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
            <i data-lucide="receipt" class="text-red-500 w-10 h-10"></i>
            <div>
                <p class="text-sm uppercase tracking-[0.3em] text-gray-500">Financial Records</p>
                <h1 class="text-4xl font-bold tracking-tight text-white">Paid Sales Records</h1>
            </div>
        </div>
        <div class="flex gap-3">
            <div class="bg-gray-900 px-4 py-2 rounded-xl border border-gray-800 text-sm text-gray-400">
                Transactions <span class="text-white font-semibold ml-2"><?= $totalSales ?></span>
            </div>
            <div class="bg-green-900/20 px-4 py-2 rounded-xl border border-green-600/40 text-sm text-green-200">
                Total ₱ <span class="text-white font-semibold ml-2"><?= number_format($grandTotal, 2) ?></span>
            </div>
        </div>
    </header>

    <section class="bg-gray-900 p-6 rounded-2xl shadow-2xl overflow-x-auto">
        <div class="rounded-xl border border-gray-800">
            <table class="min-w-full divide-y divide-gray-800 records-table">
                <thead class="bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Transaction ID</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Payment Type</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Date</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-200">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    <?php if (!empty($sales)): ?>
                        <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold text-gray-300"><?= $sale['saleID'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-100"><?= htmlspecialchars($sale['customerName']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-400">₱<?= number_format($sale['totalAmount'], 2) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">Cash</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $sale['saleDate'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-3">
                                    <form method="GET" action="generate-receipt.php" target="_blank" class="inline">
                                        <input type="hidden" name="saleID" value="<?= $sale['saleID'] ?>">
                                        <button type="submit" class="px-3 py-1 bg-blue-600/80 hover:bg-blue-600 text-white rounded-lg transition">View Receipt</button>
                                    </form>
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this sale?');">
                                        <input type="hidden" name="delete_saleID" value="<?= $sale['saleID'] ?>">
                                        <button type="submit" name="delete_sale" class="px-3 py-1 bg-red-600/70 hover:bg-red-600 text-white rounded-lg transition">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-400">No paid records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>


</main>
</div>
<script>
    lucide.createIcons();
</script>

</body>
</html>

