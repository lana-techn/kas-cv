<div class="flex h-full flex-col lg:w-64 p-4">
    <div class="flex h-16 shrink-0 items-center">
        <a href="dashboard.php" class="flex items-center gap-x-3">
            <img class="h-10 w-auto" src="../assets/images/logo.png" alt="Logo Perusahaan">
        </a>
    </div>

    <nav class="flex flex-1 flex-col mt-6">
        <ul role="list" class="flex flex-1 flex-col gap-y-7">
            <li>
                <?php
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }

                $currentUserRole = $_SESSION['user']['level'] ?? '';
                $currentPage = basename($_SERVER['PHP_SELF']);

                // Definisi navigasi dengan kelas ikon Font Awesome
                $navigation = [
                    ['name' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-solid fa-gauge-high', 'roles' => ['admin', 'pegawai', 'pemilik']],

                    // Menu Admin
                    ['name' => 'User', 'href' => 'user_management.php', 'icon' => 'fa-solid fa-users', 'roles' => ['admin']],
                    ['name' => 'Bahan', 'href' => 'material_management.php', 'icon' => 'fa-solid fa-layer-group', 'roles' => ['admin']],
                    ['name' => 'Barang', 'href' => 'product_management.php', 'icon' => 'fa-solid fa-box-archive', 'roles' => ['admin']],
                    ['name' => 'Supplier', 'href' => 'supplier_management.php', 'icon' => 'fa-solid fa-truck-fast', 'roles' => ['admin']],
                    ['name' => 'Produksi', 'href' => 'production_management.php', 'icon' => 'fa-solid fa-industry', 'roles' => ['admin']],

                    // Menu Pegawai
                    ['name' => 'Penjualan', 'href' => 'sales_management.php', 'icon' => 'fa-solid fa-cart-shopping', 'roles' => ['pegawai']],
                    ['name' => 'Pembelian', 'href' => 'purchase_management.php', 'icon' => 'fa-solid fa-dolly', 'roles' => ['pegawai']],
                    ['name' => 'Biaya', 'href' => 'cost_management.php', 'icon' => 'fa-solid fa-file-invoice-dollar', 'roles' => ['pegawai']],
                    ['name' => 'Daftar Bahan', 'href' => 'material_list.php', 'icon' => 'fa-solid fa-list-ul', 'roles' => ['pegawai']],
                    ['name' => 'Daftar Barang', 'href' => 'product_list.php', 'icon' => 'fa-solid fa-list-check', 'roles' => ['pegawai']],
                    ['name' => 'Daftar Supplier', 'href' => 'supplier_list.php', 'icon' => 'fa-solid fa-address-book', 'roles' => ['pegawai']],

                    // Menu Pemilik
                    ['name' => 'Laporan', 'href' => 'reports.php', 'icon' => 'fa-solid fa-chart-pie', 'roles' => ['pemilik']],
                ];
                ?>

                <ul role="list" class="space-y-2">
                    <?php foreach ($navigation as $item): ?>
                        <?php if (in_array($currentUserRole, $item['roles'])): ?>
                            <?php $isCurrent = ($currentPage === basename($item['href'])); ?>
                            <li>
                                <a href="<?php echo htmlspecialchars($item['href']); ?>"
                                    class="<?php echo $isCurrent
                                                ? 'bg-gradient-to-r from-blue-500 to-cyan-400 text-white shadow-lg'
                                                : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100';
                                            ?> group flex items-center gap-x-3 rounded-md py-2 px-3 text-sm leading-6 font-semibold transition-all duration-200">

                                    <span class="flex items-center justify-center h-6 w-6">
                                        <i class="<?php echo htmlspecialchars($item['icon']); ?> <?php echo $isCurrent ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?> text-lg transition-all duration-200"></i>
                                    </span>

                                    <span><?php echo htmlspecialchars($item['name']); ?></span>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </li>
        </ul>
    </nav>
</div>

<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>