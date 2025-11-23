<?php
$currentPage = basename($_SERVER['PHP_SELF']);

$navItems = [
    ['href' => 'sales-dashboard.php', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
    ['href' => 'sales-products.php', 'label' => 'Products', 'icon' => 'package'],
    ['href' => 'sales-supplier.php', 'label' => 'Suppliers', 'icon' => 'truck'],
    ['href' => 'sales-clients.php', 'label' => 'Clients', 'icon' => 'users'],
    ['href' => 'sales-approval.php', 'label' => 'Approvals', 'icon' => 'check-circle'],
    ['href' => 'sales-add-requests.php', 'label' => 'Requests', 'icon' => 'file-plus-2'],
];

$salesDropdown = [
    ['href' => 'sales-salesrecords.php', 'label' => 'Sales Records'],
    ['href' => 'sales-orders.php', 'label' => 'Sales / Orders'],
];
$isDropdownActive = in_array($currentPage, array_column($salesDropdown, 'href'), true);

if (!defined('SALES_SIDEBAR_STYLES')) {
    echo '<style>
        @import url("https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap");
        :root { font-family: "Inter", sans-serif; }
        body { font-family: "Inter", sans-serif; }
        .app-bg { background-color: #121212; }
        .sidebar-bg { background-color: #0d0d0d; }
    </style>';
    define('SALES_SIDEBAR_STYLES', true);
}
?>

<nav class="sidebar-bg fixed h-full w-64 p-6 flex flex-col justify-between z-20 shadow-xl hidden md:flex">
    <div>
        <div class="text-3xl font-extrabold text-red-500 mb-10 tracking-widest border-b border-gray-800 pb-4 flex items-center justify-center space-x-2">
            <span class="text-center">1 GARAGE</span>
        </div>

        <ul class="space-y-3">
            <?php foreach ($navItems as $item):
                $isActive = $currentPage === $item['href'];
                $liClasses = $isActive
                    ? 'p-3 bg-red-600/30 text-white rounded-xl font-semibold border-l-4 border-red-500'
                    : 'p-3 text-gray-400 hover:text-red-400 hover:bg-gray-800 rounded-xl transition duration-200';
            ?>
            <li class="<?= $liClasses ?>">
                <a href="<?= $item['href'] ?>" class="flex items-center space-x-3">
                    <i data-lucide="<?= $item['icon'] ?>"></i>
                    <span><?= $item['label'] ?></span>
                </a>
            </li>
            <?php endforeach; ?>

            <li>
                <button
                    class="w-full flex items-center justify-between p-3 text-gray-400 hover:text-red-400 hover:bg-gray-800 rounded-xl transition duration-200"
                    type="button"
                    data-sales-toggle>
                    <span class="flex items-center space-x-3">
                        <i data-lucide="shopping-bag"></i>
                        <span>Sales</span>
                    </span>
                    <span class="text-xs">▼</span>
                </button>
                <ul class="pl-8 pt-2 space-y-1 text-sm <?= $isDropdownActive ? '' : 'hidden'; ?>" data-sales-menu>
                    <?php foreach ($salesDropdown as $item): ?>
                        <li>
                            <a href="<?= $item['href'] ?>"
                               class="block rounded-md px-3 py-1 text-gray-400 hover:text-red-300 hover:bg-gray-800 transition duration-150 <?= $currentPage === $item['href'] ? 'text-red-300 font-semibold bg-gray-800/60' : '' ?>">
                                <?= $item['label'] ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </li>
        </ul>
    </div>

    <form action="logout.php" method="post">
        <button class="w-full py-3 bg-red-700 hover:bg-red-800 text-white font-semibold rounded-xl transition duration-200 shadow-lg hover:shadow-red-900/50">
            Log Out
        </button>
    </form>
</nav>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-sales-toggle]');
    const menu = document.querySelector('[data-sales-menu]');
    if (toggle && menu) {
        toggle.addEventListener('click', () => menu.classList.toggle('hidden'));
    }

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
});
</script>

