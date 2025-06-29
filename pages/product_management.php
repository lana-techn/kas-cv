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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add') {
            if (empty($_POST['nama_barang']) || empty($_POST['stok'])) {
                throw new Exception('Semua field harus diisi');
            }
            
            if ($_POST['stok'] < 0) {
                throw new Exception('Stok tidak boleh negatif');
            }
            
            $kd_barang = $_POST['kd_barang'] ?: generateId('BRG');
            $stmt = $pdo->prepare("INSERT INTO barang (kd_barang, nama_barang, stok) VALUES (?, ?, ?)");
            $stmt->execute([$kd_barang, $_POST['nama_barang'], $_POST['stok']]);
            $message = 'Produk berhasil ditambahkan';
        } elseif ($_POST['action'] === 'edit') {
            if (empty($_POST['nama_barang']) || empty($_POST['stok'])) {
                throw new Exception('Semua field harus diisi');
            }
            
            if ($_POST['stok'] < 0) {
                throw new Exception('Stok tidak boleh negatif');
            }
            
            $stmt = $pdo->prepare("UPDATE barang SET nama_barang = ?, stok = ? WHERE kd_barang = ?");
            $stmt->execute([$_POST['nama_barang'], $_POST['stok'], $_POST['kd_barang']]);
            $message = 'Produk berhasil diupdate';
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM barang WHERE kd_barang = ?");
            $stmt->execute([$_POST['kd_barang']]);
            $message = 'Produk berhasil dihapus';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$stmt = $pdo->query("SELECT * FROM barang ORDER BY nama_barang");
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
            <h2 class="text-2xl font-bold mb-6">Manajemen Produk</h2>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Daftar Produk</h3>
                    <button onclick="showAddProductForm()" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-plus mr-2"></i>Tambah Produk
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode Barang</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Barang</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stok</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="productTableBody" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($product['kd_barang']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($product['nama_barang']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($product['stok']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="editProduct('<?php echo $product['kd_barang']; ?>')" class="text-blue-600 hover:text-blue-900 mr-2">Edit</button>
                                        <button onclick="deleteProduct('<?php echo $product['kd_barang']; ?>')" class="text-red-600 hover:text-red-900">Hapus</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div id="modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center">
            <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 id="modalTitle" class="text-lg font-semibold"></h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="modalContent"></div>
            </div>
        </div>
    </main>
</div>
<script>
function showAddProductForm() {
    const content = `
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Kode Barang</label>
                <input type="text" name="kd_barang" value="<?php echo generateId('BRG'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Nama Barang</label>
                <input type="text" name="nama_barang" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Stok</label>
                <input type="number" name="stok" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-700">Batal</button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-700">Simpan</button>
            </div>
        </form>
    `;
    showModal('Tambah Produk', content);
}

function editProduct(kd_barang) {
    const productRow = document.querySelector(`button[onclick="editProduct('${kd_barang}')"]`).closest('tr');
    const cells = productRow.querySelectorAll('td');
    const nama_barang = cells[1].textContent;
    const stok = cells[2].textContent;
    
    const content = `
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="kd_barang" value="${kd_barang}">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Kode Barang</label>
                <input type="text" value="${kd_barang}" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Nama Barang</label>
                <input type="text" name="nama_barang" value="${nama_barang}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Stok</label>
                <input type="number" name="stok" value="${stok}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-700">Batal</button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-700">Update</button>
            </div>
        </form>
    `;
    showModal('Edit Produk', content);
}

function deleteProduct(kd_barang) {
    if (confirm('Apakah Anda yakin ingin menghapus produk ini?')) {
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
</script>
<?php require_once '../includes/footer.php'; ?>