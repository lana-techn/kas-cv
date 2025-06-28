<?php
require_once 'config/db_connect.php';
require_once 'includes/function.php';
require_once 'includes/header.php';

if ($_SESSION['user']['level'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $id_produksi = $_POST['id_produksi'] ?: generateId('PRD');
        $stmt = $pdo->prepare("INSERT INTO produksi (id_produksi, kd_barang, nama_barang, jumlah_produksi) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id_produksi, $_POST['kd_barang'], $_POST['nama_barang'], $_POST['jumlah_produksi']]);

        foreach ($_POST['items'] as $item) {
            $id_detproduksi = generateId('DPR');
            $stmt = $pdo->prepare("INSERT INTO detail_produksi (id_detproduksi, id_produksi, kd_bahan, satuan, jum_bahan) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id_detproduksi, $id_produksi, $item['kd_bahan'], $item['satuan'], $item['jum_bahan']]);
            $stmt = $pdo->prepare("UPDATE bahan SET stok = stok - ? WHERE kd_bahan = ?");
            $stmt->execute([$item['jum_bahan'], $item['kd_bahan']]);
        }
        $stmt = $pdo->prepare("UPDATE barang SET stok = stok + ? WHERE kd_barang = ?");
        $stmt->execute([$_POST['jumlah_produksi'], $_POST['kd_barang']]);
    } elseif ($_POST['action'] === 'delete') {
        $stmt = $pdo->prepare("SELECT kd_barang, jumlah_produksi FROM produksi WHERE id_produksi = ?");
        $stmt->execute([$_POST['id_produksi']]);
        $production = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($production) {
            $stmt = $pdo->prepare("SELECT kd_bahan, jum_bahan FROM detail_produksi WHERE id_produksi = ?");
            $stmt->execute([$_POST['id_produksi']]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as $item) {
                $stmt = $pdo->prepare("UPDATE bahan SET stok = stok + ? WHERE kd_bahan = ?");
                $stmt->execute([$item['jum_bahan'], $item['kd_bahan']]);
            }
            $stmt = $pdo->prepare("UPDATE barang SET stok = stok - ? WHERE kd_barang = ?");
            $stmt->execute([$production['jumlah_produksi'], $production['kd_barang']]);
            $stmt = $pdo->prepare("DELETE FROM detail_produksi WHERE id_produksi = ?");
            $stmt->execute([$_POST['id_produksi']]);
            $stmt = $pdo->prepare("DELETE FROM produksi WHERE id_produksi = ?");
            $stmt->execute([$_POST['id_produksi']]);
        }
    }
}

$stmt = $pdo->query("SELECT p.*, b.nama_barang AS product_name FROM produksi p JOIN barang b ON p.kd_barang = b.kd_barang");
$productions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->query("SELECT kd_barang, nama_barang FROM barang");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->query("SELECT kd_bahan, nama_bahan, satuan FROM bahan");
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="flex min-h-screen">
    <?php require_once 'includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <div id="productionManagement" class="section active">
            <h2 class="text-2xl font-bold mb-6">Manajemen Produksi</h2>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Daftar Produksi</h3>
                    <button onclick="showAddProductionForm()" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-plus mr-2"></i>Tambah Produksi
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID Produksi</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode Barang</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Barang</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jumlah Produksi</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="productionTableBody" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($productions as $production): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($production['id_produksi']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($production['kd_barang']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($production['product_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($production['jumlah_produksi']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="deleteProduction('<?php echo $production['id_produksi']; ?>')" class="text-red-600 hover:text-red-900">Hapus</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div id="modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center">
            <div class="bg-white p-6 rounded-lg shadow-xl max-w-lg w-full mx-4">
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
let itemCount = 0;

function showAddProductionForm() {
    itemCount = 0;
    const content = `
        <form method="POST" onsubmit="prepareItems()">
            <input type="hidden" name="action" value="add">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">ID Produksi</label>
                <input type="text" name="id_produksi" value="<?php echo generateId('PRD'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Produk</label>
                <select name="kd_barang" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" onchange="updateProductName(this)">
                    <option value="">Pilih Produk</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['kd_barang']; ?>" data-name="<?php echo htmlspecialchars($product['nama_barang']); ?>">
                            <?php echo htmlspecialchars($product['nama_barang']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Nama Barang</label>
                <input type="text" name="nama_barang" id="nama_barang" required readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Jumlah Produksi</label>
                <input type="number" name="jumlah_produksi" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div id="items" class="mb-4">
                <h4 class="text-sm font-bold mb-2">Bahan Produksi</h4>
                <div id="item-list"></div>
                <button type="button" onclick="addItem()" class="text-blue-600 hover:text-blue-900 text-sm">+ Tambah Bahan</button>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-700">Batal</button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-700">Simpan</button>
            </div>
        </form>
    `;
    showModal('Tambah Produksi', content);
}

function addItem() {
    const itemHtml = `
        <div id="item-${itemCount}" class="mb-2 p-2 border rounded">
            <div class="mb-2">
                <label class="block text-gray-700 text-sm font-bold mb-1">Bahan</label>
                <select name="items[${itemCount}][kd_bahan]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" onchange="updateItemSatuan(${itemCount})">
                    <option value="">Pilih Bahan</option>
                    <?php foreach ($materials as $material): ?>
                        <option value="<?php echo $material['kd_bahan']; ?>" data-satuan="<?php echo htmlspecialchars($material['satuan']); ?>">
                            <?php echo htmlspecialchars($material['nama_bahan']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-2">
                <label class="block text-gray-700 text-sm font-bold mb-1">Satuan</label>
                <input type="text" name="items[${itemCount}][satuan]" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-2">
                <label class="block text-gray-700 text-sm font-bold mb-1">Jumlah Bahan</label>
                <input type="number" name="items[${itemCount}][jum_bahan]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <button type="button" onclick="removeItem(${itemCount})" class="text-red-600 hover:text-red-900 text-sm">Hapus Bahan</button>
        </div>
    `;
    document.getElementById('item-list').insertAdjacentHTML('beforeend', itemHtml);
    itemCount++;
}

function updateItemSatuan(index) {
    const select = document.querySelector(`select[name="items[${index}][kd_bahan]"]`);
    const selectedOption = select.options[select.selectedIndex];
    document.querySelector(`input[name="items[${index}][satuan]"]`).value = selectedOption.dataset.satuan || '';
}

function removeItem(index) {
    document.getElementById(`item-${index}`).remove();
}

function updateProductName(select) {
    const selectedOption = select.options[select.selectedIndex];
    document.getElementById('nama_barang').value = selectedOption.dataset.name || '';
}

function prepareItems() {
    // Ensure all items are included in the form submission
}

function deleteProduction(id_produksi) {
    if (confirm('Apakah Anda yakin ingin menghapus produksi ini?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id_produksi" value="${id_produksi}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
<?php require_once 'includes/footer.php'; ?>