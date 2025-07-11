<?php
// ===== BAGIAN 1: LOGIKA PHP & AJAX HANDLER =====
require_once '../config/db_connect.php';
require_once '../includes/function.php';

/**
 * AJAX HANDLER: Mengambil detail pembelian untuk form edit.
 */
if (isset($_GET['action']) && $_GET['action'] === 'get_purchase_details') {
    header('Content-Type: application/json');
    $id_pembelian = $_GET['id'] ?? null;
    $response = ['success' => false];

    if (!$id_pembelian) {
        $response['message'] = 'ID Pembelian tidak valid.';
        echo json_encode($response);
        exit;
    }

    try {
        $stmt_main = $pdo->prepare("SELECT * FROM pembelian WHERE id_pembelian = ?");
        $stmt_main->execute([$id_pembelian]);
        $purchase = $stmt_main->fetch(PDO::FETCH_ASSOC);

        if ($purchase) {
            $stmt_items = $pdo->prepare("SELECT * FROM detail_pembelian WHERE id_pembelian = ?");
            $stmt_items->execute([$id_pembelian]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'purchase' => $purchase, 'items' => $items];
        } else {
            $response['message'] = 'Data pembelian tidak ditemukan.';
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
$search_query = $_GET['search'] ?? '';

// Logika untuk menangani form POST (tambah, edit, hapus)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo->beginTransaction();

        if ($_POST['action'] === 'add') {
            if (empty($_POST['tgl_beli']) || empty($_POST['id_supplier'])) throw new Exception('Tanggal dan Supplier harus diisi.');
            if (empty($_POST['items'])) throw new Exception('Minimal satu item harus ditambahkan.');
            if ($_POST['bayar'] < $_POST['total_beli']) throw new Exception('Jumlah bayar tidak boleh kurang dari total.');

            $id_pembelian = generateId('BL');
            $stmt = $pdo->prepare("INSERT INTO pembelian (id_pembelian, tgl_beli, id_supplier, total_beli, bayar, kembali) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_pembelian, $_POST['tgl_beli'], $_POST['id_supplier'], $_POST['total_beli'], $_POST['bayar'], $_POST['kembali']]);

            foreach ($_POST['items'] as $item) {
                if (empty($item['kd_bahan']) || empty($item['qty']) || !isset($item['harga_beli'])) continue;
                $stmt_detail = $pdo->prepare("INSERT INTO detail_pembelian (id_detail_beli, id_pembelian, kd_bahan, harga_beli, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_detail->execute([generateId('DBL'), $id_pembelian, $item['kd_bahan'], $item['harga_beli'], $item['qty'], $item['subtotal']]);
                $stmt_stok = $pdo->prepare("UPDATE bahan SET stok = stok + ? WHERE kd_bahan = ?");
                $stmt_stok->execute([$item['qty'], $item['kd_bahan']]);
            }

            $stmt_kas = $pdo->prepare("INSERT INTO pengeluaran_kas (id_pengeluaran_kas, id_pembelian, tgl_pengeluaran_kas, uraian, total) VALUES (?, ?, ?, ?, ?)");
            $stmt_kas->execute([generateId('PKS'), $id_pembelian, $_POST['tgl_beli'], 'Pembelian Bahan ' . $id_pembelian, $_POST['total_beli']]);
            $message = 'Pembelian berhasil disimpan.';

        } elseif ($_POST['action'] === 'edit') {
            $id_pembelian = $_POST['id_pembelian'];
            if (empty($id_pembelian)) throw new Exception("ID Pembelian tidak valid untuk diedit.");

            // 1. Kembalikan stok lama
            $stmt_old_items = $pdo->prepare("SELECT kd_bahan, qty FROM detail_pembelian WHERE id_pembelian = ?");
            $stmt_old_items->execute([$id_pembelian]);
            foreach ($stmt_old_items->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $pdo->prepare("UPDATE bahan SET stok = stok - ? WHERE kd_bahan = ?")->execute([$item['qty'], $item['kd_bahan']]);
            }

            // 2. Hapus detail lama
            $pdo->prepare("DELETE FROM detail_pembelian WHERE id_pembelian = ?")->execute([$id_pembelian]);

            // 3. Update data utama
            $stmt_update = $pdo->prepare("UPDATE pembelian SET tgl_beli = ?, id_supplier = ?, total_beli = ?, bayar = ?, kembali = ? WHERE id_pembelian = ?");
            $stmt_update->execute([$_POST['tgl_beli'], $_POST['id_supplier'], $_POST['total_beli'], $_POST['bayar'], $_POST['kembali'], $id_pembelian]);

            // 4. Masukkan detail baru dan tambahkan stok baru
            foreach ($_POST['items'] as $item) {
                if (empty($item['kd_bahan']) || empty($item['qty']) || !isset($item['harga_beli'])) continue;
                $stmt_detail = $pdo->prepare("INSERT INTO detail_pembelian (id_detail_beli, id_pembelian, kd_bahan, harga_beli, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_detail->execute([generateId('DBL'), $id_pembelian, $item['kd_bahan'], $item['harga_beli'], $item['qty'], $item['subtotal']]);
                $pdo->prepare("UPDATE bahan SET stok = stok + ? WHERE kd_bahan = ?")->execute([$item['qty'], $item['kd_bahan']]);
            }
            
            // 5. Update kas
            $pdo->prepare("UPDATE pengeluaran_kas SET tgl_pengeluaran_kas = ?, total = ? WHERE id_pembelian = ?")->execute([$_POST['tgl_beli'], $_POST['total_beli'], $id_pembelian]);
            $message = 'Pembelian berhasil diupdate.';
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Pengambilan data untuk ditampilkan
$sql = "SELECT p.*, s.nama_supplier 
        FROM pembelian p 
        JOIN supplier s ON p.id_supplier = s.id_supplier ORDER BY p.tgl_beli DESC, p.id_pembelian DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

$suppliers = $pdo->query("SELECT id_supplier, nama_supplier FROM supplier ORDER BY nama_supplier")->fetchAll(PDO::FETCH_ASSOC);
$materials = $pdo->query("SELECT kd_bahan, nama_bahan FROM bahan ORDER BY nama_bahan")->fetchAll(PDO::FETCH_ASSOC);
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
                            <h3 class="text-xl font-semibold text-white">Daftar Pembelian</h3>
                            <p class="text-blue-100 mt-1">Total: <?php echo count($purchases); ?> transaksi</p>
                        </div>
                         <button onclick="showAddPurchaseForm()" class="add-button bg-white text-blue-600 hover:bg-blue-50 px-6 py-2 rounded-lg font-medium transition duration-200 flex items-center">
                        <i class="fas fa-plus mr-2"></i>Tambah Data
                    </button>
                    </div>
            </div>
            
            <div class="p-6">
                <div class="mb-6 relative">
                    <input type="text" id="searchInput" name="search" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10" placeholder="Cari ID pembelian atau nama supplier..." value="<?php echo htmlspecialchars($search_query); ?>">
                    <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                        <i class="fas fa-search"></i>
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full border border-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Id Beli</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Bayar</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Kembali</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="purchasesTableBody">
                            <?php if (empty($purchases)): ?>
                                <tr><td colspan="8" class="text-center p-5 text-gray-500">Tidak ada data pembelian.</td></tr>
                            <?php else: ?>
                                <?php foreach ($purchases as $index => $purchase): ?>
                                    <tr class="purchase-row" data-name="<?php echo strtolower(htmlspecialchars($purchase['id_pembelian'] . ' ' . $purchase['nama_supplier'])); ?>">
                                        <td class="px-6 py-4 text-center text-sm text-gray-500"><?php echo $index + 1; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($purchase['id_pembelian']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo date('d M Y', strtotime($purchase['tgl_beli'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($purchase['nama_supplier']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-semibold"><?php echo formatCurrency($purchase['total_beli']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right"><?php echo formatCurrency($purchase['bayar']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right"><?php echo formatCurrency($purchase['kembali']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <div class="flex justify-center items-center space-x-3">
                                                <button onclick="showEditPurchaseForm('<?php echo $purchase['id_pembelian']; ?>', this)" class="text-yellow-500 hover:text-yellow-700 p-2 rounded-lg hover:bg-yellow-100 transition-colors duration-200" title="Edit">
                                                    <i class="fas fa-edit fa-lg"></i>
                                                </button>
                                                <a href="faktur_pembelian.php?id=<?php echo $purchase['id_pembelian']; ?>" target="_blank" class="text-blue-500 hover:text-blue-700 p-2 rounded-lg hover:bg-blue-100 transition-colors duration-200" title="Cetak Faktur">
                                                    <i class="fas fa-file-invoice fa-lg"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="flex justify-end mt-2 p-2">
                    <div class="flex items-center space-x-2 text-sm">
                        <a href="?search=<?php echo urlencode($search_query); ?>&page=<?php echo max(1, $page - 1); ?>" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300 <?php echo $page <= 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>">Prev</a>
                        <span class="px-2"><?php echo $page; ?> / <?php echo $totalPages; ?></span>
                        <a href="?search=<?php echo urlencode($search_query); ?>&page=<?php echo min($totalPages, $page + 1); ?>" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300 <?php echo $page >= $totalPages ? 'opacity-50 cursor-not-allowed' : ''; ?>">Next</a>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="purchase-modal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50 p-4">
    <div class="bg-gray-50 rounded-xl shadow-2xl w-full max-w-4xl mx-auto max-h-[90vh] flex flex-col">
        <form id="purchase-form" method="POST" class="flex flex-col flex-grow">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-5 rounded-t-xl flex justify-between items-center">
                <h3 id="modal-title" class="text-xl font-semibold text-white">Input Data Pembelian</h3>
                <button type="button" onclick="closeModalPurchase()" class="text-white hover:text-gray-200 text-2xl">×</button>
            </div>
            
            <div class="p-6 overflow-y-auto space-y-6">
                <input type="hidden" name="action" id="form-action">
                <input type="hidden" name="id_pembelian" id="form-id-pembelian">
                
                <div class="bg-white p-5 rounded-lg shadow">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal</label>
                            <input type="date" name="tgl_beli" id="form-tgl-beli" value="<?php echo $today; ?>" required class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Supplier</label>
                            <select name="id_supplier" id="form-id-supplier" required class="w-full px-4 py-2 border rounded-lg">
                                <?php foreach ($suppliers as $s): ?><option value="<?php echo $s['id_supplier']; ?>"><?php echo htmlspecialchars($s['nama_supplier']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-lg shadow">
                    <div class="flex justify-between items-center mb-4 border-b pb-2">
                        <h4 class="font-semibold text-gray-700">Item Pembelian</h4>
                        <button type="button" onclick="addItem()" class="bg-green-500 hover:bg-green-600 text-white font-bold py-1 px-3 rounded-md text-sm">Tambah Item</button>
                    </div>
                    <div id="item-list" class="space-y-3"></div>
                </div>
                <div class="bg-white p-5 rounded-lg shadow">
                     <div class="md:w-1/2 md:ml-auto space-y-3">
                        <div class="flex justify-between items-center"><span class="font-semibold text-gray-700">Total</span><input type="text" id="total_display" readonly class="text-right font-bold text-lg bg-transparent"></div>
                        <input type="hidden" name="total_beli" id="total_beli">
                         <div class="flex justify-between items-center"><span class="font-semibold text-gray-700">Bayar</span><input type="number" name="bayar" id="form-bayar" required class="w-1/2 px-3 py-2 border rounded-lg text-right font-bold" min="0" oninput="updateKembali()"></div>
                        <div class="flex justify-between items-center"><span class="font-semibold text-gray-700">Kembali</span><input type="text" id="kembali_display" readonly class="text-right font-bold text-lg text-green-600 bg-transparent"></div>
                        <input type="hidden" name="kembali" id="kembali">
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-4 p-4 bg-gray-100 border-t">
                <button type="button" onclick="closeModalPurchase()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-6 rounded-lg">Batal</button>
                <button type="submit" id="submit-button" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg">Simpan</button>
            </div>
        </form>
    </div>
</div>

<template id="item-template">
    <div class="item-row grid grid-cols-12 gap-3 items-center p-3 border rounded-lg bg-gray-50">
        <div class="col-span-12 md:col-span-4"><select name="kd_bahan" required class="w-full p-2 border rounded-md"><?php foreach ($materials as $m): ?><option value="<?php echo $m['kd_bahan']; ?>"><?php echo htmlspecialchars($m['nama_bahan']); ?></option><?php endforeach; ?></select></div>
        <div class="col-span-6 md:col-span-2"><input type="number" name="harga_beli" required class="w-full p-2 border rounded-md text-right" placeholder="Harga" oninput="updateTotal()"></div>
        <div class="col-span-6 md:col-span-2"><input type="number" name="qty" required class="w-full p-2 border rounded-md text-right" placeholder="Qty" value="1" oninput="updateTotal()"></div>
        <div class="col-span-10 md:col-span-3"><input type="text" name="subtotal_display" readonly class="w-full p-2 bg-transparent text-right font-semibold"></div>
        <div class="col-span-2 md:col-span-1 text-center"><button type="button" class="text-red-500 hover:text-red-700" onclick="removeItem(this)"><i class="fas fa-trash-alt"></i></button></div>
        <input type="hidden" name="subtotal">
    </div>
</template>

<script>
    // === BAGIAN 3: JAVASCRIPT ===
    const modal = document.getElementById('purchase-modal');
    const form = document.getElementById('purchase-form');
    const itemList = document.getElementById('item-list');
    const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });

    function closeModalPurchase() { modal.classList.add('hidden'); }

    function prepareForm(mode, data = {}) {
        form.reset();
        itemList.innerHTML = '';
        form.action.value = mode;
        document.getElementById('modal-title').innerText = mode === 'add' ? 'Tambah Pembelian Baru' : 'Edit Data Pembelian';
        document.getElementById('submit-button').innerText = mode === 'add' ? 'Simpan' : 'Update';

        if (mode === 'edit') {
            form.id_pembelian.value = data.purchase.id_pembelian;
            form.tgl_beli.value = data.purchase.tgl_beli;
            form.id_supplier.value = data.purchase.id_supplier;
            form.bayar.value = data.purchase.bayar;
            
            data.items.forEach(itemData => {
                const itemRow = addItem();
                itemRow.querySelector('[name$="[kd_bahan]"]').value = itemData.kd_bahan;
                itemRow.querySelector('[name$="[harga_beli]"]').value = itemData.harga_beli;
                itemRow.querySelector('[name$="[qty]"]').value = itemData.qty;
            });
        } else {
             form.id_pembelian.value = ''; // Kosongkan ID untuk form tambah
             addItem();
        }
        
        updateTotal();
        modal.classList.remove('hidden');
    }

    function showAddPurchaseForm() {
        prepareForm('add');
    }

    async function showEditPurchaseForm(id, button) {
        button.disabled = true;
        const icon = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const response = await fetch(`?action=get_purchase_details&id=${id}`);
            if (!response.ok) throw new Error('Gagal menghubungi server.');
            
            const data = await response.json();
            if (data.success) {
                prepareForm('edit', data);
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

    /**
     * PERBAIKAN DI SINI:
     * Fungsi ini sekarang hanya mengubah nama atribut jika belum dalam format array.
     * Ini membuatnya aman untuk dipanggil berkali-kali.
     */
    function updateItemNames() {
        itemList.querySelectorAll('.item-row').forEach((row, index) => {
            row.querySelectorAll('select, input').forEach(input => {
                const currentName = input.getAttribute('name');
                // Hanya ubah nama jika tidak mengandung karakter '['
                if (currentName && !currentName.includes('[')) {
                    input.name = `items[${index}][${currentName}]`;
                }
            });
        });
    }

    function addItem() {
        const template = document.getElementById('item-template').content.cloneNode(true);
        itemList.appendChild(template);
        // Panggil updateItemNames setelah menambah elemen
        updateItemNames();
        return itemList.lastElementChild;
    }

    function removeItem(button) {
        button.closest('.item-row').remove();
        updateTotal();
    }

    function updateTotal() {
        // Tidak perlu memanggil updateItemNames() lagi di sini karena sudah aman.
        let total = 0;
        itemList.querySelectorAll('.item-row').forEach(row => {
            const harga = parseFloat(row.querySelector('[name$="[harga_beli]"]').value) || 0;
            const qty = parseFloat(row.querySelector('[name$="[qty]"]').value) || 0;
            const subtotal = harga * qty;
            row.querySelector('[name$="[subtotal_display]"]').value = currencyFormatter.format(subtotal);
            row.querySelector('[name$="[subtotal]"]').value = subtotal;
            total += subtotal;
        });
        document.getElementById('total_beli').value = total;
        document.getElementById('total_display').value = currencyFormatter.format(total);
        updateKembali();
    }

    function updateKembali() {
        const total = parseFloat(document.getElementById('total_beli').value) || 0;
        const bayar = parseFloat(document.getElementById('form-bayar').value) || 0;
        const kembali = bayar >= total ? bayar - total : 0;
        document.getElementById('kembali').value = kembali;
        document.getElementById('kembali_display').value = currencyFormatter.format(kembali);
    }

    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const tableRows = document.querySelectorAll('#purchasesTableBody .purchase-row');
        
        tableRows.forEach(row => {
            const name = row.dataset.name.toLowerCase();
            row.style.display = name.includes(searchTerm) ? '' : 'none';
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>