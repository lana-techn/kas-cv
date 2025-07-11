<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';
require_once '../includes/header.php';

if ($_SESSION['user']['level'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';
$search_query = $_GET['search'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add') {
            if (empty($_POST['nama_barang'])) {
                throw new Exception('Nama barang tidak boleh kosong.');
            }
            $kd_barang = generateId('BRG');
            $stmt = $pdo->prepare("INSERT INTO barang (kd_barang, nama_barang, stok) VALUES (?, ?, 0)");
            $stmt->execute([$kd_barang, $_POST['nama_barang']]);
            $message = 'Barang baru berhasil ditambahkan.';
        } elseif ($_POST['action'] === 'edit') {
            if (empty($_POST['nama_barang']) || empty($_POST['kd_barang'])) {
                throw new Exception('Data tidak lengkap.');
            }
            $stmt = $pdo->prepare("UPDATE barang SET nama_barang = ? WHERE kd_barang = ?");
            $stmt->execute([$_POST['nama_barang'], $_POST['kd_barang']]);
            $message = 'Data barang berhasil diperbarui.';
        } elseif ($_POST['action'] === 'delete') {
            $kd_barang = $_POST['kd_barang'];
            $stmt_check_prod = $pdo->prepare("SELECT COUNT(*) FROM produksi WHERE kd_barang = ?");
            $stmt_check_prod->execute([$kd_barang]);
            $in_production = $stmt_check_prod->fetchColumn();
            $stmt_check_sale = $pdo->prepare("SELECT COUNT(*) FROM detail_penjualan WHERE kd_barang = ?");
            $stmt_check_sale->execute([$kd_barang]);
            $in_sales = $stmt_check_sale->fetchColumn();
            if ($in_production > 0 || $in_sales > 0) {
                throw new Exception('Tidak dapat menghapus barang karena sudah digunakan dalam data produksi atau penjualan.');
            }
            $stmt = $pdo->prepare("DELETE FROM barang WHERE kd_barang = ?");
            $stmt->execute([$kd_barang]);
            $message = 'Barang berhasil dihapus.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Pagination logic
$perPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM barang WHERE nama_barang LIKE :search");
$stmt->bindValue(':search', '%' . $search_query . '%');
$stmt->execute();
$totalItems = $stmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

$sql = "SELECT kd_barang, nama_barang, stok FROM barang WHERE nama_barang LIKE :search ORDER BY nama_barang ASC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':search', '%' . $search_query . '%');
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<head>
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>

<div class="flex min-h-screen bg-gray-100">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <?php if ($message): ?>
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg notification">
                <div class="flex items-center"><i class="fas fa-check-circle mr-2"></i><span><?php echo htmlspecialchars($message); ?></span></div>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg notification">
                <div class="flex items-center"><i class="fas fa-exclamation-circle mr-2"></i><span><?php echo htmlspecialchars($error); ?></span></div>
            </div>
        <?php endif; ?>

        <div id="productManagement" class="section active">
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-800">Manajemen Barang</h2>
                <p class="text-gray-600 mt-2">Kelola data barang jadi (produk)</p>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                    <div class="flex justify-between items-center card-header">
                        <div>
                            <h3 class="text-xl font-semibold text-white">Daftar Barang</h3>
                            <p class="text-purple-100 mt-1">Total: <?php echo $totalItems; ?> jenis barang</p>
                        </div>
                        <button onclick="showAddProductForm()" class="add-button bg-white text-purple-600 hover:bg-purple-50 px-6 py-2 rounded-lg font-medium transition duration-200 flex items-center">
                            <i class="fas fa-plus mr-2"></i>Tambah Barang
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    <div class="mb-6 relative">
                        <input type="text" id="searchInput" name="search" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 pr-10" placeholder="Cari nama barang..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                            <i class="fas fa-search"></i>
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full responsive-table border border-gray-200" id="productsTable">
                            <thead>
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Kode Barang</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Nama Barang</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Stok Saat Ini</th>
                                    <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="productsTableBody" class="bg-white divide-y divide-gray-200">
                                <?php if (empty($products)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                            <div class="flex flex-col items-center">
                                                <i class="fas fa-box-open fa-3x text-gray-300"></i>
                                                <p class="mt-4">Belum ada data barang.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($products as $product): ?>
                                        <tr class="product-row" data-name="<?php echo strtolower(htmlspecialchars($product['nama_barang'])); ?>">
                                            <td data-label="Kode" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($product['kd_barang']); ?></td>
                                            <td data-label="Nama" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($product['nama_barang']); ?></td>
                                            <td data-label="Stok" class="px-6 py-4 text-sm font-bold text-gray-900"><?php echo htmlspecialchars($product['stok']); ?></td>
                                            <td class="px-6 py-4 text-center actions-cell">
                                                <div class="flex justify-center items-center space-x-2">
                                                    <button onclick='showEditProductForm(<?php echo json_encode($product, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="text-blue-600 hover:text-blue-800 hover:bg-blue-100 p-2 rounded-lg transition duration-200" title="Edit">
                                                        <i class="fas fa-pencil-alt"></i>
                                                    </button>
                                                    <button onclick="deleteProduct('<?php echo $product['kd_barang']; ?>')" class="text-red-600 hover:text-red-800 hover:bg-red-100 p-2 rounded-lg transition duration-200" title="Hapus">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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

        <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-auto transform transition-all">
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-6 rounded-t-xl">
                    <div class="flex justify-between items-center">
                        <h3 id="modalTitle" class="text-xl font-semibold text-white"></h3>
                        <button onclick="closeModal()" class="text-white hover:text-gray-200 transition duration-200">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <div id="modalContent" class="p-8"></div>
            </div>
        </div>
    </main>
</div>

<script>
function showModal(title, content) {
    document.getElementById('modalTitle').innerHTML = title;
    document.getElementById('modalContent').innerHTML = content;
    document.getElementById('modal').classList.remove('hidden');
    document.getElementById('modal').classList.add('flex');
}

function closeModal() {
    document.getElementById('modal').classList.add('hidden');
    document.getElementById('modal').classList.remove('flex');
}

function showAddProductForm() {
    const content = `
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Nama Barang</label>
                    <input type="text" name="nama_barang" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="Contoh: Meja Belajar">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Stok Awal</label>
                    <input type="number" name="stok" value="0" class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100" readonly>
                    <p class="text-xs text-gray-500 mt-1">Stok awal diatur ke 0 dan akan bertambah secara otomatis setelah proses produksi selesai.</p>
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-8 border-t pt-6">
                <button type="button" onclick="closeModal()" class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200">Batal</button>
                <button type="submit" class="px-6 py-3 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition duration-200"><i class="fas fa-save mr-2"></i>Simpan Barang</button>
            </div>
        </form>
    `;
    showModal('Tambah Barang Baru', content);
}

function showEditProductForm(product) {
    const content = `
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="kd_barang" value="${product.kd_barang}">
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Kode Barang</label>
                    <input type="text" value="${product.kd_barang}" class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100" readonly>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Nama Barang</label>
                    <input type="text" name="nama_barang" value="${product.nama_barang}" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Stok Saat Ini</label>
                    <input type="number" value="${product.stok}" class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100" readonly>
                    <p class="text-xs text-gray-500 mt-1">Stok tidak dapat diubah secara manual dari halaman ini.</p>
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-8 border-t pt-6">
                <button type="button" onclick="closeModal()" class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200">Batal</button>
                <button type="submit" class="px-6 py-3 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition duration-200"><i class="fas fa-save mr-2"></i>Simpan Perubahan</button>
            </div>
        </form>
    `;
    showModal('Edit Data Barang', content);
}

function deleteProduct(kd_barang) {
    if (confirm('Apakah Anda yakin ingin menghapus barang ini? Tindakan ini tidak dapat dibatalkan.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="kd_barang" value="${kd_barang}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

document.getElementById('searchInput').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const tableRows = document.querySelectorAll('#productsTableBody .product-row');
    
    tableRows.forEach(row => {
        const name = row.dataset.name.toLowerCase();
        row.style.display = name.includes(searchTerm) ? '' : 'none';
    });
});

setTimeout(() => {
    const notification = document.querySelector('.notification');
    if (notification) {
        notification.style.transition = 'opacity 0.5s ease';
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 500);
    }
}, 5000);
</script>

<?php require_once '../includes/footer.php'; ?>