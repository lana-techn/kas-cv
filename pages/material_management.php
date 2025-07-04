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

// ... (Logika PHP Anda untuk CUD tetap sama) ...
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add') {
            if (empty($_POST['nama_bahan']) || !isset($_POST['stok']) || empty($_POST['satuan'])) {
                throw new Exception('Semua field harus diisi.');
            }
            if (!is_numeric($_POST['stok']) || $_POST['stok'] < 0) {
                throw new Exception('Stok harus berupa angka dan tidak boleh negatif.');
            }

            $kd_bahan = $_POST['kd_bahan'] ?: generateId('BHN');
            $stmt = $pdo->prepare("INSERT INTO bahan (kd_bahan, nama_bahan, stok, satuan) VALUES (?, ?, ?, ?)");
            $stmt->execute([$kd_bahan, $_POST['nama_bahan'], $_POST['stok'], $_POST['satuan']]);
            $message = 'Bahan berhasil ditambahkan.';
        } elseif ($_POST['action'] === 'edit') {
            if (empty($_POST['nama_bahan']) || !isset($_POST['stok']) || empty($_POST['satuan'])) {
                throw new Exception('Semua field harus diisi.');
            }
            if (!is_numeric($_POST['stok']) || $_POST['stok'] < 0) {
                throw new Exception('Stok harus berupa angka dan tidak boleh negatif.');
            }

            $stmt = $pdo->prepare("UPDATE bahan SET nama_bahan = ?, stok = ?, satuan = ? WHERE kd_bahan = ?");
            $stmt->execute([$_POST['nama_bahan'], $_POST['stok'], $_POST['satuan'], $_POST['kd_bahan']]);
            $message = 'Bahan berhasil diupdate.';
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM bahan WHERE kd_bahan = ?");
            $stmt->execute([$_POST['kd_bahan']]);
            $message = 'Bahan berhasil dihapus.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}


// Mengambil semua data bahan baku
$stmt = $pdo->query("SELECT * FROM bahan ORDER BY nama_bahan");
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Tambahkan link ke file CSS responsif di dalam <head> -->

<head>
    <!-- ... tag head Anda yang lain ... -->
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>

<!-- Tambahkan kelas 'flex-container' untuk mengatur layout sidebar dan main content -->
<div class="flex min-h-screen bg-gray-100">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <!-- Notifikasi -->
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

        <div id="materialManagement" class="section active">
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-800">Manajemen Bahan Baku</h2>
                <p class="text-gray-600 mt-2">Kelola data bahan baku untuk produksi</p>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                    <!-- Tambahkan kelas 'card-header' untuk mengatur header kartu -->
                    <div class="flex justify-between items-center card-header">
                        <div>
                            <h3 class="text-xl font-semibold text-white">Daftar Bahan Baku</h3>
                            <p class="text-blue-100 mt-1">Total: <?php echo count($materials); ?> bahan</p>
                        </div>
                        <!-- Tambahkan kelas 'add-button' untuk styling responsif -->
                        <button onclick="showAddMaterialForm()" class="add-button bg-white text-blue-600 hover:bg-blue-50 px-6 py-2 rounded-lg font-medium transition duration-200 flex items-center">
                            <i class="fas fa-plus mr-2"></i>Tambah Bahan
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    <?php if (empty($materials)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-box-open text-gray-400 text-6xl mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-600 mb-2">Belum ada bahan baku</h3>
                            <p class="text-gray-500">Mulai dengan menambahkan bahan pertama Anda</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <!-- Tambahkan kelas 'responsive-table' -->
                            <table class="min-w-full responsive-table">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Kode Bahan</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Nama Bahan</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Stok</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Satuan</th>
                                        <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($materials as $material): ?>
                                        <tr class="hover:bg-gray-50 transition duration-200">
                                            <!-- Tambahkan atribut data-label untuk setiap sel data -->
                                            <td data-label="Kode" class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($material['kd_bahan']); ?></td>
                                            <td data-label="Nama" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($material['nama_bahan']); ?></td>
                                            <td data-label="Stok" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($material['stok']); ?></td>
                                            <td data-label="Satuan" class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($material['satuan']); ?></td>
                                            <!-- Tambahkan kelas 'actions-cell' untuk kolom aksi -->
                                            <td class="px-6 py-4 text-center actions-cell">
                                                <div class="flex justify-center space-x-2">
                                                    <button onclick="editMaterial('<?php echo $material['kd_bahan']; ?>', '<?php echo htmlspecialchars(addslashes($material['nama_bahan'])); ?>', '<?php echo $material['stok']; ?>', '<?php echo htmlspecialchars(addslashes($material['satuan'])); ?>')"
                                                        class="text-blue-600 hover:text-blue-800 hover:bg-blue-100 p-2 rounded-lg transition duration-200">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button onclick="deleteMaterial('<?php echo $material['kd_bahan']; ?>', '<?php echo htmlspecialchars(addslashes($material['nama_bahan'])); ?>')"
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

        <!-- Modal (tidak ada perubahan signifikan, sudah cukup responsif) -->
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

<!-- ... (JavaScript Anda tetap sama) ... -->
<script>
    function showModal(title, content) {
        document.getElementById('modalTitle').innerHTML = title;
        document.getElementById('modalContent').innerHTML = content;
        document.getElementById('modal').classList.remove('hidden');
        document.getElementById('modal').classList.add('flex'); // Gunakan flex untuk centering
    }

    function closeModal() {
        document.getElementById('modal').classList.add('hidden');
        document.getElementById('modal').classList.remove('flex');
    }

    function showAddMaterialForm() {
        const content = `
        <form method="POST" onsubmit="return validateForm(this)">
            <input type="hidden" name="action" value="add">
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Kode Bahan</label>
                    <input type="text" name="kd_bahan" value="<?php echo generateId('BHN'); ?>" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50" readonly>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Nama Bahan <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_bahan" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Masukkan nama bahan">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Stok <span class="text-red-500">*</span></label>
                    <input type="number" name="stok" required min="0"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Masukkan jumlah stok awal">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Satuan <span class="text-red-500">*</span></label>
                    <input type="text" name="satuan" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Contoh: Kg, Liter, Pcs">
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
        showModal('Tambah Bahan Baku', content);
    }

    function editMaterial(kd_bahan, nama, stok, satuan) {
        const content = `
        <form method="POST" onsubmit="return validateForm(this)">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="kd_bahan" value="${kd_bahan}">
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Kode Bahan</label>
                    <input type="text" value="${kd_bahan}" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" readonly>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Nama Bahan <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_bahan" value="${nama}" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Stok <span class="text-red-500">*</span></label>
                    <input type="number" name="stok" value="${stok}" required min="0"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Satuan <span class="text-red-500">*</span></label>
                    <input type="text" name="satuan" value="${satuan}" required 
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
        showModal('Edit Bahan Baku', content);
    }

    function deleteMaterial(id, nama) {
        if (confirm(`Apakah Anda yakin ingin menghapus bahan "${nama}"?\n\nTindakan ini tidak dapat dibatalkan.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="kd_bahan" value="${id}">
        `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function validateForm(form) {
        const nama = form.querySelector('input[name="nama_bahan"]').value.trim();
        const stok = form.querySelector('input[name="stok"]').value.trim();
        const satuan = form.querySelector('input[name="satuan"]').value.trim();

        if (!nama || !stok || !satuan) {
            alert('Semua field dengan tanda * wajib diisi!');
            return false;
        }

        if (isNaN(stok) || parseInt(stok) < 0) {
            alert('Stok harus berupa angka dan tidak boleh negatif!');
            return false;
        }

        return true;
    }

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