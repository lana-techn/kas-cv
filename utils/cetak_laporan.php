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

// Set the report title based on report type
if ($report_type == 'jurnal') {
    $report_title = "Laporan Jurnal Umum";
} else {
    $report_title = "Laporan " . ucwords(str_replace('_', ' ', $report_type));
}

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

    case 'jurnal':
        // Periode 
        $period_text = "Periode " . date('j F Y', strtotime($start_date)) . " - " . date('j F Y', strtotime($end_date));

        // Laporan jurnal umum: menggabungkan kas masuk dan kas keluar sebagai jurnal
        $query = "
            -- Transaksi Pembelian (Debit: Pembelian Bahan, Kredit: Kas)
            SELECT 
                p.tgl_beli as tanggal,
                p.id_pembelian as id_transaksi,
                CONCAT('Pembelian Bahan ', GROUP_CONCAT(SUBSTRING(dp.kd_bahan, 1, 10) SEPARATOR ', ')) as keterangan,
                p.total_beli as debit,
                0 as kredit
            FROM 
                pembelian p
            JOIN 
                detail_pembelian dp ON p.id_pembelian = dp.id_pembelian
            WHERE 
                p.tgl_beli BETWEEN ? AND ?
            GROUP BY 
                p.id_pembelian
            
            UNION ALL
            
            SELECT 
                p.tgl_beli as tanggal,
                p.id_pembelian as id_transaksi,
                'Kas' as keterangan,
                0 as debit,
                p.total_beli as kredit
            FROM 
                pembelian p
            WHERE 
                p.tgl_beli BETWEEN ? AND ?
                
            UNION ALL
            
            SELECT 
                p.tgl_beli as tanggal,
                p.id_pembelian as id_transaksi,
                CONCAT('(Pembelian Bahan Baku - No.Transaksi: ', p.id_pembelian, ')') as keterangan,
                0 as debit,
                0 as kredit
            FROM 
                pembelian p
            WHERE 
                p.tgl_beli BETWEEN ? AND ?
            GROUP BY 
                p.id_pembelian
            
            UNION ALL
            
            -- Transaksi Biaya Operasional (Debit: Biaya Operasional, Kredit: Kas)
            SELECT 
                b.tgl_biaya as tanggal,
                b.id_biaya as id_transaksi,
                CONCAT('Biaya Operasional: ', b.nama_biaya) as keterangan,
                b.total as debit,
                0 as kredit
            FROM 
                biaya b
            WHERE 
                b.tgl_biaya BETWEEN ? AND ?
            
            UNION ALL
            
            SELECT 
                b.tgl_biaya as tanggal,
                b.id_biaya as id_transaksi,
                'Kas' as keterangan,
                0 as debit,
                b.total as kredit
            FROM 
                biaya b
            WHERE 
                b.tgl_biaya BETWEEN ? AND ?
                
            UNION ALL
            
            SELECT 
                b.tgl_biaya as tanggal,
                b.id_biaya as id_transaksi,
                CONCAT('(Biaya Operasional untuk ', b.nama_biaya, ')') as keterangan,
                0 as debit,
                0 as kredit
            FROM 
                biaya b
            WHERE 
                b.tgl_biaya BETWEEN ? AND ?
            
            UNION ALL
            
            -- Transaksi Penjualan (Debit: Kas, Kredit: Penjualan Produk)
            SELECT 
                p.tgl_jual as tanggal,
                p.id_penjualan as id_transaksi,
                'Kas' as keterangan,
                p.total_jual as debit,
                0 as kredit
            FROM 
                penjualan p
            WHERE 
                p.tgl_jual BETWEEN ? AND ?
            
            UNION ALL
            
            SELECT 
                p.tgl_jual as tanggal,
                p.id_penjualan as id_transaksi,
                CONCAT('Penjualan Produk ', GROUP_CONCAT(SUBSTRING(dp.kd_barang, 1, 10) SEPARATOR ', ')) as keterangan,
                0 as debit,
                p.total_jual as kredit
            FROM 
                penjualan p
            JOIN 
                detail_penjualan dp ON p.id_penjualan = dp.id_penjualan
            WHERE 
                p.tgl_jual BETWEEN ? AND ?
            GROUP BY 
                p.id_penjualan
                
            UNION ALL
            
            SELECT 
                p.tgl_jual as tanggal,
                p.id_penjualan as id_transaksi,
                CONCAT('(Penerimaan dari Penjualan No.', p.id_penjualan, ')') as keterangan,
                0 as debit,
                0 as kredit
            FROM 
                penjualan p
            WHERE 
                p.tgl_jual BETWEEN ? AND ?
            
            ORDER BY tanggal ASC, 
                     CASE 
                         WHEN keterangan LIKE '%(%' THEN 3  -- Description rows at the bottom
                         WHEN debit > 0 THEN 1              -- Debit rows first
                         ELSE 2                             -- Credit rows second
                     END ASC
        ";
        $params = [
            $start_date,
            $end_date, // First query - Pembelian Bahan (debit)
            $start_date,
            $end_date, // Second query - Kas for pembelian (kredit)
            $start_date,
            $end_date, // Third query - Pembelian description row
            $start_date,
            $end_date, // Fourth query - Biaya Operasional (debit)
            $start_date,
            $end_date, // Fifth query - Kas for biaya (kredit)
            $start_date,
            $end_date, // Sixth query - Biaya description row
            $start_date,
            $end_date, // Seventh query - Kas for penjualan (debit)
            $start_date,
            $end_date, // Eighth query - Penjualan Produk (kredit)
            $start_date,
            $end_date  // Ninth query - Penjualan description row
        ];

        $headers = ['No', 'Tanggal', 'Keterangan', 'Debit', 'Kredit'];
        $columns = ['tanggal', 'keterangan', 'debit', 'kredit'];
        break;
    case 'buku_besar':
        $report_title = "Laporan Buku Besar";

        // Tampilan buku besar berdasarkan kategori akun
        // Untuk laporan buku besar, kita akan menggunakan pendekatan berbeda
        // Data tidak langsung diambil dari database, tetapi dikelompokkan berdasarkan kategori akun

        // 1. Dapatkan semua transaksi dari jurnal
        $query = "
            -- Transaksi Pembelian (Debit: Pembelian Bahan, Kredit: Kas)
            SELECT 
                p.tgl_beli as tanggal,
                'Pembelian' as akun,
                CONCAT('Pembelian Bahan ', GROUP_CONCAT(SUBSTRING(dp.kd_bahan, 1, 10) SEPARATOR ', '), ' (No.', p.id_pembelian, ')') as keterangan,
                p.total_beli as debit,
                0 as kredit
            FROM 
                pembelian p
            JOIN 
                detail_pembelian dp ON p.id_pembelian = dp.id_pembelian
            WHERE 
                p.tgl_beli BETWEEN ? AND ?
            GROUP BY 
                p.id_pembelian
            
            UNION ALL
            
            SELECT 
                p.tgl_beli as tanggal,
                'Kas' as akun,
                CONCAT('Pembelian Bahan (No.', p.id_pembelian, ')') as keterangan,
                0 as debit,
                p.total_beli as kredit
            FROM 
                pembelian p
            WHERE 
                p.tgl_beli BETWEEN ? AND ?
            
            UNION ALL
            
            -- Transaksi Biaya Operasional (Debit: Biaya, Kredit: Kas)
            SELECT 
                b.tgl_biaya as tanggal,
                CONCAT('Biaya ', b.nama_biaya) as akun,
                b.nama_biaya as keterangan,
                b.total as debit,
                0 as kredit
            FROM 
                biaya b
            WHERE 
                b.tgl_biaya BETWEEN ? AND ?
            
            UNION ALL
            
            SELECT 
                b.tgl_biaya as tanggal,
                'Kas' as akun,
                CONCAT('Biaya ', b.nama_biaya) as keterangan,
                0 as debit,
                b.total as kredit
            FROM 
                biaya b
            WHERE 
                b.tgl_biaya BETWEEN ? AND ?
            
            UNION ALL
            
            -- Transaksi Penjualan (Debit: Kas, Kredit: Penjualan Produk)
            SELECT 
                p.tgl_jual as tanggal,
                'Kas' as akun,
                CONCAT('Penjualan Produk (No.', p.id_penjualan, ')') as keterangan,
                p.total_jual as debit,
                0 as kredit
            FROM 
                penjualan p
            WHERE 
                p.tgl_jual BETWEEN ? AND ?
            
            UNION ALL
            
            SELECT 
                p.tgl_jual as tanggal,
                'Penjualan' as akun,
                CONCAT('Penjualan Produk ', GROUP_CONCAT(SUBSTRING(dp.kd_barang, 1, 10) SEPARATOR ', '), ' (No.', p.id_penjualan, ')') as keterangan,
                0 as debit,
                p.total_jual as kredit
            FROM 
                penjualan p
            JOIN 
                detail_penjualan dp ON p.id_penjualan = dp.id_penjualan
            WHERE 
                p.tgl_jual BETWEEN ? AND ?
            GROUP BY 
                p.id_penjualan
            
            ORDER BY akun, tanggal ASC
        ";

        $params = [
            $start_date,
            $end_date, // Pembelian Bahan (debit)
            $start_date,
            $end_date, // Kas for pembelian (kredit)
            $start_date,
            $end_date, // Biaya Operasional (debit)
            $start_date,
            $end_date, // Kas for biaya (kredit)
            $start_date,
            $end_date, // Kas for penjualan (debit)
            $start_date,
            $end_date  // Penjualan Produk (kredit)
        ];

        // Tidak perlu header dan columns seperti format laporan biasa
        // karena kita akan membuat format khusus di bagian render
        $render_special = true;
        $headers = ['Tanggal', 'Keterangan', 'Debit', 'Kredit', 'Saldo'];
        $columns = [];
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
                    <?php if ($report_type != 'buku_besar'): ?>
                        <thead class="bg-gray-100">
                            <tr>
                                <?php
                                // Special header formatting for jurnal report
                                $header_style = ($report_type == 'jurnal') ? 'border text-center py-3' : 'text-sm font-semibold text-gray-700';

                                foreach ($headers as $header): ?>
                                    <th class="px-4 py-2 <?php echo $header_style; ?>"><?php echo $header; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                    <?php endif; ?>
                    <tbody>
                        <?php if (empty($data) && $report_type != 'buku_besar' && $report_type != 'jurnal'): ?>
                            <tr>
                                <td colspan="<?php echo count($headers); ?>" class="text-center p-4">Tidak ada data pada periode ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php
                            $grand_total = 0;
                            $total_debit = 0;
                            $total_kredit = 0;

                            if ($report_type == 'jurnal'):
                                $currentDate = null;
                                $entryNumber = 1;
                                $total_debit = 0;
                                $total_kredit = 0;
                                $current_transaction = null;
                                $previous_date = null;
                                $processed_entries = [];

                                // Group data by transaction date and ID for proper display
                                $grouped_transactions = [];
                                foreach ($data as $row) {
                                    if (!isset($row['tanggal'])) continue;

                                    $transaction_key = date('Y-m-d', strtotime($row['tanggal'])) . '-' .
                                        (isset($row['id_transaksi']) ? $row['id_transaksi'] : md5($row['keterangan']));

                                    if (!isset($grouped_transactions[$transaction_key])) {
                                        $grouped_transactions[$transaction_key] = [
                                            'date' => $row['tanggal'],
                                            'entries' => []
                                        ];
                                    }

                                    $grouped_transactions[$transaction_key]['entries'][] = $row;
                                }

                                // Process each transaction group
                                foreach ($grouped_transactions as $transaction) {
                                    $entry_date = $transaction['date'];
                                    $debit_entry = null;
                                    $credit_entry = null;
                                    $description_entry = null;

                                    // Find debit, credit and description entries
                                    foreach ($transaction['entries'] as $entry) {
                                        if ($entry['debit'] > 0) {
                                            $debit_entry = $entry;
                                            $total_debit += $entry['debit'];
                                        } elseif ($entry['kredit'] > 0) {
                                            $credit_entry = $entry;
                                            $total_kredit += $entry['kredit'];
                                        } elseif (strpos($entry['keterangan'], '(') === 0) {
                                            $description_entry = $entry;
                                        }
                                    }

                                    // Output debit entry
                                    if ($debit_entry) {
                                        echo '<tr class="divide-x divide-gray-200">';
                                        echo '<td class="px-4 py-2 border text-center">' . $entryNumber++ . '</td>';
                                        echo '<td class="px-4 py-2 border">' . date('d/m/Y', strtotime($entry_date)) . '</td>';
                                        echo '<td class="px-4 py-2 border"><strong>' . htmlspecialchars($debit_entry['keterangan']) . '</strong></td>';
                                        echo '<td class="px-4 py-2 border text-right">' . formatCurrency($debit_entry['debit']) . '</td>';
                                        echo '<td class="px-4 py-2 border text-right">-</td>';
                                        echo '</tr>';
                                    }

                                    // Output credit entry
                                    if ($credit_entry) {
                                        echo '<tr class="divide-x divide-gray-200">';
                                        echo '<td class="px-4 py-2 border text-center"></td>';
                                        echo '<td class="px-4 py-2 border"></td>';
                                        echo '<td class="px-4 py-2 border"><span style="padding-left: 40px;">' . htmlspecialchars($credit_entry['keterangan']) . '</span></td>';
                                        echo '<td class="px-4 py-2 border text-right">-</td>';
                                        echo '<td class="px-4 py-2 border text-right">' . formatCurrency($credit_entry['kredit']) . '</td>';
                                        echo '</tr>';
                                    }

                                    // Output description entry
                                    if ($description_entry) {
                                        echo '<tr class="divide-x divide-gray-200 italic bg-gray-50">';
                                        echo '<td class="px-4 py-2 border text-center"></td>';
                                        echo '<td class="px-4 py-2 border"></td>';
                                        echo '<td class="px-4 py-2 border" colspan="3"><span style="padding-left: 40px; font-style: italic; color: #666;">' . htmlspecialchars($description_entry['keterangan']) . '</span></td>';
                                        echo '</tr>';
                                    }

                                    // Add a spacer row between transaction groups
                                    echo '<tr><td colspan="5" class="py-1"></td></tr>';
                                }

                                // Show total only once - already calculated in the loop above
                            ?>
                                <?php elseif ($report_type == 'buku_besar'):
                                // Group data by account
                                $accounts = [];
                                foreach ($data as $row) {
                                    if (!isset($accounts[$row['akun']])) {
                                        $accounts[$row['akun']] = [
                                            'transactions' => [],
                                            'total_debit' => 0,
                                            'total_kredit' => 0
                                        ];
                                    }
                                    $accounts[$row['akun']]['transactions'][] = $row;
                                    $accounts[$row['akun']]['total_debit'] += $row['debit'];
                                    $accounts[$row['akun']]['total_kredit'] += $row['kredit'];
                                }

                                // Sort accounts - Kas first, then others
                                uksort($accounts, function ($a, $b) {
                                    if ($a === 'Kas') return -1;
                                    if ($b === 'Kas') return 1;
                                    if (strpos($a, 'Biaya') === 0 && strpos($b, 'Biaya') !== 0) return -1;
                                    if (strpos($a, 'Biaya') !== 0 && strpos($b, 'Biaya') === 0) return 1;
                                    return strcmp($a, $b);
                                });

                                // Now render each account
                                foreach ($accounts as $account => $account_data):
                                    $saldo = $account_data['total_debit'] - $account_data['total_kredit'];
                                    $is_debit = $saldo >= 0;
                                ?>
                                    <!-- Account header -->
                                    <tr class="bg-gray-100 font-bold">
                                        <td colspan="5" class="px-4 py-2 text-center border-t-2 border-b-2 border-gray-800"><?php echo htmlspecialchars($account); ?></td>
                                    </tr>

                                    <!-- Headers row for this account -->
                                    <tr class="bg-gray-100 font-semibold">
                                        <td class="px-4 py-2">Tanggal</td>
                                        <td class="px-4 py-2">Keterangan</td>
                                        <td class="px-4 py-2 text-right">Debit</td>
                                        <td class="px-4 py-2 text-right">Kredit</td>
                                        <td class="px-4 py-2 text-right">Saldo</td>
                                    </tr>

                                    <!-- Account transactions -->
                                    <?php
                                    $running_balance = 0;
                                    foreach ($account_data['transactions'] as $row):
                                        $running_balance += ($row['debit'] - $row['kredit']);
                                    ?>
                                        <tr>
                                            <td class="px-4 py-2"><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                                            <td class="px-4 py-2"><?php echo htmlspecialchars($row['keterangan']); ?></td>
                                            <td class="px-4 py-2 text-right"><?php echo $row['debit'] > 0 ? formatCurrency($row['debit']) : '-'; ?></td>
                                            <td class="px-4 py-2 text-right"><?php echo $row['kredit'] > 0 ? formatCurrency($row['kredit']) : '-'; ?></td>
                                            <td class="px-4 py-2 text-right"><?php echo formatCurrency(abs($running_balance)) . ($running_balance >= 0 ? ' (D)' : ' (K)'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <!-- Account totals -->
                                    <tr class="font-bold border-t border-gray-300">
                                        <td colspan="2" class="px-4 py-2 text-center">Total <?php echo htmlspecialchars($account); ?></td>
                                        <td class="px-4 py-2 text-right"><?php echo formatCurrency($account_data['total_debit']); ?></td>
                                        <td class="px-4 py-2 text-right"><?php echo formatCurrency($account_data['total_kredit']); ?></td>
                                        <td></td>
                                    </tr>

                                    <!-- Account balance -->
                                    <tr class="font-bold border-b-2 border-gray-800">
                                        <?php if ($is_debit): ?>
                                            <td colspan="2" class="px-4 py-2 text-center">
                                                Saldo Debit
                                            </td>
                                            <td class="px-4 py-2 text-right">
                                                <?php echo formatCurrency(abs($saldo)); ?>
                                            </td>
                                            <td colspan="2" class="px-4 py-2"></td>
                                        <?php else: ?>
                                            <td colspan="3" class="px-4 py-2 text-center">
                                                Saldo Kredit
                                            </td>
                                            <td class="px-4 py-2 text-right">
                                                <?php echo formatCurrency(abs($saldo)); ?>
                                            </td>
                                            <td></td>
                                        <?php endif; ?>
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
                            <?php if ($report_type == 'jurnal'): ?>
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-right border">Total</td>
                                    <td class="px-4 py-3 text-right border"><?php echo formatCurrency($total_debit); ?></td>
                                    <td class="px-4 py-3 text-right border"><?php echo formatCurrency($total_kredit); ?></td>
                                </tr>
                            <?php elseif ($report_type == 'buku_besar'): ?>
                                <!-- No footer needed as each account already has its own totals -->
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
                                <!-- <td class="px-4 py-2 text-right"><?php echo formatCurrency($grand_total); ?></td> -->
                                <!-- <?php if (in_array($report_type, ['penjualan', 'pembelian'])): ?> -->
                                <!-- <td colspan="2"></td> -->
                                <!-- <?php endif; ?> -->
                                <!-- </tr> -->
                            <?php endif; ?>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </section>

            <footer class="mt-12 pt-8 border-t text-center text-gray-500 text-sm">
                <p>Dicetak pada: <?php echo date('d F Y'); ?></p>
                <p>CV. Karya Wahana Sentosa &copy; <?php echo date('Y'); ?></p>
            </footer>
        </div>
    </div>
</body>

</html>