<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';
 
// ✅ Add Client
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_client'])) {
    $clientName    = $_POST['clientName'];
    $contactNumber = $_POST['contactNumber'];
    $email         = $_POST['email'];
    $address       = $_POST['address'];

    $stmt = $conn->prepare("INSERT INTO clientinfo (clientName, contactNumber, email, address, registeredDate)
                            VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $clientName, $contactNumber, $email, $address);
    $stmt->execute();
    $stmt->close();

    header("Location: admin-clients.php");
    exit();
}

// ✅ Delete Client (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_client'])) {
    $deleteID = intval($_POST['deleteID']);
    $stmt = $conn->prepare("DELETE FROM clientinfo WHERE clientID = ?");
    $stmt->bind_param("i", $deleteID);

    echo $stmt->execute() ? "success" : "error";
    $stmt->close();
    exit();
}

// ✅ Update Client
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_client'])) {
    $updateID      = intval($_POST['updateID']);
    $clientName    = $_POST['updateName'];
    $contactNumber = $_POST['updateNumber'];
    $email         = $_POST['updateEmail'];
    $address       = $_POST['updateAddress'];

    $stmt = $conn->prepare("UPDATE clientinfo 
                            SET clientName=?, contactNumber=?, email=?, address=? 
                            WHERE clientID=?");
    $stmt->bind_param("ssssi", $clientName, $contactNumber, $email, $address, $updateID);

    if ($stmt->execute()) {
        echo "<script>alert('✅ Client updated successfully!'); window.location='admin-clients.php';</script>";
    } else {
        echo "<script>alert('❌ Error updating client.'); window.location='admin-clients.php';</script>";
    }

    $stmt->close();
    exit();
}

// ✅ Fetch clients
$result = $conn->query("SELECT * FROM clientinfo ORDER BY clientID ASC");
$clientCountQuery = $conn->query("SELECT COUNT(*) AS total FROM clientinfo");
$clientCount = $clientCountQuery ? (int)$clientCountQuery->fetch_assoc()['total'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients (Admin)</title>
    <link rel="stylesheet" href="css/client.css">
    <script src="https://cdn.tailwindcss.com" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');

        :root { font-family: 'Inter', sans-serif; }
        .app-bg { background-color: #121212; }
        .sidebar-bg { background-color: #0d0d0d; }
        [data-lucide] { width: 1.5rem; height: 1.5rem; }

        .client-table tbody tr {
            background-color: #1f2937;
            border-bottom: 1px solid #374151;
            transition: background-color 0.2s;
        }
        .client-table tbody tr:hover { background-color: #2c3e50; }
    </style>
</head>

<script>
function confirmDelete(clientID, event) {
    if (!confirm("Are you sure you want to delete this client?")) return;

    fetch('admin-clients.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            delete_client: '1',
            deleteID: clientID
        })
    })
    .then(r => r.text())
    .then(data => {
        if (data.trim() === "success") {
            alert("🗑️ Client deleted successfully!");
            const row = event.target.closest('tr');
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 300);
        } else alert("❌ Error deleting client.");
    })
    .catch(() => alert("⚠️ Error connecting to server."));
}

function openUpdateModal(id, name, number, email, address) {
    document.getElementById('updateModal').style.display = 'flex';
    document.getElementById('updateID').value = id;
    document.getElementById('updateName').value = name;
    document.getElementById('updateNumber').value = number;
    document.getElementById('updateEmail').value = email;
    document.getElementById('updateAddress').value = address;
}
</script>

<body class="app-bg text-gray-100 min-h-screen">
<div class="flex w-full min-h-screen">
<?php include "admin-sidebar.php"; ?>
<main class="flex-1 md:ml-64 p-6 md:p-10 w-full space-y-8">

<div class="space-y-8">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-gray-800 pb-4">
        <div class="flex items-center space-x-3">
            <i data-lucide="users-round" class="text-red-500 w-10 h-10"></i>
            <div>
                <p class="text-sm uppercase tracking-[0.3em] text-gray-500">Relationships</p>
                <h1 class="text-4xl font-bold tracking-tight text-white">Client Management</h1>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <div class="bg-gray-900 px-4 py-2 rounded-xl border border-gray-800 text-sm text-gray-400">
                Total Clients <span class="text-white font-semibold ml-2"><?= $clientCount ?></span>
            </div>
            <button class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl transition duration-200 shadow-lg flex items-center space-x-2"
                onclick="document.getElementById('addModal').style.display='flex'">
                <i data-lucide="user-plus" class="w-5 h-5"></i>
                <span>Add Client</span>
            </button>
        </div>
    </header>

    <section class="bg-gray-900 p-6 rounded-2xl shadow-2xl overflow-x-auto">
        <div class="rounded-xl border border-gray-800">
            <table class="min-w-full divide-y divide-gray-800 client-table">
                <thead class="bg-gray-800">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Client Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Contact</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Address</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-200">Registered</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-200">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300 font-medium"><?= htmlspecialchars($row['clientName']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400"><?= htmlspecialchars($row['contactNumber']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?= htmlspecialchars($row['email']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-400"><?= htmlspecialchars($row['address']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['registeredDate']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-3">
                                    <button class="text-blue-400 hover:text-blue-500 transition duration-150"
                                        onclick="openUpdateModal(
                                            '<?= $row['clientID']; ?>',
                                            '<?= htmlspecialchars($row['clientName']); ?>',
                                            '<?= htmlspecialchars($row['contactNumber']); ?>',
                                            '<?= htmlspecialchars($row['email']); ?>',
                                            '<?= htmlspecialchars($row['address']); ?>'
                                        )">
                                        Update
                                    </button>
                                    <button class="text-red-400 hover:text-red-500 transition duration-150" onclick="confirmDelete(<?= $row['clientID']; ?>, event)">Delete</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-400">No clients found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<!-- Add Client Modal -->
<div class="modal" id="addModal">
    <div class="modal-content">
        <h3>Add Client</h3>
        <form method="POST" action="admin-clients.php">
            <label>Name:</label>
            <input type="text" name="clientName" required><br>

            <label>Contact:</label>
            <input type="text" name="contactNumber"><br>

            <label>Email:</label>
            <input type="email" name="email"><br>

            <label>Address:</label>
            <input type="text" name="address"><br>

            <button type="submit" name="add_client">Save</button>
            <button type="button" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
        </form>
    </div>
</div>

<!-- Update Modal -->
<div class="modal" id="updateModal">
    <div class="modal-content">
        <h3>Update Client</h3>
        <form method="POST" action="admin-clients.php">
            <input type="hidden" name="updateID" id="updateID">
            <label>Name:</label>
            <input type="text" name="updateName" id="updateName" required><br>

            <label>Contact:</label>
            <input type="text" name="updateNumber" id="updateNumber"><br>

            <label>Email:</label>
            <input type="email" name="updateEmail" id="updateEmail"><br>

            <label>Address:</label>
            <input type="text" name="updateAddress" id="updateAddress"><br>

            <button type="submit" name="update_client">Update</button>
            <button type="button" onclick="document.getElementById('updateModal').style.display='none'">Cancel</button>
        </form>
    </div>
</div>

<script>
    function confirmDelete(clientID, event) {
        if (!confirm("Are you sure you want to delete this client?")) return;

        fetch('admin-clients.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                delete_client: '1',
                deleteID: clientID
            })
        })
        .then(r => r.text())
        .then(data => {
            if (data.trim() === "success") {
                alert("🗑️ Client deleted successfully!");
                const row = event.target.closest('tr');
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
            } else alert("❌ Error deleting client.");
        })
        .catch(() => alert("⚠️ Error connecting to server."));
    }

    function openUpdateModal(id, name, number, email, address) {
        document.getElementById('updateModal').style.display = 'flex';
        document.getElementById('updateID').value = id;
        document.getElementById('updateName').value = name;
        document.getElementById('updateNumber').value = number;
        document.getElementById('updateEmail').value = email;
        document.getElementById('updateAddress').value = address;
    }

    lucide.createIcons();
</script>

</main>
</div>

</body>
</html>
