<?php
session_start();

// ✅ Protect page
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

// ✅ Add Supplier
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

    header("Location: sales-supplier.php");
    exit();
}

// ✅ Delete Supplier
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

// ✅ Update Supplier
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
        echo "<script>alert('✅ Supplier updated successfully!'); window.location='sales-supplier.php';</script>";
    } else {
        echo "<script>alert('❌ Error updating supplier.'); window.location='sales-supplier.php';</script>";
    }

    $stmt->close();
    exit();
}

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
                    <span>Actions</span>
                    <i data-lucide="plus-circle" class="text-red-300"></i>
                </div>
                <p class="text-sm text-red-100 mt-4">Onboard new vendors instantly.</p>
                <button class="mt-4 px-4 py-3 bg-red-600 text-white rounded-xl font-semibold shadow-lg shadow-red-600/40 hover:bg-red-500 transition"
                        onclick="toggleModal('addSupplierModal', true)">
                    Add Supplier
                </button>
            </div>
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
                    <button class="hidden md:flex items-center space-x-2 px-4 py-2 bg-gray-800 text-white rounded-xl hover:bg-gray-700 transition"
                            onclick="toggleModal('addSupplierModal', true)">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Add</span>
                    </button>
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
                                        <td class="px-6 py-4 text-right space-x-3">
                                            <button
                                                class="inline-flex items-center space-x-1 text-blue-400 hover:text-blue-300 transition text-sm font-semibold"
                                                onclick="openUpdateModal(
                                                    '<?= $row['supplierID']; ?>',
                                                    '<?= htmlspecialchars($row['supplierName']); ?>',
                                                    '<?= htmlspecialchars($row['contactPerson']); ?>',
                                                    '<?= htmlspecialchars($row['contactNumber']); ?>',
                                                    '<?= htmlspecialchars($row['email']); ?>',
                                                    '<?= htmlspecialchars($row['address']); ?>',
                                                    '<?= htmlspecialchars($row['status']); ?>'
                                                )">
                                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                                                <span>Edit</span>
                                            </button>
                                            <button
                                                class="inline-flex items-center space-x-1 text-red-400 hover:text-red-300 transition text-sm font-semibold"
                                                onclick="confirmDelete(<?= $row['supplierID']; ?>)">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                <span>Delete</span>
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

<!-- Add Supplier Modal -->
<div id="addSupplierModal" class="modal-backdrop fixed inset-0 hidden items-center justify-center p-4 z-40">
    <div class="bg-gray-900 rounded-2xl w-full max-w-2xl shadow-2xl border border-gray-800">
        <div class="flex items-center justify-between border-b border-gray-800 px-6 py-4">
            <div>
                <p class="text-xs uppercase tracking-[0.25em] text-gray-500">New Partner</p>
                <h3 class="text-xl font-semibold text-white">Add Supplier</h3>
            </div>
            <button class="text-gray-400 hover:text-red-400" onclick="toggleModal('addSupplierModal', false)">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <form method="POST" class="px-6 py-6 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-400">Supplier Name</label>
                    <input type="text" name="supplierName" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100" required>
                </div>
                <div>
                    <label class="text-sm text-gray-400">Contact Person</label>
                    <input type="text" name="contactPerson" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100" required>
                </div>
                <div>
                    <label class="text-sm text-gray-400">Contact Number</label>
                    <input type="text" name="contactNumber" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100">
                </div>
                <div>
                    <label class="text-sm text-gray-400">Email</label>
                    <input type="email" name="email" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100">
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-400">Address</label>
                    <input type="text" name="address" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100">
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-400">Status</label>
                    <select name="status" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="flex flex-col md:flex-row items-center justify-end gap-3 pt-4 border-t border-gray-800">
                <button type="button" class="w-full md:w-auto px-4 py-3 rounded-xl bg-gray-800 text-gray-200 hover:bg-gray-700 transition" onclick="toggleModal('addSupplierModal', false)">
                    Cancel
                </button>
                <button type="submit" name="add_supplier" class="w-full md:w-auto px-4 py-3 rounded-xl bg-red-600 text-white font-semibold hover:bg-red-500 transition">
                    Save Supplier
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Update Supplier Modal -->
<div id="updateSupplierModal" class="modal-backdrop fixed inset-0 hidden items-center justify-center p-4 z-40">
    <div class="bg-gray-900 rounded-2xl w-full max-w-2xl shadow-2xl border border-gray-800">
        <div class="flex items-center justify-between border-b border-gray-800 px-6 py-4">
            <div>
                <p class="text-xs uppercase tracking-[0.25em] text-gray-500">Edit Partner</p>
                <h3 class="text-xl font-semibold text-white">Update Supplier</h3>
            </div>
            <button class="text-gray-400 hover:text-red-400" onclick="toggleModal('updateSupplierModal', false)">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <form method="POST" class="px-6 py-6 space-y-4">
            <input type="hidden" name="updateID" id="updateID">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-400">Supplier Name</label>
                    <input type="text" name="updateSupplierName" id="updateSupplierName" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100" required>
                </div>
                <div>
                    <label class="text-sm text-gray-400">Contact Person</label>
                    <input type="text" name="updateContactPerson" id="updateContactPerson" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100" required>
                </div>
                <div>
                    <label class="text-sm text-gray-400">Contact Number</label>
                    <input type="text" name="updateContact" id="updateContact" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100">
                </div>
                <div>
                    <label class="text-sm text-gray-400">Email</label>
                    <input type="email" name="updateEmail" id="updateEmail" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100">
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-400">Address</label>
                    <input type="text" name="updateAddress" id="updateAddress" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100">
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-400">Status</label>
                    <select name="updateStatus" id="updateStatus" class="w-full bg-gray-950/70 border border-gray-800 rounded-xl px-4 py-3 text-gray-100">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-col md:flex-row items-center justify-end gap-3 pt-4 border-t border-gray-800">
                <button type="button" class="w-full md:w-auto px-4 py-3 rounded-xl bg-gray-800 text-gray-200 hover:bg-gray-700 transition" onclick="toggleModal('updateSupplierModal', false)">
                    Cancel
                </button>
                <button type="submit" name="update_supplier" class="w-full md:w-auto px-4 py-3 rounded-xl bg-red-600 text-white font-semibold hover:bg-red-500 transition">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
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

function openUpdateModal(id, name, person, number, email, address, status) {
    toggleModal('updateSupplierModal', true);
    document.getElementById('updateID').value = id;
    document.getElementById('updateSupplierName').value = name;
    document.getElementById('updateContactPerson').value = person;
    document.getElementById('updateContact').value = number;
    document.getElementById('updateEmail').value = email;
    document.getElementById('updateAddress').value = address;
    document.getElementById('updateStatus').value = status;
}

function confirmDelete(supplierID) {
    if (!confirm("Are you sure you want to delete this supplier?")) return;

    fetch('sales-supplier.php', {
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
            alert("Supplier deleted successfully.");
            window.location.reload();
        } else {
            alert("Error deleting supplier.");
            console.log("Server response:", data);
        }
    })
    .catch(error => {
        alert("Error connecting to server.");
        console.error(error);
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
