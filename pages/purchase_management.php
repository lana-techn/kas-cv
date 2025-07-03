<?php
// ... (Logika PHP Anda tetap sama) ...
require_once '../config/db_connect.php';
require_once '../includes/function.php';
require_once '../includes/header.php';
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
            if (empty($_POST['tgl_beli']) || empty($_POST['id_supplier']) || !isset($_POST['total_beli']) || !isset($_POST['bayar'])) {
                throw new Exception('Semua field utama (tanggal, supplier, total, bayar) harus diisi.');
            }
            if (empty($_POST['items'])) {
                throw new Exception('Minimal satu item pembelian harus ditambahkan.');
            }
            if ($_POST['bayar'] < $_POST['total_beli']) {
                throw new Exception('Jumlah bayar tidak boleh kurang dari total pembelian.');
            }
            $id_pembelian = $_POST['id_pembelian'] ?: generateId('BL');
            $stmt = $pdo->prepare("INSERT INTO pembelian (id_pembelian, tgl_beli, id_supplier, total_beli, bayar, kembali) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_pembelian, $_POST['tgl_beli'], $_POST['id_supplier'], $_POST['total_beli'], $_POST['bayar'], $_POST['kembali']]);
            foreach ($_POST['items'] as $item) {
                if (empty($item['kd_bahan']) || empty($item['qty']) || empty($item['harga_beli'])) {
                    continue;
                }
                $id_detail_beli = generateId('DBL');
                $stmt = $pdo->prepare("INSERT INTO detail_pembelian (id_detail_beli, id_pembelian, kd_bahan, harga_beli, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$id_detail_beli, $id_pembelian, $item['kd_bahan'], $item['harga_beli'], $item['qty'], $item['subtotal']]);
                $stmt = $pdo->prepare("UPDATE bahan SET stok = stok + ? WHERE kd_bahan = ?");
                $stmt->execute([$item['qty'], $item['kd_bahan']]);
            }
            $stmt = $pdo->prepare("INSERT INTO pengeluaran_kas (id_pengeluaran_kas, id_pembelian, tgl_pengeluaran_kas, uraian, total) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([generateId('PKS'), $id_pembelian, $_POST['tgl_beli'], 'Pembelian Bahan ' . $id_pembelian, $_POST['total_beli']]);
            $pdo->commit();
            $message = 'Pembelian berhasil disimpan dengan ID: ' . $id_pembelian;
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("SELECT kd_bahan, qty FROM detail_pembelian WHERE id_pembelian = ?");
            $stmt->execute([$_POST['id_pembelian']]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as $item) {
                $stmt = $pdo->prepare("UPDATE bahan SET stok = stok - ? WHERE kd_bahan = ?");
                $stmt->execute([$item['qty'], $item['kd_bahan']]);
            }
            $stmt = $pdo->prepare("DELETE FROM pengeluaran_kas WHERE id_pembelian = ?");
            $stmt->execute([$_POST['id_pembelian']]);
            $stmt = $pdo->prepare("DELETE FROM detail_pembelian WHERE id_pembelian = ?");
            $stmt->execute([$_POST['id_pembelian']]);
            $stmt = $pdo->prepare("DELETE FROM pembelian WHERE id_pembelian = ?");
            $stmt->execute([$_POST['id_pembelian']]);
            $pdo->commit();
            $message = 'Pembelian berhasil dihapus';
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
$stmt = $pdo->query("SELECT p.*, s.nama_supplier FROM pembelian p JOIN supplier s ON p.id_supplier = s.id_supplier ORDER BY p.tgl_beli DESC");
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->query("SELECT id_supplier, nama_supplier FROM supplier");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->query("SELECT kd_bahan, nama_bahan FROM bahan");
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
$today = date('Y-m-d');
?>

<head>
    <!-- Pastikan file responsive.css sudah dimuat -->
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>

<div class="flex-container min-h-screen bg-gray-100">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <!-- ... (Bagian Notifikasi dan Header Halaman tetap sama) ... -->
        <div id="purchaseManagement" class="section active">
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-800">Manajemen Pembelian</h2>
                <p class="text-gray-600 mt-2">Kelola transaksi pembelian bahan baku dari supplier</p>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                    <div class="flex justify-between items-center card-header">
                        <div>
                            <h3 class="text-xl font-semibold text-white">Daftar Pembelian</h3>
                            <p class="text-blue-100 mt-1">Total: <?php echo count($purchases); ?> transaksi</p>
                        </div>
                        <button onclick="showAddPurchaseForm()" class="add-button bg-white text-blue-600 hover:bg-blue-50 px-6 py-2 rounded-lg font-medium transition duration-200 flex items-center">
                            <i class="fas fa-plus mr-2"></i>Tambah Pembelian
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full responsive-table">
                            <thead>
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">ID Pembelian</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Tanggal</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Supplier</th>
                                    <th class="px-6 py-4 text-right text-sm font-semibold text-gray-700">Total</th>
                                    <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($purchases as $purchase): ?>
                                    <tr>
                                        <td data-label="ID" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($purchase['id_pembelian']); ?></td>
                                        <td data-label="Tanggal" class="px-6 py-4 text-sm text-gray-900"><?php echo date('d M Y', strtotime($purchase['tgl_beli'])); ?></td>
                                        <td data-label="Supplier" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($purchase['nama_supplier']); ?></td>
                                        <td data-label="Total" class="px-6 py-4 text-sm text-gray-900 text-right font-semibold"><?php echo formatCurrency($purchase['total_beli']); ?></td>
                                        <td class="px-6 py-4 text-center actions-cell">
                                            <div class="flex justify-center space-x-2">
                                                <a href="faktur_pembelian.php?id=<?php echo $purchase['id_pembelian']; ?>" class="text-blue-600 hover:text-blue-800 p-2 rounded-lg hover:bg-blue-100" title="Lihat Faktur">
                                                    <i class="fas fa-file-invoice"></i>
                                                </a>
                                                <button onclick="deletePurchase('<?php echo $purchase['id_pembelian']; ?>')" class="text-red-600 hover:text-red-800 p-2 rounded-lg hover:bg-red-100" title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Pembelian -->
        <div id="purchase-modal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50 p-4">
            <div class="bg-gray-50 rounded-xl shadow-2xl w-full max-w-5xl mx-auto transform transition-all max-h-[90vh] flex flex-col">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-5 rounded-t-xl flex-shrink-0">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-white"><i class="fas fa-shopping-cart mr-3"></i>Input Data Pembelian</h3>
                        <button type="button" onclick="closeModalPurchase()" class="text-white hover:text-gray-200 text-2xl" title="Tutup"><i class="fas fa-times-circle"></i></button>
                    </div>
                </div>

                <form id="purchase-form" method="POST" class="flex flex-col flex-grow">
                    <div class="p-6 overflow-y-auto flex-grow">
                        <!-- ... (Bagian input ID, Tanggal, Supplier tetap sama) ... -->
                        <input type="hidden" name="action" value="add">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div>
                                <label class="block text-gray-700 text-sm font-semibold mb-2">ID Pembelian</label>
                                <input type="text" name="id_pembelian" value="<?php echo generateId('BL'); ?>" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-200">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-semibold mb-2">Tanggal</label>
                                <input type="date" name="tgl_beli" value="<?php echo $today; ?>" max="<?php echo $today; ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-semibold mb-2">Supplier</label>
                                <select name="id_supplier" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                    <option value="" disabled selected>-- Pilih Supplier --</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?php echo $supplier['id_supplier']; ?>"><?php echo htmlspecialchars($supplier['nama_supplier']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="border-t border-gray-300 pt-4">
                            <div class="flex justify-between items-center mb-3">
                                <h4 class="text-md font-semibold text-gray-700">Item Pembelian</h4>
                                <button type="button" onclick="addItem()" class="bg-green-500 hover:bg-green-600 text-white font-bold py-1 px-3 rounded-md text-sm"><i class="fas fa-plus mr-1"></i> Tambah Item</button>
                            </div>
                            <!-- Terapkan kelas 'responsive-form-table' di sini -->
                            <table class="min-w-full responsive-form-table">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-bold text-gray-600 uppercase w-2/5">Nama Bahan</th>
                                        <th class="px-4 py-2 text-left text-xs font-bold text-gray-600 uppercase w-1/5">Harga</th>
                                        <th class="px-4 py-2 text-left text-xs font-bold text-gray-600 uppercase w-1/5">Qty</th>
                                        <th class="px-4 py-2 text-right text-xs font-bold text-gray-600 uppercase w-1/5">Subtotal</th>
                                        <th class="px-4 py-2 text-center text-xs font-bold text-gray-600 uppercase">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="item-list"></tbody>
                            </table>
                        </div>
                        
                        <!-- ... (Bagian Total, Bayar, Kembali tetap sama) ... -->
                        <div class="flex flex-col md:flex-row justify-end mt-6">
                            <div class="w-full md:w-2/5 space-y-3">
                                <div>
                                    <label class="flex justify-between items-center text-gray-700 text-sm font-semibold"><span>Total Pembelian</span></label>
                                    <input type="text" id="total_display" value="Rp 0" readonly class="w-full px-3 py-2 border-gray-300 rounded-lg bg-gray-200 text-right font-bold text-xl">
                                    <input type="hidden" name="total_beli" id="total_beli" value="0">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-semibold">Jumlah Bayar</label>
                                    <input type="number" name="bayar" id="bayar" required class="w-full px-3 py-2 border-gray-300 rounded-lg text-right font-bold text-xl" oninput="updateKembali()">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-semibold">Kembali</label>
                                    <input type="text" id="kembali_display" value="Rp 0" readonly class="w-full px-3 py-2 border-gray-300 rounded-lg bg-gray-200 text-right font-bold text-xl">
                                    <input type="hidden" name="kembali" id="kembali" value="0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4 p-4 bg-gray-200 border-t border-gray-300 flex-shrink-0">
                        <button type="button" onclick="closeModalPurchase()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-6 rounded-lg">Batal</button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg">Simpan Transaksi</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<!-- PERBAIKAN PADA TEMPLATE -->
<template id="item-template">
    <tr class="item-row">
        <!-- Tambahkan atribut data-label yang sesuai -->
        <td data-label="Nama Bahan">
            <select name="kd_bahan" required class="w-full p-2 border border-gray-300 rounded-md">
                <option value="" disabled selected>Cari Bahan...</option>
                <?php foreach ($materials as $material): ?>
                    <option value="<?php echo $material['kd_bahan']; ?>"><?php echo htmlspecialchars($material['nama_bahan']); ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td data-label="Harga Beli">
            <input type="number" name="harga_beli" required class="w-full p-2 border border-gray-300 rounded-md text-right" placeholder="0" oninput="updateTotal()">
        </td>
        <td data-label="Kuantitas">
            <input type="number" name="qty" required class="w-full p-2 border border-gray-300 rounded-md text-right" placeholder="0" oninput="updateTotal()">
        </td>
        <td data-label="Subtotal">
            <input type="text" name="subtotal_display" readonly class="w-full p-2 border-gray-300 rounded-md bg-gray-200 text-right font-semibold">
            <input type="hidden" name="subtotal">
        </td>
        <!-- Beri kelas khusus untuk sel tombol hapus -->
        <td class="delete-button-cell">
            <button type="button" class="text-red-500 hover:text-red-700" onclick="removeItem(this)" title="Hapus item">
                <i class="fas fa-trash-alt fa-lg"></i>
            </button>
        </td>
    </tr>
</template>

<!-- ... (JavaScript Anda tetap sama) ... -->
<script>
const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });
function showAddPurchaseForm() {
    document.getElementById('purchase-form').reset();
    document.getElementById('item-list').innerHTML = '';
    addItem();
    updateTotal();
    document.getElementById('purchase-modal').classList.remove('hidden');
}
function closeModalPurchase() {
    document.getElementById('purchase-modal').classList.add('hidden');
}
function addItem() {
    const template = document.getElementById('item-template').content.cloneNode(true);
    const itemCount = document.querySelectorAll('#item-list tr').length;
    template.querySelector('[name="kd_bahan"]').name = `items[${itemCount}][kd_bahan]`;
    template.querySelector('[name="harga_beli"]').name = `items[${itemCount}][harga_beli]`;
    template.querySelector('[name="qty"]').name = `items[${itemCount}][qty]`;
    template.querySelector('[name="subtotal_display"]').name = `items[${itemCount}][subtotal_display]`;
    template.querySelector('[name="subtotal"]').name = `items[${itemCount}][subtotal]`;
    document.getElementById('item-list').appendChild(template);
}
function removeItem(button) {
    button.closest('.item-row').remove();
    updateTotal();
}
function updateTotal() {
    let total = 0;
    document.querySelectorAll('#item-list tr').forEach(row => {
        const harga = parseFloat(row.querySelector('[name*="[harga_beli]"]').value) || 0;
        const qty = parseFloat(row.querySelector('[name*="[qty]"]').value) || 0;
        const subtotal = harga * qty;
        row.querySelector('[name*="[subtotal_display]"]').value = currencyFormatter.format(subtotal);
        row.querySelector('[name*="[subtotal]"]').value = subtotal;
        total += subtotal;
    });
    document.getElementById('total_beli').value = total;
    document.getElementById('total_display').value = currencyFormatter.format(total);
    updateKembali();
}
function updateKembali() {
    const total = parseFloat(document.getElementById('total_beli').value) || 0;
    const bayar = parseFloat(document.getElementById('bayar').value) || 0;
    const kembali = bayar > total ? bayar - total : 0;
    document.getElementById('kembali').value = kembali;
    document.getElementById('kembali_display').value = currencyFormatter.format(kembali);
}
function deletePurchase(id_pembelian) {
    if (confirm('Apakah Anda yakin ingin menghapus transaksi pembelian ini? Tindakan ini akan mengembalikan stok bahan ke jumlah semula dan tidak dapat dibatalkan.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id_pembelian" value="${id_pembelian}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
document.addEventListener('DOMContentLoaded', function() {
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