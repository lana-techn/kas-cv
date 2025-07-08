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

<head>
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {}
            }
        }
    </script>
</head>

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

        <div id="salesManagement" class="section active">
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-800">Manajemen Penjualan</h2>
                <p class="text-gray-600 mt-2">Kelola transaksi penjualan produk jadi</p>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                    <div class="flex justify-between items-center card-header">
                        <div>
                            <h3 class="text-xl font-semibold text-white">Daftar Penjualan</h3>
                            <p class="text-blue-100 mt-1">Total: <?php echo count($sales); ?> transaksi</p>
                        </div>
                        <button onclick="showAddSaleForm()" class="add-button bg-white text-blue-600 hover:bg-blue-50 px-6 py-2 rounded-lg font-medium transition duration-200 flex items-center">
                            <i class="fas fa-plus mr-2"></i>Tambah Penjualan
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full responsive-table">
                            <thead>
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">ID Penjualan</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Tanggal</th>
                                    <th class="px-6 py-4 text-right text-sm font-semibold text-gray-700">Total</th>
                                    <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($sales as $sale): ?>
                                    <tr>
                                        <td data-label="ID" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($sale['id_penjualan']); ?></td>
                                        <td data-label="Tanggal" class="px-6 py-4 text-sm text-gray-900"><?php echo date('d M Y', strtotime($sale['tgl_jual'])); ?></td>
                                        <td data-label="Total" class="px-6 py-4 text-sm text-gray-900 text-right font-semibold"><?php echo formatCurrency($sale['total_jual']); ?></td>
                                        <td class="px-6 py-4 text-center actions-cell">
                                            <div class="flex justify-center space-x-2">
                                                <a href="faktur_penjualan.php?id=<?php echo $sale['id_penjualan']; ?>" class="text-blue-600 hover:text-blue-800 p-2 rounded-lg hover:bg-blue-100" title="Lihat Faktur">
                                                    <i class="fas fa-file-invoice"></i>
                                                </a>
                                                <button onclick="deleteSale('<?php echo $sale['id_penjualan']; ?>')" class="text-red-600 hover:text-red-800 p-2 rounded-lg hover:bg-red-100" title="Hapus">
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

        <div id="sale-modal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50 px-4 py-6">
            <div class="bg-gray-50 rounded-xl shadow-2xl w-full md:max-w-5xl mx-auto transform transition-all max-h-[90vh] flex flex-col modal-container">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-5 rounded-t-xl flex-shrink-0">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-white"><i class="fas fa-cash-register mr-3"></i>Input Data Penjualan</h3>
                        <button type="button" onclick="closeModalSales()" class="text-white hover:text-gray-200 text-2xl" title="Tutup">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </div>
                </div>

                <form id="sale-form" method="POST" class="flex flex-col flex-grow">
                    <div class="p-6 overflow-y-auto max-h-[calc(90vh-12rem)] flex-grow modal-content">
                        <input type="hidden" name="action" value="add">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 form-grid">
                            <div>
                                <label class="block text-gray-700 text-sm font-semibold mb-2">ID Penjualan</label>
                                <input type="text" name="id_penjualan" value="<?php echo generateId('JUL'); ?>" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-semibold mb-2">Tanggal</label>
                                <input type="date" name="tgl_jual" value="<?php echo $today; ?>" max="<?php echo $today; ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            </div>
                        </div>

                        <div class="border-t border-gray-300 pt-4 card-section">
                            <div class="flex flex-col md:flex-row justify-between items-center mb-3 flex-responsive">
                                <h4 class="text-md font-semibold text-gray-700">Item Penjualan</h4>
                                <button type="button" onclick="addItem()" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-md text-sm mt-2 md:mt-0 min-w-[120px]">
                                    <i class="fas fa-plus mr-1"></i> Tambah Item
                                </button>
                            </div>
                            <div class="overflow-y-auto max-h-[calc(60vh-8rem)]">
                                <table class="min-w-full responsive-form-table md:table hidden md:table">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-bold text-gray-600 uppercase w-2/5">Nama Produk</th>
                                            <th class="px-4 py-2 text-left text-xs font-bold text-gray-600 uppercase w-1/5">Harga</th>
                                            <th class="px-4 py-2 text-left text-xs font-bold text-gray-600 uppercase w-1/5">Qty</th>
                                            <th class="px-4 py-2 text-right text-xs font-bold text-gray-600 uppercase w-1/5">Subtotal</th>
                                            <th class="px-4 py-2 text-center text-xs font-bold text-gray-600 uppercase">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="item-list"></tbody>
                                </table>
                                <div id="item-list-mobile" class="block md:hidden space-y-4">
                                    <!-- Item akan ditambahkan di sini oleh JavaScript -->
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col md:flex-row justify-end mt-6 flex-responsive card-section">
                            <div class="w-full md:w-2/5 space-y-4">
                                <div>
                                    <label class="flex justify-between items-center text-gray-700 text-sm font-semibold"><span>Total Penjualan</span></label>
                                    <input type="text" id="total_display" value="Rp 0" readonly class="w-full px-3 py-2 border-gray-300 rounded-lg bg-gray-200 text-right font-bold text-base">
                                    <input type="hidden" name="total_jual" id="total_jual" value="0">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-semibold">Jumlah Bayar</label>
                                    <input type="number" name="bayar" id="bayar" required class="w-full px-3 py-2 border-gray-300 rounded-lg text-right font-bold text-base" oninput="updateKembali()">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-semibold">Kembali</label>
                                    <input type="text" id="kembali_display" value="Rp 0" readonly class="w-full px-3 py-2 border-gray-300 rounded-lg bg-gray-200 text-right font-bold text-base">
                                    <input type="hidden" name="kembali" id="kembali" value="0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row justify-end items-center space-y-2 md:space-x-4 md:space-y-0 p-4 bg-gray-200 border-t border-gray-300 flex-shrink-0 button-container">
                        <button type="button" onclick="closeModalSales()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg text-sm min-w-[120px]">Batal</button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg text-sm min-w-[120px]">Simpan Transaksi</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<template id="item-template">
    <tr class="item-row">
        <td data-label="Produk">
            <select name="kd_barang" required class="w-full p-2 border border-gray-300 rounded-md text-sm" onchange="checkStock(this)">
                <option value="" disabled selected>Cari Produk...</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['kd_barang']; ?>" data-stok="<?php echo $product['stok']; ?>"><?php echo htmlspecialchars($product['nama_barang']) . ' (Stok: ' . $product['stok'] . ')'; ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td data-label="Harga">
            <input type="number" name="harga_jual" required class="w-full p-2 border border-gray-300 rounded-md text-right text-sm" placeholder="0" oninput="updateTotal()">
        </td>
        <td data-label="Qty">
            <input type="number" name="qty" required class="w-full p-2 border border-gray-300 rounded-md text-right text-sm" placeholder="0" oninput="updateTotal(); checkStock(this);">
        </td>
        <td data-label="Subtotal">
            <input type="text" name="subtotal_display" readonly class="w-full p-2 border-gray-300 rounded-md bg-gray-200 text-right font-semibold text-sm">
            <input type="hidden" name="subtotal">
        </td>
        <td>
            <button type="button" class="text-red-500 hover:text-red-700 p-2" onclick="removeItem(this)" title="Hapus item">
                <i class="fas fa-trash-alt"></i>
            </button>
        </td>
    </tr>
</template>

<script>
    const currencyFormatter = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    });

    function showAddSaleForm() {
        document.getElementById('sale-form').reset();
        document.getElementById('item-list').innerHTML = '';
        document.getElementById('item-list-mobile').innerHTML = '';
        addItem();
        updateTotal();
        document.getElementById('sale-modal').classList.remove('hidden');
    }

    function closeModalSales() {
        document.getElementById('sale-modal').classList.add('hidden');
    }

    function addItem() {
        const template = document.getElementById('item-template');
        if (!template || !template.content) {
            console.error('Template item-template tidak ditemukan atau kontennya kosong.');
            return;
        }

        const itemCount = document.querySelectorAll('#item-list tr').length;
        const desktopItem = template.content.cloneNode(true).querySelector('.item-row');
        const mobileItem = template.content.cloneNode(true).querySelector('.item-row');

        // Konfigurasi nama field untuk desktop
        desktopItem.querySelector('[name="kd_barang"]').name = `items[${itemCount}][kd_barang]`;
        desktopItem.querySelector('[name="harga_jual"]').name = `items[${itemCount}][harga_jual]`;
        desktopItem.querySelector('[name="qty"]').name = `items[${itemCount}][qty]`;
        desktopItem.querySelector('[name="subtotal_display"]').name = `items[${itemCount}][subtotal_display]`;
        desktopItem.querySelector('[name="subtotal"]').name = `items[${itemCount}][subtotal]`;

        // Konfigurasi nama field untuk mobile
        mobileItem.querySelector('[name="kd_barang"]').name = `items[${itemCount}][kd_barang]`;
        mobileItem.querySelector('[name="harga_jual"]').name = `items[${itemCount}][harga_jual]`;
        mobileItem.querySelector('[name="qty"]').name = `items[${itemCount}][qty]`;
        mobileItem.querySelector('[name="subtotal_display"]').name = `items[${itemCount}][subtotal_display]`;
        mobileItem.querySelector('[name="subtotal"]').name = `items[${itemCount}][subtotal]`;

        // Tambah ke tabel untuk desktop
        document.getElementById('item-list').appendChild(desktopItem);

        // Ubah layout untuk mobile
        const mobileDiv = document.createElement('div');
        mobileDiv.classList.add('grid', 'grid-cols-1', 'gap-4', 'p-3', 'border', 'rounded-lg', 'bg-gray-50', 'item-row');
        mobileItem.querySelectorAll('td').forEach((td, index) => {
            const label = td.getAttribute('data-label');
            const input = td.querySelector('select, input, button');
            if (index < 4 && input) {
                const labelElement = document.createElement('label');
                labelElement.classList.add('block', 'text-gray-700', 'text-sm', 'font-bold', 'mb-1');
                labelElement.textContent = label;
                const wrapper = document.createElement('div');
                wrapper.appendChild(labelElement);
                wrapper.appendChild(input);
                mobileDiv.appendChild(wrapper);
            } else if (input) {
                mobileDiv.appendChild(input); // Tombol aksi
            }
        });
        document.getElementById('item-list-mobile').appendChild(mobileDiv);
    }

    function removeItem(button) {
        const row = button.closest('.item-row');
        row.remove();
        updateTotal();
        // Sinkronkan penghapusan pada mobile
        const itemIndex = Array.from(document.querySelectorAll('#item-list .item-row')).indexOf(row);
        if (itemIndex !== -1) {
            document.querySelectorAll('#item-list-mobile .item-row')[itemIndex]?.remove();
        }
    }

    function checkStock(element) {
        const row = element.closest('.item-row');
        const productSelect = row.querySelector('[name*="[kd_barang]"]');
        const qtyInput = row.querySelector('[name*="[qty]"]');
        const selectedOption = productSelect.options[productSelect.selectedIndex];

        if (selectedOption && selectedOption.value) {
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
        document.querySelectorAll('#item-list .item-row').forEach(row => {
            const harga = parseFloat(row.querySelector('[name*="[harga_jual]"]').value) || 0;
            const qty = parseFloat(row.querySelector('[name*="[qty]"]').value) || 0;
            const subtotal = harga * qty;
            row.querySelector('[name*="[subtotal_display]"]').value = currencyFormatter.format(subtotal);
            row.querySelector('[name*="[subtotal]"]').value = subtotal;
            total += subtotal;
        });

        document.getElementById('total_jual').value = total;
        document.getElementById('total_display').value = currencyFormatter.format(total);
        updateKembali();
    }

    function updateKembali() {
        const total = parseFloat(document.getElementById('total_jual').value) || 0;
        const bayar = parseFloat(document.getElementById('bayar').value) || 0;
        const kembali = bayar > total ? bayar - total : 0;

        document.getElementById('kembali').value = kembali;
        document.getElementById('kembali_display').value = currencyFormatter.format(kembali);
    }

    function deleteSale(id_penjualan) {
        if (confirm('Apakah Anda yakin ingin menghapus transaksi penjualan ini? Tindakan ini akan mengembalikan stok produk ke jumlah semula dan tidak dapat dibatalkan.')) {
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
            }, 3000);
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>