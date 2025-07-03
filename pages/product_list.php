<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';
require_once '../includes/header.php';

// Mengambil semua data  dari barang tabel 'barang'
$stmt = $pdo->query("SELECT * FROM barang ORDER BY nama_barang");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="flex min-h-screen bg-gray-100">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <div id="productManagement" class="section active">
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-800">Daftar Barang</h2>
                <p class="text-gray-600 mt-2">Menampilkan semua barang jadi yang tersedia</p>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-xl font-semibold text-white">Daftar barang</h3>
                            <p class="text-blue-100 mt-1">Total: <?php echo count($products); ?> barang</p>
                        </div>
                    </div>
                </div>
                
                <div class="p-6">
                    <?php if (empty($products)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-cookie-bite text-gray-400 text-6xl mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-600 mb-2">Belum ada barang</h3>
                            <p class="text-gray-500">Saat ini tidak ada data barang yang tersedia.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
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
                                            <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['kd_barang']); ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($product['nama_barang']); ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($product['stok']); ?></td>
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