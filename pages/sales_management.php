<?php
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
            if (empty($_POST['tgl_jual']) || !isset($_POST['total_jual']) || !isset($_POST['bayar'])) {
                throw new Exception('Semua field utama (tanggal, total, bayar) harus diisi.');
            }
            if (empty($_POST['items'])) {
                throw new Exception('Minimal satu item penjualan harus ditambahkan.');
            }
            if ($_POST['bayar'] < $_POST['total_jual']) {
                throw new Exception('Jumlah bayar tidak boleh kurang dari total penjualan.');
            }
            
            $id_penjualan = $_POST['id_penjualan'] ?: generateId('JUL');
            $stmt = $pdo->prepare("INSERT INTO penjualan (id_penjualan, tgl_jual, total_jual, bayar, kembali) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id_penjualan, $_POST['tgl_jual'], $_POST['total_jual'], $_POST['bayar'], $_POST['kembali']]);

            foreach ($_POST['items'] as $item) {
                if (empty($item['kd_barang']) || empty($item['qty']) || empty($item['harga_jual'])) {
                    continue;
                }
                
                // Pengecekan Stok (Logika yang sudah ada tetap dipertahankan)
                $stmt_stock = $pdo->prepare("SELECT nama_barang, stok FROM barang WHERE kd_barang = ?");
                $stmt_stock->execute([$item['kd_barang']]);
                $product_stock = $stmt_stock->fetch(PDO::FETCH_ASSOC);
                
                if (!$product_stock || $product_stock['stok'] < $item['qty']) {
                    throw new Exception("Stok untuk '" . ($product_stock['nama_barang'] ?? $item['kd_barang']) . "' tidak mencukupi (tersisa: " . ($product_stock['stok'] ?? 0) . ")");
                }
                
                $id_detail_jual = generateId('DJL');
                $stmt = $pdo->prepare("INSERT INTO detail_penjualan (id_detail_jual, id_penjualan, kd_barang, harga_jual, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$id_detail_jual, $id_penjualan, $item['kd_barang'], $item['harga_jual'], $item['qty'], $item['subtotal']]);
                
                $stmt = $pdo->prepare("UPDATE barang SET stok = stok - ? WHERE kd_barang = ?");
                $stmt->execute([$item['qty'], $item['kd_barang']]);
            }
            $stmt = $pdo->prepare("INSERT INTO penerimaan_kas (id_penerimaan_kas, id_penjualan, tgl_terima_kas, uraian, total) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([generateId('PKS'), $id_penjualan, $_POST['tgl_jual'], 'Penjualan Produk ' . $id_penjualan, $_POST['total_jual']]);
            
            $pdo->commit();
            $message = 'Penjualan berhasil disimpan dengan ID: ' . $id_penjualan;
        } elseif ($_POST['action'] === 'delete') {
            // Logika Hapus (Tetap Sama)
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
            
            $pdo->commit();
            $message = 'Penjualan berhasil dihapus';
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$stmt = $pdo->query("SELECT * FROM penjualan ORDER BY tgl_jual DESC");
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->query("SELECT kd_barang, nama_barang, stok FROM barang");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        <div id="salesManagement" class="section active">
            <h2 class="text-2xl font-bold mb-6 text-gray-800">Manajemen Penjualan</h2>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-700">Daftar Transaksi Penjualan</h3>
                    <button onclick="showAddSaleForm()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition-transform transform hover:scale-105">
                        <i class="fas fa-plus mr-2"></i>Tambah Penjualan
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID Penjualan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="saleTableBody" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($sales as $sale): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono"><?php echo htmlspecialchars($sale['id_penjualan']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('d M Y', strtotime($sale['tgl_jual'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-semibold"><?php echo formatCurrency($sale['total_jual']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-center">
                                        <a href="faktur_penjualan.php?id=<?php echo $sale['id_penjualan']; ?>" class="text-blue-600 hover:text-blue-800 mr-3" title="Lihat Faktur">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                        <button onclick="deleteSale('<?php echo $sale['id_penjualan']; ?>')" class="text-red-600 hover:text-red-800" title="Hapus">
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

        <div id="sale-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden flex items-center justify-center z-40 transition-opacity">
            <div class="bg-gray-100 rounded-lg shadow-2xl w-full max-w-5xl mx-4 transform transition-all" style="max-height: 90vh;">
                <div class="flex justify-between items-center p-4 border-b border-gray-300">
                    <h3 class="text-xl font-bold text-gray-800"><i class="fas fa-cash-register mr-3"></i>Input Data Penjualan</h3>
                    <button type="button" onclick="closeModalSales()" class="text-gray-500 hover:text-red-600 text-2xl" title="Batal dan Tutup">
                        <i class="fas fa-times-circle"></i>
                    </button>
                </div>

                <form id="sale-form" method="POST">
                    <div class="p-6 overflow-y-auto" style="max-height: calc(90vh - 140px);">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">ID Penjualan</label>
                                <input type="text" name="id_penjualan" value="<?php echo generateId('JUL'); ?>" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-200 cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal Penjualan</label>
                                <input type="date" name="tgl_jual" value="<?php echo $today; ?>" max="<?php echo $today; ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>

                        <div class="border-t border-b border-gray-300 py-4">
                            <div class="flex justify-between items-center mb-3">
                                <h4 class="text-md font-bold text-gray-700">Item Penjualan</h4>
                                <button type="button" onclick="addItem()" class="bg-green-500 hover:bg-green-600 text-white font-bold py-1 px-3 rounded-md text-sm shadow-sm">
                                    <i class="fas fa-plus mr-1"></i> Baris Baru
                                </button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead class="bg-gray-200">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-bold text-gray-600 uppercase w-2/5">Nama Produk</th>
                                            <th class="px-4 py-2 text-left text-xs font-bold text-gray-600 uppercase w-1/5">Harga Jual</th>
                                            <th class="px-4 py-2 text-left text-xs font-bold text-gray-600 uppercase w-1/5">Kuantitas</th>
                                            <th class="px-4 py-2 text-right text-xs font-bold text-gray-600 uppercase w-1/5">Subtotal</th>
                                            <th class="px-4 py-2 text-center text-xs font-bold text-gray-600 uppercase">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="item-list" class="bg-white"></tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="flex justify-end mt-6">
                            <div class="w-full md:w-1/3 space-y-3">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold">Total Penjualan</label>
                                    <input type="text" id="total_display" value="Rp 0" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-200 text-right font-bold text-lg">
                                    <input type="hidden" name="total_jual" id="total_jual" value="0">
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
                        <button type="button" onclick="closeModalSales()" class="bg-gray-500 hover:bg-red-600 text-white font-bold py-2 px-6 rounded-lg shadow-sm">Batal</button>
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
            <select name="kd_barang" required class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500" onchange="checkStock(this)">
                <option value="" disabled selected>Cari Produk...</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['kd_barang']; ?>" data-stok="<?php echo $product['stok']; ?>"><?php echo htmlspecialchars($product['nama_barang']) . ' (Stok: ' . $product['stok'] . ')'; ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="p-2">
            <input type="number" name="harga_jual" required class="w-full p-2 border border-gray-300 rounded-md text-right" placeholder="0" oninput="updateTotal()">
        </td>
        <td class="p-2">
            <input type="number" name="qty" required class="w-full p-2 border border-gray-300 rounded-md text-right" placeholder="0" oninput="updateTotal(); checkStock(this);">
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

function showAddSaleForm() {
    document.getElementById('sale-form').reset();
    document.getElementById('item-list').innerHTML = '';
    itemCount = 0;
    updateTotal();
    
    document.getElementById('sale-modal').classList.remove('hidden');
}

function closeModalSales() {
    document.getElementById('sale-modal').classList.add('hidden');
}

function addItem() {
    const template = document.getElementById('item-template').content.cloneNode(true);
    const tr = template.querySelector('tr');
    
    tr.querySelector('[name="kd_barang"]').name = `items[${itemCount}][kd_barang]`;
    tr.querySelector('[name="harga_jual"]').name = `items[${itemCount}][harga_jual]`;
    tr.querySelector('[name="qty"]').name = `items[${itemCount}][qty]`;
    tr.querySelector('[name="subtotal"]').name = `items[${itemCount}][subtotal]`;

    document.getElementById('item-list').appendChild(tr);
    itemCount++;
}

function removeItem(button) {
    button.closest('.item-row').remove();
    updateTotal();
}

function checkStock(element) {
    const row = element.closest('.item-row');
    const productSelect = row.querySelector('[name*="[kd_barang]"]');
    const qtyInput = row.querySelector('[name*="[qty]"]');
    const selectedOption = productSelect.options[productSelect.selectedIndex];
    
    if (selectedOption) {
        const stok = parseInt(selectedOption.getAttribute('data-stok'), 10);
        const qty = parseInt(qtyInput.value, 10) || 0;
        
        if (qty > stok) {
            alert(`Stok tidak mencukupi! Sisa stok untuk produk ini adalah ${stok}.`);
            qtyInput.value = stok;
            updateTotal();
        }
    }
}

function updateTotal() {
    let total = 0;
    document.querySelectorAll('#item-list tr').forEach(row => {
        const harga = parseFloat(row.querySelector('[name*="[harga_jual]"]').value) || 0;
        const qty = parseFloat(row.querySelector('[name*="[qty]"]').value) || 0;
        const subtotal = harga * qty;
        row.querySelector('[name*="[subtotal]"]').value = currencyFormatter.format(subtotal);
        total += subtotal;
    });

    document.getElementById('total_jual').value = total;
    document.getElementById('total_display').value = currencyFormatter.format(total);
    
    updateKembali();
}

function updateKembali() {
    const total = parseFloat(document.getElementById('total_jual').value) || 0;
    const bayar = parseFloat(document.getElementById('bayar').value) || 0;
    const kembali = bayar - total;
    
    document.getElementById('kembali').value = kembali;
    document.getElementById('kembali_display').value = currencyFormatter.format(kembali);
}

function deleteSale(id_penjualan) {
    if (confirm('Apakah Anda yakin ingin menghapus transaksi penjualan ini? Aksi ini akan mengembalikan stok barang.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id_penjualan" value="${id_penjualan}">
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