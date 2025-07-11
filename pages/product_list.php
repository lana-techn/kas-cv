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
$perPage = 10; // Jumlah item per halaman
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM barang WHERE kd_barang LIKE :search OR nama_barang LIKE :search");
$stmt->bindValue(':search', '%' . $search_query . '%');
$stmt->execute();
$totalItems = $stmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

$stmt = $pdo->prepare("SELECT * FROM barang WHERE kd_barang LIKE :search OR nama_barang LIKE :search ORDER BY nama_barang LIMIT :limit OFFSET :offset");
$stmt->bindValue(':search', '%' . $search_query . '%');
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
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
            <div class="bg-white rounded-xl shadow-lg overflow-hidden w-full">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-xl font-semibold text-white">Informasi Barang</h3>
                            <p class="text-blue-100 mt-1">Total: <?php echo $totalItems; ?> barang</p>
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
                        <table class="min-w-full table-auto border-collapse border border-gray-300">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">No</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Kode Barang</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Nama Barang</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Stok</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200" id="productsTableBody">
                                <?php 
                                $i = 1;
                                foreach ($products as $product): ?>
                                    <tr class="product-row border-b border-gray-200" data-name="<?php echo strtolower(htmlspecialchars($product['kd_barang'] . ' ' . $product['nama_barang'])); ?>">
                                        <td data-label="No." class="px-6 py-4 text-sm text-gray-900"><?php echo $i++; ?></td>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['kd_barang']); ?></td>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['nama_barang']); ?></td>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['stok']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div class="flex justify-end mt-2 p-2">
                        <div class="flex items-center space-x-2 text-sm">
                            <a href="?search=<?php echo urlencode($search_query); ?>&page=<?php echo max(1, $page - 1); ?>" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300 <?php echo $page <= 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>">Prev</a>
                            <span class="px-2"><?php echo $page; ?> / <?php echo $totalPages; ?></span>
                            <a href="?search=<?php echo urlencode($search_query); ?>&page=<?php echo min($totalPages, $page + 1); ?>" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300 <?php echo $page >= $totalPages ? 'opacity-50 cursor-not-allowed' : ''; ?>">Next</a>
                        </div>
                    </div>
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
            row.style.display = !searchTerm || name.includes(searchTerm) ? '' : 'none';
        });
    });
</script>
<?php require_once '../includes/footer.php'; ?>