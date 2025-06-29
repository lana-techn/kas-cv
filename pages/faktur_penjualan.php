<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';

if (!isset($_GET['id'])) {
    header('Location: sales_management.php');
    exit;
}

$id_penjualan = $_GET['id'];

// Ambil data utama penjualan
$stmt_main = $pdo->prepare("SELECT * FROM penjualan WHERE id_penjualan = ?");
$stmt_main->execute([$id_penjualan]);
$sale = $stmt_main->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    die("Faktur tidak ditemukan.");
}

// Ambil detail item penjualan
$stmt_items = $pdo->prepare("
    SELECT dp.*, b.nama_barang 
    FROM detail_penjualan dp 
    JOIN barang b ON dp.kd_barang = b.kd_barang 
    WHERE dp.id_penjualan = ?
");
$stmt_items->execute([$id_penjualan]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faktur Penjualan #<?php echo htmlspecialchars($sale['id_penjualan']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            #invoice-box, #invoice-box * {
                visibility: visible;
            }
            #invoice-box {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-4 md:p-8">
        <div class="no-print mb-6 flex justify-between items-center">
            <a href="sales_management.php" class="text-blue-600 hover:underline">
                <i class="fas fa-arrow-left mr-2"></i>Kembali ke Manajemen Penjualan
            </a>
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm">
                <i class="fas fa-print mr-2"></i>Cetak Faktur
            </button>
        </div>

        <div id="invoice-box" class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg p-8 md:p-12">
            <header class="flex justify-between items-center pb-8 border-b">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">FAKTUR PENJUALAN</h1>
                    <p class="text-gray-500">ID: #<?php echo htmlspecialchars($sale['id_penjualan']); ?></p>
                </div>
                <div class="text-right">
                    <h2 class="text-xl font-semibold text-gray-700">CV. Karya Wahana Sentosa</h2>
                    <p class="text-gray-500 text-sm">Perusahaan Manufaktur Tas</p>
                </div>
            </header>

            <section class="grid grid-cols-2 gap-8 mt-8">
                <div>
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Pelanggan</h3>
                    <p class="text-lg font-bold text-gray-800">Pelanggan Umum</p>
                </div>
                <div class="text-right">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Informasi Transaksi</h3>
                    <p class="text-gray-600">Tanggal: <span class="font-semibold"><?php echo date('d F Y', strtotime($sale['tgl_jual'])); ?></span></p>
                </div>
            </section>

            <section class="mt-10">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Detail Penjualan</h3>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Produk</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Jual</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($item['nama_barang']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 text-right"><?php echo formatCurrency($item['harga_jual']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 text-center"><?php echo $item['qty']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-semibold"><?php echo formatCurrency($item['subtotal']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <section class="mt-8 flex justify-end">
                <div class="w-full max-w-sm">
                    <div class="flex justify-between py-2 border-b">
                        <span class="text-gray-600">Total</span>
                        <span class="font-bold text-gray-800"><?php echo formatCurrency($sale['total_jual']); ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b">
                        <span class="text-gray-600">Bayar</span>
                        <span class="font-bold text-gray-800"><?php echo formatCurrency($sale['bayar']); ?></span>
                    </div>
                    <div class="flex justify-between py-2 bg-gray-100 px-4 rounded-b-lg">
                        <span class="font-bold text-gray-800 text-lg">Kembali</span>
                        <span class="font-bold text-blue-600 text-lg"><?php echo formatCurrency($sale['kembali']); ?></span>
                    </div>
                </div>
            </section>
            
            <footer class="mt-12 pt-8 border-t text-center text-gray-500 text-sm">
                <p>Terima kasih telah berbelanja.</p>
                <p>CV. Karya Wahana Sentosa &copy; <?php echo date('Y'); ?></p>
            </footer>
        </div>
    </div>
</body>
</html>