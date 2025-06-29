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
            if (empty($_POST['nama_bahan']) || empty($_POST['stok']) || empty($_POST['satuan'])) {
                throw new Exception('Semua field harus diisi');
            }
            
            if ($_POST['stok'] < 0) {
                throw new Exception('Stok tidak boleh negatif');
            }
            
            $kd_bahan = $_POST['kd_bahan'] ?: generateId('BHN');
            $stmt = $pdo->prepare("INSERT INTO bahan (kd_bahan, nama_bahan, stok, satuan) VALUES (?, ?, ?, ?)");
            $stmt->execute([$kd_bahan, $_POST['nama_bahan'], $_POST['stok'], $_POST['satuan']]);
            $message = 'Bahan berhasil ditambahkan';
        } elseif ($_POST['action'] === 'edit') {
            if (empty($_POST['nama_bahan']) || empty($_POST['stok']) || empty($_POST['satuan'])) {
                throw new Exception('Semua field harus diisi');
            }
            
            if ($_POST['stok'] < 0) {
                throw new Exception('Stok tidak boleh negatif');
            }
            
            $stmt = $pdo->prepare("UPDATE bahan SET nama_bahan = ?, stok = ?, satuan = ? WHERE kd_bahan = ?");
            $stmt->execute([$_POST['nama_bahan'], $_POST['stok'], $_POST['satuan'], $_POST['kd_bahan']]);
            $message = 'Bahan berhasil diupdate';
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM bahan WHERE kd_bahan = ?");
            $stmt->execute([$_POST['kd_bahan']]);
            $message = 'Bahan berhasil dihapus';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$stmt = $pdo->query("SELECT * FROM bahan ORDER BY nama_bahan");
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        <div id="materialManagement" class="section active">
            <h2 class="text-2xl font-bold mb-6">Manajemen Bahan Baku</h2>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Daftar Bahan Baku</h3>
                    <button onclick="showAddMaterialForm()" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-plus mr-2"></i>Tambah Bahan
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode Bahan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Bahan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stok</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Satuan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="materialTableBody" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($materials as $material): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($material['kd_bahan']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($material['nama_bahan']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($material['stok']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($material['satuan']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="editMaterial('<?php echo $material['kd_bahan']; ?>')" class="text-blue-600 hover:text-blue-900 mr-2">Edit</button>
                                        <button onclick="deleteMaterial('<?php echo $material['kd_bahan']; ?>')" class="text-red-600 hover:text-red-900">Hapus</button>
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
function showAddMaterialForm() {
    const content = `
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Kode Bahan</label>
                <input type="text" name="kd_bahan" value="<?php echo generateId('BHN'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Nama Bahan</label>
                <input type="text" name="nama_bahan" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Stok</label>
                <input type="number" name="stok" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Satuan</label>
                <input type="text" name="satuan" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-700">Batal</button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-700">Simpan</button>
            </div>
        </form>
    `;
    showModal('Tambah Bahan Baku', content);
}

function editMaterial(kd_bahan) {
    const materialRow = document.querySelector(`button[onclick="editMaterial('${kd_bahan}')"]`).closest('tr');
    const cells = materialRow.querySelectorAll('td');
    const nama_bahan = cells[1].textContent;
    const stok = cells[2].textContent;
    const satuan = cells[3].textContent;
    
    const content = `
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="kd_bahan" value="${kd_bahan}">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Kode Bahan</label>
                <input type="text" value="${kd_bahan}" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Nama Bahan</label>
                <input type="text" name="nama_bahan" value="${nama_bahan}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Stok</label>
                <input type="number" name="stok" value="${stok}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Satuan</label>
                <input type="text" name="satuan" value="${satuan}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-700">Batal</button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-700">Update</button>
            </div>
        </form>
    `;
    showModal('Edit Bahan Baku', content);
}

function deleteMaterial(kd_bahan) {
    if (confirm('Apakah Anda yakin ingin menghapus bahan ini?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="kd_bahan" value="${kd_bahan}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
<?php require_once '../includes/footer.php'; ?>