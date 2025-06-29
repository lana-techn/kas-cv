<?php
require_once '../config/db_connect.php'; //
require_once '../includes/function.php'; //
require_once '../includes/header.php'; //

if (!isset($_GET['id'])) {
    echo "<div class='p-6 bg-red-100 border-l-4 border-red-500 text-red-700'>ID pembelian tidak ditemukan.</div>";
    require_once '../includes/footer.php';
    exit;
}

$id_pembelian = $_GET['id'];

// Ambil data pembelian utama beserta informasi supplier
$stmt = $pdo->prepare("SELECT p.*, s.nama_supplier 
                      FROM pembelian p 
                      LEFT JOIN supplier s ON p.id_supplier = s.id_supplier 
                      WHERE p.id_pembelian = ?");
$stmt->execute([$id_pembelian]);
$pembelian = $stmt->fetch(PDO::FETCH_ASSOC);

// Jika pembelian tidak ditemukan
if (!$pembelian) {
    echo "<div class='p-6 bg-red-100 border-l-4 border-red-500 text-red-700'>Data pembelian dengan ID: " . htmlspecialchars($id_pembelian) . " tidak ditemukan.</div>";
    require_once '../includes/footer.php';
    exit;
}

// Ambil detail item pembelian
$stmt_detail = $pdo->prepare("
    SELECT dp.*, b.nama_bahan 
    FROM detail_pembelian dp
    JOIN bahan b ON dp.kd_bahan = b.kd_bahan
    WHERE dp.id_pembelian = ?
"); //
$stmt_detail->execute([$id_pembelian]);
$detail_items = $stmt_detail->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="flex min-h-screen">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <div class="bg-white p-8 rounded-lg shadow-md max-w-4xl mx-auto">
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-2xl font-bold">FAKTUR pembelian</h1>
                    <p>CV. Karya Wahana Sentosa</p>
                </div>
                <div class="text-right">
                    <p class="font-semibold">ID pembelian: <?php echo htmlspecialchars($pembelian['id_pembelian']); ?></p>
                    <p>Tanggal: <?php echo date('d/m/Y', strtotime($pembelian['tgl_beli'])); ?></p>
                    <p>Supplier: <?php echo htmlspecialchars($pembelian['nama_supplier'] ?? 'Tidak ada'); ?></p>
                </div>
            </div>

            <div class="border-b mb-8"></div>

            <h3 class="text-lg font-semibold mb-4">Detail Transaksi:</h3>
            <?php if (!empty($detail_items)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto mb-8">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2 text-left">No.</th>
                            <th class="px-4 py-2 text-left">Nama Barang</th>
                            <th class="px-4 py-2 text-right">Harga Satuan</th>
                            <th class="px-4 py-2 text-center">Jumlah</th>
                            <th class="px-4 py-2 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detail_items as $index => $item): ?>
                            <tr>
                                <td class="border-b px-4 py-2"><?php echo $index + 1; ?></td>
                                <td class="border-b px-4 py-2"><?php echo htmlspecialchars($item['nama_bahan']); ?></td>
                                <td class="border-b px-4 py-2 text-right"><?php echo formatCurrency($item['harga_beli']); ?></td>
                                <td class="border-b px-4 py-2 text-center"><?php echo htmlspecialchars($item['qty']); ?></td>
                                <td class="border-b px-4 py-2 text-right"><?php echo formatCurrency($item['subtotal']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="font-semibold">
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-right">Total</td>
                            <td class="px-4 py-2 text-right"><?php echo formatCurrency($pembelian['total_beli']); ?></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-right">Bayar</td>
                            <td class="px-4 py-2 text-right"><?php echo formatCurrency($pembelian['bayar']); ?></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-right">Kembali</td>
                            <td class="px-4 py-2 text-right"><?php echo formatCurrency($pembelian['kembali']); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php else: ?>
            <div class="p-4 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700">
                Tidak ada detail barang untuk pembelian ini.
            </div>
            <?php endif; ?>

            <div class="flex justify-end space-x-2 mt-8">
                <button onclick="window.print()" class="bg-gray-500 hover:bg-gray-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-print mr-2"></i>Cetak
                </button>
                <a href="../utils/export_faktur.php?type=pembelian&id=<?php echo $id_pembelian; ?>" class="bg-green-500 hover:bg-green-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-file-excel mr-2"></i>Ekspor ke Excel
                </a>
            </div>
        </div>
    </main>
</div>

<?php require_once '../includes/footer.php'; ?>