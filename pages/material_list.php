<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';
require_once '../includes/header.php';

// Mengambil semua data bahan baku
$stmt = $pdo->query("SELECT * FROM bahan ORDER BY nama_bahan");
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        <div id="materialManagement" class="section active">
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-800">Daftar Bahan Baku</h2>
                <p class="text-gray-600 mt-2">Menampilkan semua bahan baku yang tersedia</p>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-xl font-semibold text-white">Informasi Bahan Baku</h3>
                            <p class="text-blue-100 mt-1">Total: <?php echo count($materials); ?> bahan</p>
                        </div>
                    </div>
                </div>
                
                <div class="p-6">
                    <?php if (empty($materials)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-box-open text-gray-400 text-6xl mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-600 mb-2">Belum ada bahan baku</h3>
                            <p class="text-gray-500">Saat ini tidak ada data bahan baku yang tersedia.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <!-- Tambahkan kelas 'responsive-table' ke tabel -->
                            <table class="min-w-full responsive-table">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Kode Bahan</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Nama Bahan</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Stok</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Satuan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($materials as $material): ?>
                                        <tr class="hover:bg-gray-50 transition duration-200">
                                            <!-- Tambahkan atribut data-label untuk setiap sel -->
                                            <td data-label="Kode" class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($material['kd_bahan']); ?></td>
                                            <td data-label="Nama" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($material['nama_bahan']); ?></td>
                                            <td data-label="Stok" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($material['stok']); ?></td>
                                            <td data-label="Satuan" class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($material['satuan']); ?></td>
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
