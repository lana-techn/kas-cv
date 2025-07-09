<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';

if (!isset($_GET['id'])) {
    header('Location: purchase_management.php');
    exit;
}
$no = 1;
$id_pembelian = $_GET['id'];

// Ambil data utama pembelian
$stmt_main = $pdo->prepare("
    SELECT p.*, s.nama_supplier, s.alamat, s.no_telpon 
    FROM pembelian p 
    JOIN supplier s ON p.id_supplier = s.id_supplier 
    WHERE p.id_pembelian = ?
");
$stmt_main->execute([$id_pembelian]);
$purchase = $stmt_main->fetch(PDO::FETCH_ASSOC);

if (!$purchase) {
    die("Faktur tidak ditemukan.");
}

// Ambil detail item pembelian
$stmt_items = $pdo->prepare("
    SELECT dp.*, b.nama_bahan 
    FROM detail_pembelian dp 
    JOIN bahan b ON dp.kd_bahan = b.kd_bahan 
    WHERE dp.id_pembelian = ?
");
$stmt_items->execute([$id_pembelian]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faktur Pembelian #<?php echo htmlspecialchars($purchase['id_pembelian']); ?></title>
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
            <a href="purchase_management.php" class="text-blue-600 hover:underline">
                <i class="fas fa-arrow-left mr-2"></i>Kembali ke Manajemen Pembelian
            </a>
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm">
                <i class="fas fa-print mr-2"></i>Cetak Faktur
            </button>
        </div>

        <div id="invoice-box" class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg p-8 md:p-12">
            <header class="flex justify-between items-center pb-8 border-b">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">FAKTUR PEMBELIAN</h1>
                    <p class="text-gray-500">ID: #<?php echo htmlspecialchars($purchase['id_pembelian']); ?></p>
                </div>
                
            </header>

            <section class="grid grid-cols-2 gap-8 mt-8">
                <div>
                    <p class="text-lg font-bold text-gray-800">CV. Karya Wahana Sentosa</p>
                    <p class="text-gray-600">Jl. Imogiri Barat Km. 17, Bungas, Jetis, Bantul, Yogyakarta</p>
                </div>
                <div class="text-right">
                    <p class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($purchase['nama_supplier']); ?></p>
                    <p class="text-gray-600"><?php echo htmlspecialchars($purchase['alamat']); ?></p>
                    <p class="text-gray-600">Telp: <?php echo htmlspecialchars($purchase['no_telpon']); ?></p>
                    <p class="text-gray-600 mt-2">Tanggal Transaksi: <span class="font-semibold"><?php echo date('d F Y', strtotime($purchase['tgl_beli'])); ?></span></p>
                </div>
            </section>

            <section class="mt-10">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Detail Pembelian</h3>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Bahan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Bahan</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Satuan</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo $no++; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($item['kd_bahan']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($item['nama_bahan']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 text-right"><?php echo formatCurrency($item['harga_beli']); ?></td>
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
                        <span class="font-bold text-gray-800"><?php echo formatCurrency($purchase['total_beli']); ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b">
                        <span class="text-gray-600">Bayar</span>
                        <span class="font-bold text-gray-800"><?php echo formatCurrency($purchase['bayar']); ?></span>
                    </div>
                    <div class="flex justify-between py-2 bg-gray-100 px-4 rounded-b-lg">
                        <span class="font-bold text-gray-800 text-lg">Kembali</span>
                        <span class="font-bold text-blue-600 text-lg"><?php echo formatCurrency($purchase['kembali']); ?></span>
                    </div>
                </div>
            </section>
            
            <footer class="mt-12 pt-8 border-t text-center text-gray-500 text-sm">
                <p>Terima kasih atas kerja samanya.</p>
                <p>CV. Karya Wahana Sentosa &copy; <?php echo date('Y'); ?></p>
            </footer>
        </div>
    </div>
</body>
</html>