<?php
session_start();
require 'db.php';

// ensure logged-in sales (or just logged-in)
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
// fetch only requests by this user
$stmt = $conn->prepare("SELECT * FROM product_add_requests WHERE requestedBy = ? ORDER BY id DESC");
$stmt->bind_param("s", $username);
$stmt->execute();
$res = $stmt->get_result();
$requests = [];
while ($row = $res->fetch_assoc()) {
    $requests[] = $row;
}
$stmt->close();

// Calculate stats
$totalRequests = count($requests);
$pendingCount = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));
$approvedCount = count(array_filter($requests, fn($r) => $r['status'] === 'approved'));
$rejectedCount = count(array_filter($requests, fn($r) => $r['status'] === 'rejected'));

$lastActivity = date('M d, Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales • Add Requests</title>
    <script src="https://cdn.tailwindcss.com" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        :root { font-family: 'Inter', sans-serif; }
        body { font-family: 'Inter', sans-serif; }
        .app-bg { background-color: #121212; }
        .sidebar-bg { background-color: #0d0d0d; }
        .modal-backdrop { background-color: rgba(0, 0, 0, 0.75); }
    </style>
</head>
<body class="app-bg text-gray-100 min-h-screen">
<div class="flex">
    <?php include('sales-sidebar.php'); ?>

    <main class="flex-1 md:ml-64 p-6 md:p-10 space-y-10">
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center border-b border-gray-800 pb-6">
            <div>
                <p class="text-sm uppercase tracking-[0.35em] text-gray-500">Product Requests</p>
                <h1 class="text-4xl font-light tracking-tight mt-2 flex items-center space-x-3">
                    <span class="font-bold text-red-500">My Add Requests</span>
                    <i data-lucide="file-plus" class="w-8 h-8 text-red-500"></i>
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
                    <span class="text-xs uppercase tracking-wider text-gray-400">Total Requests</span>
                    <i data-lucide="file-text" class="text-blue-400"></i>
                </div>
                <div class="mt-4">
                    <p class="text-4xl font-bold text-white"><?= $totalRequests; ?></p>
                    <p class="text-sm text-gray-500 mt-2">all time</p>
                </div>
            </div>

            <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl transition duration-300 hover:ring-2 hover:ring-yellow-500/50">
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wider text-gray-400">Pending</span>
                    <i data-lucide="clock" class="text-yellow-400"></i>
                </div>
                <div class="mt-4">
                    <p class="text-4xl font-bold text-white"><?= $pendingCount; ?></p>
                    <p class="text-sm text-gray-500 mt-2">awaiting review</p>
                </div>
            </div>

            <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl transition duration-300 hover:ring-2 hover:ring-green-500/50">
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wider text-gray-400">Approved</span>
                    <i data-lucide="check-circle" class="text-green-400"></i>
                </div>
                <div class="mt-4">
                    <p class="text-4xl font-bold text-white"><?= $approvedCount; ?></p>
                    <p class="text-sm text-gray-500 mt-2">successful</p>
                </div>
            </div>

            <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl transition duration-300 hover:ring-2 hover:ring-red-500/50">
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wider text-gray-400">Rejected</span>
                    <i data-lucide="x-circle" class="text-red-400"></i>
                </div>
                <div class="mt-4">
                    <p class="text-4xl font-bold text-white"><?= $rejectedCount; ?></p>
                    <p class="text-sm text-gray-500 mt-2">declined</p>
                </div>
            </div>
        </section>

        <section class="bg-gray-900 rounded-2xl p-8 shadow-2xl">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-semibold flex items-center space-x-2">
                    <i data-lucide="list" class="text-red-500 w-7 h-7"></i>
                    <span>Request History</span>
                </h2>
            </div>

            <?php if (!empty($requests)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-800">
                        <thead class="bg-gray-800/60">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Product Name</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Price</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Date Requested</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-gray-900 divide-y divide-gray-800">
                            <?php foreach ($requests as $r): ?>
                                <tr class="hover:bg-gray-800/60 transition">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">#<?= $r['id']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white"><?= htmlspecialchars($r['productName']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400"><?= htmlspecialchars($r['category'] ?: 'N/A'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">₱<?= number_format(floatval($r['price']), 2); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?= intval($r['stockQuantity']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php
                                            $statusColors = [
                                                'pending' => 'bg-yellow-500/20 text-yellow-400',
                                                'approved' => 'bg-green-500/20 text-green-400',
                                                'rejected' => 'bg-red-500/20 text-red-400'
                                            ];
                                            $status = strtolower($r['status']);
                                            $badgeClass = $statusColors[$status] ?? 'bg-gray-500/20 text-gray-300';
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $badgeClass; ?>">
                                            <?= ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400"><?= htmlspecialchars($r['dateRequested']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                        <button onclick="openView(<?= $r['id']; ?>)" class="text-blue-400 hover:text-blue-300 transition duration-150 flex items-center space-x-1 mx-auto">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                            <span>View</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <i data-lucide="inbox" class="w-16 h-16 text-gray-700 mx-auto mb-4"></i>
                    <p class="text-gray-500">No add requests found. Create a product request to get started.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<!-- View Modal -->
<div id="viewModal" class="hidden fixed inset-0 z-50 overflow-y-auto modal-backdrop flex items-center justify-center" onclick="if(event.target === this) closeView()">
    <div class="bg-gray-900 rounded-2xl p-8 shadow-2xl max-w-2xl w-full mx-4 border border-gray-800">
        <div class="flex justify-between items-center border-b border-gray-800 pb-4 mb-6">
            <h3 class="text-2xl font-semibold text-white flex items-center space-x-2">
                <i data-lucide="file-text" class="w-6 h-6 text-red-500"></i>
                <span>Request Details</span>
            </h3>
            <button onclick="closeView()" class="text-gray-400 hover:text-red-500 transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div id="viewBody" class="text-gray-300"></div>
        <div class="mt-6 flex justify-end">
            <button onclick="closeView()" class="px-6 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg transition duration-200">
                Close
            </button>
        </div>
    </div>
</div>

<script>
const requests = {};
<?php
$res2 = $conn->prepare("SELECT * FROM product_add_requests WHERE requestedBy = ? ORDER BY id DESC");
$res2->bind_param("s", $username);
$res2->execute();
$rres = $res2->get_result();
$all = [];
while($row = $rres->fetch_assoc()) $all[] = $row;
echo "const _reqs = " . json_encode($all, JSON_HEX_APOS|JSON_HEX_QUOT) . ";\n";
$res2->close();
?>
_reqs.forEach(r=> requests[r.id] = r);

function openView(id){
    const r = requests[id];
    if (!r) return;
    const body = document.getElementById('viewBody');
    let html = '<div class="space-y-4">';
    html += `<div class="grid grid-cols-2 gap-4"><div class="text-sm text-gray-400">Product Name</div><div class="text-white font-medium">${escapeHtml(r.productName)}</div></div>`;
    html += `<div class="grid grid-cols-2 gap-4"><div class="text-sm text-gray-400">Category</div><div class="text-white">${escapeHtml(r.category || 'N/A')}</div></div>`;
    html += `<div class="grid grid-cols-2 gap-4"><div class="text-sm text-gray-400">Price</div><div class="text-white">₱${parseFloat(r.price).toFixed(2)}</div></div>`;
    html += `<div class="grid grid-cols-2 gap-4"><div class="text-sm text-gray-400">Stock Quantity</div><div class="text-white">${r.stockQuantity}</div></div>`;
    html += `<div class="grid grid-cols-2 gap-4"><div class="text-sm text-gray-400">Supplier ID</div><div class="text-white">${r.supplierID || 'N/A'}</div></div>`;
    html += `<div class="grid grid-cols-2 gap-4"><div class="text-sm text-gray-400">Status</div><div class="text-white">`;
    const statusColors = {
        'pending': 'bg-yellow-500/20 text-yellow-400',
        'approved': 'bg-green-500/20 text-green-400',
        'rejected': 'bg-red-500/20 text-red-400'
    };
    const status = r.status.toLowerCase();
    const badgeClass = statusColors[status] || 'bg-gray-500/20 text-gray-300';
    html += `<span class="px-3 py-1 rounded-full text-xs font-semibold ${badgeClass}">${escapeHtml(r.status)}</span></div></div>`;
    html += `<div class="grid grid-cols-2 gap-4"><div class="text-sm text-gray-400">Date Requested</div><div class="text-white">${escapeHtml(r.dateRequested)}</div></div>`;
    if (r.imagePath) {
        html += `<div class="grid grid-cols-2 gap-4"><div class="text-sm text-gray-400">Image</div><div class="text-white"><img src="${escapeHtml(r.imagePath)}" alt="Product Image" class="max-w-xs max-h-48 object-contain rounded-lg border border-gray-700 p-2" /></div></div>`;
    }
    html += '</div>';
    body.innerHTML = html;
    document.getElementById('viewModal').classList.remove('hidden');
    lucide.createIcons();
}

function closeView(){ 
    document.getElementById('viewModal').classList.add('hidden');
}

function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, function(m) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
    });
}

window.addEventListener('click', function(e){
    const modal = document.getElementById('viewModal');
    if (e.target === modal) closeView();
});

// Initialize Lucide icons
document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
});
</script>
</body>
</html>
