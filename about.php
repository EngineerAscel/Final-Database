<?php
session_start();

// Protect page
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require 'db.php'; // Include your database connection
// Fetch some dynamic stats for the about page (e.g., user count, product count)

$userCount = $conn->query("SELECT COUNT(*) as count FROM usermanagement")->fetch_assoc()['count'];
$productCount = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Inventory System</title>
    <link rel="stylesheet" href="css/about.css">
    <script src="https://cdn.tailwindcss.com" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        :root { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="app-bg text-gray-100 min-h-screen">
<div class="flex w-full min-h-screen">
    <?php
    if (!defined('ADMIN_SIDEBAR_VARIANT')) {
        define('ADMIN_SIDEBAR_VARIANT', 'tailwind');
    }
    include 'admin-sidebar.php';
    ?>

    <main class="flex-1 md:ml-64 p-6 md:p-10 w-full">
        <div class="space-y-8 max-w-5xl mx-auto w-full">
            <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-gray-800 pb-4">
                <div class="flex items-center space-x-3">
                    <i data-lucide="info" class="text-red-500 w-10 h-10"></i>
                    <div>
                        <p class="text-sm uppercase tracking-[0.3em] text-gray-500">System Overview</p>
                        <h1 class="text-4xl font-bold text-white">About Inventory System</h1>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <div class="bg-gray-900 px-4 py-2 rounded-xl border border-gray-800 text-sm text-gray-400">
                        Users <span class="text-white font-semibold ml-2"><?= $userCount ?></span>
                    </div>
                    <div class="bg-gray-900 px-4 py-2 rounded-xl border border-gray-800 text-sm text-gray-400">
                        Products <span class="text-white font-semibold ml-2"><?= $productCount ?></span>
                    </div>
                </div>
            </header>

            <section class="bg-gray-900 p-6 rounded-2xl shadow-2xl space-y-8 border border-gray-800">
                <div class="space-y-3">
                    <h2 class="text-2xl font-semibold text-white flex items-center gap-2">
                        <i data-lucide="book-open" class="text-red-500"></i>
                        Introduction
                    </h2>
                    <p class="text-gray-300">
                        Welcome to the 1Garage Inventory Management System, a comprehensive tool designed to streamline inventory operations for automotive businesses. Track products, manage suppliers, handle clients, and monitor sales from a single hub.
                    </p>
                </div>

                <div class="space-y-3">
                    <h2 class="text-2xl font-semibold text-white flex items-center gap-2">
                        <i data-lucide="check-circle" class="text-red-500"></i>
                        Key Features
                    </h2>
                    <ul class="grid md:grid-cols-2 gap-3 text-gray-300">
                        <li class="bg-gray-800/60 border border-gray-700 rounded-2xl p-4">
                            🧾 <span class="text-white font-semibold">User Management</span> with secure role-based access for admins, sales, and accountants.
                        </li>
                        <li class="bg-gray-800/60 border border-gray-700 rounded-2xl p-4">
                            📦 <span class="text-white font-semibold">Product Tracking</span> to monitor stock, control updates, and trigger low-inventory alerts.
                        </li>
                        <li class="bg-gray-800/60 border border-gray-700 rounded-2xl p-4">
                            🏭 <span class="text-white font-semibold">Supplier Management</span> keeping vendor relationships linked to ordering workflows.
                        </li>
                        <li class="bg-gray-800/60 border border-gray-700 rounded-2xl p-4">
                            👥 <span class="text-white font-semibold">Client Management</span> storing rider profiles and connecting them to bikes and sales.
                        </li>
                        <li class="bg-gray-800/60 border border-gray-700 rounded-2xl p-4">
                            💰 <span class="text-white font-semibold">Sales Monitoring</span> that summarizes receipts, totals, and performance.
                        </li>
                        <li class="bg-gray-800/60 border border-gray-700 rounded-2xl p-4">
                            🔒 <span class="text-white font-semibold">Security Stack</span> with password resets, session protection, and audit-ready logs.
                        </li>
                    </ul>
                </div>

                <div class="space-y-3">
                    <h2 class="text-2xl font-semibold text-white flex items-center gap-2">
                        <i data-lucide="trending-up" class="text-red-500"></i>
                        Benefits
                    </h2>
                    <p class="text-gray-300">
                        Reduce manual errors, unlock real-time insights, and automate routine inventory tasks. Tailored alerts, consolidated reports, and guard-railed permissions keep the business scalable and secure.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-800/60 border border-gray-700 rounded-2xl p-4">
                        <span class="text-sm text-gray-400 uppercase tracking-widest">Current Users</span>
                        <p class="text-3xl font-bold text-white mt-2"><?= $userCount ?></p>
                    </div>
                    <div class="bg-gray-800/60 border border-gray-700 rounded-2xl p-4">
                        <span class="text-sm text-gray-400 uppercase tracking-widest">Total Products</span>
                        <p class="text-3xl font-bold text-white mt-2"><?= $productCount ?></p>
                    </div>
                </div>

                <div class="bg-gray-800/60 border border-gray-700 rounded-2xl p-6">
                    <h2 class="text-2xl font-semibold text-white flex items-center gap-2">
                        <i data-lucide="messages-square" class="text-red-500"></i>
                        Contact Us
                    </h2>
                    <p class="text-gray-300 mt-2">
                        Need assistance or want to request new capabilities? Email
                        <span class="text-white font-semibold">support@1garage.com</span> or visit our website for more details.
                    </p>
                </div>
            </section>
        </div>
    </main>
</div>

<script>
    if (window.lucide) {
        window.lucide.createIcons();
    }
</script>
</body>
</html>