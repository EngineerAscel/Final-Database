
<?php
session_start();

// ✅ Protect page
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

// --- PHP ACTIONS ---

// Note: Keeping Add, Update, Delete handlers for completeness, though Update/Delete are not used in the UI.



// ✅ Request NEW Item from Supplier (via the 'Request Item' button on the main table)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_item'])) {
    $supplierID    = intval($_POST['supplierID']);
    $productName   = $_POST['productName'];
    $category      = $_POST['category'];
    $price         = floatval($_POST['price']);
    $stockQuantity = intval($_POST['stockQuantity']);
    $requestedBy   = $_SESSION['userID'] ?? 0;
    $status        = 'Pending';

    $imagePath = NULL;
    if (!empty($_FILES['imagePath']['name'])) {
        $ext = pathinfo($_FILES['imagePath']['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $ext;
        $targetDir = 'uploads/';
        if (!file_exists($targetDir)) mkdir($targetDir, 0755, true);
        move_uploaded_file($_FILES['imagePath']['tmp_name'], $targetDir . $filename);
        $imagePath = $targetDir . $filename;
    }

    $stmt = $conn->prepare("INSERT INTO supplier_item_requests (supplierID, productName, category, price, stockQuantity, imagePath, requestedBy, status, dateRequested)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("issdisis", $supplierID, $productName, $category, $price, $stockQuantity, $imagePath, $requestedBy, $status);
    $stmt->execute();
    $stmt->close();

    header("Location: sales-supplier.php?request=success");
    exit();
}


// --- DATA FETCHING ---

// ✅ Fetch Suppliers
$result = $conn->query("SELECT * FROM supplier ORDER BY supplierID ASC");
$suppliers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

$totalSuppliers = count($suppliers);
$activeSuppliers = array_reduce($suppliers, fn($carry, $item) => $carry + ($item['status'] === 'Active' ? 1 : 0), 0);
$inactiveSuppliers = $totalSuppliers - $activeSuppliers;
$recentSuppliers = array_slice(array_reverse($suppliers), 0, 4);
$lastUpdated = date('M d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sales • Suppliers</title>
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
<?php include("sales-sidebar.php") ?>
<main class="flex-1 md:ml-64 p-6 md:p-10 space-y-10">

<header class="flex flex-col md:flex-row justify-between items-start md:items-center border-b border-gray-800 pb-6">
    <div>
        <p class="text-sm uppercase tracking-[0.35em] text-gray-500">Vendor Network</p>
        <h1 class="text-4xl font-light tracking-tight mt-2 flex items-center space-x-3">
            <span class="font-bold text-red-500">Supplier Management</span>
            <i data-lucide="factory" class="w-8 h-8 text-red-500"></i>
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
        <span>Total Suppliers</span>
        <i data-lucide="handshake" class="text-red-400"></i>
    </div>
    <p class="text-4xl font-extrabold text-white mt-4"><?= number_format($totalSuppliers); ?></p>
    <p class="text-sm text-gray-500 mt-2">All registered partners</p>
</div>

<div class="bg-gray-900 p-6 rounded-2xl shadow-2xl hover:ring-2 hover:ring-green-500/40 transition">
    <div class="flex items-center justify-between text-gray-400 text-xs uppercase tracking-widest">
        <span>Active</span>
        <i data-lucide="badge-check" class="text-green-400"></i>
    </div>
    <p class="text-4xl font-extrabold text-white mt-4"><?= number_format($activeSuppliers); ?></p>
    <p class="text-sm text-gray-500 mt-2">Supplying currently</p>
</div>

<div class="bg-gray-900 p-6 rounded-2xl shadow-2xl hover:ring-2 hover:ring-yellow-500/40 transition">
    <div class="flex items-center justify-between text-gray-400 text-xs uppercase tracking-widest">
        <span>Inactive</span>
        <i data-lucide="pause-circle" class="text-yellow-400"></i>
    </div>
    <p class="text-4xl font-extrabold text-white mt-4"><?= number_format($inactiveSuppliers); ?></p>
    <p class="text-sm text-gray-500 mt-2">Need follow-up</p>
</div>

<div class="bg-red-900/40 p-6 rounded-2xl shadow-2xl ring-4 ring-red-500/60 border-l-4 border-red-500 flex flex-col justify-between">
    <div class="flex items-center justify-between text-red-100 text-xs uppercase tracking-widest">
        
</section>

<section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
<div class="xl:col-span-2 bg-gray-900 rounded-2xl shadow-2xl overflow-hidden">
<div class="flex items-center justify-between border-b border-gray-800 px-6 py-4">
    <div>
        <h2 class="text-2xl font-semibold flex items-center space-x-2">
            <i data-lucide="notebook-text" class="text-red-500 w-6 h-6"></i>
            <span>Supplier Roster</span>
        </h2>
        <p class="text-sm text-gray-500">Manage contact channels and availability</p>
    </div>
</div>

<?php if (!empty($suppliers)): ?>
<div class="overflow-x-auto">
<table class="min-w-full divide-y divide-gray-800">
<thead class="bg-gray-800/60">
<tr>
<th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Supplier</th>
<th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Contact</th>
<th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Email</th>
<th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Status</th>
<th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-400">Actions</th>
</tr>
</thead>
<tbody class="bg-gray-900 divide-y divide-gray-800">
<?php foreach ($suppliers as $row): ?>
<tr class="hover:bg-gray-800/50 transition">
<td class="px-6 py-4">
    <p class="text-white font-semibold"><?= htmlspecialchars($row['supplierName']); ?></p>
    <p class="text-xs text-gray-500"><?= htmlspecialchars($row['address']); ?></p>
</td>
<td class="px-6 py-4">
    <p class="text-gray-200 font-medium"><?= htmlspecialchars($row['contactPerson']); ?></p>
    <p class="text-xs text-gray-500 flex items-center space-x-1">
        <i data-lucide="phone" class="w-3.5 h-3.5"></i>
        <span><?= htmlspecialchars($row['contactNumber']); ?></span>
    </p>
</td>
<td class="px-6 py-4 text-sm text-gray-300"><?= htmlspecialchars($row['email']); ?></td>
<td class="px-6 py-4">
<?php
$statusClass = $row['status'] === 'Active'
    ? 'bg-green-500/20 text-green-400'
    : 'bg-yellow-500/20 text-yellow-400';
?>
<span class="px-3 py-1 rounded-full text-xs font-semibold <?= $statusClass; ?>">
    <?= htmlspecialchars($row['status']); ?>
</span>
</td>
<td class="px-6 py-4 text-right space-x-2 flex items-center justify-end">
    <button class="inline-flex items-center space-x-1 text-blue-400 hover:text-blue-300 transition text-sm font-semibold"
        onclick="openSupplierItemsModal(<?= $row['supplierID']; ?>, '<?= htmlspecialchars($row['supplierName'], ENT_QUOTES); ?>')">
        <i data-lucide="eye" class="w-4 h-4"></i>
        <span>View Item</span>
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
<p class="text-gray-400 text-lg">No suppliers yet. Start by adding a new partner.</p>
</div>
<?php endif; ?>
</div>

<div class="bg-gray-900 rounded-2xl shadow-2xl p-6 space-y-4">
<h2 class="text-2xl font-semibold flex items-center space-x-2">
<i data-lucide="clock-8" class="text-yellow-400 w-6 h-6"></i>
<span>Recently Added</span>
</h2>
<div class="space-y-4 max-h-[420px] overflow-y-auto pr-2">
<?php if (!empty($recentSuppliers)): ?>
<?php foreach ($recentSuppliers as $supplier): ?>
<div class="bg-gray-950/40 border border-gray-800 rounded-xl p-4 flex items-center justify-between">
    <div>
        <p class="font-semibold text-white"><?= htmlspecialchars($supplier['supplierName']); ?></p>
        <p class="text-xs text-gray-500 flex items-center space-x-1">
            <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
            <span><?= htmlspecialchars($supplier['address']); ?></span>
        </p>
    </div>
    <span class="text-xs text-gray-400"><?= date('M d', strtotime($supplier['dateAdded'] ?? 'now')); ?></span>
</div>
<?php endforeach; ?>
<?php else: ?>
<p class="text-gray-500 text-sm">No recent supplier activity.</p>
<?php endif; ?>
</div>
</div>
</section>
</main>
</div>

<div id="addSupplierModal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-backdrop transition-opacity duration-300" onclick="if(event.target.id === 'addSupplierModal') toggleModal('addSupplierModal', false)">
    <div class="bg-gray-900 rounded-2xl p-8 w-full max-w-lg shadow-2xl scale-100 transform transition-all duration-300">
        <h3 class="text-2xl font-bold mb-6 text-white border-b border-gray-800 pb-3 flex items-center space-x-2">
            <i data-lucide="factory" class="w-6 h-6 text-red-500"></i>
            <span>Add New Supplier</span>
        </h3>
        <form action="sales-supplier.php" method="POST">
            <input type="hidden" name="add_supplier" value="1">
            <div class="grid grid-cols-1 gap-4">
                <label class="block">
                    <span class="text-gray-400">Supplier Name</span>
                    <input type="text" name="supplierName" required class="mt-1 block w-full bg-gray-800 border-gray-700 rounded-lg p-2.5 text-white focus:ring-red-500 focus:border-red-500">
                </label>
                <label class="block">
                    <span class="text-gray-400">Contact Person</span>
                    <input type="text" name="contactPerson" required class="mt-1 block w-full bg-gray-800 border-gray-700 rounded-lg p-2.5 text-white focus:ring-red-500 focus:border-red-500">
                </label>
                <label class="block">
                    <span class="text-gray-400">Contact Number</span>
                    <input type="text" name="contactNumber" required class="mt-1 block w-full bg-gray-800 border-gray-700 rounded-lg p-2.5 text-white focus:ring-red-500 focus:border-red-500">
                </label>
                <label class="block">
                    <span class="text-gray-400">Email</span>
                    <input type="email" name="email" required class="mt-1 block w-full bg-gray-800 border-gray-700 rounded-lg p-2.5 text-white focus:ring-red-500 focus:border-red-500">
                </label>
                <label class="block">
                    <span class="text-gray-400">Address</span>
                    <textarea name="address" rows="2" required class="mt-1 block w-full bg-gray-800 border-gray-700 rounded-lg p-2.5 text-white focus:ring-red-500 focus:border-red-500"></textarea>
                </label>
                <label class="block">
                    <span class="text-gray-400">Status</span>
                    <select name="status" required class="mt-1 block w-full bg-gray-800 border-gray-700 rounded-lg p-2.5 text-white focus:ring-red-500 focus:border-red-500">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </label>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="toggleModal('addSupplierModal', false)" class="px-5 py-2 rounded-xl text-gray-300 bg-gray-700 hover:bg-gray-600 transition">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl text-white bg-red-600 hover:bg-red-500 transition font-semibold">Add Supplier</button>
            </div>
        </form>
    </div>
</div>

<div id="supplierItemsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-backdrop transition-opacity duration-300" onclick="if(event.target.id === 'supplierItemsModal') toggleModal('supplierItemsModal', false)">
    <div class="bg-gray-900 rounded-2xl p-8 w-full max-w-4xl shadow-2xl scale-100 transform transition-all duration-300">
        <div class="flex justify-between items-center border-b border-gray-800 pb-3 mb-6">
            <h3 class="text-2xl font-bold text-white flex items-center space-x-2">
                <i data-lucide="package" class="w-6 h-6 text-blue-500"></i>
                <span id="itemsModalTitle">Items from: Loading...</span>
            </h3>
            <div class="space-x-2">
                
                <button type="button" onclick="toggleModal('supplierItemsModal', false)" class="px-4 py-2 rounded-xl text-gray-300 bg-gray-700 hover:bg-gray-600 transition flex items-center space-x-1">
                    <i data-lucide="x" class="w-4 h-4"></i>
                    <span>Close</span>
                </button>
            </div>
        </div>
        <input type="hidden" id="currentViewSupplierID">
        
        <div class="overflow-y-auto max-h-[70vh]">
            <div class="grid grid-cols-6 gap-2 text-left text-xs font-semibold uppercase text-gray-400 border-b border-gray-700 pb-2 mb-2 sticky top-0 bg-gray-900 z-10">
                <div class="col-span-1">Image</div>
                <div class="col-span-2">Item Name</div>
                <div class="col-span-1">Category</div>
                <div class="col-span-1">Price</div>
                <div class="col-span-1 text-right">Action</div>
            </div>
            <div id="supplierItemsList" class="divide-y divide-gray-800">
                </div>
        </div>
    </div>
</div>

<div id="requestItemModal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-backdrop transition-opacity duration-300" onclick="if(event.target.id === 'requestItemModal') toggleModal('requestItemModal', false)">
    <div class="bg-gray-900 rounded-2xl p-8 w-full max-w-lg shadow-2xl scale-100 transform transition-all duration-300">
        <h3 class="text-2xl font-bold mb-6 text-white border-b border-gray-800 pb-3 flex items-center space-x-2">
            <i data-lucide="plus-circle" class="w-6 h-6 text-green-500"></i>
            <span>Request NEW Item</span>
        </h3>
        <form action="sales-supplier.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="request_item" value="1">
            <input type="hidden" name="supplierID" id="requestSupplierID">
            <div class="grid grid-cols-1 gap-4">
                <label class="block">
                    <span class="text-gray-400">Product Name</span>
                    <input type="text" name="productName" required class="mt-1 block w-full bg-gray-800 border-gray-700 rounded-lg p-2.5 text-white focus:ring-red-500 focus:border-red-500">
                </label>
                <label class="block">
                    <span class="text-gray-400">Category</span>
                    <input type="text" name="category" required class="mt-1 block w-full bg-gray-800 border-gray-700 rounded-lg p-2.5 text-white focus:ring-red-500 focus:border-red-500">
                </label>
                <div class="grid grid-cols-2 gap-4">
                    <label class="block">
                        <span class="text-gray-400">Suggested Price (₱)</span>
                        <input type="number" step="0.01" name="price" required class="mt-1 block w-full bg-gray-800 border-gray-700 rounded-lg p-2.5 text-white focus:ring-red-500 focus:border-red-500">
                    </label>
                    <label class="block">
                        <span class="text-gray-400">Stock Quantity</span>
                        <input type="number" name="stockQuantity" required class="mt-1 block w-full bg-gray-800 border-gray-700 rounded-lg p-2.5 text-white focus:ring-red-500 focus:border-red-500">
                    </label>
                </div>
                <label class="block">
                    <span class="text-gray-400">Image (Optional)</span>
                    <input type="file" name="imagePath" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                </label>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="toggleModal('requestItemModal', false)" class="px-5 py-2 rounded-xl text-gray-300 bg-gray-700 hover:bg-gray-600 transition">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl text-white bg-green-600 hover:bg-green-500 transition font-semibold">Send Request</button>
            </div>
        </form>
    </div>
</div>


<script>
// Check for URL flags and show alerts
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('request') === 'success') {
        alert('✅ Item request sent to Admin successfully!');
        history.replaceState(null, null, 'sales-supplier.php'); 
    }
});


function toggleModal(id, show) {
    const modal = document.getElementById(id);
    if (!modal) return;
    if (show) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    } else {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

/**
 * Loads and displays items for a specific supplier, allowing stock requests.
 */
function openSupplierItemsModal(supplierID, supplierName) {
    // 1. Set context for the modal
    document.getElementById('currentViewSupplierID').value = supplierID;
    document.getElementById('itemsModalTitle').textContent = `Items from: ${supplierName}`;
    const itemList = document.getElementById("supplierItemsList");
    itemList.innerHTML = '<div class="text-center py-4 text-gray-500">Loading items...</div>';

    // 2. Fetch data
    fetch('ajax-get-supplier-items.php?supplierID=' + supplierID)
        .then(response => response.json())
        .then(data => {
            itemList.innerHTML = "";
            if (data.length === 0) {
                itemList.innerHTML = `<div class="text-center py-8 text-gray-500">No items found for this supplier.</div>`;
            } else {
                data.forEach(item => {
                    // Default image path if none exists
                    const imagePath = item.productsImg ? item.productsImg : 'assets/placeholder.png'; 
                    
                    itemList.innerHTML += `
                        <div class="grid grid-cols-6 gap-2 items-center py-3 hover:bg-gray-800/50 transition">
                            <div class="col-span-1">
                                <img src="${imagePath}" alt="${item.productName}" class="w-12 h-12 object-cover rounded-md">
                            </div>
                            <div class="col-span-2 text-white font-medium">${item.productName}</div>
                            <div class="col-span-1 text-gray-400 text-sm">${item.category}</div>
                            <div class="col-span-1 text-red-400 text-sm">₱${parseFloat(item.price).toFixed(2)}</div>
                            <div class="col-span-1 text-right">
                                <button class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-500 transition"
                                    onclick="requestStockForExistingItem(${supplierID}, ${item.productID}, '${item.productName}', '${item.category}', ${item.price})">
                                    Request Stock (To Admin)
                                </button>
                            </div>
                        </div>`;
                });
            }
            // 3. Show modal after loading
            toggleModal("supplierItemsModal", true);
        })
        .catch(error => {
            console.error('Fetch error:', error);
            itemList.innerHTML = `<div class="text-center py-8 text-red-400">Failed to fetch data. Check console for details.</div>`;
            toggleModal("supplierItemsModal", true);
        });
}

/**
 * Handles the "Request Stock" action for an item ALREADY in the system.
 */
function requestStockForExistingItem(supplierID, productID, productName, category, price) {
    const quantity = prompt(`Enter quantity to request for ad "${productName}" (Price: ₱${price}):`);
    
    if (quantity === null || quantity.trim() === "" || isNaN(quantity) || parseInt(quantity) <= 0) {
        if (quantity !== null) alert("Please enter a valid quantity.");
        return;
    }
    
    const stockQuantity = parseInt(quantity);
    
    // Send request to the AJAX endpoint
    fetch('ajax-get-supplier-items.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 
            request_existing_stock: '1',
            supplierID: supplierID,
            productID: productID,
            stockQuantity: stockQuantity,
            productName: productName,
            category: category,
            price: price // Include price for the request table record
        })
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === "success"){ 
            alert(`✅ Request for ${stockQuantity} units of ${productName} sent to Admin for approval!`); 
        } else {
            alert(`❌ Error sending request: ${data.message || 'Unknown error'}`); 
            console.error(data);
        } 
    })
    .catch(error => {
        alert("An unexpected error occurred during the request.");
        console.error("Fetch error:", error);
    });
}

function openRequestItemModal(supplierID) {
    document.getElementById('requestSupplierID').value = supplierID;
    toggleModal('requestItemModal', true);
}

document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
});
</script>
</body>
</html>

