<?php
session_start();

// Protect page
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

// -------------------- Supplier CRUD (your existing logic) --------------------

// Add Supplier
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_supplier'])) {
    $supplierName  = $_POST['supplierName'];
    $contactPerson = $_POST['contactPerson'];
    $contactNumber = $_POST['contactNumber'];
    $email         = $_POST['email'];
    $address       = $_POST['address'];
    $status        = $_POST['status'];

    $stmt = $conn->prepare("INSERT INTO supplier (supplierName, contactPerson, contactNumber, email, address, status, dateAdded)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssss", $supplierName, $contactPerson, $contactNumber, $email, $address, $status);
    $stmt->execute();
    $stmt->close();

    header("Location: admin-supplier.php");
    exit();
}

// Delete Supplier
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_supplier'])) {
    $deleteID = intval($_POST['deleteID']);

    $stmt = $conn->prepare("DELETE FROM supplier WHERE supplierID = ?");
    $stmt->bind_param("i", $deleteID);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error";
    }

    $stmt->close();
    exit();
}

// Update Supplier
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_supplier'])) {
    $updateID      = intval($_POST['updateID']);
    $supplierName  = $_POST['updateSupplierName'];
    $contactPerson = $_POST['updateContactPerson'];
    $contactNumber = $_POST['updateContact'];
    $email         = $_POST['updateEmail'];
    $address       = $_POST['updateAddress'];
    $status        = $_POST['updateStatus'];

    $stmt = $conn->prepare("UPDATE supplier 
                            SET supplierName=?, contactPerson=?, contactNumber=?, email=?, address=?, status=? 
                            WHERE supplierID=?");
    $stmt->bind_param("ssssssi", $supplierName, $contactPerson, $contactNumber, $email, $address, $status, $updateID);

    if ($stmt->execute()) {
        echo "<script>alert('✅ Supplier updated successfully!'); window.location='admin-supplier.php';</script>";
    } else {
        echo "<script>alert('❌ Error updating supplier.'); window.location='admin-supplier.php';</script>";
    }

    $stmt->close();
    exit();
}

// Fetch Suppliers
$result = $conn->query("SELECT * FROM supplier ORDER BY supplierID ASC");
$supplierCountQuery = $conn->query("SELECT COUNT(*) AS total FROM supplier");
$supplierCount = $supplierCountQuery ? (int)$supplierCountQuery->fetch_assoc()['total'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers (Admin)</title>
    <link rel="stylesheet" href="css/supplier.css">
    <script src="https://cdn.tailwindcss.com" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        :root { font-family: 'Inter', sans-serif; }
        .app-bg { background-color: #121212; }
        .sidebar-bg { background-color: #0d0d0d; }
        [data-lucide] { width: 1.5rem; height: 1.5rem; }
        .input-style { background-color: #1f2937; color: #d1d5db; border: 2px solid #374151; transition: all 0.3s ease; }
        .input-style:focus { outline: none; border-color: #ef4444; }
        .supplier-grid tbody tr { background-color: #1f2937; border-bottom: 1px solid #374151; transition: background-color 0.2s; }
        .supplier-grid tbody tr:hover { background-color: #2c3e50; }
        /* Modal basic */
        .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); align-items:center; justify-content:center; z-index:50; }
        .modal .modal-content { background:#0b0b0b; padding:20px; border-radius:12px; width:90%; max-width:1000px; color:#e6e6e6; }
        .small-input { background:#111827; color:#e5e7eb; padding:8px; border-radius:8px; border:1px solid #374151; width:100%; }
        .btn { padding:8px 12px; border-radius:8px; font-weight:600; }
    </style>
</head>

<script>
// Delete Supplier (AJAX)
function confirmDelete(supplierID, event) {
    if (!confirm("Are you sure you want to delete this supplier?")) return;

    fetch('admin-supplier.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            delete_supplier: '1',
            deleteID: supplierID
        })
    })
    .then(response => response.text())
    .then(data => {
        if (data.trim() === "success") {
            alert("🗑️ Supplier deleted successfully!");
            const row = event.target.closest('tr');
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 300);
        } else {
            alert("❌ Error deleting supplier.");
            console.log("Server response:", data);
        }
    })
    .catch(error => {
        alert("⚠️ Error connecting to server.");
        console.error(error);
    });
}
</script>

<body class="app-bg text-gray-100 min-h-screen">
<div class="flex w-full min-h-screen">
<?php include "admin-sidebar.php"; ?>
<main class="flex-1 md:ml-64 p-6 md:p-10 w-full">

<div class="space-y-8">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-gray-800 pb-4">
        <div class="flex items-center space-x-3">
            <i data-lucide="factory" class="text-red-500 w-10 h-10"></i>
            <div>
                <p class="text-sm uppercase tracking-[0.3em] text-gray-500">Strategic Partners</p>
                <h1 class="text-4xl font-bold tracking-tight text-white">Supplier Management</h1>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <div class="bg-gray-900 px-4 py-2 rounded-xl border border-gray-800 text-sm text-gray-400">
                Total Suppliers <span class="text-white font-semibold ml-2"><?= $supplierCount ?></span>
            </div>
            <button class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl transition duration-200 shadow-lg flex items-center space-x-2"
                onclick="document.getElementById('addSupplierModal').style.display='flex'">
                <i data-lucide="plus" class="w-5 h-5"></i>
                <span>Add Supplier</span>
            </button>
        </div>
    </header>

    <!-- Supplier List -->
    <section class="bg-gray-900 p-6 rounded-2xl shadow-2xl overflow-x-auto">
        <div class="rounded-xl border border-gray-800">
            <table class="min-w-full divide-y divide-gray-800 supplier-grid">
                <thead class="bg-gray-800">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Supplier</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Contact Person</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Phone</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Address</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Status</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-200">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300 font-medium"><?= htmlspecialchars($row['supplierName']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?= htmlspecialchars($row['contactPerson']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400"><?= htmlspecialchars($row['contactNumber']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?= htmlspecialchars($row['email']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-400"><?= htmlspecialchars($row['address']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= strtolower($row['status']) === 'active' ? 'bg-green-900/40 text-green-300 border border-green-500/50' : 'bg-yellow-900/40 text-yellow-200 border border-yellow-500/50' ?>">
                                        <?= htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-3">
                                    <button class="text-blue-400 hover:text-blue-500 transition duration-150"
                                        onclick="openUpdateModal(
                                            '<?= $row['supplierID']; ?>',
                                            '<?= htmlspecialchars($row['supplierName']); ?>',
                                            '<?= htmlspecialchars($row['contactPerson']); ?>',
                                            '<?= htmlspecialchars($row['contactNumber']); ?>',
                                            '<?= htmlspecialchars($row['email']); ?>',
                                            '<?= htmlspecialchars($row['address']); ?>',
                                            '<?= htmlspecialchars($row['status']); ?>'
                                        )">Update</button>

                                    <!-- View Items button opens modal -->
                                    <button class="text-indigo-300 hover:text-indigo-400 transition duration-150"
                                        onclick="openItemsModal(<?= $row['supplierID']; ?>, '<?= htmlspecialchars(addslashes($row['supplierName'])) ?>')">
                                        View Items
                                    </button>

                                    <button class="text-red-400 hover:text-red-500 transition duration-150"
                                        onclick="confirmDelete(<?= $row['supplierID']; ?>, event)">Delete</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-400">No suppliers found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<!-- Add Supplier Modal (keeps your form but as modal) -->
<div class="modal" id="addSupplierModal">
    <div class="modal-content">
        <h3 class="text-2xl mb-3">Add New Supplier</h3>
        <form method="POST" action="admin-supplier.php">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <input class="small-input" placeholder="Supplier Name" type="text" name="supplierName" required>
                <input class="small-input" placeholder="Contact Person" type="text" name="contactPerson">
                <input class="small-input" placeholder="Contact Number" type="text" name="contactNumber">
                <input class="small-input" placeholder="Email" type="email" name="email">
                <input class="small-input" placeholder="Address" type="text" name="address">
                <select class="small-input" name="status">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            <div class="mt-4 flex gap-2 justify-end">
                <button type="button" class="btn" onclick="document.getElementById('addSupplierModal').style.display='none'">Cancel</button>
                <button type="submit" name="add_supplier" class="btn bg-red-600 text-white">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Update Supplier Modal (unchanged) -->
<div class="modal" id="updateModal">
    <div class="modal-content">
        <h3 class="text-2xl mb-3">Update Supplier</h3>
        <form method="POST" action="admin-supplier.php">
            <input type="hidden" name="updateID" id="updateID">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <input class="small-input" type="text" name="updateSupplierName" id="updateSupplierName" required>
                <input class="small-input" type="text" name="updateContactPerson" id="updateContactPerson" required>
                <input class="small-input" type="text" name="updateContact" id="updateContact" required>
                <input class="small-input" type="email" name="updateEmail" id="updateEmail" required>
                <input class="small-input" type="text" name="updateAddress" id="updateAddress" required>
                <select class="small-input" name="updateStatus" id="updateStatus">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            <div class="mt-4 flex gap-2 justify-end">
                <button type="button" class="btn" onclick="document.getElementById('updateModal').style.display='none'">Cancel</button>
                <button type="submit" name="update_supplier" class="btn bg-indigo-600 text-white">Update</button>
            </div>
        </form>
    </div>
</div>

<!-- Supplier Items Modal -->
<div class="modal" id="itemsModal">
    <div class="modal-content">
        <div class="flex justify-between items-center mb-4">
            <h3 id="itemsModalTitle" class="text-2xl">Supplier Items</h3>
            <div class="flex gap-2">
                <button class="btn" onclick="document.getElementById('itemsModal').style.display='none'">Close</button>
                <button class="btn bg-green-600 text-white" id="openAddItemBtn">Add Item</button>
            </div>
        </div>

        <div id="itemsContainer">
            <!-- items list will be injected here -->
            <div class="text-gray-400">Loading items...</div>
        </div>
    </div>
</div>

<!-- Add/Edit Supplier Item Modal -->
<div class="modal" id="itemManageModal">
    <div class="modal-content">
        <h3 id="itemManageTitle" class="text-2xl">Add Item</h3>

        <form id="itemManageForm" enctype="multipart/form-data">
            <input type="hidden" name="action" id="itemFormAction" value="add_item">
            <input type="hidden" name="supplierID" id="formSupplierID" value="">
            <input type="hidden" name="itemID" id="formItemID" value="">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <input class="small-input" name="itemName" id="formItemName" placeholder="Item name" required>
                <select class="small-input" name="category" id="formCategory" required>
                    <option value="" disabled selected>Select Category</option>
                    <option value="Engine & Transmission">Engine & Transmission</option>
                    <option value="Breaking system">Breaking system</option>
                    <option value="Suspension & Steering">Suspension & Steering</option>
                    <option value="Electrical & Lightning">Electrical & Lightning</option>
                    <option value="Tires and Wheels">Tires and Wheels</option>
                </select>
                <input class="small-input" name="unitPrice" id="formUnitPrice" placeholder="Unit price (optional)">
                <input class="small-input" type="file" name="image" id="formImage">
                <textarea class="small-input" name="description" id="formDescription" placeholder="Description (optional)"></textarea>
            </div>

            <div class="mt-4 flex gap-2 justify-end">
                <button type="button" class="btn" onclick="document.getElementById('itemManageModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn bg-red-600 text-white">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
// helper: open update modal (supplier)
function openUpdateModal(id, name, person, number, email, address, status) {
    document.getElementById('updateModal').style.display = 'flex';
    document.getElementById('updateID').value = id;
    document.getElementById('updateSupplierName').value = name;
    document.getElementById('updateContactPerson').value = person;
    document.getElementById('updateContact').value = number;
    document.getElementById('updateEmail').value = email;
    document.getElementById('updateAddress').value = address;
    document.getElementById('updateStatus').value = status;
}

// ---------- Items modal logic ----------
let currentSupplierID = null;

function openItemsModal(supplierID, supplierName) {
    currentSupplierID = supplierID;
    document.getElementById('itemsModalTitle').innerText = `Items from: ${supplierName}`;
    document.getElementById('itemsModal').style.display = 'flex';
    document.getElementById('formSupplierID').value = supplierID;
    loadItemsForSupplier(supplierID);
}

// fetch items via AJAX
function loadItemsForSupplier(supplierID) {
    document.getElementById('itemsContainer').innerHTML = '<div class="text-gray-400">Loading items...</div>';
    fetch('supplier_items_action.php?action=fetch_items&supplierID=' + supplierID)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                document.getElementById('itemsContainer').innerHTML = '<div class="text-red-400">' + (res.message || 'Failed to load items') + '</div>';
                return;
            }
            const items = res.data;
            if (items.length === 0) {
                document.getElementById('itemsContainer').innerHTML = '<div class="text-gray-400">No items found for this supplier. Click "Add Item" to add.</div>';
                return;
            }

            let html = '<div class="overflow-x-auto"><table class="min-w-full text-left"><thead><tr class="text-sm text-gray-400"><th class="p-2">Image</th><th class="p-2">Item</th><th class="p-2">Category</th><th class="p-2">Price</th><th class="p-2">Action</th></tr></thead><tbody>';
            items.forEach(it => {
                const img = it.imagePath ? `<img src="${it.imagePath}" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:6px">` : '<div class="w-16 h-16 bg-gray-800 rounded"></div>';
                html += `<tr class="border-t border-gray-700">
                    <td class="p-2 align-top">${img}</td>
                    <td class="p-2 align-top"><div class="font-semibold">${escapeHtml(it.itemName)}</div><div class="text-xs text-gray-400">${escapeHtml(it.description || '')}</div></td>
                    <td class="p-2 align-top">${escapeHtml(it.category)}</td>
                    <td class="p-2 align-top">₱${Number(it.unitPrice).toFixed(2)}</td>
                    <td class="p-2 align-top">
                        <button class="btn bg-yellow-600 text-white mr-2" onclick="openEditItem(${it.itemID})">Edit</button>
                        <button class="btn bg-blue-600 text-white mr-2" onclick="promptRequest(${it.itemID}, '${escapeForJs(it.itemName)}')">Request Stock</button>
                        <button class="btn bg-red-600 text-white" onclick="deleteItem(${it.itemID})">Delete</button>
                    </td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            document.getElementById('itemsContainer').innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            document.getElementById('itemsContainer').innerHTML = '<div class="text-red-400">Error fetching items.</div>';
        });
}

// Open add item modal
document.getElementById('openAddItemBtn').addEventListener('click', function(){
    openAddItem();
});

function openAddItem() {
    document.getElementById('itemManageModal').style.display = 'flex';
    document.getElementById('itemManageTitle').innerText = 'Add Item';
    document.getElementById('itemFormAction').value = 'add_item';
    document.getElementById('formItemID').value = '';
    document.getElementById('formItemName').value = '';
    document.getElementById('formCategory').value = '';
    document.getElementById('formUnitPrice').value = '';
    document.getElementById('formDescription').value = '';
    document.getElementById('formImage').value = '';
}

// Open edit item and populate form
function openEditItem(item) {
    // fetch item by id (we have a helper fetch in supplier_items_action, reuse it)
    fetch('supplier_items_action.php?action=get_item&itemID=' + item)
        .then(r => r.json())
        .then(res => {
            if (!res.success) { alert(res.message || 'Failed'); return; }
            const it = res.data;
            document.getElementById('itemManageModal').style.display = 'flex';
            document.getElementById('itemManageTitle').innerText = 'Edit Item';
            document.getElementById('itemFormAction').value = 'edit_item';
            document.getElementById('formItemID').value = it.itemID;
            document.getElementById('formItemName').value = it.itemName;
            document.getElementById('formCategory').value = it.category;
            document.getElementById('formUnitPrice').value = it.unitPrice;
            document.getElementById('formDescription').value = it.description || '';
            document.getElementById('formSupplierID').value = it.supplierID;
        })
        .catch(err => { console.error(err); alert('Error loading item.'); });
}

function deleteItem(itemID) {
    if (!confirm("Delete this supplier item?")) return;
    fetch('supplier_items_action.php', {
        method: 'POST',
        body: new URLSearchParams({ action: 'delete_item', itemID: itemID })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert('Deleted');
            loadItemsForSupplier(currentSupplierID);
        } else alert(res.message || 'Delete failed');
    });
}

// handle form submit (add/edit item)
document.getElementById('itemManageForm').addEventListener('submit', function(e){
    e.preventDefault();
    const form = document.getElementById('itemManageForm');
    const data = new FormData(form);
    // ensure supplier id is set
    data.set('supplierID', document.getElementById('formSupplierID').value || currentSupplierID);
    fetch('supplier_items_action.php', {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert(res.message || 'Saved');
            document.getElementById('itemManageModal').style.display = 'none';
            loadItemsForSupplier(currentSupplierID);
        } else {
            alert(res.message || 'Failed to save.');
        }
    }).catch(err => { console.error(err); alert('Error'); });
});

// Prompt for request quantity then send request_stock
function promptRequest(itemID, itemName) {
    const qty = prompt('Request how many units of "' + itemName + '"? (enter a whole number)', '1');
    if (qty === null) return; // cancelled
    const qnum = parseInt(qty);
    if (isNaN(qnum) || qnum <= 0) { alert('Enter a valid quantity'); return; }

    // send request to create/update product
    fetch('supplier_items_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'request_stock', itemID: itemID, qty: qnum })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert(res.message || 'Stock updated/created in products.');
            // optionally refresh items or product list
            loadItemsForSupplier(currentSupplierID);
        } else {
            alert(res.message || 'Request failed');
        }
    }).catch(err => { console.error(err); alert('Error'); });
}

// small helpers
function escapeHtml(s){ return String(s).replace(/[&<>"'`=\/]/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c]; }); }
function escapeForJs(s){ return String(s).replace(/'/g,"\\'").replace(/\\/g,"\\\\"); }

// close modals on click outside
document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', function(e){
        if (e.target === m) m.style.display = 'none';
    });
});

lucide.createIcons();
</script>

</main>
</div>
</body>
</html>
