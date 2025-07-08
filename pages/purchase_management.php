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
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'blue-gradient-start': '#4A90E2',
                        'blue-gradient-end': '#357ABD'
                    }
                }
            }
        }
    </script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%);
        }
    </style>
</head>

<div class="flex min-h-screen bg-gray-100">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
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

        <div id="purchase-modal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50 p-4">
            <div class="bg-gray-50 rounded-xl shadow-2xl w-full max-w-4xl mx-auto transform transition-all max-h-[90vh] flex flex-col modal-container">
                <form id="purchase-form" method="POST" class="flex flex-col flex-grow">
                    <div class="gradient-bg p-5 rounded-t-xl flex-shrink-0 flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-white">
                            <svg class="w-6 h-6 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                                <path fill-rule="evenodd" d="M4 5a2 2 0 012-2v1a1 1 0 001 1h6a1 1 0 001-1V3a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                            </svg>
                            Input Data Pembelian
                        </h3>
                        <button type="button" onclick="closeModalPurchase()" class="text-white hover:text-gray-200 text-2xl" title="Tutup">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="p-6 overflow-y-auto max-h-[calc(90vh-12rem)] space-y-6">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="bg-white p-5 rounded-lg shadow card-section">
                            <h4 class="font-semibold text-gray-700 mb-4 border-b pb-2">Informasi Utama</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 form-grid">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">ID Pembelian</label>
                                    <input type="text" name="id_pembelian" value="<?php echo generateId('BL'); ?>" readonly class="w-full px-4 py-2 border rounded-lg bg-gray-100 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal</label>
                                    <div class="relative">
                                        <input type="date" name="tgl_beli" value="<?php echo $today; ?>" max="<?php echo $today; ?>" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 pr-10">
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Supplier</label>
                                    <div class="relative">
                                        <select name="id_supplier" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 appearance-none">
                                            <option value="" disabled selected>-- Pilih Supplier --</option>
                                            <?php foreach ($suppliers as $supplier): ?>
                                                <option value="<?php echo $supplier['id_supplier']; ?>"><?php echo htmlspecialchars($supplier['nama_supplier']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white p-5 rounded-lg shadow card-section">
                            <div class="flex justify-between items-center mb-4 border-b pb-2 flex-responsive">
                                <h4 class="font-semibold text-gray-700">Item Pembelian</h4>
                                <button type="button" onclick="addItem()" class="bg-green-500 hover:bg-green-600 text-white font-bold py-1 px-3 rounded-md text-sm flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    Tambah Item
                                </button>
                            </div>
                            <div id="item-list" class="space-y-3">
                            </div>
                        </div>

                        <div class="bg-white p-5 rounded-lg shadow overflow-auto overflow-x-hidden">
                            <h4 class="font-semibold text-gray-700 mb-4 border-b pb-2">Detail Pembayaran</h4>
                            <div class="flex flex-col space-y-2">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-1 md:hidden">Total Pembelian</label>
                                    <div class="flex items-center">
                                        <span class="w-1/2 text-gray-700 font-bold hidden md:block">Total Pembelian</span>
                                        <input type="text" id="total_display" value="Rp 0" readonly class="w-full max-w-xs px-2 md:px-3 py-2 border-0 bg-transparent text-right font-bold text-lg md:text-xl text-gray-800">
                                    </div>
                                    <input type="hidden" name="total_beli" id="total_beli" value="0">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-1 md:hidden">Jumlah Bayar</label>
                                    <div class="flex items-center">
                                        <span class="w-1/2 text-gray-700 font-bold hidden md:block">Jumlah Bayar</span>
                                        <input type="number" name="bayar" id="bayar" required class="w-full max-w-xs px-2 md:px-3 py-2 border rounded-lg text-right font-bold text-lg md:text-xl focus:ring-2 focus:ring-blue-500" min="0" placeholder="Masukkan Jumlah Bayar" oninput="updateKembali()">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-1 md:hidden">Kembali</label>
                                    <div class="flex items-center">
                                        <span class="w-1/2 text-gray-700 font-bold hidden md:block">Kembali</span>
                                        <input type="text" id="kembali_display" value="Rp 0" readonly class="w-full max-w-xs px-2 md:px-3 py-2 border-0 bg-transparent text-right font-bold text-lg md:text-xl text-green-600">
                                    </div>
                                    <input type="hidden" name="kembali" id="kembali" value="0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4 p-4 bg-gray-100 border-t rounded-b-xl flex-shrink-0">
                        <button type="button" onclick="closeModalPurchase()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-6 rounded-lg transition-transform transform hover:scale-105">Batal</button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition-transform transform hover:scale-105">Simpan Transaksi</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<template id="item-template">
    <div class="item-row grid grid-cols-12 gap-3 items-center p-3 border rounded-lg bg-gray-50/50">
        <div class="col-span-12 md:col-span-4">
            <label class="block text-gray-700 text-xs font-bold mb-1 md:hidden">Nama Bahan</label>
            <div class="relative">
                <select name="items[][kd_bahan]" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 appearance-none">
                    <option value="" disabled selected>Cari Bahan...</option>
                    <?php foreach ($materials as $material): ?>
                        <option value="<?php echo $material['kd_bahan']; ?>"><?php echo htmlspecialchars($material['nama_bahan']); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                    <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
        </div>
        <div class="col-span-6 md:col-span-2">
            <label class="block text-gray-700 text-xs font-bold mb-1 md:hidden">Harga</label>
            <input type="number" name="items[][harga_beli]" required class="w-full p-2 border border-gray-300 rounded-md text-right focus:ring-2 focus:ring-blue-500" placeholder="Masukkan Harga" min="0" oninput="updateTotal()">
        </div>
        <div class="col-span-6 md:col-span-2">
            <label class="block text-gray-700 text-xs font-bold mb-1 md:hidden">Qty</label>
            <input type="number" name="items[][qty]" required class="w-full p-2 border border-gray-300 rounded-md text-right focus:ring-2 focus:ring-blue-500" placeholder="Masukkan Jumlah" min="0" oninput="updateTotal()">
        </div>
        <div class="col-span-10 md:col-span-3">
            <label class="block text-gray-700 text-xs font-bold mb-1 md:hidden">Subtotal</label>
            <input type="text" name="items[][subtotal_display]" value="Rp 0" readonly class="w-full p-2 border-0 rounded-md bg-transparent text-right font-semibold">
            <input type="hidden" name="items[][subtotal]" value="0">
        </div>
        <div class="col-span-2 md:col-span-1 flex items-center justify-end">
            <button type="button" class="text-red-500 hover:text-red-700 transition-colors" onclick="removeItem(this)" title="Hapus item">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
            </button>
        </div>
    </div>
</template>

<script>
    const currencyFormatter = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    });

    function showAddPurchaseForm() {
        document.getElementById('purchase-form').reset();
        document.getElementById('item-list').innerHTML = '';
        addItem();
        updateTotal();
        document.getElementById('purchase-modal').classList.remove('hidden');
        document.getElementById('purchase-modal').classList.add('flex');
    }

    function closeModalPurchase() {
        document.getElementById('purchase-modal').classList.add('hidden');
        document.getElementById('purchase-modal').classList.remove('flex');
    }

    function addItem() {
        const template = document.getElementById('item-template').content.cloneNode(true);
        const itemCount = document.querySelectorAll('.item-row').length;
        template.querySelector('[name="items[][kd_bahan]"]').name = `items[${itemCount}][kd_bahan]`;
        template.querySelector('[name="items[][harga_beli]"]').name = `items[${itemCount}][harga_beli]`;
        template.querySelector('[name="items[][qty]"]').name = `items[${itemCount}][qty]`;
        template.querySelector('[name="items[][subtotal_display]"]').name = `items[${itemCount}][subtotal_display]`;
        template.querySelector('[name="items[][subtotal]"]').name = `items[${itemCount}][subtotal]`;
        document.getElementById('item-list').appendChild(template);
    }

    function removeItem(button) {
        button.closest('.item-row').remove();
        updateTotal();
    }

    function updateTotal() {
        let total = 0;
        document.querySelectorAll('.item-row').forEach((row, index) => {
            const harga = parseFloat(row.querySelector(`[name="items[${index}][harga_beli]"]`).value) || 0;
            const qty = parseFloat(row.querySelector(`[name="items[${index}][qty]"]`).value) || 0;
            const subtotal = harga * qty;
            row.querySelector(`[name="items[${index}][subtotal_display]"]`).value = currencyFormatter.format(subtotal);
            row.querySelector(`[name="items[${index}][subtotal]"]`).value = subtotal;
            total += subtotal;
        });
        document.getElementById('total_beli').value = total;
        document.getElementById('total_display').value = currencyFormatter.format(total);
        updateKembali();
    }

    function updateKembali() {
        const total = parseFloat(document.getElementById('total_beli').value) || 0;
        const bayar = parseFloat(document.getElementById('bayar').value) || 0;
        const kembali = bayar >= total ? bayar - total : 0;
        document.getElementById('kembali').value = kembali;
        document.getElementById('kembali_display').value = currencyFormatter.format(kembali);
    }

    function deletePurchase(id_pembelian) {
        if (confirm('Apakah Anda yakin ingin menghapus transaksi pembelian ini? Stok akan dikembalikan.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="id_pembelian" value="${id_pembelian}">`;
            document.body.appendChild(form);
            form.submit();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (document.querySelectorAll('.item-row').length === 0) {
            addItem();
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>