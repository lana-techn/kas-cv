<?php
require_once '../config/db_connect.php'; //
require_once '../includes/function.php'; //
require_once '../includes/header.php'; //

if (!isset($_GET['id'])) {
    echo "<div class='p-6'>ID Penjualan tidak ditemukan.</div>";
    require_once '../includes/footer.php'; //
    exit;
}

$id_penjualan = $_GET['id'];

// Ambil data penjualan utama
$stmt = $pdo->prepare("SELECT * FROM penjualan WHERE id_penjualan = ?"); //
$stmt->execute([$id_penjualan]);
$penjualan = $stmt->fetch(PDO::FETCH_ASSOC);

// Ambil detail item penjualan
$stmt_detail = $pdo->prepare("
    SELECT dp.*, b.nama_barang 
    FROM detail_penjualan dp
    JOIN barang b ON dp.kd_barang = b.kd_barang
    WHERE dp.id_penjualan = ?
"); //
$stmt_detail->execute([$id_penjualan]);
$detail_items = $stmt_detail->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="flex min-h-screen">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <div class="bg-white p-8 rounded-lg shadow-md max-w-4xl mx-auto">
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-2xl font-bold">FAKTUR PENJUALAN</h1>
                    <p>CV. Karya Wahana Sentosa</p>
                </div>
                <div class="text-right">
                    <p class="font-semibold">ID Penjualan: <?php echo htmlspecialchars($penjualan['id_penjualan']); ?></p>
                    <p>Tanggal: <?php echo htmlspecialchars($penjualan['tgl_jual']); ?></p>
                </div>
            </div>

            <div class="border-b mb-8"></div>

            <h3 class="text-lg font-semibold mb-4">Detail Transaksi:</h3>
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
                                <td class="border-b px-4 py-2"><?php echo htmlspecialchars($item['nama_barang']); ?></td>
                                <td class="border-b px-4 py-2 text-right"><?php echo formatCurrency($item['harga_jual']); ?></td>
                                <td class="border-b px-4 py-2 text-center"><?php echo htmlspecialchars($item['qty']); ?></td>
                                <td class="border-b px-4 py-2 text-right"><?php echo formatCurrency($item['subtotal']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="font-semibold">
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-right">Total</td>
                            <td class="px-4 py-2 text-right"><?php echo formatCurrency($penjualan['total_jual']); ?></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-right">Bayar</td>
                            <td class="px-4 py-2 text-right"><?php echo formatCurrency($penjualan['bayar']); ?></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-right">Kembali</td>
                            <td class="px-4 py-2 text-right"><?php echo formatCurrency($penjualan['kembali']); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="flex justify-end space-x-2">
                <button onclick="window.print()" class="bg-gray-500 hover:bg-gray-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-print mr-2"></i>Cetak
                </button>
                <a href="../utils/export_faktur.php?type=penjualan&id=<?php echo $id_penjualan; ?>" class="bg-green-500 hover:bg-green-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-file-excel mr-2"></i>Ekspor ke Excel
                </a>
            </div>
        </div>
    </main>
</div>

<?php require_once '../includes/footer.php'; ?>