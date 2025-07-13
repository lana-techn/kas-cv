<?php
// ===== BAGIAN 1: LOGIKA PHP & AJAX HANDLER =====
require_once '../config/db_connect.php';
require_once '../includes/function.php';

// Pastikan session aktif dan periksa hak akses
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['level'], ['admin', 'pegawai'])) {
    header('Location: login.php');
    exit;
}

// Ambil pesan dari sesi (jika ada) untuk notifikasi
$message = $_SESSION['message'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['message'], $_SESSION['error']);

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
        error_log('Sales AJAX Error: ' . $e->getMessage());
        $response['message'] = 'Terjadi kesalahan pada server.';
    }

    echo json_encode($response);
    exit;
}

/**
 * FORM POST HANDLER: Menangani Tambah & Edit Penjualan.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $items = $_POST['items'] ?? [];

    // Validasi server-side
    if (empty($items)) {
        $_SESSION['error'] = 'Transaksi harus memiliki minimal satu item.';
        header('Location: sales_management.php');
        exit;
    }
    if (empty($_POST['tgl_jual']) || !isset($_POST['total_jual']) || !isset($_POST['bayar']) || floatval($_POST['bayar']) < floatval($_POST['total_jual'])) {
        $_SESSION['error'] = 'Data tidak lengkap atau jumlah bayar kurang dari total.';
        header('Location: sales_management.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        if ($action === 'add') {
            $id_penjualan = $_POST['id_penjualan'];
            $stmt = $pdo->prepare("INSERT INTO penjualan (id_penjualan, tgl_jual, total_jual, bayar, kembali) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id_penjualan, $_POST['tgl_jual'], $_POST['total_jual'], $_POST['bayar'], $_POST['kembali']]);

            foreach ($items as $item) {
                // Kunci baris untuk mencegah race condition saat cek dan update stok
                $stmt_stock = $pdo->prepare("SELECT stok FROM barang WHERE kd_barang = ? FOR UPDATE");
                $stmt_stock->execute([$item['kd_barang']]);
                $current_stock = $stmt_stock->fetchColumn();

                if ($current_stock < $item['qty']) {
                    throw new Exception("Stok untuk barang {$item['kd_barang']} tidak mencukupi. Sisa: {$current_stock}");
                }

                $stmt_detail = $pdo->prepare("INSERT INTO detail_penjualan (id_detail_jual, id_penjualan, kd_barang, harga_jual, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_detail->execute([generateId('DJL'), $id_penjualan, $item['kd_barang'], $item['harga_jual'], $item['qty'], $item['subtotal']]);
                
                $pdo->prepare("UPDATE barang SET stok = stok - ? WHERE kd_barang = ?")->execute([$item['qty'], $item['kd_barang']]);
            }

            $pdo->prepare("INSERT INTO penerimaan_kas (id_penerimaan_kas, id_penjualan, tgl_terima_kas, uraian, total) VALUES (?, ?, ?, ?, ?)")->execute([generateId('PKS'), $id_penjualan, $_POST['tgl_jual'], 'Penjualan Produk ' . $id_penjualan, $_POST['total_jual']]);
            
            $_SESSION['message'] = 'Penjualan berhasil ditambahkan.';
            unset($_SESSION['temp_sale_id']);

        } elseif ($action === 'edit') {
            $id_penjualan = $_POST['id_penjualan'];
            if (empty($id_penjualan)) throw new Exception("ID Penjualan tidak valid untuk diedit.");

            // 1. Kembalikan stok lama
            $stmt_old_items = $pdo->prepare("SELECT kd_barang, qty FROM detail_penjualan WHERE id_penjualan = ?");
            $stmt_old_items->execute([$id_penjualan]);
            foreach ($stmt_old_items->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $pdo->prepare("UPDATE barang SET stok = stok + ? WHERE kd_barang = ?")->execute([$item['qty'], $item['kd_barang']]);
            }

            // 2. Hapus detail lama
            $pdo->prepare("DELETE FROM detail_penjualan WHERE id_penjualan = ?")->execute([$id_penjualan]);

            // 3. Update data utama
            $pdo->prepare("UPDATE penjualan SET tgl_jual = ?, total_jual = ?, bayar = ?, kembali = ? WHERE id_penjualan = ?")->execute([$_POST['tgl_jual'], $_POST['total_jual'], $_POST['bayar'], $_POST['kembali'], $id_penjualan]);

            // 4. Masukkan detail baru dan kurangi stok baru
            foreach ($items as $item) {
                $stmt_stock = $pdo->prepare("SELECT stok FROM barang WHERE kd_barang = ? FOR UPDATE");
                $stmt_stock->execute([$item['kd_barang']]);
                $current_stock = $stmt_stock->fetchColumn();

                if ($current_stock < $item['qty']) {
                    throw new Exception("Stok untuk barang {$item['kd_barang']} tidak mencukupi setelah update. Sisa: {$current_stock}");
                }
                
                $pdo->prepare("INSERT INTO detail_penjualan (id_detail_jual, id_penjualan, kd_barang, harga_jual, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)")->execute([generateId('DJL'), $id_penjualan, $item['kd_barang'], $item['harga_jual'], $item['qty'], $item['subtotal']]);
                $pdo->prepare("UPDATE barang SET stok = stok - ? WHERE kd_barang = ?")->execute([$item['qty'], $item['kd_barang']]);
            }

            // 5. Update kas
            $pdo->prepare("UPDATE penerimaan_kas SET tgl_terima_kas = ?, total = ? WHERE id_penjualan = ?")->execute([$_POST['tgl_jual'], $_POST['total_jual'], $id_penjualan]);
            
            $_SESSION['message'] = 'Penjualan berhasil diupdate.';
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Transaksi Gagal: ' . $e->getMessage();
    }
    
    header('Location: sales_management.php');
    exit;
}

// ===== BAGIAN 2: LOGIKA PENGAMBILAN DATA UNTUK TAMPILAN =====
require_once '../includes/header.php';

// Buat ID transaksi sementara untuk form tambah baru
if (!isset($_SESSION['temp_sale_id'])) {
    $_SESSION['temp_sale_id'] = generateId('JUL');
}

// Logika pagination dan pencarian
$search_query = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM penjualan WHERE id_penjualan LIKE :search");
$stmtCount->execute([':search' => '%' . $search_query . '%']);
$totalItems = $stmtCount->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

$stmt = $pdo->prepare("SELECT * FROM penjualan WHERE id_penjualan LIKE :search ORDER BY tgl_jual DESC, id_penjualan DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':search', '%' . $search_query . '%');
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Data untuk modal form
$products = $pdo->query("SELECT kd_barang, nama_barang, stok FROM barang ORDER BY nama_barang")->fetchAll(PDO::FETCH_ASSOC);
$today = date('Y-m-d');
?>

<!-- ===== BAGIAN 3: KODE HTML & JAVASCRIPT ===== -->
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<div class="flex min-h-screen ">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">

        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                <div class="flex justify-between items-center card-header">
                    <div>
                        <h3 class="text-xl font-semibold text-white">Manajemen Penjualan</h3>
                        <p class="text-green-100 mt-1">Total: <?php echo $totalItems; ?> transaksi</p>
                    </div>
                    <button onclick="showAddSaleForm( )" class="add-button bg-white text-green-600 hover:bg-green-50 px-6 py-2 rounded-lg font-medium transition duration-200 flex items-center">
                        <i class="fas fa-plus mr-2"></i>Tambah Penjualan
                    </button>
                </div>
            </div>

            <div class="p-6">
                <!-- Blok Notifikasi -->
                <?php if ($message): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                        <p><?php echo htmlspecialchars($message); ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Form Pencarian -->
                <form method="GET" action="sales_management.php" class="mb-6 relative">
                    <input type="text" name="search" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 pr-10" placeholder="Cari ID penjualan..." value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-green-500">
                        <i class="fas fa-search"></i>
                    </button>
                </form>

                <!-- Tabel Data -->
                <div class="overflow-x-auto">
                    <table class="min-w-full border border-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Id Jual</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($sales)): ?>
                                <tr>
                                    <td colspan="5" class="text-center p-5 text-gray-500">Tidak ada data penjualan.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sales as $index => $sale): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 text-sm text-gray-500"><?php echo $offset + $index + 1; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($sale['id_penjualan']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo date('d M Y', strtotime($sale['tgl_jual'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-semibold"><?php echo formatCurrency($sale['total_jual']); ?></td>
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
                
                <!-- Pagination -->
                <div class="flex justify-between items-center mt-4">
                    <span class="text-sm text-gray-700">
                        Menampilkan <?php echo $offset + 1; ?> sampai <?php echo min($offset + $perPage, $totalItems); ?> dari <?php echo $totalItems; ?> data
                    </span>
                    <div class="flex items-center space-x-2 text-sm">
                        <a href="?search=<?php echo urlencode($search_query); ?>&page=<?php echo max(1, $page - 1); ?>" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 <?php echo $page <= 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>">Prev</a>
                        <span class="px-3 py-1 bg-green-500 text-white rounded"><?php echo $page; ?></span>
                        <a href="?search=<?php echo urlencode($search_query); ?>&page=<?php echo min($totalPages, $page + 1); ?>" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 <?php echo $page >= $totalPages ? 'opacity-50 cursor-not-allowed' : ''; ?>">Next</a>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal Form Penjualan -->
<div id="sale-modal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50 p-4">
    <div class="bg-gray-50 rounded-xl shadow-2xl w-full max-w-4xl mx-auto max-h-[90vh] flex flex-col">
        <form id="sale-form" method="POST" action="sales_management.php" class="flex flex-col flex-grow">
            <div class="bg-gradient-to-r from-green-500 to-green-600 p-5 rounded-t-xl flex justify-between items-center">
                <h3 id="modal-title" class="text-xl font-semibold text-white">Input Data Penjualan</h3>
                <button type="button" onclick="closeModalSales()" class="text-white hover:text-gray-200 text-2xl">&times;</button>
            </div>

            <div class="p-6 overflow-y-auto space-y-6">
                <input type="hidden" name="action" id="form-action">

                <div class="bg-white p-5 rounded-lg shadow">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">ID Penjualan</label>
                            <input type="text" name="id_penjualan" id="form-id-penjualan" readonly class="w-full px-4 py-2 border rounded-lg bg-gray-200">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal</label>
                            <input type="date" name="tgl_jual" id="form-tgl-jual" value="<?php echo $today; ?>" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                        </div>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-lg shadow">
                    <div class="flex justify-between items-center mb-4 border-b pb-2">
                        <h4 class="font-semibold text-gray-700">Item Penjualan</h4>
                        <button type="button" onclick="addItem()" class="bg-green-500 hover:bg-green-600 text-white font-bold py-1 px-3 rounded-md text-sm flex items-center"><i class="fas fa-plus mr-1"></i> Tambah</button>
                    </div>
                    <div id="item-list" class="space-y-3"></div>
                </div>
                <div class="bg-white p-5 rounded-lg shadow">
                    <div class="md:w-1/2 md:ml-auto space-y-3">
                        <div class="flex justify-between items-center"><span class="font-semibold text-gray-700">Total</span><input type="text" id="total_display" readonly class="text-right font-bold text-lg bg-transparent border-0 focus:ring-0"></div>
                        <input type="hidden" name="total_jual" id="total_jual">
                        <div class="flex justify-between items-center"><span class="font-semibold text-gray-700">Bayar</span><input type="number" name="bayar" id="bayar" required class="w-1/2 px-3 py-2 border rounded-lg text-right font-bold" min="0" oninput="updateKembali()"></div>
                        <div class="flex justify-between items-center"><span class="font-semibold text-gray-700">Kembali</span><input type="text" id="kembali_display" readonly class="text-right font-bold text-lg text-green-600 bg-transparent border-0 focus:ring-0"></div>
                        <input type="hidden" name="kembali" id="kembali">
                    </div>
                </div>
            </div>

            <div class="flex justify-end space-x-4 p-4 bg-gray-100 border-t rounded-b-xl">
                <button type="button" onclick="closeModalSales()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-6 rounded-lg">Batal</button>
                <button type="submit" id="submit-button" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Template untuk baris item -->
<template id="item-template">
    <div class="item-row grid grid-cols-12 gap-x-4 gap-y-2 items-center p-3 border rounded-lg bg-gray-50">
        <div class="col-span-12 md:col-span-4">
            <label class="text-xs font-medium text-gray-600">Barang</label>
            <select name="items[0][kd_barang]" required class="w-full p-2 border rounded-md text-sm" onchange="checkStock(this)">
                <option value="">Pilih Barang...</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?php echo htmlspecialchars($p['kd_barang']); ?>" data-stok="<?php echo htmlspecialchars($p['stok']); ?>">
                        <?php echo htmlspecialchars($p['nama_barang']) . ' (Stok: ' . htmlspecialchars($p['stok']) . ')'; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-span-6 md:col-span-2">
            <label class="text-xs font-medium text-gray-600">Harga</label>
            <input type="number" name="items[0][harga_jual]" required class="w-full p-2 border rounded-md text-right text-sm" placeholder="0" oninput="updateTotal(this)">
        </div>
        <div class="col-span-6 md:col-span-2">
            <label class="text-xs font-medium text-gray-600">Jumlah</label>
            <input type="number" name="items[0][qty]" required value="1" class="w-full p-2 border rounded-md text-right text-sm" placeholder="0" oninput="updateTotal(this); checkStock(this);">
        </div>
        <div class="col-span-10 md:col-span-3">
            <label class="text-xs font-medium text-gray-600">Subtotal</label>
            <input type="text" name="items[0][subtotal_display]" readonly class="w-full p-2 border-0 bg-transparent text-right font-bold text-emerald-700 text-sm">
        </div>
        <div class="col-span-2 md:col-span-1 flex items-end justify-center">
            <button type="button" class="w-8 h-8 flex items-center justify-center text-red-500 bg-red-100 rounded-full hover:bg-red-200" onclick="removeItem(this)" title="Hapus item">
                <i class="fas fa-trash-alt text-sm"></i>
            </button>
        </div>
        <input type="hidden" name="items[0][subtotal]">
    </div>
</template>

<script>
    const modal = document.getElementById('sale-modal');
    const form = document.getElementById('sale-form');
    const itemList = document.getElementById('item-list');
    const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });
    let itemIndex = 0;
    const tempSaleId = '<?php echo htmlspecialchars($_SESSION['temp_sale_id']); ?>';

    function closeModalSales() {
        modal.classList.add('hidden');
    }

    function prepareSaleForm(mode, data = {}) {
        form.reset();
        itemList.innerHTML = '';
        itemIndex = 0;
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
            form.querySelector('[name="id_penjualan"]').value = tempSaleId;
            addItem();
        }

        updateTotal();
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function showAddSaleForm() {
        prepareSaleForm('add');
    }

    async function showEditSaleForm(id, button) {
        const originalIcon = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;

        try {
            const response = await fetch(`sales_management.php?action=get_sale_details&id=${id}`);
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
            button.innerHTML = originalIcon;
            button.disabled = false;
        }
    }

    function addItem() {
        const template = document.getElementById('item-template').content.cloneNode(true);
        const itemRow = template.querySelector('.item-row');
        itemRow.querySelectorAll('select, input').forEach(input => {
            let name = input.getAttribute('name');
            if (name) input.name = name.replace('[0]', `[${itemIndex}]`);
        });
        itemList.appendChild(template);
        itemIndex++;
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
                alert(`Stok tidak mencukupi! Sisa stok untuk produk ini: ${stok}.`);
                qtyInput.value = stok;
                updateTotal();
            }
        }
    }

    function updateTotal(element) {
        if (element) checkStock(element); // Cek stok setiap kali ada input
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
        const bayar = parseFloat(document.getElementById('bayar').value) || 0;
        const kembali = bayar >= total ? bayar - total : 0;
        document.getElementById('kembali').value = kembali;
        document.getElementById('kembali_display').value = currencyFormatter.format(kembali);
    }

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModalSales();
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>