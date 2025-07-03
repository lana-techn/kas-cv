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
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-800">Manajemen Produk</h2>
                <p class="text-gray-600 mt-2">Kelola data produk untuk kebutuhan penjualan dan produksi</p>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-white">Daftar Produk</h3>
                        <button onclick="showAddProductForm()" class="bg-white text-blue-600 hover:bg-blue-50 px-6 py-2 rounded-lg font-medium transition duration-200 flex items-center">
                            <i class="fas fa-plus mr-2"></i>Tambah Produk
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-auto">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Barang</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Barang</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($product['kd_barang']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($product['nama_barang']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($product['stok']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-4">
                                                <button onclick="editProduct('<?php echo $product['kd_barang']; ?>')" class="text-blue-600 hover:text-blue-900"><i class="fas fa-edit"></i></button>
                                                <button onclick="deleteProduct('<?php echo $product['kd_barang']; ?>')" class="text-red-600 hover:text-red-900"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
                <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 transform transition-all">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6 rounded-t-xl">
                        <div class="flex justify-between items-center">
                            <h3 id="modalTitle" class="text-xl font-semibold text-white"></h3>
                            <button onclick="closeModal()" class="text-white hover:text-gray-200 transition duration-200">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div id="modalContent" class="p-6"></div>
                </div>
            </div>
    </main>
</div>
<script>
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
                           placeholder="Masukkan jumlah stok">
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

    function editProduct(kd_barang) {
        const productRow = document.querySelector(`button[onclick="editProduct('${kd_barang}')"]`).closest('tr');
        const cells = productRow.querySelectorAll('td');
        const nama_barang = cells[1].textContent;
        const stok = cells[2].textContent;

        const content = `
        <form method="POST" onsubmit="return validateForm(this)">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="kd_barang" value="${kd_barang}">
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Kode Barang</label>
                    <input type="text" value="${kd_barang}" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50" readonly>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Nama Barang <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_barang" value="${nama_barang}" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Masukkan nama produk">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Stok <span class="text-red-500">*</span></label>
                    <input type="number" name="stok" value="${stok}" required min="0"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Masukkan jumlah stok">
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

    function validateForm(form) {
        const nama = form.querySelector('input[name="nama_barang"]').value.trim();
        const stok = form.querySelector('input[name="stok"]').value.trim();

        if (!nama || !stok) {
            alert('Semua field wajib diisi!');
            return false;
        }

        if (parseInt(stok) < 0) {
            alert('Stok tidak boleh negatif!');
            return false;
        }

        return true;
    }

    function closeModal() {
        document.getElementById('modal').classList.add('hidden');
        document.getElementById('modal').classList.remove('flex');
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