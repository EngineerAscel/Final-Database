<?php
session_start();
require 'db.php';

// ✅ Protect page
if (!isset($_SESSION['username']) || $_SESSION['role'] !== "Admin") {
    header("Location: login.php");
    exit();
}
// ---------- Handle Delete ----------
if (isset($_GET['delete'])) {
    $deleteID = intval($_GET['delete']);

    if ($deleteID > 0) {
        $stmt = $conn->prepare("DELETE FROM product_update_requests WHERE id=?");
        $stmt->bind_param("i", $deleteID);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: admin-update-requests.php");
    exit();
}
// ---------- Handle Approve / Reject ----------
if (isset($_GET['id'], $_GET['action'])) {
    $requestID = intval($_GET['id']);
    $action = $_GET['action'];

    if ($requestID && in_array($action, ['approve', 'reject'])) {
        // Fetch the request
        $stmt = $conn->prepare("SELECT * FROM product_update_requests WHERE id=? AND status='Pending'");
        $stmt->bind_param("i", $requestID);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($request) {
            if ($action === 'approve') {
                $field = $request['fieldName'];
                $newValue = $request['newValue'];
                $productID = $request['productID'];

                $allowedFields = [
                    'productName' => 's',
                    'category' => 's',
                    'price' => 'd',
                    'stockQuantity' => 'i',
                    'supplierID' => 'i',
                    'productsImg' => 's'
                ];

                if (isset($allowedFields[$field])) {
                    $type = $allowedFields[$field];
                    $bindType = $type . 'i';

                    $stmt2 = $conn->prepare("UPDATE products SET $field=? WHERE productID=?");
                    if ($type === 'd') {
                        $newValue = (float)$newValue;
                        $stmt2->bind_param($bindType, $newValue, $productID);
                    } elseif ($type === 'i') {
                        $newValue = (int)$newValue;
                        $stmt2->bind_param($bindType, $newValue, $productID);
                    } else {
                        $stmt2->bind_param($bindType, $newValue, $productID);
                    }
                    $stmt2->execute();
                    $stmt2->close();
                }

                // Mark request as approved
                $stmt3 = $conn->prepare("UPDATE product_update_requests SET status='Approved', adminID=? WHERE id=?");
                $stmt3->bind_param("ii", $_SESSION['user_id'], $requestID);
                $stmt3->execute();
                $stmt3->close();

            } elseif ($action === 'reject') {
                $stmt3 = $conn->prepare("UPDATE product_update_requests SET status='Rejected', adminID=? WHERE id=?");
                $stmt3->bind_param("ii", $_SESSION['user_id'], $requestID);
                $stmt3->execute();
                $stmt3->close();
            }
        }

        // Redirect back to main page
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit();
    }
}

$sql = "SELECT ur.id, ur.productID, ur.salesID, ur.fieldName, ur.oldValue, ur.newValue, 
               ur.status, ur.created_at, p.productName
        FROM product_update_requests ur
        JOIN products p ON ur.productID = p.productID
        ORDER BY ur.created_at DESC";
$requestsResult = $conn->query($sql);
$requests = [];
if ($requestsResult) {
    while ($row = $requestsResult->fetch_assoc()) {
        $requests[] = $row;
    }
}
$totalRequests = count($requests);
$pendingCountQuery = $conn->query("SELECT COUNT(*) AS total FROM product_update_requests WHERE LOWER(status) = 'pending'");
$pendingRequests = $pendingCountQuery ? (int)$pendingCountQuery->fetch_assoc()['total'] : 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Update Requests</title>
    <link rel="stylesheet" href="css/request.css">
    <script src="https://cdn.tailwindcss.com" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');

        :root { font-family: 'Inter', sans-serif; }
        .app-bg { background-color: #121212; }
        .sidebar-bg { background-color: #0d0d0d; }
        [data-lucide] { width: 1.5rem; height: 1.5rem; }
        .request-table tbody tr { background-color: #1f2937; border-bottom: 1px solid #374151; transition: background-color 0.2s; }
        .request-table tbody tr:hover { background-color: #2c3e50; }
        .status-pill { display:inline-flex; align-items:center; padding:0.25rem 0.75rem; border-radius:999px; font-size:0.75rem; font-weight:600; }
        .status-pending { background:rgba(250,204,21,0.15); color:#facc15; border:1px solid rgba(250,204,21,0.4); }
        .status-approved { background:rgba(34,197,94,0.15); color:#34d399; border:1px solid rgba(34,197,94,0.4); }
        .status-rejected { background:rgba(239,68,68,0.15); color:#f87171; border:1px solid rgba(239,68,68,0.4); }
    </style>
</head>
<body class="app-bg text-gray-100 min-h-screen">
<div class="flex w-full min-h-screen">
    <?php include 'admin-sidebar.php'; ?>
    <main class="flex-1 md:ml-64 p-6 md:p-10 w-full space-y-8">
<div class="space-y-8">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-gray-800 pb-4">
        <div class="flex items-center space-x-3">
            <i data-lucide="inbox" class="text-red-500 w-10 h-10"></i>
            <div>
                <p class="text-sm uppercase tracking-[0.3em] text-gray-500">Quality Control</p>
                <h1 class="text-4xl font-bold tracking-tight text-white">Product Update Requests</h1>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <div class="bg-gray-900 px-4 py-2 rounded-xl border border-gray-800 text-sm text-gray-400">
                Total Requests <span class="text-white font-semibold ml-2"><?= $totalRequests ?></span>
            </div>
            <div class="bg-yellow-900/20 px-4 py-2 rounded-xl border border-yellow-600/40 text-sm text-yellow-300">
                Pending <span class="text-white font-semibold ml-2"><?= $pendingRequests ?></span>
            </div>
        </div>
    </header>

    <section class="bg-gray-900 p-6 rounded-2xl shadow-2xl overflow-x-auto">
        <div class="rounded-xl border border-gray-800">
            <table class="min-w-full divide-y divide-gray-800 request-table">
                <thead class="bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Product</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Field</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Old Value</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">New Value</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Date Requested</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-200">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    <?php if (!empty($requests)): ?>
                        <?php foreach ($requests as $row): ?>
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-300"><?= $row['id'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?= htmlspecialchars($row['productName']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400"><?= htmlspecialchars($row['fieldName']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-300"><?= htmlspecialchars($row['oldValue']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-300"><?= htmlspecialchars($row['newValue']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php
                                        $statusClass = 'status-pending';
                                        if (strtolower($row['status']) === 'approved') {
                                            $statusClass = 'status-approved';
                                        } elseif (strtolower($row['status']) === 'rejected') {
                                            $statusClass = 'status-rejected';
                                        }
                                    ?>
                                    <span class="status-pill <?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $row['created_at'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-2">
                                    <a href="?view=<?= $row['id'] ?>" class="text-blue-400 hover:text-blue-500 transition duration-150">View</a>
                                

                                
                                    <!-- DELETE BUTTON -->
                                    <a href="javascript:void(0)" 
                                    onclick="confirmDelete(<?= $row['id'] ?>)" 
                                    class="text-red-400 hover:text-red-500 transition duration-150">Delete</a>

                                    <?php if(strtolower($row['status']) === 'pending'): ?>
                                        <a href="?id=<?= $row['id'] ?>&action=approve" class="text-green-400 hover:text-green-500 transition duration-150">Approve</a>
                                        <a href="?id=<?= $row['id'] ?>&action=reject" class="text-red-400 hover:text-red-500 transition duration-150">Reject</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-gray-400">No update requests found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php
// Single request view
if (isset($_GET['view'])):
    $viewID = intval($_GET['view']);
    $stmt = $conn->prepare("SELECT ur.*, p.productName AS originalProductName, p.productsImg AS originalProductsImg 
                            FROM product_update_requests ur
                            JOIN products p ON ur.productID = p.productID
                            WHERE ur.id=?");
    $stmt->bind_param("i", $viewID);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($request):
?>
    <section class="bg-gray-900 p-6 rounded-2xl shadow-2xl space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm uppercase tracking-widest text-gray-500">Request Detail</p>
                <h2 class="text-2xl font-semibold text-white">View Update Request</h2>
                <p class="text-sm text-gray-400 mt-1">Product: <?= htmlspecialchars($request['originalProductName']) ?> (ID: <?= $request['productID'] ?>)</p>
            </div>
            <span class="status-pill <?= strtolower($request['status']) === 'pending' ? 'status-pending' : (strtolower($request['status']) === 'approved' ? 'status-approved' : 'status-rejected') ?>">
                <?= htmlspecialchars($request['status']) ?>
            </span>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div class="bg-gray-800/40 rounded-xl p-4 border border-gray-800">
                <p class="text-sm text-gray-400 uppercase tracking-widest mb-1">Requested By</p>
                <p class="text-white text-lg font-semibold">Sales ID: <?= $request['salesID'] ?></p>
                <p class="text-sm text-gray-500">Date: <?= $request['created_at'] ?></p>
            </div>
            <div class="bg-gray-800/40 rounded-xl p-4 border border-gray-800">
                <p class="text-sm text-gray-400 uppercase tracking-widest mb-1">Field</p>
                <p class="text-white text-lg font-semibold"><?= htmlspecialchars($request['fieldName']) ?></p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-800">
                <thead class="bg-gray-800">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Original Value</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Requested Value</th>
                    </tr>
                </thead>
                <tbody class="bg-gray-900 divide-y divide-gray-800 text-gray-300">
                    <tr>
                        <td class="px-6 py-4 text-sm">
                            <?php if ($request['fieldName'] === 'productsImg'): ?>
                                <img src="<?= $request['oldValue'] ?>" width="140">
                            <?php else: ?>
                                <?= htmlspecialchars($request['oldValue']) ?>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <?php if ($request['fieldName'] === 'productsImg'): ?>
                                <img src="<?= $request['newValue'] ?>" width="140">
                            <?php else: ?>
                                <?= htmlspecialchars($request['newValue']) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php if($request['status'] === 'pending'): ?>
            <div class="flex gap-3">
                <a href="?id=<?= $request['id'] ?>&action=approve" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-xl transition">Approve</a>
                <a href="?id=<?= $request['id'] ?>&action=reject" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl transition">Reject</a>
            </div>
        <?php endif; ?>

        <p><a href="admin-update-requests.php" class="text-blue-400 hover:text-blue-500 transition">⬅ Back to all requests</a></p>
    </section>
 
<?php
    endif;
endif;
?>
</div>
    </main>
</div>
<script>
    lucide.createIcons();
</script>
   <script>
        function confirmDelete(id) {
            if (!confirm("Are you sure you want to delete this request?")) return;
            window.location.href = "?delete=" + id;
        }
        </script>
</body>
</html>
