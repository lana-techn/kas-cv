<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUserRole = isset($_SESSION['user']['level']) ? $_SESSION['user']['level'] : '';
$currentPage = basename($_SERVER['PHP_SELF']);

$navigation = [
    ['name' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z', 'roles' => ['admin', 'pegawai', 'pemilik']],
    ['name' => 'User', 'href' => 'user_management.php', 'icon' => 'M15 19.128a9.38 9.38 0 002.625.372A9.337 9.337 0 0021 12c0-2.828-1.22-5.34-3.1-7.128S15.282 2 12.5 2a9.338 9.338 0 00-2.625.372A9.337 9.337 0 006 12c0 2.828 1.22 5.34 3.1 7.128S11.718 22 14.5 22a9.338 9.338 0 002.625-.372z', 'roles' => ['admin']],
    ['name' => 'Bahan', 'href' => 'material_management.php', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10', 'roles' => ['admin']],
    ['name' => 'Bahan', 'href' => 'material_list.php', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10', 'roles' => ['pegawai']],
    ['name' => 'Barang', 'href' => 'product_management.php', 'icon' => 'M5.25 7.5A2.25 2.25 0 017.5 5.25h9a2.25 2.25 0 012.25 2.25v9a2.25 2.25 0 01-2.25 2.25h-9a2.25 2.25 0 01-2.25-2.25v-9z', 'roles' => ['admin']],
    ['name' => 'Barang', 'href' => 'product_list.php', 'icon' => 'M5.25 7.5A2.25 2.25 0 017.5 5.25h9a2.25 2.25 0 012.25 2.25v9a2.25 2.25 0 01-2.25-2.25h-9a2.25 2.25 0 01-2.25-2.25v-9z', 'roles' => ['pegawai']],
    ['name' => 'Supplier', 'href' => 'supplier_management.php', 'icon' => 'M9 17.25v1.5a3 3 0 003 3h3a3 3 0 003-3v-1.5M10.5 4.5h3m-3 0V3a.75.75 0 01.75-.75h1.5a.75.75 0 01.75.75V4.5m-3 0h3m-3 3.75h3m-3 3.75h3m-3 3.75h3m-3 3.75h3M4.5 3.75h15a2.25 2.25 0 012.25 2.25v13.5a2.25 2.25 0 01-2.25-2.25h-15a2.25 2.25 0 01-2.25-2.25V6a2.25 2.25 0 012.25-2.25z', 'roles' => ['admin']],
    ['name' => 'Supplier', 'href' => 'supplier_list.php', 'icon' => 'M9 17.25v1.5a3 3 0 003 3h3a3 3 0 003-3v-1.5M10.5 4.5h3m-3 0V3a.75.75 0 01.75-.75h1.5a.75.75 0 01.75.75V4.5m-3 0h3m-3 3.75h3m-3 3.75h3m-3 3.75h3m-3 3.75h3M4.5 3.75h15a2.25 2.25 0 012.25 2.25v13.5a2.25 2.25 0 01-2.25-2.25h-15a2.25 2.25 0 01-2.25-2.25V6a2.25 2.25 0 012.25-2.25z', 'roles' => ['pegawai']],
    ['name' => 'Produksi', 'href' => 'production_management.php', 'icon' => 'M3.75 21h16.5M4.5 3.75h15M5.25 3.75v17.25M18.75 3.75v17.25M9 13.5h6M9 17.25h6', 'roles' => ['admin']],
    ['name' => 'Biaya', 'href' => 'cost_management.php', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'roles' => ['pegawai']],
    ['name' => 'Pembelian', 'href' => 'purchase_management.php', 'icon' => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c.51 0 .962-.343 1.087-.835l.383-1.437M7.5 14.25L5.106 5.106A2.25 2.25 0 002.869 3H2.25', 'roles' => ['pegawai']],
    ['name' => 'Penjualan', 'href' => 'sales_management.php', 'icon' => 'M15.75 15.75V18m-4.5 .75h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008v-.008zm-2.25-4.5h.008v.008h-.008v-.008zM9 15.75h.008v.008H9v-.008zm-2.25 2.25h.008v.008h-.008v-.008zM9 18h.008v.008H9v-.008zm-2.25 2.25h.008v.008h-.008v-.008zm-2.25-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008v-.008zM4.5 18h.008v.008h-.008v-.008zm2.25 2.25h.008v.008h-.008v-.008zM18 15.75h.008v.008h-.008v-.008zm2.25 2.25h.008v.008h-.008v-.008zM18 18h.008v.008h-.008v-.008zm2.25 2.25h.008v.008h-.008v-.008zM4.5 6.75h15v6h-15v-6z', 'roles' => ['pegawai']],
    ['name' => 'Laporan', 'href' => 'reports.php', 'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'roles' => ['pemilik']],
];
?>

<ul role="list" class="-mx-2 space-y-1">
    <?php foreach ($navigation as $item): ?>
        <?php // Check if the current user's role is in the item's roles array
        if (in_array($currentUserRole, $item['roles'])): ?>
            <?php $isCurrent = ($currentPage === basename($item['href'])); ?>
            <li>
                <a href="<?php echo htmlspecialchars($item['href']); ?>" 
                   class="<?php echo $isCurrent ? 'bg-gray-100 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50'; ?> group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                    <svg class="<?php echo $isCurrent ? 'text-primary-600' : 'text-gray-400 group-hover:text-primary-600'; ?> h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="<?php echo $item['icon']; ?>" />
                    </svg>
                    <?php echo htmlspecialchars($item['name']); ?>
                </a>
            </li>
        <?php endif; ?>
    <?php endforeach; ?>
</ul>
