<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';
require_once '../includes/header.php';

// Mengambil semua data produk
$stmt = $pdo->query("SELECT * FROM barang ORDER BY nama_barang");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Tambahkan link ke file CSS responsif di dalam <head> -->
<head>
    <!-- ... tag head Anda yang lain ... -->
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>

<!-- Tambahkan kelas 'flex-container' untuk layout utama -->
<div class="flex-container min-h-screen bg-gray-100">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <div id="productManagement" class="section active">
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-800">Daftar Produk</h2>
                <p class="text-gray-600 mt-2">Menampilkan semua produk jadi yang tersedia</p>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                    <!-- Tambahkan kelas 'card-header' untuk konsistensi -->
                    <div class="flex justify-between items-center card-header">
                        <div>
                            <h3 class="text-xl font-semibold text-white">Daftar Produk</h3>
                            <p class="text-blue-100 mt-1">Total: <?php echo count($products); ?> produk</p>
                        </div>
                    </div>
                </div>
                
                <div class="p-6">
                    <?php if (empty($products)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-cookie-bite text-gray-400 text-6xl mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-600 mb-2">Belum ada produk</h3>
                            <p class="text-gray-500">Saat ini tidak ada data produk yang tersedia.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <!-- Tambahkan kelas 'responsive-table' ke tabel -->
                            <table class="min-w-full responsive-table">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Kode Barang</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Nama Barang</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Stok</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($products as $product): ?>
                                        <tr class="hover:bg-gray-50 transition duration-200">
                                            <!-- Tambahkan atribut data-label untuk setiap sel -->
                                            <td data-label="Kode" class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['kd_barang']); ?></td>
                                            <td data-label="Nama" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($product['nama_barang']); ?></td>
                                            <td data-label="Stok" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($product['stok']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once '../includes/footer.php'; ?>
