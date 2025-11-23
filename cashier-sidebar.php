<?php
$currentPage = basename($_SERVER['PHP_SELF']);

$navItems = [
    ['href' => 'cashier-dashboard.php', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
    ['href' => 'cashier-items.php', 'label' => 'Products', 'icon' => 'package'],
    ['href' => 'cashier-payments.php', 'label' => 'Sales Records', 'icon' => 'credit-card'],
];

if (!defined('CASHIER_SIDEBAR_STYLES')) {
    echo '<style>
        @import url("https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap");
        :root { font-family: "Inter", sans-serif; }
        body { font-family: "Inter", sans-serif; }
        .app-bg { background-color: #121212; }
        .sidebar-bg { background-color: #0d0d0d; }
    </style>';
    define('CASHIER_SIDEBAR_STYLES', true);
}
?>

<nav class="sidebar-bg fixed h-full w-64 p-6 flex flex-col justify-between z-20 shadow-xl hidden md:flex">
    <div>
        <div class="text-3xl font-extrabold text-red-500 mb-10 tracking-widest border-b border-gray-800 pb-4 flex items-center justify-center space-x-2">
            <span class="text-center">1 GARAGE</span>
            <i data-lucide="gear" class="w-6 h-6"></i>
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
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
});
</script>