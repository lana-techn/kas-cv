<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';
require_once '../includes/header.php';
if ($_SESSION['user']['level'] !== 'pegawai') {
    header('Location: dashboard.php');
    exit;
}
$message = '';
$error = '';
$search_query = $_GET['search'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM barang WHERE kd_barang LIKE :search OR nama_barang LIKE :search ORDER BY nama_barang");
$stmt->bindValue(':search', '%' . $search_query . '%');
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="flex min-h-screen">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <!-- Notifications -->
        <?php if ($message): ?>
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg notification">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg notification">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            </div>
        <?php endif; ?>
        <div id="productManagement" class="section active">
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-800">Daftar Barang</h2>
                <p class="text-gray-600 mt-2">Menampilkan informasi barang yang tersedia</p>
            </div>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-xl font-semibold text-white">Informasi Barang</h3>
                            <p class="text-blue-100 mt-1">Total: <?php echo count($products); ?> barang</p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="mb-6 relative">
                        <input type="text" id="searchInput" name="search" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10" placeholder="Cari kode atau nama barang..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                            <i class="fas fa-search"></i>
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-auto">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Barang</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Barang</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="productsTableBody">
                                <?php foreach ($products as $product): ?>
                                    <tr class="product-row" data-name="<?php echo strtolower(htmlspecialchars($product['kd_barang'] . ' ' . $product['nama_barang'])); ?>">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($product['kd_barang']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($product['nama_barang']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($product['stok']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
    </main>
</div>
<script>
    document.getElementById('searchInput').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const tableRows = document.querySelectorAll('#productsTableBody .product-row');
        
        tableRows.forEach(row => {
            const name = row.dataset.name.toLowerCase();
            row.style.display = name.includes(searchTerm) ? '' : 'none';
        });
    });
</script>
<?php require_once '../includes/footer.php'; ?>