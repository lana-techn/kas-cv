<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$menuItems = [
    ['id' => 'dashboard', 'title' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'file' => 'dashboard.php', 'roles' => ['admin', 'pegawai', 'pemilik']],
    ['id' => 'userManagement', 'title' => 'User', 'icon' => 'fas fa-users', 'file' => 'user_management.php', 'roles' => ['admin']],
    ['id' => 'materialManagement', 'title' => 'Bahan', 'icon' => 'fas fa-cubes', 'file' => 'material_management.php', 'roles' => ['admin']],
    ['id' => 'listMaterial', 'title' => 'Bahan', 'icon' => 'fas fa-cubes', 'file' => 'material_list.php', 'roles' => ['pegawai']],
    ['id' => 'productManagement', 'title' => 'Barang', 'icon' => 'fas fa-boxes', 'file' => 'product_management.php', 'roles' => ['admin']],
    ['id' => 'listProduct', 'title' => 'Barang', 'icon' => 'fas fa-boxes', 'file' => 'product_list.php', 'roles' => ['pegawai']],
    ['id' => 'supplierManagement', 'title' => 'Supplier', 'icon' => 'fas fa-truck', 'file' => 'supplier_management.php', 'roles' => ['admin']],
    ['id' => 'listSupplier', 'title' => 'Supplier', 'icon' => 'fas fa-truck', 'file' => 'supplier_list.php', 'roles' => ['pegawai']],
    ['id' => 'productionManagement', 'title' => 'Produksi', 'icon' => 'fas fa-industry', 'file' => 'production_management.php', 'roles' => ['admin']],
    ['id' => 'costManagement', 'title' => 'Biaya', 'icon' => 'fas fa-receipt', 'file' => 'cost_management.php', 'roles' => [ 'pegawai']],
    ['id' => 'purchaseManagement', 'title' => 'Pembelian', 'icon' => 'fas fa-shopping-cart', 'file' => 'purchase_management.php', 'roles' => [ 'pegawai']],
    ['id' => 'salesManagement', 'title' => 'Penjualan', 'icon' => 'fas fa-cash-register', 'file' => 'sales_management.php', 'roles' => [ 'pegawai']],
    ['id' => 'reports', 'title' => 'Laporan', 'icon' => 'fas fa-chart-bar', 'file' => 'reports.php', 'roles' => ['pemilik']],
];
?>

<!-- Sidebar -->
<aside id="sidebar" class="bg-blue-800 text-white w-64 p-4 space-y-6 flex-shrink-0
                           fixed inset-y-0 left-0 transform -translate-x-full 
                           lg:relative lg:translate-x-0 
                           transition-transform duration-300 ease-in-out z-50
                           overflow-y-auto">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold">Menu</h2>
        <!-- Tombol close untuk mobile -->
        <button id="close-sidebar-button" class="lg:hidden text-white hover:text-gray-300">
            <i class="fas fa-times fa-lg"></i>
        </button>
    </div>
    <nav>
        <ul class="space-y-2">
            <?php if (isset($_SESSION['user']['level'])): ?>
                <?php foreach ($menuItems as $item): ?>
                    <?php if (in_array($_SESSION['user']['level'], $item['roles'])): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($item['file']); ?>" class="navbar-item flex items-center px-4 py-3 text-blue-200 hover:bg-blue-700 hover:text-white rounded-lg transition-colors duration-200 <?php echo (basename($_SERVER['PHP_SELF']) === $item['file']) ? 'active' : ''; ?>">
                                <i class="<?php echo htmlspecialchars($item['icon']); ?> w-6 text-center mr-3"></i>
                                <span><?php echo htmlspecialchars($item['title']); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </nav>
</aside>

<!-- Overlay untuk mobile saat sidebar terbuka -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden"></div>
