<?php
// ===== BAGIAN 1: LOGIKA PHP & AJAX HANDLER =====
require_once '../config/db_connect.php';
require_once '../includes/function.php';

// Pastikan session aktif dan periksa hak akses
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['level'], ['admin', 'Pegawai Operasional'])) {
    header('Location: login.php'); // Arahkan ke login jika tidak berhak
    exit;
}

// Ambil pesan dari sesi (jika ada) untuk notifikasi
$message = $_SESSION['message'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['message'], $_SESSION['error']);

/**
 * AJAX HANDLER: Mengambil detail pembelian untuk form edit.
 * Ini dipicu oleh JavaScript saat tombol edit diklik.
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
        error_log('Purchase AJAX Error: ' . $e->getMessage()); // Log error untuk admin
        $response['message'] = 'Terjadi kesalahan pada server.';
    }

    echo json_encode($response);
    exit;
}

/**
 * FORM POST HANDLER: Menangani Tambah & Edit Pembelian.
 * Ini dieksekusi saat form di dalam modal disubmit.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $items = $_POST['items'] ?? [];

    // Validasi server-side yang kuat
    if (empty($items)) {
        $_SESSION['error'] = 'Transaksi harus memiliki minimal satu item.';
        header('Location: purchase_management.php');
        exit;
    }
    if (empty($_POST['tgl_beli']) || empty($_POST['id_supplier'])) {
        $_SESSION['error'] = 'Tanggal dan Supplier wajib diisi.';
        header('Location: purchase_management.php');
        exit;
    }
    if (!isset($_POST['total_beli']) || !isset($_POST['bayar']) || floatval($_POST['bayar']) < floatval($_POST['total_beli'])) {
        $_SESSION['error'] = 'Jumlah bayar tidak boleh kurang dari total pembelian.';
        header('Location: purchase_management.php');
        exit;
    }

    // Validasi tanggal pembelian maksimal hari ini (tidak boleh di masa depan)
    $today = date('Y-m-d');
    if ($_POST['tgl_beli'] > $today) {
        $_SESSION['error'] = 'Tanggal pembelian tidak boleh di masa depan.';
        header('Location: purchase_management.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        if ($action === 'add') {
            $id_pembelian = generateId('BL');
            // 1. Insert ke tabel utama 'pembelian'
            $stmt = $pdo->prepare("INSERT INTO pembelian (id_pembelian, tgl_beli, id_supplier, total_beli, bayar, kembali) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_pembelian, $_POST['tgl_beli'], $_POST['id_supplier'], $_POST['total_beli'], $_POST['bayar'], $_POST['kembali']]);

            // 2. Insert detail pembelian dan update stok bahan
            foreach ($items as $item) {
                $stmt_detail = $pdo->prepare("INSERT INTO detail_pembelian (id_detail_beli, id_pembelian, kd_bahan, harga_beli, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_detail->execute([generateId('DBL'), $id_pembelian, $item['kd_bahan'], $item['harga_beli'], $item['qty'], $item['subtotal']]);

                $stmt_stok = $pdo->prepare("UPDATE bahan SET stok = stok + ? WHERE kd_bahan = ?");
                $stmt_stok->execute([$item['qty'], $item['kd_bahan']]);
            }

            // 3. Catat sebagai pengeluaran kas
            $id_pengeluaran_kas = generateId('PKS');
            $uraian = 'Pembelian Bahan ' . $id_pembelian;
            $stmt_kas = $pdo->prepare("INSERT INTO pengeluaran_kas (id_pengeluaran_kas, id_pembelian, tgl_pengeluaran_kas, uraian, total) VALUES (?, ?, ?, ?, ?)");
            $stmt_kas->execute([$id_pengeluaran_kas, $id_pembelian, $_POST['tgl_beli'], $uraian, $_POST['total_beli']]);

            // 4. Update tabel kas
            // Cek saldo terakhir
            $stmt_saldo = $pdo->query("SELECT COALESCE(MAX(saldo), 0) FROM kas");
            $saldo_terakhir = $stmt_saldo->fetchColumn();
            $saldo_baru = $saldo_terakhir - $_POST['total_beli'];

            // Catat ke buku kas
            $pdo->prepare("INSERT INTO kas (id_kas, id_pengeluaran_kas, tanggal, keterangan, debit, kredit, saldo) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([
                    generateId('KAS'),
                    $id_pengeluaran_kas,
                    $_POST['tgl_beli'],
                    $uraian,
                    0,
                    $_POST['total_beli'],
                    $saldo_baru
                ]);

            $_SESSION['message'] = 'Pembelian berhasil ditambahkan.';
        } elseif ($action === 'edit') {
            $id_pembelian = $_POST['id_pembelian'];
            if (empty($id_pembelian)) throw new Exception("ID Pembelian tidak valid untuk diedit.");

            // 1. Kembalikan stok lama ke kondisi semula
            $stmt_old_items = $pdo->prepare("SELECT kd_bahan, qty FROM detail_pembelian WHERE id_pembelian = ?");
            $stmt_old_items->execute([$id_pembelian]);
            foreach ($stmt_old_items->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $pdo->prepare("UPDATE bahan SET stok = stok - ? WHERE kd_bahan = ?")->execute([$item['qty'], $item['kd_bahan']]);
            }

            // 2. Hapus semua detail pembelian yang lama (karena akan diganti dengan yang baru dari form)
            $pdo->prepare("DELETE FROM detail_pembelian WHERE id_pembelian = ?")->execute([$id_pembelian]);

            // 3. Update data utama di tabel 'pembelian'
            $stmt_update = $pdo->prepare("UPDATE pembelian SET tgl_beli = ?, id_supplier = ?, total_beli = ?, bayar = ?, kembali = ? WHERE id_pembelian = ?");
            $stmt_update->execute([$_POST['tgl_beli'], $_POST['id_supplier'], $_POST['total_beli'], $_POST['bayar'], $_POST['kembali'], $id_pembelian]);

            // 4. Masukkan kembali semua item dari form (yang sudah berisi item lama + item baru) dan tambahkan stoknya
            foreach ($items as $item) {
                $stmt_detail = $pdo->prepare("INSERT INTO detail_pembelian (id_detail_beli, id_pembelian, kd_bahan, harga_beli, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_detail->execute([generateId('DBL'), $id_pembelian, $item['kd_bahan'], $item['harga_beli'], $item['qty'], $item['subtotal']]);
                $pdo->prepare("UPDATE bahan SET stok = stok + ? WHERE kd_bahan = ?")->execute([$item['qty'], $item['kd_bahan']]);
            }

            // 5. Update data di tabel pengeluaran kas
            $pdo->prepare("UPDATE pengeluaran_kas SET tgl_pengeluaran_kas = ?, total = ? WHERE id_pembelian = ?")->execute([$_POST['tgl_beli'], $_POST['total_beli'], $id_pembelian]);

            // 6. Update tabel kas
            // Cari id_pengeluaran_kas
            $stmt_pengeluaran = $pdo->prepare("SELECT id_pengeluaran_kas FROM pengeluaran_kas WHERE id_pembelian = ?");
            $stmt_pengeluaran->execute([$id_pembelian]);
            $id_pengeluaran_kas = $stmt_pengeluaran->fetchColumn();

            // Hapus entri kas lama
            $pdo->prepare("DELETE FROM kas WHERE id_pengeluaran_kas = ?")->execute([$id_pengeluaran_kas]);

            // Cek saldo terakhir
            $stmt_saldo = $pdo->query("SELECT COALESCE(MAX(saldo), 0) FROM kas");
            $saldo_terakhir = $stmt_saldo->fetchColumn();
            $saldo_baru = $saldo_terakhir - $_POST['total_beli'];

            // Catat ke buku kas yang baru
            $uraian = 'Pembelian Bahan ' . $id_pembelian . ' (Update)';
            $pdo->prepare("INSERT INTO kas (id_kas, id_pengeluaran_kas, tanggal, keterangan, debit, kredit, saldo) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([
                    generateId('KAS'),
                    $id_pengeluaran_kas,
                    $_POST['tgl_beli'],
                    $uraian,
                    0,
                    $_POST['total_beli'],
                    $saldo_baru
                ]);

            $_SESSION['message'] = 'Pembelian berhasil diupdate.';
        }

        $pdo->commit(); // Jika semua berhasil, simpan perubahan
    } catch (Exception $e) {
        $pdo->rollBack(); // Jika ada error, batalkan semua perubahan
        $_SESSION['error'] = 'Terjadi kesalahan: ' . $e->getMessage();
    }

    // Alihkan kembali ke halaman utama untuk mencegah resubmit (Pola PRG)
    header('Location: purchase_management.php');
    exit;
}

// ===== BAGIAN 2: LOGIKA PENGAMBILAN DATA UNTUK TAMPILAN =====
require_once '../includes/header.php';

$search_query = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Query untuk menghitung total data (untuk pagination)
$countSql = "SELECT COUNT(*) FROM pembelian p JOIN supplier s ON p.id_supplier = s.id_supplier WHERE p.id_pembelian LIKE :search OR s.nama_supplier LIKE :search";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute([':search' => '%' . $search_query . '%']);
$totalItems = $stmtCount->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// Query untuk mengambil data pembelian yang akan ditampilkan di tabel
$dataSql = "SELECT p.*, s.nama_supplier 
            FROM pembelian p 
            JOIN supplier s ON p.id_supplier = s.id_supplier 
            WHERE p.id_pembelian LIKE :search OR s.nama_supplier LIKE :search
            ORDER BY p.tgl_beli DESC, p.id_pembelian DESC
            LIMIT :limit OFFSET :offset";
$stmtData = $pdo->prepare($dataSql);
$stmtData->bindValue(':search', '%' . $search_query . '%');
$stmtData->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtData->execute();
$purchases = $stmtData->fetchAll(PDO::FETCH_ASSOC);

// Data yang dibutuhkan untuk mengisi pilihan di dalam modal form
$suppliers = $pdo->query("SELECT id_supplier, nama_supplier FROM supplier ORDER BY nama_supplier")->fetchAll(PDO::FETCH_ASSOC);
$materials = $pdo->query("SELECT kd_bahan, nama_bahan FROM bahan ORDER BY nama_bahan")->fetchAll(PDO::FETCH_ASSOC);
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
                        <h3 class="text-xl font-semibold text-white">Manajemen Pembelian</h3>
                        <p class="text-blue-100 mt-1">Total: <?php echo $totalItems; ?> transaksi</p>
                    </div>
                    <button onclick="showAddPurchaseForm( )" class="add-button bg-white text-blue-600 hover:bg-blue-50 px-6 py-2 rounded-lg font-medium transition duration-200 flex items-center">
                        <i class="fas fa-plus mr-2"></i>Tambah Pembelian
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
                <form method="GET" action="purchase_management.php" class="mb-6 relative">
                    <input type="text" id="searchInput" name="search" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10" placeholder="Cari ID pembelian atau nama supplier..." value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-blue-500">
                        <i class="fas fa-search"></i>
                    </button>
                </form>

                <!-- Tabel Data -->
                <div class="overflow-x-auto">
                    <table class="min-w-full border border-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Id Beli</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="purchasesTableBody">
                            <?php if (empty($purchases)): ?>
                                <tr>
                                    <td colspan="6" class="text-center p-5 text-gray-500">Tidak ada data yang cocok dengan pencarian Anda.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($purchases as $index => $purchase): ?>
                                    <tr>
                                        <td class="px-6 py-4 text-sm text-gray-500"><?php echo $offset + $index + 1; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($purchase['id_pembelian']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo date('d M Y', strtotime($purchase['tgl_beli'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($purchase['nama_supplier']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-semibold"><?php echo formatCurrency($purchase['total_beli']); ?></td>
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
                <div class="flex justify-between items-center mt-4">
                    <span class="text-sm text-gray-700">
                        Menampilkan <?php echo $offset + 1; ?> sampai <?php echo min($offset + $perPage, $totalItems); ?> dari <?php echo $totalItems; ?> data
                    </span>
                    <div class="flex items-center space-x-2 text-sm">
                        <a href="?search=<?php echo urlencode($search_query); ?>&page=<?php echo max(1, $page - 1); ?>" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 <?php echo $page <= 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>">Prev</a>
                        <span class="px-3 py-1 bg-blue-500 text-white rounded"><?php echo $page; ?></span>
                        <a href="?search=<?php echo urlencode($search_query); ?>&page=<?php echo min($totalPages, $page + 1); ?>" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 <?php echo $page >= $totalPages ? 'opacity-50 cursor-not-allowed' : ''; ?>">Next</a>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal Form Pembelian -->
<div id="purchase-modal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50 p-4">
    <div class="bg-gray-50 rounded-xl shadow-2xl w-full max-w-4xl mx-auto max-h-[90vh] flex flex-col">
        <form id="purchase-form" method="POST" action="purchase_management.php" class="flex flex-col flex-grow" onsubmit="return validatePurchaseForm()">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-5 rounded-t-xl flex justify-between items-center">
                <h3 id="modal-title" class="text-xl font-semibold text-white">Input Data Pembelian</h3>
                <button type="button" onclick="closeModalPurchase()" class="text-white hover:text-gray-200 text-2xl">&times;</button>
            </div>

            <div class="p-6 overflow-y-auto space-y-6">
                <input type="hidden" name="action" id="form-action">
                <input type="hidden" name="id_pembelian" id="form-id-pembelian">

                <div class="bg-white p-5 rounded-lg shadow">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal</label>
                            <input type="date" name="tgl_beli" id="form-tgl-beli" value="<?php echo $today; ?>" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Supplier</label>
                            <select name="id_supplier" id="form-id-supplier" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Pilih Supplier...</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s['id_supplier']); ?>"><?php echo htmlspecialchars($s['nama_supplier']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-lg shadow">
                    <div class="flex justify-between items-center mb-4 border-b pb-2">
                        <h4 class="font-semibold text-gray-700">Item Pembelian</h4>
                        <button type="button" onclick="addItem()" class="bg-green-500 hover:bg-green-600 text-white font-bold py-1 px-3 rounded-md text-sm flex items-center"><i class="fas fa-plus mr-1"></i> Tambah</button>
                    </div>
                    <div id="item-list" class="space-y-3">
                        <!-- Item rows will be injected here by JavaScript -->
                    </div>
                </div>
                <div class="bg-white p-5 rounded-lg shadow">
                    <div class="md:w-1/2 md:ml-auto space-y-3">
                        <div class="flex justify-between items-center"><span class="font-semibold text-gray-700">Total</span><input type="text" id="total_display" readonly class="text-right font-bold text-lg bg-transparent border-0 focus:ring-0"></div>
                        <input type="hidden" name="total_beli" id="total_beli">
                        <div class="flex justify-between items-center"><span class="font-semibold text-gray-700">Bayar</span><input type="number" name="bayar" id="form-bayar" required class="w-1/2 px-3 py-2 border rounded-lg text-right font-bold" min="0" oninput="updateKembali()"></div>
                        <div class="flex justify-between items-center"><span class="font-semibold text-gray-700">Kembali</span><input type="text" id="kembali_display" readonly class="text-right font-bold text-lg text-green-600 bg-transparent border-0 focus:ring-0"></div>
                        <input type="hidden" name="kembali" id="kembali">
                    </div>
                </div>
            </div>

            <div class="flex justify-end space-x-4 p-4 bg-gray-100 border-t rounded-b-xl">
                <button type="button" onclick="closeModalPurchase()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-6 rounded-lg">Batal</button>
                <button type="submit" id="submit-button" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Template untuk baris item, tidak akan terlihat -->
<template id="item-template">
    <div class="item-row grid grid-cols-12 gap-x-4 gap-y-2 items-center p-3 border rounded-lg bg-gray-50">
        <div class="col-span-12 md:col-span-4">
            <label class="text-xs font-medium text-gray-600">Bahan</label>
            <select name="items[0][kd_bahan]" required class="w-full p-2 border rounded-md text-sm">
                <option value="">Pilih Bahan...</option>
                <?php foreach ($materials as $m): ?>
                    <option value="<?php echo htmlspecialchars($m['kd_bahan']); ?>"><?php echo htmlspecialchars($m['nama_bahan']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-span-6 md:col-span-2">
            <label class="text-xs font-medium text-gray-600">Harga</label>
            <input type="number" name="items[0][harga_beli]" required class="w-full p-2 border rounded-md text-right text-sm" placeholder="0" oninput="updateTotal(this)">
        </div>
        <div class="col-span-6 md:col-span-2">
            <label class="text-xs font-medium text-gray-600">Jumlah</label>
            <input type="number" name="items[0][qty]" required value="1" class="w-full p-2 border rounded-md text-right text-sm" placeholder="0" oninput="updateTotal(this)">
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
    const modal = document.getElementById('purchase-modal');
    const form = document.getElementById('purchase-form');
    const itemList = document.getElementById('item-list');
    const currencyFormatter = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    });
    let itemIndex = 0;

    function closeModalPurchase() {
        modal.classList.add('hidden');
    }

    function prepareForm(mode, data = {}) {
        form.reset();
        itemList.innerHTML = '';
        itemIndex = 0;
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
            form.id_pembelian.value = '';
            addItem(); // Tambah satu baris kosong untuk form baru
        }

        updateTotal();
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function showAddPurchaseForm() {
        prepareForm('add');
    }

    async function showEditPurchaseForm(id, button) {
        const originalIcon = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;

        try {
            const response = await fetch(`purchase_management.php?action=get_purchase_details&id=${id}`);
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
            button.innerHTML = originalIcon;
            button.disabled = false;
        }
    }

    function addItem() {
        const template = document.getElementById('item-template').content.cloneNode(true);
        const itemRow = template.querySelector('.item-row');

        // Update name attributes with a unique index
        itemRow.querySelectorAll('select, input').forEach(input => {
            let name = input.getAttribute('name');
            if (name) {
                input.name = name.replace('[0]', `[${itemIndex}]`);
            }
        });

        itemList.appendChild(template);
        itemIndex++;
        return itemList.lastElementChild;
    }

    function removeItem(button) {
        button.closest('.item-row').remove();
        updateTotal();
    }

    function updateTotal() {
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

    // Tutup modal jika klik di luar area form
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModalPurchase();
        }
    });

    // Validasi form pembelian
    function validatePurchaseForm() {
        // Validasi tanggal pembelian (tidak boleh di masa depan)
        const tglBeliInput = document.getElementById('form-tgl-beli');
        const selectedDate = new Date(tglBeliInput.value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        selectedDate.setHours(0, 0, 0, 0);

        if (selectedDate > today) {
            alert('Tanggal pembelian tidak boleh di masa depan.');
            tglBeliInput.focus();
            return false;
        }

        // Validasi lainnya bisa ditambahkan di sini

        return true;
    }
</script>

<?php require_once '../includes/footer.php'; ?>