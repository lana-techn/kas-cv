<?php
// ===== BAGIAN 1: LOGIKA PHP & AJAX HANDLER =====
require_once '../config/db_connect.php';
require_once '../includes/function.php';

// Pastikan session aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * AJAX HANDLER: Mengambil detail penjualan untuk form edit.
 */
if (isset($_GET['action']) && $_GET['action'] === 'get_sale_details') {
    header('Content-Type: application/json');
    $id_penjualan = $_GET['id'] ?? null;
    $response = ['success' => false];

    if (!$id_penjualan) {
        $response['message'] = 'ID Penjualan tidak valid.';
        echo json_encode($response);
        exit;
    }

    try {
        $stmt_main = $pdo->prepare("SELECT * FROM penjualan WHERE id_penjualan = ?");
        $stmt_main->execute([$id_penjualan]);
        $sale = $stmt_main->fetch(PDO::FETCH_ASSOC);

        if ($sale) {
            $stmt_items = $pdo->prepare("SELECT * FROM detail_penjualan WHERE id_penjualan = ?");
            $stmt_items->execute([$id_penjualan]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'sale' => $sale, 'items' => $items];
        } else {
            $response['message'] = 'Data penjualan tidak ditemukan.';
        }
    } catch (PDOException $e) {
        $response['message'] = 'Database Error: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}


// Sertakan header setelah blok AJAX selesai.
require_once '../includes/header.php';

// Cek hak akses
if (!in_array($_SESSION['user']['level'], ['admin', 'pegawai'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';

// Logika untuk menangani form POST (tambah, edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo->beginTransaction();

        if ($_POST['action'] === 'add') {
            if (empty($_POST['tgl_jual']) || !isset($_POST['total_jual']) || !isset($_POST['bayar'])) throw new Exception('Field utama harus diisi.');
            if (empty($_POST['items'])) throw new Exception('Minimal satu item harus ditambahkan.');
            if ($_POST['bayar'] < $_POST['total_jual']) throw new Exception('Jumlah bayar tidak boleh kurang dari total.');

            $id_penjualan = $_POST['id_penjualan']; // Ambil dari form yang menggunakan session
            $stmt = $pdo->prepare("INSERT INTO penjualan (id_penjualan, tgl_jual, total_jual, bayar, kembali) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id_penjualan, $_POST['tgl_jual'], $_POST['total_jual'], $_POST['bayar'], $_POST['kembali']]);

            foreach ($_POST['items'] as $item) {
                if (empty($item['kd_barang']) || empty($item['qty']) || !isset($item['harga_jual'])) continue;
                $stmt_stock = $pdo->prepare("SELECT stok FROM barang WHERE kd_barang = ?");
                $stmt_stock->execute([$item['kd_barang']]);
                if ($stmt_stock->fetchColumn() < $item['qty']) throw new Exception("Stok untuk barang tidak mencukupi.");

                $stmt_detail = $pdo->prepare("INSERT INTO detail_penjualan (id_detail_jual, id_penjualan, kd_barang, harga_jual, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_detail->execute([generateId('DJL'), $id_penjualan, $item['kd_barang'], $item['harga_jual'], $item['qty'], $item['subtotal']]);
                $pdo->prepare("UPDATE barang SET stok = stok - ? WHERE kd_barang = ?")->execute([$item['qty'], $item['kd_barang']]);
            }

            $pdo->prepare("INSERT INTO penerimaan_kas (id_penerimaan_kas, id_penjualan, tgl_terima_kas, uraian, total) VALUES (?, ?, ?, ?, ?)")->execute([generateId('PKS'), $id_penjualan, $_POST['tgl_jual'], 'Penjualan Produk ' . $id_penjualan, $_POST['total_jual']]);
            $message = 'Penjualan berhasil disimpan.';
            unset($_SESSION['temp_sale_id']); // Hapus ID sementara setelah berhasil

        } elseif ($_POST['action'] === 'edit') {
            $id_penjualan = $_POST['id_penjualan'];
            if (empty($id_penjualan)) throw new Exception("ID Penjualan tidak valid untuk diedit.");

            // 1. Kembalikan stok lama
            $stmt_old_items = $pdo->prepare("SELECT kd_barang, qty FROM detail_penjualan WHERE id_penjualan = ?");
            $stmt_old_items->execute([$id_penjualan]);
            foreach ($stmt_old_items->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $pdo->prepare("UPDATE barang SET stok = stok + ? WHERE kd_barang = ?")->execute([$item['qty'], $item['kd_barang']]);
            }
            $pdo->prepare("DELETE FROM detail_penjualan WHERE id_penjualan = ?")->execute([$id_penjualan]);

            // 2. Update data utama & masukkan detail baru
            $pdo->prepare("UPDATE penjualan SET tgl_jual = ?, total_jual = ?, bayar = ?, kembali = ? WHERE id_penjualan = ?")->execute([$_POST['tgl_jual'], $_POST['total_jual'], $_POST['bayar'], $_POST['kembali'], $id_penjualan]);
            foreach ($_POST['items'] as $item) {
                if (empty($item['kd_barang']) || empty($item['qty']) || !isset($item['harga_jual'])) continue;
                $stmt_stock = $pdo->prepare("SELECT stok FROM barang WHERE kd_barang = ?");
                $stmt_stock->execute([$item['kd_barang']]);
                if ($stmt_stock->fetchColumn() < $item['qty']) throw new Exception("Stok tidak mencukupi saat update.");
                
                $pdo->prepare("INSERT INTO detail_penjualan (id_detail_jual, id_penjualan, kd_barang, harga_jual, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)")->execute([generateId('DJL'), $id_penjualan, $item['kd_barang'], $item['harga_jual'], $item['qty'], $item['subtotal']]);
                $pdo->prepare("UPDATE barang SET stok = stok - ? WHERE kd_barang = ?")->execute([$item['qty'], $item['kd_barang']]);
            }
            
            // 3. Update kas
            $pdo->prepare("UPDATE penerimaan_kas SET tgl_terima_kas = ?, total = ? WHERE id_penjualan = ?")->execute([$_POST['tgl_jual'], $_POST['total_jual'], $id_penjualan]);
            $message = 'Penjualan berhasil diupdate.';
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Buat ID sementara jika belum ada
if (!isset($_SESSION['temp_sale_id'])) {
    $_SESSION['temp_sale_id'] = generateId('JUL');
}

// Pengambilan data untuk ditampilkan
$sales = $pdo->query("SELECT * FROM penjualan ORDER BY tgl_jual DESC, id_penjualan DESC")->fetchAll(PDO::FETCH_ASSOC);
$products = $pdo->query("SELECT kd_barang, nama_barang, stok FROM barang ORDER BY nama_barang")->fetchAll(PDO::FETCH_ASSOC);
$today = date('Y-m-d');
?>

<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<div class="flex min-h-screen bg-gray-100">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        
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
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Id Jual</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Bayar</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Kembali</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                             <?php if(empty($sales)): ?>
                                <tr><td colspan="7" class="text-center p-5 text-gray-500">Tidak ada data penjualan.</td></tr>
                            <?php else: ?>
                                <?php foreach ($sales as $index => $sale): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 text-center text-sm text-gray-500"><?php echo $index + 1; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($sale['id_penjualan']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo date('d M Y', strtotime($sale['tgl_jual'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-semibold"><?php echo formatCurrency($sale['total_jual']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right"><?php echo formatCurrency($sale['bayar']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right"><?php echo formatCurrency($sale['kembali']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <div class="flex justify-center items-center space-x-3">
                                                <button onclick="showEditSaleForm('<?php echo $sale['id_penjualan']; ?>', this)" class="text-yellow-500 hover:text-yellow-700 p-2 rounded-lg hover:bg-yellow-100" title="Edit Penjualan"><i class="fas fa-edit fa-lg"></i></button>
                                                <a href="faktur_penjualan.php?id=<?php echo $sale['id_penjualan']; ?>" target="_blank" class="text-blue-500 hover:text-blue-700 p-2 rounded-lg hover:bg-blue-100" title="Cetak Faktur"><i class="fas fa-file-invoice fa-lg"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="sale-modal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50 p-4">
    <div class="bg-gray-50 rounded-xl shadow-2xl w-full max-w-4xl mx-auto max-h-[90vh] flex flex-col">
        <form id="sale-form" method="POST" class="flex flex-col flex-grow">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-5 rounded-t-xl flex justify-between items-center">
                <h3 id="modal-title" class="text-xl font-semibold text-white">Input Data Penjualan</h3>
                <button type="button" onclick="closeModalSales()" class="text-white hover:text-gray-200 text-2xl">&times;</button>
            </div>
            
            <div class="p-6 overflow-y-auto space-y-6">
                <input type="hidden" name="action" id="form-action">
                
                <div class="bg-white p-5 rounded-lg shadow">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                         <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">ID Penjualan</label>
                            <input type="text" name="id_penjualan" id="form-id-penjualan" value="<?php echo htmlspecialchars($_SESSION['temp_sale_id']); ?>" readonly class="w-full px-4 py-2 border rounded-lg bg-gray-200">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal</label>
                            <input type="date" name="tgl_jual" id="form-tgl-jual" value="<?php echo $today; ?>" required class="w-full px-4 py-2 border rounded-lg">
                        </div>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-lg shadow">
                    <div class="flex justify-between items-center mb-4 border-b pb-2">
                        <h4 class="font-semibold text-gray-700">Item Penjualan</h4>
                        <button type="button" onclick="addItem()" class="bg-green-500 hover:bg-green-600 text-white font-bold py-1 px-3 rounded-md text-sm">Tambah Item</button>
                    </div>
                    <div id="item-list" class="space-y-3"></div>
                </div>
                <div class="bg-white p-5 rounded-lg shadow">
                     <div class="md:w-1/2 md:ml-auto space-y-3">
                        <div class="flex justify-between items-center"><span class="font-semibold text-gray-700">Total</span><input type="text" id="total_display" readonly class="text-right font-bold text-lg bg-transparent"></div>
                        <input type="hidden" name="total_jual" id="total_jual">
                         <div class="flex justify-between items-center"><span class="font-semibold text-gray-700">Bayar</span><input type="number" name="bayar" id="bayar" required class="w-1/2 px-3 py-2 border rounded-lg text-right font-bold" min="0" oninput="updateKembali()"></div>
                        <div class="flex justify-between items-center"><span class="font-semibold text-gray-700">Kembali</span><input type="text" id="kembali_display" readonly class="text-right font-bold text-lg text-green-600 bg-transparent"></div>
                        <input type="hidden" name="kembali" id="kembali">
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-4 p-4 bg-gray-100 border-t">
                <button type="button" onclick="closeModalSales()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-6 rounded-lg">Batal</button>
                <button type="submit" id="submit-button" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg">Simpan</button>
            </div>
        </form>
    </div>
</div>

<template id="item-template">
    <div class="item-row grid grid-cols-12 gap-3 items-center p-3 border rounded-lg bg-gray-50">
        <div class="col-span-12 md:col-span-4"><select name="kd_barang" required class="w-full p-2 border rounded-md"><?php foreach ($products as $p): ?><option value="<?php echo $p['kd_barang']; ?>" data-stok="<?php echo $p['stok']; ?>"><?php echo htmlspecialchars($p['nama_barang']) . ' (Stok: ' . $p['stok'] . ')'; ?></option><?php endforeach; ?></select></div>
        <div class="col-span-6 md:col-span-2"><input type="number" name="harga_jual" required class="w-full p-2 border rounded-md text-right" placeholder="Harga" oninput="updateTotal()"></div>
        <div class="col-span-6 md:col-span-2"><input type="number" name="qty" required class="w-full p-2 border rounded-md text-right" placeholder="Qty" value="1" oninput="updateTotal(); checkStock(this);"></div>
        <div class="col-span-10 md:col-span-3"><input type="text" name="subtotal_display" readonly class="w-full p-2 bg-transparent text-right font-semibold"></div>
        <div class="col-span-2 md:col-span-1 text-center"><button type="button" class="text-red-500 hover:text-red-700" onclick="removeItem(this)"><i class="fas fa-trash-alt"></i></button></div>
        <input type="hidden" name="subtotal">
    </div>
</template>

<script>
    // === BAGIAN 3: JAVASCRIPT ===
    const modal = document.getElementById('sale-modal');
    const form = document.getElementById('sale-form');
    const itemList = document.getElementById('item-list');
    const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });

    function closeModalSales() { modal.classList.add('hidden'); }

    function prepareSaleForm(mode, data = {}) {
        form.reset();
        itemList.innerHTML = '';
        form.querySelector('[name="action"]').value = mode;
        document.getElementById('modal-title').innerText = mode === 'add' ? 'Tambah Penjualan Baru' : 'Edit Data Penjualan';
        document.getElementById('submit-button').innerText = mode === 'add' ? 'Simpan' : 'Update';

        if (mode === 'edit') {
            form.querySelector('[name="id_penjualan"]').value = data.sale.id_penjualan;
            form.querySelector('[name="tgl_jual"]').value = data.sale.tgl_jual;
            form.querySelector('[name="bayar"]').value = data.sale.bayar;
            
            data.items.forEach(itemData => {
                const itemRow = addItem();
                itemRow.querySelector('[name$="[kd_barang]"]').value = itemData.kd_barang;
                itemRow.querySelector('[name$="[harga_jual]"]').value = itemData.harga_jual;
                itemRow.querySelector('[name$="[qty]"]').value = itemData.qty;
            });
        } else {
             form.querySelector('[name="id_penjualan"]').value = '<?php echo htmlspecialchars($_SESSION['temp_sale_id']); ?>';
             addItem();
        }
        
        updateTotal();
        modal.classList.remove('hidden');
    }

    function showAddSaleForm() {
        prepareSaleForm('add');
    }

    async function showEditSaleForm(id, button) {
        button.disabled = true;
        const icon = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const response = await fetch(`?action=get_sale_details&id=${id}`);
            if (!response.ok) throw new Error('Gagal menghubungi server.');
            
            const data = await response.json();
            if (data.success) {
                prepareSaleForm('edit', data);
            } else {
                alert('Gagal memuat data: ' + data.message);
            }
        } catch (err) {
            alert('Terjadi kesalahan jaringan: ' + err.message);
        } finally {
            button.disabled = false;
            button.innerHTML = icon;
        }
    }

    function updateItemNames() {
        itemList.querySelectorAll('.item-row').forEach((row, index) => {
            row.querySelectorAll('select, input').forEach(input => {
                const currentName = input.getAttribute('name');
                if (currentName && !currentName.includes('[')) {
                    input.name = `items[${index}][${currentName}]`;
                }
            });
        });
    }

    function addItem() {
        const template = document.getElementById('item-template').content.cloneNode(true);
        itemList.appendChild(template);
        updateItemNames();
        return itemList.lastElementChild;
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

        if (selectedOption && selectedOption.value) {
            const stok = parseInt(selectedOption.getAttribute('data-stok'), 10);
            const qty = parseInt(qtyInput.value, 10) || 0;

            if (qty > stok) {
                alert(`Stok tidak mencukupi! Sisa stok: ${stok}.`);
                qtyInput.value = stok;
                updateTotal();
            }
        }
    }

    function updateTotal() {
        let total = 0;
        itemList.querySelectorAll('.item-row').forEach(row => {
            const harga = parseFloat(row.querySelector('[name$="[harga_jual]"]').value) || 0;
            const qty = parseFloat(row.querySelector('[name$="[qty]"]').value) || 0;
            const subtotal = harga * qty;
            row.querySelector('[name$="[subtotal_display]"]').value = currencyFormatter.format(subtotal);
            row.querySelector('[name$="[subtotal]"]').value = subtotal;
            total += subtotal;
        });
        document.getElementById('total_jual').value = total;
        document.getElementById('total_display').value = currencyFormatter.format(total);
        updateKembali();
    }

    function updateKembali() {
        const total = parseFloat(document.getElementById('total_jual').value) || 0;
        // PERBAIKAN: Menggunakan ID 'bayar' yang benar.
        const bayar = parseFloat(document.getElementById('bayar').value) || 0;
        const kembali = bayar >= total ? bayar - total : 0;
        document.getElementById('kembali').value = kembali;
        document.getElementById('kembali_display').value = currencyFormatter.format(kembali);
    }
</script>

<?php require_once '../includes/footer.php'; ?>