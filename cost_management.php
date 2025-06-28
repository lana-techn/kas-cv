<?php
require_once 'config/db_connect.php';
require_once 'includes/function.php';
require_once 'includes/header.php';

if (!in_array($_SESSION['user']['level'], ['admin', 'pegawai'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $id_biaya = $_POST['id_biaya'] ?: generateId('BYA');
        $stmt = $pdo->prepare("INSERT INTO biaya (id_biaya, nama_biaya, tgl_biaya, total) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id_biaya, $_POST['nama_biaya'], $_POST['tgl_biaya'], $_POST['total']]);
        $stmt = $pdo->prepare("INSERT INTO pengeluaran_kas (id_pengeluaran_kas, id_biaya, tgl_pengeluaran_kas, uraian, total) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([generateId('PKS'), $id_biaya, $_POST['tgl_biaya'], 'Biaya Operasional: ' . $_POST['nama_biaya'], $_POST['total']]);
    } elseif ($_POST['action'] === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM pengeluaran_kas WHERE id_biaya = ?");
        $stmt->execute([$_POST['id_biaya']]);
        $stmt = $pdo->prepare("DELETE FROM biaya WHERE id_biaya = ?");
        $stmt->execute([$_POST['id_biaya']]);
    }
}

$stmt = $pdo->query("SELECT * FROM biaya");
$costs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="flex min-h-screen">
    <?php require_once 'includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <div id="costManagement" class="section active">
            <h2 class="text-2xl font-bold mb-6">Manajemen Biaya</h2>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Daftar Biaya</h3>
                    <button onclick="showAddCostForm()" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-plus mr-2"></i>Tambah Biaya
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID Biaya</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Biaya</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="costTableBody" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($costs as $cost): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($cost['id_biaya']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($cost['nama_biaya']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($cost['tgl_biaya']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo formatCurrency($cost['total']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="deleteCost('<?php echo $cost['id_biaya']; ?>')" class="text-red-600 hover:text-red-900">Hapus</button>
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
function showAddCostForm() {
    const content = `
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">ID Biaya</label>
                <input type="text" name="id_biaya" value="<?php echo generateId('BYA'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Nama Biaya</label>
                <input type="text" name="nama_biaya" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal</label>
                <input type="date" name="tgl_biaya" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Total</label>
                <input type="number" name="total" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-700">Batal</button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-700">Simpan</button>
            </div>
        </form>
    `;
    showModal('Tambah Biaya', content);
}

function deleteCost(id_biaya) {
    if (confirm('Apakah Anda yakin ingin menghapus biaya ini?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id_biaya" value="${id_biaya}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
<?php require_once 'includes/footer.php'; ?>