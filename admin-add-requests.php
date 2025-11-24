<?php
session_start();
require 'db.php'; // expects $conn (mysqli)

// ensure admin
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$flash = '';

// Approve / Reject handlers (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['req_id'])) {
        $req_id = intval($_POST['req_id']);
        $action = $_POST['action']; // 'approve' or 'reject'

        // fetch request
        $stmt = $conn->prepare("SELECT * FROM product_add_requests WHERE id = ?");
        $stmt->bind_param("i", $req_id);
        $stmt->execute();
        $req = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($action === 'delete') {
        $del = $conn->prepare("DELETE FROM product_add_requests WHERE id = ?");
        $del->bind_param("i", $req_id);
        if ($del->execute()) {
            $flash = "Request #{$req_id} has been deleted.";
        } else {
            $flash = "Failed to delete request #{$req_id}.";
        }
        $del->close();
        }   

        if (!$req) {
            $flash = "Request not found.";
        } else {
            if ($action === 'approve') {
                // Insert to products
                $pname = $req['productName'];
                $pcat = $req['category'];
                $pprice = floatval($req['price']);
                $pqty = intval($req['stockQuantity']);
                $psup = intval($req['supplierID']);
                $imgPath = $req['imagePath']; // may be null

                // If an image exists in add_requests folder, move it to uploads/
                if (!empty($imgPath) && file_exists($imgPath)) {
                    $uploadsDir = "uploads/";
                    if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);
                    $base = basename($imgPath);
                    $newPath = $uploadsDir . $base;
                    // If destination already exists, add timestamp
                    if (file_exists($newPath)) $newPath = $uploadsDir . time() . '_' . $base;
                    if (@rename($imgPath, $newPath)) {
                        $imgPathToSave = $newPath;
                    } else {
                        // fallback: keep original path if move fails
                        $imgPathToSave = $imgPath;
                    }
                } else {
                    $imgPathToSave = "";
                }

                // insert into products
                $insert = $conn->prepare("INSERT INTO products (productsImg, productName, category, price, stockQuantity,   
                supplierID, dateAdded) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $insert->bind_param("sssdis", $imgPathToSave, $pname, $pcat, $pprice, $pqty, $psup);
                if ($insert->execute()) {
                    // mark request approved
                    $upd = $conn->prepare("UPDATE product_add_requests SET status = 'approved' WHERE id = ?");
                    $upd->bind_param("i", $req_id);
                    $upd->execute();
                    $upd->close();

                    $flash = "Request #{$req_id} approved and product added.";
                } else {
                    $flash = "Failed to insert product: " . $conn->error;
                }
                $insert->close();
            } elseif ($action === 'reject') {
                $upd = $conn->prepare("UPDATE product_add_requests SET status = 'rejected' WHERE id = ?");
                $upd->bind_param("i", $req_id);
                if ($upd->execute()) {
                    $flash = "Request #{$req_id} rejected.";
                } else {
                    $flash = "Failed to update request status.";
                }
                $upd->close();
            }
        }
    }
}

// Fetch all add requests (most recent first)
$res = $conn->query("SELECT * FROM product_add_requests ORDER BY id DESC");
$requests = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $requests[] = $row;
    }
}
$totalRequests = count($requests);
$pendingRes = $conn->query("SELECT COUNT(*) AS total FROM product_add_requests WHERE status = 'pending'");
$pendingCount = $pendingRes ? (int)$pendingRes->fetch_assoc()['total'] : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Add Requests</title>
    <link rel="stylesheet" href="css/products.css">
    <script src="https://cdn.tailwindcss.com" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
    :root { font-family: 'Inter', sans-serif; }
    .req-table tbody tr { background-color: #1f2937; border-bottom: 1px solid #374151; transition: background-color 0.2s; }
    .req-table tbody tr:hover { background-color: #2c3e50; }
    .badge { padding:4px 10px; border-radius:999px; font-weight:600; font-size:12px; display:inline-flex; align-items:center; gap:4px; letter-spacing:0.05em; }
    .badge.pending { background:rgba(250,204,21,0.15); color:#facc15; border:1px solid rgba(250,204,21,0.4); }
    .badge.approved { background:rgba(34,197,94,0.15); color:#34d399; border:1px solid rgba(34,197,94,0.4); }
    .badge.rejected { background:rgba(239,68,68,0.15); color:#f87171; border:1px solid rgba(239,68,68,0.4); }
    .btn { padding:6px 12px; border-radius:999px; cursor:pointer; border:none; font-weight:600; letter-spacing:0.05em; }
        .btn-view { background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.4); }
        .btn-approve { background:rgba(34,197,94,0.15); color:#34d399; border:1px solid rgba(34,197,94,0.4); }
        .btn-reject { background:rgba(239,68,68,0.15); color:#f87171; border:1px solid rgba(239,68,68,0.4); }
        .modal-backdrop { background-color: rgba(0, 0, 0, 0.75); }
    </style>
</head>
<body class="app-bg text-gray-100 min-h-screen">
<div class="flex w-full min-h-screen">
<?php include 'admin-sidebar.php'; ?>
<main class="flex-1 md:ml-64 p-6 md:p-10 w-full space-y-8">

<div class="space-y-8">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-gray-800 pb-4">
        <div class="flex items-center space-x-3">
            <i data-lucide="clipboard-list" class="text-red-500 w-10 h-10"></i>
            <div>
                <p class="text-sm uppercase tracking-[0.3em] text-gray-500">Catalog Expansion</p>
                <h1 class="text-4xl font-bold tracking-tight text-white">Add Product Requests</h1>
            </div>
        </div>
        <div class="flex gap-3">
            <div class="bg-gray-900 px-4 py-2 rounded-xl border border-gray-800 text-sm text-gray-400">
                Total Requests <span class="text-white font-semibold ml-2"><?= $totalRequests ?></span>
            </div>
            <div class="bg-yellow-900/20 px-4 py-2 rounded-xl border border-yellow-600/40 text-sm text-yellow-200">
                Pending <span class="text-white font-semibold ml-2"><?= $pendingCount ?></span>
            </div>
        </div>
    </header>

    <?php if (!empty($flash)): ?>
        <div class="bg-amber-900/30 border border-amber-500/50 text-amber-100 px-4 py-3 rounded-xl">
            <?= htmlspecialchars($flash) ?>
        </div>
    <?php endif; ?>

    <section class="bg-gray-900 p-6 rounded-2xl shadow-2xl overflow-x-auto">
         <header class="flex items-center justify-between mb-4 border-b border-gray-800 pb-2">
        <h2 class="text-xl font-semibold text-white">Sales Requests to Add Products Manually</h2>
    </header>
        <div class="rounded-xl border border-gray-800">
            <table class="min-w-full divide-y divide-gray-800 req-table">
                <thead class="bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Product</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Details</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Date Requested</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-200">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800 text-gray-300">
                <?php if (!empty($requests)): ?>
                    <?php foreach ($requests as $r):
                        $newValParts = [];
                        $newValParts[] = "Category: " . ($r['category'] === "" ? "(empty)" : $r['category']);
                        $newValParts[] = "Price: ₱" . number_format((float)$r['price'], 2);
                        $newValParts[] = "Stock: " . (int)$r['stockQuantity'];
                        $newValParts[] = "Supplier ID: " . ($r['supplierID'] === null ? '(none)' : $r['supplierID']);
                        $newVal = implode(" • ", $newValParts);
                        $status = strtolower($r['status']);
                        $badgeClass = $status === 'pending' ? 'badge pending' : ($status === 'approved' ? 'badge approved' : 'badge rejected');
                    ?>
                        <tr>
                            <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold text-gray-300"><?= $r['id'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-100"><?= htmlspecialchars($r['productName']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-400"><?= htmlspecialchars($newVal) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm"><span class="<?= $badgeClass ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($r['dateRequested']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-2">
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-2">
                    
                        <!-- View button -->
                        <button class="btn btn-view" onclick="openView(<?= $r['id'] ?>)">View</button>

                            <!-- Delete button -->
                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete request #<?= $r['id'] ?>?');">
                                <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-reject">Delete</button>
                            </form>

                         
                                <?php if ($status === 'pending'): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-approve">Approve</button>
                                    </form>

                                    <form method="POST" class="inline">
                                        <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-reject">Reject</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-400">No add requests found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<!-- View Modal -->
<div id="viewModal" class="hidden fixed inset-0 z-50 overflow-y-auto modal-backdrop flex items-center justify-center" onclick="if(event.target === this) closeView()">
    <div class="bg-gray-900 rounded-2xl p-8 shadow-2xl max-w-3xl w-full mx-4 border border-gray-800">
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
<section class="bg-gray-900 p-6 rounded-2xl shadow-2xl overflow-x-auto">
    <header class="flex items-center justify-between mb-4 border-b border-gray-800 pb-2">
        <h2 class="text-xl font-semibold text-white">Sales Requests to Add Products from Supplier</h2>
    </header>
    <div class="rounded-xl border border-gray-800 p-4">
        <?php
        // Fetch only pending requests
        $salesRequests = $conn->query("SELECT * FROM product_add_requests WHERE status='pending' ORDER BY id DESC");
        if ($salesRequests && $salesRequests->num_rows > 0):
        ?>
            <ul class="space-y-3">
                <?php while($sr = $salesRequests->fetch_assoc()): ?>
                    <li class="flex justify-between items-center bg-gray-800 rounded-xl p-3 border border-gray-700 hover:bg-gray-700 transition-colors">
                        <div>
                            <p class="text-white font-medium"><?= htmlspecialchars($sr['productName']) ?></p>
                            <p class="text-gray-400 text-sm">
                                Category: <?= htmlspecialchars($sr['category'] ?: 'N/A') ?> • 
                                Supplier ID: <?= htmlspecialchars($sr['supplierID'] ?: 'N/A') ?> • 
                                Price: ₱<?= number_format((float)$sr['price'], 2) ?>
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <!-- Approve / Assign Product button -->
                            <form method="POST">
                                <input type="hidden" name="req_id" value="<?= $sr['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-approve text-sm px-4 py-2">Add Product</button>
                            </form>
                            <!-- Reject button -->
                            <form method="POST">
                                <input type="hidden" name="req_id" value="<?= $sr['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-reject text-sm px-4 py-2">Reject</button>
                            </form>
                        </div>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p class="text-gray-400 text-center py-6">No pending sales requests at the moment.</p>
        <?php endif; ?>
    </div>
</section>
<script>
// fetch details via AJAX-like (we'll embed data into JS map to avoid extra requests)
const requests = {};
<?php
// create a JS object with request details
$res2 = $conn->query("SELECT * FROM product_add_requests ORDER BY id DESC");
$all = [];
while($row = $res2->fetch_assoc()) {
    $all[] = $row;
}
echo "const _reqs = " . json_encode($all, JSON_HEX_APOS|JSON_HEX_QUOT) . ";\n";
?>
_reqs.forEach(r=> requests[r.id] = r);

function openView(id){
    const r = requests[id];
    if (!r) return;
    const body = document.getElementById('viewBody');
    let html = '<div class="space-y-4">';
    
    // Product details in grid layout
    html += `<div class="grid grid-cols-2 gap-4"><div class="text-sm text-gray-400">Product</div><div class="text-white font-medium">${escapeHtml(r.productName)}</div></div>`;
    html += `<div class="grid grid-cols-2 gap-4"><div class="text-sm text-gray-400">Category</div><div class="text-white">${escapeHtml(r.category || 'N/A')}</div></div>`;
    html += `<div class="grid grid-cols-2 gap-4"><div class="text-sm text-gray-400">Price</div><div class="text-white">₱${parseFloat(r.price).toFixed(2)}</div></div>`;
    html += `<div class="grid grid-cols-2 gap-4"><div class="text-sm text-gray-400">Stock</div><div class="text-white">${r.stockQuantity}</div></div>`;
    html += `<div class="grid grid-cols-2 gap-4"><div class="text-sm text-gray-400">Supplier ID</div><div class="text-white">${r.supplierID || 'N/A'}</div></div>`;
    html += `<div class="grid grid-cols-2 gap-4"><div class="text-sm text-gray-400">Requested By</div><div class="text-white">${escapeHtml(r.requestedBy)}</div></div>`;
    
    // Status with badge
    html += `<div class="grid grid-cols-2 gap-4"><div class="text-sm text-gray-400">Status</div><div class="text-white">`;
    const statusColors = {
        'pending': 'bg-yellow-500/20 text-yellow-400',
        'approved': 'bg-green-500/20 text-green-400',
        'rejected': 'bg-red-500/20 text-red-400'
    };
    const status = (r.status || '').toLowerCase();
    const badgeClass = statusColors[status] || 'bg-gray-500/20 text-gray-300';
    html += `<span class="px-3 py-1 rounded-full text-xs font-semibold ${badgeClass}">${escapeHtml(r.status)}</span></div></div>`;
    
    // Date Requested - fix typo from dateRequeste to dateRequested
    const dateRequested = r.dateRequested || r.dateRequeste || 'N/A';
    html += `<div class="grid grid-cols-2 gap-4"><div class="text-sm text-gray-400">Date Requested</div><div class="text-white">${escapeHtml(dateRequested)}</div></div>`;
    
    // Image with modern styling
    if (r.imagePath && r.imagePath.trim() !== '') {
        let imgSrc = r.imagePath.trim();
        // Ensure path starts correctly - if it's already "uploads/", keep it, otherwise ensure it's correct
        if (!imgSrc.startsWith('http') && !imgSrc.startsWith('/') && !imgSrc.startsWith('uploads/')) {
            imgSrc = 'uploads/' + imgSrc;
        }
        
        html += `<div class="grid grid-cols-2 gap-4 items-start"><div class="text-sm text-gray-400">Image</div>`;
        html += `<div class="flex flex-col items-start space-y-2 w-full">`;
        html += `<div class="w-full bg-gray-800/50 rounded-lg border-2 border-gray-700 p-4 flex items-center justify-center" style="min-height: 200px;">`;
        html += `<img src="${escapeHtml(imgSrc)}" alt="Product Image" `;
        html += `class="max-w-full max-h-96 w-auto h-auto object-contain rounded-lg shadow-lg" `;
        html += `style="display: block; max-width: 100%; height: auto;" `;
        html += `onload="this.parentElement.style.minHeight='auto'; this.style.display='block';" `;
        html += `onerror="console.error('Image failed to load:', '${escapeHtml(imgSrc)}'); this.style.display='none'; const errDiv = this.nextElementSibling; if(errDiv) { errDiv.style.display='block'; lucide.createIcons(); }" />`;
        html += `<div style="display:none; text-align:center; color: #9ca3af; width: 100%; padding: 1rem;" class="text-sm">`;
        html += `<i data-lucide="image-off" class="w-12 h-12 mx-auto mb-2 text-gray-600"></i>`;
        html += `<div class="mt-2">Image not found</div>`;
        html += `<div class="text-xs text-gray-500 mt-1 break-all">Path: ${escapeHtml(imgSrc)}</div>`;
        html += `</div>`;
        html += `</div>`;
        html += `<a href="${escapeHtml(imgSrc)}" target="_blank" class="text-xs text-blue-400 hover:text-blue-300 flex items-center space-x-1 mt-2 transition-colors">`;
        html += `<i data-lucide="external-link" class="w-3 h-3"></i>`;
        html += `<span>Open in new tab</span></a>`;
        html += `</div></div>`;
    } else {
        html += `<div class="grid grid-cols-2 gap-4"><div class="text-sm text-gray-400">Image</div><div class="text-gray-500 italic">No image provided</div></div>`;
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

// close modal when clicking outside
window.addEventListener('click', function(e){
    const modal = document.getElementById('viewModal');
    if (e.target === modal) modal.style.display = 'none';
});

lucide.createIcons();
</script>
</main>
</div>
</body>
</html>
