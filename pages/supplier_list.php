<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';
require_once '../includes/header.php';

// Mengambil semua data supplier
$stmt = $pdo->query("SELECT * FROM supplier ORDER BY nama_supplier");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="flex min-h-screen bg-gray-100">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <div id="supplierManagement" class="section active">
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-800">Daftar Supplier</h2>
                <p class="text-gray-600 mt-2">Menampilkan data semua supplier</p>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-xl font-semibold text-white">Daftar Supplier</h3>
                            <p class="text-blue-100 mt-1">Total: <?php echo count($suppliers); ?> supplier</p>
                        </div>
                    </div>
                </div>
                
                <div class="p-6">
                    <?php if (empty($suppliers)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-truck text-gray-400 text-6xl mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-600 mb-2">Belum ada supplier</h3>
                            <p class="text-gray-500">Saat ini tidak ada data supplier yang tersedia.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">ID Supplier</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Nama Supplier</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Alamat</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">No. Telpon</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <tr class="hover:bg-gray-50 transition duration-200">
                                            <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($supplier['id_supplier']); ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($supplier['nama_supplier']); ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-600 max-w-xs truncate"><?php echo htmlspecialchars($supplier['alamat']); ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($supplier['no_telpon']); ?></td>
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