<?php
session_start();

// ✅ Protect page (Only Sales Account)
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'sales') {
    header("Location: login.php");
    exit();
}

include "db.php";

// ---------------------
// Handle AJAX Actions
// ---------------------
if (isset($_POST['ajax_action'])) {
    $action = $_POST['ajax_action'];
    $saleID = intval($_POST['saleID']);

    // --- Fetch sale items for confirmation modal ---
    if ($action === 'get_items') {
        $stmt = $conn->prepare("
            SELECT p.productName, si.quantity, si.unitPrice 
            FROM sale_items si
            JOIN products p ON si.productID = p.productID
            WHERE si.saleID = ?
        ");
        $stmt->bind_param("i", $saleID);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        echo json_encode(["success" => true, "items" => $items]);
        exit;
    }

    // --- Approve ---
    if ($action === 'approve_confirmed') {
        $check = $conn->prepare("SELECT status FROM sales WHERE saleID = ?");
        $check->bind_param("i", $saleID);
        $check->execute();
        $check->bind_result($currentStatus);
        $check->fetch();
        $check->close();

        if ($currentStatus === 'approved' || $currentStatus === 'rejected') {
            echo json_encode(["success" => false, "error" => "This sale has already been processed."]);
            exit;
        }

        $conn->begin_transaction();
        try {
            // 1️⃣ Deduct stock per item
            $itemsQuery = $conn->prepare("SELECT productID, quantity FROM sale_items WHERE saleID = ?");
            $itemsQuery->bind_param("i", $saleID);
            $itemsQuery->execute();
            $itemsResult = $itemsQuery->get_result();

            while ($item = $itemsResult->fetch_assoc()) {
                $productID = $item['productID'];
                $quantity  = $item['quantity'];

                $updateStock = $conn->prepare("
                    UPDATE products 
                    SET stockQuantity = GREATEST(stockQuantity - ?, 0)
                    WHERE productID = ?
                ");
                $updateStock->bind_param("ii", $quantity, $productID);
                $updateStock->execute();
                $updateStock->close();
            }
            $itemsQuery->close();

            // 2️⃣ Update sale status
            $updateStatus = $conn->prepare("UPDATE sales SET status = 'approved' WHERE saleID = ?");
            $updateStatus->bind_param("i", $saleID);
            $updateStatus->execute();
            $updateStatus->close();

            $conn->commit();
            echo json_encode(["success" => true, "status" => "approved"]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        exit;
    }

    // --- Reject ---
    if ($action === 'reject') {
        $check = $conn->prepare("SELECT status FROM sales WHERE saleID = ?");
        $check->bind_param("i", $saleID);
        $check->execute();
        $check->bind_result($currentStatus);
        $check->fetch();
        $check->close();

        if ($currentStatus === 'approved' || $currentStatus === 'rejected') {
            echo json_encode(["success" => false, "error" => "This sale has already been processed."]);
            exit;
        }

        $stmt = $conn->prepare("UPDATE sales SET status = 'rejected' WHERE saleID = ?");
        $stmt->bind_param("i", $saleID);
        $stmt->execute();
        $stmt->close();

        echo json_encode(["success" => true, "status" => "rejected"]);
        exit;
    }

    // --- Delete ---
    if ($action === 'delete') {
        $delItems = $conn->prepare("DELETE FROM sale_items WHERE saleID = ?");
        $delItems->bind_param("i", $saleID);
        $delItems->execute();
        $delItems->close();

        $delSale = $conn->prepare("DELETE FROM sales WHERE saleID = ?");
        $delSale->bind_param("i", $saleID);
        $delSale->execute();
        $delSale->close();

        echo json_encode(["success" => true, "deleted" => true]);
        exit;
    }
}

// ---------------------
// Fetch all sales for this account
// ---------------------
$currentSales = $_SESSION['username'];

$sql = "
SELECT 
    s.saleID, 
    s.customerName, 
    s.totalAmount, 
    s.status, 
    s.saleDate,
    uc.username AS cashierName
FROM sales s
LEFT JOIN usermanagement uc ON s.cashierID = uc.userID
LEFT JOIN usermanagement us ON s.salesAccountID = us.userID 
WHERE us.username = ?
ORDER BY s.saleDate DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $currentSales);
$stmt->execute();
$res = $stmt->get_result();
$sales = [];
while ($sale = $res->fetch_assoc()) {
    $sales[] = $sale;
}
$stmt->close();

$totalApprovals = count($sales);
$pendingApprovals = 0;
$approvedToday = 0;
$rejectedCount = 0;
$today = date('Y-m-d');
$recentActivity = array_slice($sales, 0, 5);

foreach ($sales as $sale) {
    if ($sale['status'] === 'pending') {
        $pendingApprovals++;
    } elseif ($sale['status'] === 'rejected') {
        $rejectedCount++;
    } elseif ($sale['status'] === 'approved' && substr($sale['saleDate'], 0, 10) === $today) {
        $approvedToday++;
    }
}

$lastUpdated = date('M d, Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales • Approvals</title>
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
                <p class="text-sm uppercase tracking-[0.35em] text-gray-500">Workflow</p>
                <h1 class="text-4xl font-light tracking-tight mt-2 flex items-center space-x-3">
                    <span class="font-bold text-red-500">Sales Approvals</span>
                    <i data-lucide="clipboard-check" class="w-8 h-8 text-red-500"></i>
                </h1>
            </div>
            <div class="mt-6 md:mt-0 text-gray-400 text-sm flex items-center space-x-2">
                <i data-lucide="refresh-ccw" class="w-4 h-4 text-red-400"></i>
                <span>Updated <?= $lastUpdated; ?></span>
            </div>
        </header>

        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">
            <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl hover:ring-2 hover:ring-red-500/40 transition">
                <div class="flex items-center justify-between text-gray-400 text-xs uppercase tracking-widest">
                    <span>Pending</span>
                    <i data-lucide="loader" class="text-red-400 animate-spin"></i>
                </div>
                <p class="text-4xl font-extrabold text-white mt-4"><?= number_format($pendingApprovals); ?></p>
                <p class="text-sm text-gray-500 mt-2">Awaiting review</p>
            </div>

            <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl hover:ring-2 hover:ring-green-500/40 transition">
                <div class="flex items-center justify-between text-gray-400 text-xs uppercase tracking-widest">
                    <span>Approved Today</span>
                    <i data-lucide="check-circle" class="text-green-400"></i>
                </div>
                <p class="text-4xl font-extrabold text-white mt-4"><?= number_format($approvedToday); ?></p>
                <p class="text-sm text-gray-500 mt-2">Processed <?= $today; ?></p>
            </div>

            <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl hover:ring-2 hover:ring-yellow-500/40 transition">
                <div class="flex items-center justify-between text-gray-400 text-xs uppercase tracking-widest">
                    <span>Rejected</span>
                    <i data-lucide="x-octagon" class="text-yellow-400"></i>
                </div>
                <p class="text-4xl font-extrabold text-white mt-4"><?= number_format($rejectedCount); ?></p>
                <p class="text-sm text-gray-500 mt-2">Need follow up</p>
            </div>

            <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl hover:ring-2 hover:ring-blue-500/40 transition">
                <div class="flex items-center justify-between text-gray-400 text-xs uppercase tracking-widest">
                    <span>Total Queue</span>
                    <i data-lucide="package-check" class="text-blue-400"></i>
                </div>
                <p class="text-4xl font-extrabold text-white mt-4"><?= number_format($totalApprovals); ?></p>
                <p class="text-sm text-gray-500 mt-2">All submissions</p>
            </div>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 bg-gray-900 rounded-2xl shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between border-b border-gray-800 px-6 py-4">
                    <div>
                        <h2 class="text-2xl font-semibold flex items-center space-x-2">
                            <i data-lucide="list-checks" class="text-red-500 w-6 h-6"></i>
                            <span>Approval Queue</span>
                        </h2>
                        <p class="text-sm text-gray-500">Manage approvals, rejections, and cleanup</p>
                    </div>
                </div>

                <?php if (!empty($sales)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-800">
                            <thead class="bg-gray-800/60">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Sale</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Cashier</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-400">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-gray-900 divide-y divide-gray-800">
                                <?php foreach ($sales as $sale): ?>
                                    <?php
                                        $statusColors = [
                                            'approved' => 'bg-green-500/20 text-green-400',
                                            'rejected' => 'bg-red-500/20 text-red-400',
                                            'pending'  => 'bg-yellow-500/20 text-yellow-400',
                                        ];
                                        $statusClass = $statusColors[$sale['status']] ?? 'bg-gray-500/20 text-gray-300';
                                    ?>
                                    <tr class="hover:bg-gray-800/50 transition" id="row-<?= $sale['saleID'] ?>">
                                        <td class="px-6 py-4">
                                            <p class="text-white font-semibold">#<?= htmlspecialchars($sale['saleID']) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($sale['customerName']) ?></p>
                                            <p class="text-xs text-gray-500"><?= date('M d, Y g:i A', strtotime($sale['saleDate'])); ?></p>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-300"><?= htmlspecialchars($sale['cashierName'] ?? '—'); ?></td>
                                        <td class="px-6 py-4">
                                            <p class="text-green-400 font-semibold">₱<?= number_format($sale['totalAmount'], 2); ?></p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-3 py-1 rounded-full text-xs font-semibold status <?= $statusClass; ?>">
                                                <?= ucfirst($sale['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right space-x-2">
                                            <?php if ($sale['status'] === 'pending'): ?>
                                                <button class="inline-flex items-center px-3 py-1 rounded-full bg-green-600 text-white text-xs font-semibold hover:bg-green-500 transition approve-btn"
                                                        onclick="showApprovalModal(<?= $sale['saleID']; ?>)">
                                                    <i data-lucide="check" class="w-3.5 h-3.5 mr-1"></i>Approve
                                                </button>
                                                <button class="inline-flex items-center px-3 py-1 rounded-full bg-red-600 text-white text-xs font-semibold hover:bg-red-500 transition reject-btn"
                                                        onclick="handleAction(<?= $sale['saleID']; ?>, 'reject')">
                                                    <i data-lucide="x" class="w-3.5 h-3.5 mr-1"></i>Reject
                                                </button>
                                            <?php endif; ?>
                                            <form method="GET" action="generate-receipt.php" target="_blank" class="inline">
                                                <input type="hidden" name="saleID" value="<?= $sale['saleID']; ?>">
                                                <button type="submit" class="inline-flex items-center px-3 py-1 rounded-full bg-blue-600 text-white text-xs font-semibold hover:bg-blue-500 transition">
                                                    <i data-lucide="file-text" class="w-3.5 h-3.5 mr-1"></i>View
                                                </button>
                                            </form>
                                            <button class="inline-flex items-center px-3 py-1 rounded-full bg-gray-700 text-white text-xs font-semibold hover:bg-gray-600 transition"
                                                    onclick="handleAction(<?= $sale['saleID']; ?>, 'delete')">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5 mr-1"></i>Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-16">
                        <i data-lucide="inbox" class="w-20 h-20 text-gray-700 mx-auto mb-4"></i>
                        <p class="text-gray-400 text-lg">No sales records found.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bg-gray-900 rounded-2xl shadow-2xl p-6 space-y-4">
                <h2 class="text-2xl font-semibold flex items-center space-x-2">
                    <i data-lucide="activity" class="text-yellow-400 w-6 h-6"></i>
                    <span>Recent Activity</span>
                </h2>
                <div class="space-y-4 max-h-[420px] overflow-y-auto pr-2">
                    <?php if (!empty($recentActivity)): ?>
                        <?php foreach ($recentActivity as $activity): ?>
                            <?php
                                $badge = $statusColors[$activity['status']] ?? 'bg-gray-500/20 text-gray-300';
                            ?>
                            <div class="bg-gray-950/40 border border-gray-800 rounded-xl p-4 flex items-center justify-between">
                                <div>
                                    <p class="font-semibold text-white">#<?= htmlspecialchars($activity['saleID']); ?> — <?= htmlspecialchars($activity['customerName']); ?></p>
                                    <p class="text-xs text-gray-500"><?= date('M d, Y g:i A', strtotime($activity['saleDate'])); ?></p>
                                </div>
                                <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $badge; ?>">
                                    <?= ucfirst($activity['status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm">No recent updates yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
</div>

<!-- Approval Modal -->
<div id="approveModal" class="modal-backdrop fixed inset-0 hidden items-center justify-center p-4 z-40">
    <div class="bg-gray-900 rounded-2xl w-full max-w-lg shadow-2xl border border-gray-800">
        <div class="flex items-center justify-between border-b border-gray-800 px-6 py-4">
            <div>
                <p class="text-xs uppercase tracking-[0.25em] text-gray-500">Confirm</p>
                <h3 class="text-xl font-semibold text-white">Approve Sale</h3>
            </div>
            <button class="text-gray-400 hover:text-red-400" onclick="toggleModal(false)">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div class="px-6 py-6 space-y-4">
            <p class="text-sm text-gray-400">The following items will be deducted from stock:</p>
            <div class="overflow-x-auto border border-gray-800 rounded-xl">
                <table class="min-w-full divide-y divide-gray-800 text-sm" id="itemTable">
                    <thead class="bg-gray-800/60 text-gray-400 text-xs uppercase tracking-widest">
                        <tr>
                            <th class="px-4 py-2 text-left">Product</th>
                            <th class="px-4 py-2 text-left">Qty</th>
                            <th class="px-4 py-2 text-left">Price</th>
                        </tr>
                    </thead>
                    <tbody class="bg-gray-900 text-gray-100"></tbody>
                </table>
            </div>
            <div class="flex flex-col md:flex-row items-center justify-end gap-3 pt-4 border-t border-gray-800">
                <button class="w-full md:w-auto px-4 py-3 rounded-xl bg-gray-800 text-gray-200 hover:bg-gray-700 transition" onclick="toggleModal(false)">
                    Cancel
                </button>
                <button class="w-full md:w-auto px-4 py-3 rounded-xl bg-green-600 text-white font-semibold hover:bg-green-500 transition" onclick="confirmApproval()">
                    Confirm Approval
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedSale = null;

function toggleModal(show) {
    const modal = document.getElementById("approveModal");
    if (show) {
        modal.classList.remove("hidden");
        modal.classList.add("flex");
    } else {
        modal.classList.add("hidden");
        modal.classList.remove("flex");
    }
}

function showApprovalModal(saleID) {
    selectedSale = saleID;
    fetch("sales-approval.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: `ajax_action=get_items&saleID=${saleID}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const tbody = document.querySelector("#itemTable tbody");
            tbody.innerHTML = "";
            data.items.forEach(item => {
                tbody.innerHTML += `
                    <tr class="divide-x divide-gray-800">
                        <td class="px-4 py-2">${item.productName}</td>
                        <td class="px-4 py-2">${item.quantity}</td>
                        <td class="px-4 py-2">₱${parseFloat(item.unitPrice).toFixed(2)}</td>
                    </tr>`;
            });
            toggleModal(true);
        } else {
            alert("Failed to load items.");
        }
    });
}

function confirmApproval() {
    if (!selectedSale) return;
    fetch("sales-approval.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: `ajax_action=approve_confirmed&saleID=${selectedSale}`
    })
    .then(res => res.json())
    .then(data => {
        toggleModal(false);
        if (data.success) {
            const row = document.getElementById(`row-${selectedSale}`);
            if (row) {
                const statusCell = row.querySelector(".status");
                statusCell.textContent = "Approved";
                statusCell.className = "status px-3 py-1 rounded-full text-xs font-semibold bg-green-500/20 text-green-400";
                row.querySelectorAll(".approve-btn,.reject-btn").forEach(b => b.remove());
            }
            alert("✅ Sale approved and inventory updated.");
        } else {
            alert("⚠️ " + data.error);
        }
    });
}

function handleAction(saleID, action) {
    if (action === 'delete' && !confirm(`Are you sure you want to delete Sale #${saleID}?`)) return;
    if (action === 'reject' && !confirm(`Reject Sale #${saleID}?`)) return;

    fetch("sales-approval.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: `ajax_action=${action}&saleID=${saleID}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById(`row-${saleID}`);
            if (data.deleted) {
                if (row) row.remove();
                alert(`🗑 Sale #${saleID} deleted.`);
            } else {
                const statusCell = row.querySelector(".status");
                statusCell.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
                const classMap = {
                    approved: "bg-green-500/20 text-green-400",
                    rejected: "bg-red-500/20 text-red-400",
                    pending: "bg-yellow-500/20 text-yellow-400"
                };
                statusCell.className = `status px-3 py-1 rounded-full text-xs font-semibold ${classMap[data.status] || 'bg-gray-500/20 text-gray-300'}`;
                row.querySelectorAll(".approve-btn,.reject-btn").forEach(b => b.remove());
                alert(`Sale #${saleID} ${data.status}.`);
            }
        } else {
            alert("⚠️ " + (data.error || "Something went wrong."));
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
});
</script>
</body>
</html>
