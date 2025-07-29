<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';

// Validasi parameter URL
if (!isset($_GET['type']) || !isset($_GET['start']) || !isset($_GET['end'])) {
    die("Parameter tidak lengkap. Harap kembali dan coba lagi.");
}

$report_type = $_GET['type'];
$start_date = $_GET['start'];
$end_date = $_GET['end'];
$no = 1;

// Logika untuk mengambil data berdasarkan jenis laporan
$data = [];
$headers = [];
$columns = [];
$total_column = null;
$query = "";
$params = [$start_date, $end_date];
$report_title = "Laporan " . ucwords(str_replace('_', ' ', $report_type));

switch ($report_type) {
    case 'penjualan':
        $query = "SELECT id_penjualan, tgl_jual, total_jual, bayar, kembali FROM penjualan WHERE tgl_jual BETWEEN ? AND ? ORDER BY tgl_jual";
        $headers = ['No.', 'Id Jual', 'Tanggal', 'Total', 'Bayar', 'Kembali'];
        $columns = ['id_penjualan', 'tgl_jual', 'total_jual', 'bayar', 'kembali'];
        $total_column = 'total_jual';
        break;

    case 'pembelian':
        $query = "SELECT p.id_pembelian, p.tgl_beli, s.nama_supplier, p.total_beli, p.bayar, p.kembali FROM pembelian p JOIN supplier s ON p.id_supplier = s.id_supplier WHERE p.tgl_beli BETWEEN ? AND ? ORDER BY p.tgl_beli";
        $headers = ['No.', 'Id Beli', 'Tanggal', 'Supplier', 'Total', 'Bayar', 'Kembali'];
        $columns = ['id_pembelian', 'tgl_beli', 'nama_supplier', 'total_beli', 'bayar', 'kembali'];
        $total_column = 'total_beli';
        break;

    case 'penerimaan_kas':
        $query = "SELECT tgl_terima_kas, uraian, total FROM penerimaan_kas WHERE tgl_terima_kas BETWEEN ? AND ? ORDER BY tgl_terima_kas";
        $headers = ['No.', 'Tanggal', 'Uraian', 'Jumlah (Rp)'];
        $columns = ['tgl_terima_kas', 'uraian', 'total'];
        $total_column = 'total';
        break;

    case 'pengeluaran_kas':
        $query = "SELECT tgl_pengeluaran_kas, uraian, total FROM pengeluaran_kas WHERE tgl_pengeluaran_kas BETWEEN ? AND ? ORDER BY tgl_pengeluaran_kas";
        $headers = ['No.', 'Tanggal', 'Uraian', 'Jumlah (Rp)'];
        $columns = ['tgl_pengeluaran_kas', 'uraian', 'total'];
        $total_column = 'total';
        break;

    case 'buku_besar':
        $report_title = "Laporan Buku Besar";

        // Cek apakah ada data dalam tabel kas
        $check_kas = $pdo->query("SELECT COUNT(*) FROM kas")->fetchColumn();

        if ($check_kas > 0) {
            // Gunakan tabel kas jika ada data

            // 1. Cari saldo awal (saldo terakhir sebelum start_date)
            $stmt_saldo_awal = $pdo->prepare("SELECT COALESCE(MAX(saldo), 0) FROM kas WHERE tanggal < ?");
            $stmt_saldo_awal->execute([$start_date]);
            $saldo_awal = $stmt_saldo_awal->fetchColumn();

            // 2. Ambil transaksi dari tabel kas untuk periode yang dipilih
            $query = "SELECT tanggal, keterangan as uraian, debit, kredit, saldo FROM kas WHERE tanggal BETWEEN ? AND ? ORDER BY tanggal ASC, id_kas ASC";
            $params = [$start_date, $end_date];
        } else {
            // Fallback ke penerimaan_kas dan pengeluaran_kas jika kas kosong
            // 1. Hitung Saldo Awal
            $stmt_saldo_awal = $pdo->prepare("SELECT (SELECT COALESCE(SUM(total), 0) FROM penerimaan_kas WHERE tgl_terima_kas < ?) - (SELECT COALESCE(SUM(total), 0) FROM pengeluaran_kas WHERE tgl_pengeluaran_kas < ?) as saldo_awal");
            $stmt_saldo_awal->execute([$start_date, $start_date]);
            $saldo_awal = $stmt_saldo_awal->fetchColumn();

            // 2. Query untuk transaksi pada periode yang dipilih
            $query = "(SELECT tgl_terima_kas as tanggal, uraian, total as debit, 0 as kredit FROM penerimaan_kas WHERE tgl_terima_kas BETWEEN ? AND ?) UNION ALL (SELECT tgl_pengeluaran_kas as tanggal, uraian, 0 as debit, total as kredit FROM pengeluaran_kas WHERE tgl_pengeluaran_kas BETWEEN ? AND ?) ORDER BY tanggal ASC";
            $params = [$start_date, $end_date, $start_date, $end_date];
        }

        $headers = ['No.', 'Tanggal', 'Keterangan', 'Debit', 'Kredit', 'Saldo'];
        $columns = ['tanggal', 'uraian', 'debit', 'kredit', 'saldo'];
        break;

    case 'kas':
        $report_title = "Laporan Kas (Lengkap)";
        // Query untuk mengambil data langsung dari tabel kas
        $query = "SELECT tanggal, keterangan, debit, kredit, saldo FROM kas WHERE tanggal BETWEEN ? AND ? ORDER BY tanggal ASC, id_kas ASC";
        $params = [$start_date, $end_date];
        $headers = ['No.', 'Tanggal', 'Keterangan', 'Debit', 'Kredit', 'Saldo'];
        $columns = ['tanggal', 'keterangan', 'debit', 'kredit', 'saldo'];
        break;

    default:
        die("Jenis laporan tidak valid.");
}

if (!empty($query)) {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($report_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .no-print {
                display: none;
            }

            #report-box {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                box-shadow: none;
                border: none;
            }
        }

        .table-bordered th,
        .table-bordered td {
            border: 1px solid #e2e8f0;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-4 md:p-8">
        <div class="no-print mb-6 flex justify-between items-center">
            <a href="../pages/reports.php" class="text-blue-600 hover:underline">
                <i class="fas fa-arrow-left mr-2"></i>Kembali ke Halaman Laporan
            </a>
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm">
                <i class="fas fa-print mr-2"></i>Cetak Laporan
            </button>
        </div>

        <div id="report-box" class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg p-8">
            <header class="text-center pb-6 border-b-2 border-gray-800">
                <h1 class="text-2xl font-bold text-gray-800">CV. KARYA WAHANA SENTOSA</h1>
                <h2 class="text-xl font-semibold text-gray-700 mt-1"><?php echo htmlspecialchars($report_title); ?></h2>
                <p class="text-gray-500 text-sm">Periode: <?php echo date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date)); ?></p>
            </header>

            <section class="mt-8">
                <table class="w-full table-bordered">
                    <thead class="bg-gray-100">
                        <tr>
                            <?php foreach ($headers as $header): ?>
                                <th class="px-4 py-2 text-sm font-semibold text-gray-700"><?php echo $header; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data) && $report_type != 'buku_besar'): ?>
                            <tr>
                                <td colspan="<?php echo count($headers); ?>" class="text-center p-4">Tidak ada data pada periode ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php
                            $grand_total = 0;
                            if ($report_type == 'buku_besar'):
                                $saldo_berjalan = 0; // Mulai dari 0 untuk setiap periode
                            ?>
                                <?php foreach ($data as $row):
                                    $saldo_berjalan += $row['debit'] - $row['kredit'];
                                ?>
                                    <tr>
                                        <td class="px-4 py-2 text-center"><?php echo $no++; ?></td>
                                        <td class="px-4 py-2"><?php echo date('d-m-Y', strtotime($row['tanggal'])); ?></td>
                                        <td class="px-4 py-2"><?php echo htmlspecialchars($row['uraian']); ?></td>
                                        <td class="px-4 py-2 text-right"><?php echo $row['debit'] > 0 ? formatCurrency($row['debit']) : '-'; ?></td>
                                        <td class="px-4 py-2 text-right"><?php echo $row['kredit'] > 0 ? formatCurrency($row['kredit']) : '-'; ?></td>
                                        <td class="px-4 py-2 text-right"><?php echo formatCurrency($saldo_berjalan); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($data as $row): ?>
                                    <tr>
                                        <td class="px-4 py-2 text-center"><?php echo $no++; ?></td>
                                        <?php foreach ($columns as $col): ?>
                                            <?php
                                            $is_currency = in_array($col, ['total_jual', 'total_beli', 'bayar', 'kembali', 'total']);
                                            $is_date = in_array($col, ['tgl_jual', 'tgl_beli', 'tgl_terima_kas', 'tgl_pengeluaran_kas']);
                                            $value = $is_date ? date('d-m-Y', strtotime($row[$col])) : ($is_currency ? formatCurrency($row[$col]) : htmlspecialchars($row[$col]));
                                            $align = $is_currency ? 'text-right' : 'text-left';
                                            ?>
                                            <td class="px-4 py-2 <?php echo $align; ?>"><?php echo $value; ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php if ($total_column) $grand_total += $row[$total_column]; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($data)): ?>
                        <tfoot class="bg-gray-100 font-bold">
                            <?php if ($report_type == 'buku_besar'): ?>
                                <tr>
                                    <td colspan="<?php echo count($headers) - 1; ?>" class="px-4 py-2 text-right">Saldo Akhir Periode</td>
                                    <td class="px-4 py-2 text-right"><?php echo formatCurrency($saldo_berjalan); ?></td>
                                </tr>
                            <?php elseif ($total_column): ?>
                                <tr>
                                    <?php
                                    if ($report_type == 'penjualan') $colspan = 3;
                                    elseif ($report_type == 'pembelian') $colspan = 4;
                                    else $colspan = count($headers) - 1;
                                    ?>
                                    <td colspan="<?php echo $colspan; ?>" class="px-4 py-2 text-right">Total</td>
                                    <td class="px-4 py-2 text-right"><?php echo formatCurrency($grand_total); ?></td>
                                    <?php if (in_array($report_type, ['penjualan', 'pembelian'])): ?>
                                        <td colspan="2"></td>
                                    <?php endif; ?>
                                </tr>
                                <td class="px-4 py-2 text-right"><?php echo formatCurrency($grand_total); ?></td>
                                <?php if (in_array($report_type, ['penjualan', 'pembelian'])): ?>
                                    <td colspan="2"></td>
                                <?php endif; ?>
                                </tr>
                            <?php endif; ?>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </section>

            <footer class="mt-12 pt-8 border-t text-center text-gray-500 text-sm">
                <p>Dicetak pada: <?php echo date('d F Y, H:i:s'); ?></p>
                <p>CV. Karya Wahana Sentosa &copy; <?php echo date('Y'); ?></p>
            </footer>
        </div>
    </div>
</body>

</html>