<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';
require_once '../includes/header.php';

// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['user']['level'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';

// Penanganan form untuk Aksi Tambah, Edit, dan Hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add') {
            if (empty($_POST['nama_barang']) || !isset($_POST['stok'])) {
                throw new Exception('Semua field harus diisi.');
            }
            if (!is_numeric($_POST['stok']) || $_POST['stok'] < 0) {
                throw new Exception('Stok harus berupa angka dan tidak boleh negatif.');
            }
            
            $kd_barang = $_POST['kd_barang'] ?: generateId('BRG');
            $stmt = $pdo->prepare("INSERT INTO barang (kd_barang, nama_barang, stok) VALUES (?, ?, ?)");
            $stmt->execute([$kd_barang, $_POST['nama_barang'], $_POST['stok']]);
            $message = 'Produk berhasil ditambahkan.';

        } elseif ($_POST['action'] === 'edit') {
            if (empty($_POST['nama_barang']) || !isset($_POST['stok'])) {
                throw new Exception('Semua field harus diisi.');
            }
            if (!is_numeric($_POST['stok']) || $_POST['stok'] < 0) {
                throw new Exception('Stok harus berupa angka dan tidak boleh negatif.');
            }
            
            $stmt = $pdo->prepare("UPDATE barang SET nama_barang = ?, stok = ? WHERE kd_barang = ?");
            $stmt->execute([$_POST['nama_barang'], $_POST['stok'], $_POST['kd_barang']]);
            $message = 'Produk berhasil diupdate.';

        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM barang WHERE kd_barang = ?");
            $stmt->execute([$_POST['kd_barang']]);
            $message = 'Produk berhasil dihapus.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Mengambil semua data produk
$stmt = $pdo->query("SELECT * FROM barang ORDER BY nama_barang");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="flex min-h-screen bg-gray-100">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <!-- Notifikasi -->
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
                <h2 class="text-3xl font-bold text-gray-800">Manajemen Produk</h2>
                <p class="text-gray-600 mt-2">Kelola data produk jadi yang siap dijual</p>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-xl font-semibold text-white">Daftar Produk</h3>
                            <p class="text-blue-100 mt-1">Total: <?php echo count($products); ?> produk</p>
                        </div>
                        <button onclick="showAddProductForm()" class="bg-white text-blue-600 hover:bg-blue-50 px-6 py-2 rounded-lg font-medium transition duration-200 flex items-center">
                            <i class="fas fa-plus mr-2"></i>Tambah Produk
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <?php if (empty($products)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-cookie-bite text-gray-400 text-6xl mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-600 mb-2">Belum ada produk</h3>
                            <p class="text-gray-500">Mulai dengan menambahkan produk pertama Anda</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Kode Barang</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Nama Barang</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Stok</th>
                                        <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($products as $product): ?>
                                        <tr class="hover:bg-gray-50 transition duration-200">
                                            <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['kd_barang']); ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($product['nama_barang']); ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($product['stok']); ?></td>
                                            <td class="px-6 py-4 text-center">
                                                <div class="flex justify-center space-x-2">
                                                    <button onclick="editProduct('<?php echo $product['kd_barang']; ?>', '<?php echo htmlspecialchars(addslashes($product['nama_barang'])); ?>', '<?php echo $product['stok']; ?>')" 
                                                            class="text-blue-600 hover:text-blue-800 hover:bg-blue-100 p-2 rounded-lg transition duration-200">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button onclick="deleteProduct('<?php echo $product['kd_barang']; ?>', '<?php echo htmlspecialchars(addslashes($product['nama_barang'])); ?>')" 
                                                            class="text-red-600 hover:text-red-800 hover:bg-red-100 p-2 rounded-lg transition duration-200">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 transform transition-all">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6 rounded-t-xl">
                    <div class="flex justify-between items-center">
                        <h3 id="modalTitle" class="text-xl font-semibold text-white"></h3>
                        <button onclick="closeModal()" class="text-white hover:text-gray-200 transition duration-200">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <div id="modalContent" class="p-6"></div>
            </div>
        </div>
    </main>
</div>

<script>
function showModal(title, content) {
    document.getElementById('modalTitle').innerHTML = title;
    document.getElementById('modalContent').innerHTML = content;
    document.getElementById('modal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('modal').classList.add('hidden');
}

function showAddProductForm() {
    const content = `
        <form method="POST" onsubmit="return validateForm(this)">
            <input type="hidden" name="action" value="add">
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Kode Barang</label>
                    <input type="text" name="kd_barang" value="<?php echo generateId('BRG'); ?>" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50" readonly>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Nama Barang <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_barang" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Masukkan nama produk">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Stok <span class="text-red-500">*</span></label>
                    <input type="number" name="stok" required min="0"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Masukkan jumlah stok awal">
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-8">
                <button type="button" onclick="closeModal()" 
                        class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200">
                    Batal
                </button>
                <button type="submit" 
                        class="px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                    <i class="fas fa-save mr-2"></i>Simpan
                </button>
            </div>
        </form>
    `;
    showModal('Tambah Produk', content);
}

function editProduct(kd_barang, nama, stok) {
    const content = `
        <form method="POST" onsubmit="return validateForm(this)">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="kd_barang" value="${kd_barang}">
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Kode Barang</label>
                    <input type="text" value="${kd_barang}" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" readonly>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Nama Barang <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_barang" value="${nama}" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Stok <span class="text-red-500">*</span></label>
                    <input type="number" name="stok" value="${stok}" required min="0"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-8">
                <button type="button" onclick="closeModal()" 
                        class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200">
                    Batal
                </button>
                <button type="submit" 
                        class="px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                    <i class="fas fa-save mr-2"></i>Update
                </button>
            </div>
        </form>
    `;
    showModal('Edit Produk', content);
}

function deleteProduct(id, nama) {
    if (confirm(`Apakah Anda yakin ingin menghapus produk "${nama}"?\n\nTindakan ini tidak dapat dibatalkan.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="kd_barang" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function validateForm(form) {
    const nama = form.querySelector('input[name="nama_barang"]').value.trim();
    const stok = form.querySelector('input[name="stok"]').value.trim();
    
    if (!nama || !stok) {
        alert('Semua field dengan tanda * wajib diisi!');
        return false;
    }
    
    if (isNaN(stok) || parseInt(stok) < 0) {
        alert('Stok harus berupa angka dan tidak boleh negatif!');
        return false;
    }
    
    return true;
}

// Menutup notifikasi setelah beberapa detik
document.addEventListener('DOMContentLoaded', (event) => {
    const notifications = document.querySelectorAll('.notification');
    notifications.forEach(notification => {
        setTimeout(() => {
            notification.style.transition = 'opacity 0.5s ease';
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 500);
        }, 3000);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
