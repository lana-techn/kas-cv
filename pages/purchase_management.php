<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';
require_once '../includes/header.php';

// Logika PHP untuk menangani form submission (Tidak ada perubahan di sini)
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
<div class="flex min-h-screen bg-gray-100">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <div id="notification-container" class="fixed top-5 right-5 z-50 space-y-2">
            <?php if ($message): ?>
            <div class="p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg notification shadow-lg">
                <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg notification shadow-lg">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
        </div>

        <div id="purchaseManagement" class="section active">
            <h2 class="text-2xl font-bold mb-6 text-gray-800">Manajemen Pembelian</h2>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-700">Daftar Transaksi Pembelian</h3>
                    <button onclick="showAddPurchaseForm()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition-transform transform hover:scale-105">
                        <i class="fas fa-plus mr-2"></i>Tambah Pembelian
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID Pembelian</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="purchaseTableBody" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($purchases as $purchase): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono"><?php echo htmlspecialchars($purchase['id_pembelian']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('d M Y', strtotime($purchase['tgl_beli'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($purchase['nama_supplier']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-semibold"><?php echo formatCurrency($purchase['total_beli']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-center">
                                        <a href="faktur_pembelian.php?id=<?php echo $purchase['id_pembelian']; ?>" class="text-blue-600 hover:text-blue-800 mr-3" title="Lihat Faktur">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                        <button onclick="deletePurchase('<?php echo $purchase['id_pembelian']; ?>')" class="text-red-600 hover:text-red-800" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="purchase-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden flex items-center justify-center z-40 transition-opacity">
            <div class="bg-gray-100 rounded-lg shadow-2xl w-full max-w-5xl mx-4 transform transition-all" style="max-height: 90vh;">
                <div class="flex justify-between items-center p-4 border-b border-gray-300">
                    <h3 id="modalTitle" class="text-xl font-bold text-gray-800"><i class="fas fa-shopping-cart mr-3"></i>Input Data Pembelian</h3>
                    <button type="button" onclick="closeModalPurchase()" class="text-gray-500 hover:text-red-600 text-2xl" title="Batal dan Tutup">
                        <i class="fas fa-times-circle"></i>
                    </button>
                </div>

                <form id="purchase-form" method="POST">
                    <div class="p-6 overflow-y-auto" style="max-height: calc(90vh - 140px);">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">ID Pembelian</label>
                                <input type="text" name="id_pembelian" value="<?php echo generateId('BL'); ?>" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-200 cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal Pembelian</label>
                                <input type="date" name="tgl_beli" value="<?php echo $today; ?>" max="<?php echo $today; ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Pilih Supplier</label>
                                <select name="id_supplier" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="" disabled selected>-- Pilih Supplier --</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?php echo $supplier['id_supplier']; ?>"><?php echo htmlspecialchars($supplier['nama_supplier']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="border-t border-b border-gray-300 py-4">
                            <div class="flex justify-between items-center mb-3">
                                <h4 class="text-md font-bold text-gray-700">Item Pembelian</h4>
                                <button type="button" onclick="addItem()" class="bg-green-500 hover:bg-green-600 text-white font-bold py-1 px-3 rounded-md text-sm shadow-sm">
                                    <i class="fas fa-plus mr-1"></i> Baris Baru
                                </button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead class="bg-gray-200">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-bold text-gray-600 uppercase w-2/5">Nama Bahan</th>
                                            <th class="px-4 py-2 text-left text-xs font-bold text-gray-600 uppercase w-1/5">Harga Beli</th>
                                            <th class="px-4 py-2 text-left text-xs font-bold text-gray-600 uppercase w-1/5">Kuantitas</th>
                                            <th class="px-4 py-2 text-right text-xs font-bold text-gray-600 uppercase w-1/5">Subtotal</th>
                                            <th class="px-4 py-2 text-center text-xs font-bold text-gray-600 uppercase">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="item-list" class="bg-white">
                                        </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="flex justify-end mt-6">
                            <div class="w-full md:w-1/3 space-y-3">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold">Total Pembelian</label>
                                    <input type="text" id="total_display" value="Rp 0" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-200 text-right font-bold text-lg">
                                    <input type="hidden" name="total_beli" id="total_beli" value="0">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold">Jumlah Bayar</label>
                                    <input type="number" name="bayar" id="bayar" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-right font-bold text-lg focus:outline-none focus:ring-2 focus:ring-blue-500" oninput="updateKembali()">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold">Kembali</label>
                                    <input type="text" id="kembali_display" value="Rp 0" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-200 text-right font-bold text-lg">
                                    <input type="hidden" name="kembali" id="kembali" value="0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4 p-4 bg-gray-200 border-t border-gray-300">
                        <button type="button" onclick="closeModalPurchase()" class=" bg-gray-500 hover:bg-red-600 text-white font-bold py-2 px-6 rounded-lg shadow-sm">Batal</button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg shadow-sm">Simpan Transaksi</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<template id="item-template">
    <tr class="item-row border-b">
        <td class="p-2">
            <select name="kd_bahan" required class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                <option value="" disabled selected>Cari Bahan...</option>
                <?php foreach ($materials as $material): ?>
                    <option value="<?php echo $material['kd_bahan']; ?>"><?php echo htmlspecialchars($material['nama_bahan']); ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="p-2">
            <input type="number" name="harga_beli" required class="w-full p-2 border border-gray-300 rounded-md text-right" placeholder="0" oninput="updateTotal()">
        </td>
        <td class="p-2">
            <input type="number" name="qty" required class="w-full p-2 border border-gray-300 rounded-md text-right" placeholder="0" oninput="updateTotal()">
        </td>
        <td class="p-2">
            <input type="text" name="subtotal" readonly class="w-full p-2 border-gray-300 rounded-md bg-gray-200 text-right font-semibold">
        </td>
        <td class="p-2 text-center">
            <button type="button" class="text-red-500 hover:text-red-700" onclick="removeItem(this)" title="Hapus item">
                <i class="fas fa-trash-alt"></i>
            </button>
        </td>
    </tr>
</template>

<script>
let itemCount = 0;
const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });

function showAddPurchaseForm() {
    document.getElementById('purchase-form').reset();
    document.getElementById('item-list').innerHTML = '';
    itemCount = 0;
    updateTotal();
    
    const modal = document.getElementById('purchase-modal');
    modal.classList.remove('hidden');
}

function closeModalPurchase() {
    const modal = document.getElementById('purchase-modal');
    modal.classList.add('hidden');
}

function addItem() {
    const template = document.getElementById('item-template').content.cloneNode(true);
    const tr = template.querySelector('tr');
    
    tr.querySelector('[name="kd_bahan"]').name = `items[${itemCount}][kd_bahan]`;
    tr.querySelector('[name="harga_beli"]').name = `items[${itemCount}][harga_beli]`;
    tr.querySelector('[name="qty"]').name = `items[${itemCount}][qty]`;
    tr.querySelector('[name="subtotal"]').name = `items[${itemCount}][subtotal]`;

    document.getElementById('item-list').appendChild(tr);
    itemCount++;
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
        row.querySelector('[name*="[subtotal]"]').value = currencyFormatter.format(subtotal);
        total += subtotal;
    });

    document.getElementById('total_beli').value = total;
    document.getElementById('total_display').value = currencyFormatter.format(total);
    
    updateKembali();
}

function updateKembali() {
    const total = parseFloat(document.getElementById('total_beli').value) || 0;
    const bayar = parseFloat(document.getElementById('bayar').value) || 0;
    const kembali = bayar - total;
    
    document.getElementById('kembali').value = kembali;
    document.getElementById('kembali_display').value = currencyFormatter.format(kembali);
}

function deletePurchase(id_pembelian) {
    if (confirm('Apakah Anda yakin ingin menghapus transaksi pembelian ini? Aksi ini tidak dapat dibatalkan.')) {
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
        }, 5000);
    });
});
</script>
<?php require_once '../includes/footer.php'; ?>