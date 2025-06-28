<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$menuItems = [
    ['id' => 'dashboard', 'title' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'file' => 'dashboard.php', 'roles' => ['admin', 'pegawai', 'pemilik']],
    ['id' => 'userManagement', 'title' => 'Manajemen User', 'icon' => 'fas fa-users', 'file' => 'user_management.php', 'roles' => ['admin']],
    ['id' => 'materialManagement', 'title' => 'Manajemen Bahan', 'icon' => 'fas fa-cubes', 'file' => 'material_management.php', 'roles' => ['admin']],
    ['id' => 'productManagement', 'title' => 'Manajemen Barang', 'icon' => 'fas fa-boxes', 'file' => 'product_management.php', 'roles' => ['admin']],
    ['id' => 'supplierManagement', 'title' => 'Manajemen Supplier', 'icon' => 'fas fa-truck', 'file' => 'supplier_management.php', 'roles' => ['admin']],
    ['id' => 'productionManagement', 'title' => 'Manajemen Produksi', 'icon' => 'fas fa-industry', 'file' => 'production_management.php', 'roles' => ['admin']],
    ['id' => 'costManagement', 'title' => 'Manajemen Biaya', 'icon' => 'fas fa-receipt', 'file' => 'cost_management.php', 'roles' => ['admin', 'pegawai']],
    ['id' => 'purchaseManagement', 'title' => 'Manajemen Pembelian', 'icon' => 'fas fa-shopping-cart', 'file' => 'purchase_management.php', 'roles' => ['admin', 'pegawai']],
    ['id' => 'salesManagement', 'title' => 'Manajemen Penjualan', 'icon' => 'fas fa-cash-register', 'file' => 'sales_management.php', 'roles' => ['admin', 'pegawai']],
    ['id' => 'reports', 'title' => 'Laporan', 'icon' => 'fas fa-chart-bar', 'file' => 'reports.php', 'roles' => ['admin', 'pegawai', 'pemilik']],
];
?>
<nav class="bg-blue-800 text-white w-64 min-h-screen">
    <div class="p-4">
        <h2 class="text-lg font-semibold mb-4">Menu</h2>
        <ul class="space-y-2">
            <?php if (isset($_SESSION['user']['level'])): ?>
                <?php foreach ($menuItems as $item): ?>
                    <?php if (in_array($_SESSION['user']['level'], $item['roles'])): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($item['file']); ?>" class="navbar-item flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-blue-600 rounded-lg transition-colors <?php echo (basename($_SERVER['PHP_SELF']) === $item['file']) ? 'active' : ''; ?>">
                                <i class="<?php echo htmlspecialchars($item['icon']); ?> mr-3"></i>
                                <?php echo htmlspecialchars($item['title']); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</nav>