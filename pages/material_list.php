<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';
require_once '../includes/header.php';

// Mengambil semua data bahan baku dengan pagination
$search_query = $_GET['search'] ?? '';
$perPage = 10; // Jumlah item per halaman
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bahan WHERE kd_bahan LIKE :search OR nama_bahan LIKE :search");
$stmt->bindValue(':search', '%' . $search_query . '%');
$stmt->execute();
$totalItems = $stmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

$stmt = $pdo->prepare("SELECT * FROM bahan WHERE kd_bahan LIKE :search OR nama_bahan LIKE :search ORDER BY nama_bahan LIMIT :limit OFFSET :offset");
$stmt->bindValue(':search', '%' . $search_query . '%');
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<head>
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>

<div class="flex min-h-screen bg-gray-100">
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
                            <p class="text-blue-100 mt-1">Total: <?php echo $totalItems; ?> bahan</p>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <div class="mb-6 relative">
                        <input type="text" id="searchInput" name="search" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10" placeholder="Cari kode atau nama bahan..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                            <i class="fas fa-search"></i>
                        </span>
                    </div>
                    <?php if (empty($materials)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-box-open text-gray-400 text-6xl mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-600 mb-2">Belum ada bahan baku</h3>
                            <p class="text-gray-500">Saat ini tidak ada data bahan baku yang tersedia.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full responsive-table border border-gray-300">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">No</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Kode Bahan</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Nama Bahan</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Stok</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Satuan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200" id="materialsTableBody">
                                    <?php 
                                    $i = 1;
                                    foreach ($materials as $material): ?>
                                        <tr class="hover:bg-gray-50 transition duration-200 material-row" data-name="<?php echo strtolower(htmlspecialchars($material['kd_bahan'] . ' ' . $material['nama_bahan'])); ?>">
                                            <td data-label="No." class="px-6 py-4 text-sm text-gray-900"><?php echo $i++; ?></td>
                                            <td data-label="Kode Bahan" class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($material['kd_bahan']); ?></td>
                                            <td data-label="Nama Bahan" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($material['nama_bahan']); ?></td>
                                            <td data-label="Stok" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($material['stok']); ?></td>
                                            <td data-label="Satuan" class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($material['satuan']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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
        const tableRows = document.querySelectorAll('#materialsTableBody .material-row');
        tableRows.forEach(row => {
            const name = row.dataset.name.toLowerCase();
            row.style.display = !searchTerm || name.includes(searchTerm) ? '' : 'none';
        });
    });
</script>
<?php require_once '../includes/footer.php'; ?>