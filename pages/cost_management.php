<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';
require_once '../includes/header.php';

// ... (Logika PHP Anda tetap sama) ...
if (!in_array($_SESSION['user']['level'], ['admin', 'pegawai'])) {
    header('Location: dashboard.php');
    exit;
}
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo->beginTransaction();
        if ($_POST['action'] === 'add') {
            if (empty($_POST['nama_biaya']) || empty($_POST['tgl_biaya']) || !isset($_POST['total'])) {
                throw new Exception('Semua field wajib diisi.');
            }
            if (!is_numeric($_POST['total']) || $_POST['total'] <= 0) {
                throw new Exception('Total biaya harus berupa angka positif.');
            }
            $id_biaya = $_POST['id_biaya'] ?: generateId('BYA');
            $stmt = $pdo->prepare("INSERT INTO biaya (id_biaya, nama_biaya, tgl_biaya, total) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id_biaya, $_POST['nama_biaya'], $_POST['tgl_biaya'], $_POST['total']]);
            $stmt = $pdo->prepare("INSERT INTO pengeluaran_kas (id_pengeluaran_kas, id_biaya, tgl_pengeluaran_kas, uraian, total) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([generateId('PKS'), $id_biaya, $_POST['tgl_biaya'], 'Biaya Operasional: ' . $_POST['nama_biaya'], $_POST['total']]);
            $message = 'Biaya berhasil ditambahkan dan dicatat di kas keluar.';
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM pengeluaran_kas WHERE id_biaya = ?");
            $stmt->execute([$_POST['id_biaya']]);
            $stmt = $pdo->prepare("DELETE FROM biaya WHERE id_biaya = ?");
            $stmt->execute([$_POST['id_biaya']]);
            $message = 'Biaya berhasil dihapus.';
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
$stmt = $pdo->query("SELECT * FROM biaya ORDER BY tgl_biaya DESC");
$costs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<head>
    <!-- Pastikan file responsive.css sudah dimuat -->
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>

<div class="flex-container min-h-screen bg-gray-100">
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

        <div id="costManagement" class="section active">
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-800">Manajemen Biaya</h2>
                <p class="text-gray-600 mt-2">Kelola biaya operasional dan pengeluaran lainnya</p>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                    <div class="flex justify-between items-center card-header">
                        <div>
                            <h3 class="text-xl font-semibold text-white">Daftar Biaya</h3>
                            <p class="text-blue-100 mt-1">Total: <?php echo count($costs); ?> catatan biaya</p>
                        </div>
                        <button onclick="showAddCostForm()" class="add-button bg-white text-blue-600 hover:bg-blue-50 px-6 py-2 rounded-lg font-medium transition duration-200 flex items-center">
                            <i class="fas fa-plus mr-2"></i>Tambah Biaya
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <?php if (empty($costs)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-file-invoice-dollar text-gray-400 text-6xl mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-600 mb-2">Belum ada catatan biaya</h3>
                            <p class="text-gray-500">Mulai dengan menambahkan biaya pertama Anda</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full responsive-table">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">ID Biaya</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Nama Biaya</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Tanggal</th>
                                        <th class="px-6 py-4 text-right text-sm font-semibold text-gray-700">Total</th>
                                        <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($costs as $cost): ?>
                                        <tr class="hover:bg-gray-50 transition duration-200">
                                            <td data-label="ID" class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($cost['id_biaya']); ?></td>
                                            <td data-label="Nama Biaya" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($cost['nama_biaya']); ?></td>
                                            <td data-label="Tanggal" class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars(date('d M Y', strtotime($cost['tgl_biaya']))); ?></td>
                                            <td data-label="Total" class="px-6 py-4 text-sm text-gray-900 font-medium text-right"><?php echo formatCurrency($cost['total']); ?></td>
                                            <td class="px-6 py-4 text-center actions-cell">
                                                <button onclick="deleteCost('<?php echo $cost['id_biaya']; ?>', '<?php echo htmlspecialchars(addslashes($cost['nama_biaya'])); ?>')" 
                                                        class="text-red-600 hover:text-red-800 hover:bg-red-100 p-2 rounded-lg transition duration-200">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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

        <!-- Modal yang Disempurnakan -->
        <div id="modal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-auto transform transition-all">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-5 rounded-t-xl">
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
    document.getElementById('modal').classList.add('flex');
}

function closeModal() {
    document.getElementById('modal').classList.add('hidden');
    document.getElementById('modal').classList.remove('flex');
}

function showAddCostForm() {
    const today = new Date().toISOString().split('T')[0];
    const content = `
        <form method="POST" onsubmit="return validateForm(this)">
            <input type="hidden" name="action" value="add">
            <div class="space-y-5">
                <div>
                    <label for="id_biaya" class="block text-gray-700 text-sm font-semibold mb-2">ID Biaya</label>
                    <input id="id_biaya" type="text" name="id_biaya" value="<?php echo generateId('BYA'); ?>" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed" readonly>
                </div>
                <div>
                    <label for="nama_biaya" class="block text-gray-700 text-sm font-semibold mb-2">Nama Biaya <span class="text-red-500">*</span></label>
                    <input id="nama_biaya" type="text" name="nama_biaya" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Contoh: Biaya Listrik, Gaji Karyawan">
                </div>
                <div>
                    <label for="tgl_biaya" class="block text-gray-700 text-sm font-semibold mb-2">Tanggal <span class="text-red-500">*</span></label>
                    <input id="tgl_biaya" type="date" name="tgl_biaya" value="\${today}" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="total" class="block text-gray-700 text-sm font-semibold mb-2">Total (Rp) <span class="text-red-500">*</span></label>
                    <input id="total" type="number" name="total" required min="1"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Masukkan jumlah total biaya">
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-8 pt-6 border-t">
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
    showModal('<i class="fas fa-plus-circle mr-2"></i>Tambah Biaya Baru', content);
}

function deleteCost(id, nama) {
    if (confirm(`Apakah Anda yakin ingin menghapus biaya "\${nama}"?\n\nTindakan ini juga akan menghapus catatan terkait di kas keluar dan tidak dapat dibatalkan.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id_biaya" value="\${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function validateForm(form) {
    const nama = form.querySelector('input[name="nama_biaya"]').value.trim();
    const tanggal = form.querySelector('input[name="tgl_biaya"]').value.trim();
    const total = form.querySelector('input[name="total"]').value.trim();
    
    if (!nama || !tanggal || !total) {
        alert('Semua field dengan tanda * wajib diisi!');
        return false;
    }
    
    if (isNaN(total) || parseInt(total) <= 0) {
        alert('Total biaya harus berupa angka positif!');
        return false;
    }
    
    return true;
}

document.addEventListener('DOMContentLoaded', (event) => {
    document.querySelectorAll('.notification').forEach(notification => {
        setTimeout(() => {
            notification.style.transition = 'opacity 0.5s ease';
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 500);
        }, 3000);
    });
});
</script>
<?php require_once '../includes/footer.php'; ?>