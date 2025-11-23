<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $stmt = $conn->prepare("SELECT * FROM clientinfo WHERE clientName LIKE ? OR email LIKE ? ORDER BY clientID DESC");
    $term = "%$search%";
    $stmt->bind_param("ss", $term, $term);
} else {
    $stmt = $conn->prepare("SELECT * FROM clientinfo ORDER BY clientID DESC");
}
$stmt->execute();
$clientsResult = $stmt->get_result();
$clients = [];
while ($row = $clientsResult->fetch_assoc()) {
    $clients[] = $row;
}
$stmt->close();

$totalClientsQuery = $conn->query("SELECT COUNT(*) AS total FROM clientinfo");
$totalClients = $totalClientsQuery ? (int)$totalClientsQuery->fetch_assoc()['total'] : 0;

$monthStart = date('Y-m-01');
$newClientsQuery = $conn->query("SELECT COUNT(*) AS total FROM clientinfo WHERE DATE(registeredDate) >= '$monthStart'");
$newClients = $newClientsQuery ? (int)$newClientsQuery->fetch_assoc()['total'] : 0;

$emailClientsQuery = $conn->query("SELECT COUNT(*) AS total FROM clientinfo WHERE email IS NOT NULL AND email <> ''");
$emailClients = $emailClientsQuery ? (int)$emailClientsQuery->fetch_assoc()['total'] : 0;

$recentClientsQuery = $conn->query("SELECT clientName, address, registeredDate FROM clientinfo ORDER BY registeredDate DESC LIMIT 5");
$recentClients = [];
if ($recentClientsQuery) {
    while ($row = $recentClientsQuery->fetch_assoc()) {
        $recentClients[] = $row;
    }
}

$lastUpdated = date('M d, Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales • Clients</title>
    <script src="https://cdn.tailwindcss.com" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        :root { font-family: 'Inter', sans-serif; }
        body { font-family: 'Inter', sans-serif; }
        .app-bg { background-color: #121212; }
        .sidebar-bg { background-color: #0d0d0d; }
    </style>
</head>
<body class="app-bg text-gray-100 min-h-screen">
<div class="flex">
    <?php include("sales-sidebar.php"); ?>

    <main class="flex-1 md:ml-64 p-6 md:p-10 space-y-10">
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center border-b border-gray-800 pb-6">
            <div>
                <p class="text-sm uppercase tracking-[0.35em] text-gray-500">Client Network</p>
                <h1 class="text-4xl font-light tracking-tight mt-2 flex items-center space-x-3">
                    <span class="font-bold text-red-500">Customer Directory</span>
                    <i data-lucide="users" class="w-8 h-8 text-red-500"></i>
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
                    <span>Total Clients</span>
                    <i data-lucide="layers" class="text-red-400"></i>
                </div>
                <p class="text-4xl font-extrabold text-white mt-4"><?= number_format($totalClients); ?></p>
                <p class="text-sm text-gray-500 mt-2">Profiles on record</p>
            </div>

            <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl hover:ring-2 hover:ring-green-500/40 transition">
                <div class="flex items-center justify-between text-gray-400 text-xs uppercase tracking-widest">
                    <span>New This Month</span>
                    <i data-lucide="calendar-plus" class="text-green-400"></i>
                </div>
                <p class="text-4xl font-extrabold text-white mt-4"><?= number_format($newClients); ?></p>
                <p class="text-sm text-gray-500 mt-2">Since <?= date('M j', strtotime($monthStart)); ?></p>
            </div>

            <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl hover:ring-2 hover:ring-blue-500/40 transition">
                <div class="flex items-center justify-between text-gray-400 text-xs uppercase tracking-widest">
                    <span>Email Reachable</span>
                    <i data-lucide="mail" class="text-blue-400"></i>
                </div>
                <p class="text-4xl font-extrabold text-white mt-4"><?= number_format($emailClients); ?></p>
                <p class="text-sm text-gray-500 mt-2">Have valid contact email</p>
            </div>

            <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl hover:ring-2 hover:ring-yellow-500/40 transition">
                <div class="flex items-center justify-between text-gray-400 text-xs uppercase tracking-widest">
                    <span>Search Results</span>
                    <i data-lucide="search" class="text-yellow-400"></i>
                </div>
                <p class="text-4xl font-extrabold text-white mt-4"><?= number_format(count($clients)); ?></p>
                <p class="text-sm text-gray-500 mt-2"><?= $search === '' ? 'Showing latest clients' : 'Matches for “' . htmlspecialchars($search) . '”'; ?></p>
            </div>
        </section>

        <section class="bg-gray-900 rounded-2xl p-6 shadow-2xl space-y-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold flex items-center space-x-2">
                        <i data-lucide="filter" class="text-red-500 w-6 h-6"></i>
                        <span>Quick Search</span>
                    </h2>
                    <p class="text-sm text-gray-500">Find clients by name or email</p>
                </div>
                <form method="GET" class="flex-1">
                    <div class="relative">
                        <input
                            type="text"
                            name="search"
                            value="<?= htmlspecialchars($search); ?>"
                            placeholder="Search by name, email, or city"
                            class="w-full bg-gray-950/70 border border-gray-800 rounded-xl pl-12 pr-4 py-3 text-gray-100 placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-red-500">
                        <i data-lucide="search" class="absolute left-4 top-3.5 text-gray-500 w-5 h-5"></i>
                    </div>
                </form>
            </div>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 bg-gray-900 rounded-2xl shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between border-b border-gray-800 px-6 py-4">
                    <div>
                        <h2 class="text-2xl font-semibold flex items-center space-x-2">
                            <i data-lucide="users-round" class="text-red-500 w-6 h-6"></i>
                            <span>Client Roster</span>
                        </h2>
                        <p class="text-sm text-gray-500"><?= $search === '' ? 'Most recent registrations' : 'Filtered results'; ?></p>
                    </div>
                </div>

                <?php if (!empty($clients)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-800">
                            <thead class="bg-gray-800/60">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Contact</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Address</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Registered</th>
                                </tr>
                            </thead>
                            <tbody class="bg-gray-900 divide-y divide-gray-800">
                                <?php foreach ($clients as $client): ?>
                                    <tr class="hover:bg-gray-800/50 transition">
                                        <td class="px-6 py-4">
                                            <p class="text-white font-semibold"><?= htmlspecialchars($client['clientName']); ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($client['email']); ?></p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-gray-200 font-medium flex items-center space-x-2">
                                                <i data-lucide="phone" class="w-4 h-4"></i>
                                                <span><?= htmlspecialchars($client['contactNumber']); ?></span>
                                            </p>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-300"><?= htmlspecialchars($client['address']); ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-400"><?= date('M d, Y', strtotime($client['registeredDate'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-16">
                        <i data-lucide="inbox" class="w-20 h-20 text-gray-700 mx-auto mb-4"></i>
                        <p class="text-gray-400 text-lg">No clients match your search.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bg-gray-900 rounded-2xl shadow-2xl p-6 space-y-4">
                <h2 class="text-2xl font-semibold flex items-center space-x-2">
                    <i data-lucide="clock-8" class="text-yellow-400 w-6 h-6"></i>
                    <span>Recent Signups</span>
                </h2>
                <div class="space-y-4 max-h-[420px] overflow-y-auto pr-2">
                    <?php if (!empty($recentClients)): ?>
                        <?php foreach ($recentClients as $recent): ?>
                            <div class="bg-gray-950/40 border border-gray-800 rounded-xl p-4 flex items-center justify-between">
                                <div>
                                    <p class="font-semibold text-white"><?= htmlspecialchars($recent['clientName']); ?></p>
                                    <p class="text-xs text-gray-500 flex items-center space-x-1">
                                        <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
                                        <span><?= htmlspecialchars($recent['address']); ?></span>
                                    </p>
                                </div>
                                <span class="text-xs text-gray-400"><?= date('M d', strtotime($recent['registeredDate'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm">No recent client activity.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
});
</script>
</body>
</html>
