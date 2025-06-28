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
        $id_penjualan = $_POST['id_penjualan'] ?: generateId('JUL');
        $stmt = $pdo->prepare("INSERT INTO penjualan (id_penjualan, tgl_jual, total_jual, bayar, kembali) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$id_penjualan, $_POST['tgl_jual'], $_POST['total_jual'], $_POST['bayar'], $_POST['kembali']]);

        foreach ($_POST['items'] as $item) {
            $id_detail_jual = generateId('DJL');
            $stmt = $pdo->prepare("INSERT INTO detail_penjualan (id_detail_jual, id_penjualan, kd_barang, harga_jual, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_detail_jual, $id_penjualan, $item['kd_barang'], $item['harga_jual'], $item['qty'], $item['subtotal']]);
            $stmt = $pdo->prepare("UPDATE barang SET stok = stok - ? WHERE kd_barang = ?");
            $stmt->execute([$item['qty'], $item['kd_barang']]);
        }
        $stmt = $pdo->prepare("INSERT INTO penerimaan_kas (id_penerimaan_kas, id_penjualan, tgl_terima_kas, uraian, total) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([generateId('PKS'), $id_penjualan, $_POST['tgl_jual'], 'Penjualan Produk', $_POST['total_jual']]);
    } elseif ($_POST['action'] === 'delete') {
        $stmt = $pdo->prepare("SELECT kd_barang, qty FROM detail_penjualan WHERE id_penjualan = ?");
        $stmt->execute([$_POST['id_penjualan']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as $item) {
            $stmt = $pdo->prepare("UPDATE barang SET stok = stok + ? WHERE kd_barang = ?");
            $stmt->execute([$item['qty'], $item['kd_barang']]);
        }
        $stmt = $pdo->prepare("DELETE FROM penerimaan_kas WHERE id_penjualan = ?");
        $stmt->execute([$_POST['id_penjualan']]);
        $stmt = $pdo->prepare("DELETE FROM detail_penjualan WHERE id_penjualan = ?");
        $stmt->execute([$_POST['id_penjualan']]);
        $stmt = $pdo->prepare("DELETE FROM penjualan WHERE id_penjualan = ?");
        $stmt->execute([$_POST['id_penjualan']]);
    }
}

$stmt = $pdo->query("SELECT * FROM penjualan");
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->query("SELECT kd_barang, nama_barang FROM barang");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="flex min-h-screen">
    <?php require_once 'includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <div id="salesManagement" class="section active">
            <h2 class="text-2xl font-bold mb-6">Manajemen Penjualan</h2>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Daftar Penjualan</h3>
                    <button onclick="showAddSaleForm()" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-plus mr-2"></i>Tambah Penjualan
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID Penjualan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="saleTableBody" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($sales as $sale): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($sale['id_penjualan']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($sale['tgl_jual']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo formatCurrency($sale['total_jual']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="deleteSale('<?php echo $sale['id_penjualan']; ?>')" class="text-red-600 hover:text-red-900">Hapus</button>
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

function showAddSaleForm() {
    itemCount = 0;
    const content = `
        <form method="POST" onsubmit="prepareItems()">
            <input type="hidden" name="action" value="add">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">ID Penjualan</label>
                <input type="text" name="id_penjualan" value="<?php echo generateId('JUL'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal</label>
                <input type="date" name="tgl_jual" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div id="items" class="mb-4">
                <h4 class="text-sm font-bold mb-2">Item Penjualan</h4>
                <div id="item-list"></div>
                <button type="button" onclick="addItem()" class="text-blue-600 hover:text-blue-900 text-sm">+ Tambah Item</button>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Total</label>
                <input type="number" name="total_jual" id="total_jual" required readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Bayar</label>
                <input type="number" name="bayar" id="bayar" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" oninput="updateKembali()">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Kembali</label>
                <input type="text" name="kembali" id="kembali" required readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-700">Batal</button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-700">Simpan</button>
            </div>
        </form>
    `;
    showModal('Tambah Penjualan', content);
}

function addItem() {
    const itemHtml = `
        <div id="item-${itemCount}" class="mb-2 p-2 border rounded">
            <div class="mb-2">
                <label class="block text-gray-700 text-sm font-bold mb-1">Produk</label>
                <select name="items[${itemCount}][kd_barang]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" onchange="updateItemSubtotal(${itemCount})">
                    <option value="">Pilih Produk</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['kd_barang']; ?>"><?php echo htmlspecialchars($product['nama_barang']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-2">
                <label class="block text-gray-700 text-sm font-bold mb-1">Harga Jual</label>
                <input type="number" name="items[${itemCount}][harga_jual]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" oninput="updateItemSubtotal(${itemCount})">
            </div>
            <div class="mb-2">
                <label class="block text-gray-700 text-sm font-bold mb-1">Kuantitas</label>
                <input type="number" name="items[${itemCount}][qty]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" oninput="