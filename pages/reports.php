<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';
require_once '../includes/header.php';

// Cek hak akses
if (!isset($_SESSION['user']) || $_SESSION['user']['level'] !== 'pemilik' && $_SESSION['user']['level'] !== 'Pegawai Operasional') {
    header('Location: dashboard.php');
    exit;
}
date_default_timezone_set('Asia/Jakarta');

// Logika untuk filter tanggal dan jenis laporan
$today = date('Y-m-d');
$default_start_date = date('Y-m-d', strtotime('-29 days'));
$user_level = $_SESSION['user']['level'] ?? '';
$report_type = $_POST['report_type'] ?? 'penjualan';
// Jika bukan pemilik, paksa report_type selain buku_besar atau jurnal
if (($report_type === 'buku_besar' || $report_type === 'jurnal') && $user_level !== 'pemilik') {
    $report_type = 'penjualan';
}
$start_date = $_POST['start_date'] ?? $default_start_date;
$end_date = $_POST['end_date'] ?? $today;
if ($end_date > $today) $end_date = $today;
if ($start_date > $end_date) $start_date = $end_date;

// Data untuk kartu ringkasan di atas (opsional, bisa Anda uncomment jika ingin menampilkannya lagi)
// $total_penjualan = $pdo->query("SELECT SUM(total_jual) as total FROM penjualan")->fetchColumn() ?? 0;
// $total_pembelian = $pdo->query("SELECT SUM(total_beli) as total FROM pembelian")->fetchColumn() ?? 0;
// $total_biaya = $pdo->query("SELECT SUM(total) as total FROM biaya")->fetchColumn() ?? 0;
// $saldo_kas = $total_penjualan - ($total_pembelian + $total_biaya);
?>

<head>
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>

<div class="flex min-h-screen bg-gray-50">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <div id="reports" class="section active">
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-800">Laporan Pengelolaan Kas Perusahaan</h2>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <h3 class="text-xl font-semibold mb-4">Filter Laporan </h3>
                <form method="POST" action="reports.php" class="flex flex-col md:flex-row md:items-end gap-4 bg-white p-6 rounded-xl shadow-md">
                    <div class="lg:col-span-2 w-full">
                        <label class="block text-sm font-semibold text-gray-800 mb-1">Jenis Laporan</label>
                        <select name="report_type" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="penjualan" <?php if ($report_type == 'penjualan') echo 'selected'; ?>>Laporan Penjualan</option>
                            <option value="pembelian" <?php if ($report_type == 'pembelian') echo 'selected'; ?>>Laporan Pembelian</option>
                            <option value="penerimaan_kas" <?php if ($report_type == 'penerimaan_kas') echo 'selected'; ?>>Laporan Penerimaan Kas</option>
                            <option value="pengeluaran_kas" <?php if ($report_type == 'pengeluaran_kas') echo 'selected'; ?>>Laporan Pengeluaran Kas</option>
                            <?php if ($user_level === 'pemilik'): ?>
                                <option value="jurnal" <?php if ($report_type == 'jurnal') echo 'selected'; ?>>Laporan Jurnal Umum</option>
                                <option value="buku_besar" <?php if ($report_type == 'buku_besar') echo 'selected'; ?>>Laporan Buku Besar</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="w-full">
                        <label class="block text-sm font-semibold text-gray-800 mb-1">Tanggal Mulai</label>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" max="<?php echo $today; ?>" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div class="w-full">
                        <label class="block text-sm font-semibold text-gray-800 mb-1">Tanggal Akhir</label>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" max="<?php echo $today; ?>" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <button type="submit" class="w-full md:w-auto text-blue-800 font-semibold py-2 px-6 rounded-lg shadow transition duration-150">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>

            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex flex-col md:flex-row justify-between md:items-center mb-4 gap-4">
                    <h3 class="text-xl font-semibold">
                        <?php
                        if ($report_type == 'jurnal') {
                            echo 'Laporan Jurnal Umum';
                        } else {
                            echo 'Laporan ' . ucwords(str_replace('_', ' ', $report_type));
                        }
                        ?>
                    </h3>
                    <div class="flex justify-between gap-4 flex-wrap">
                        <a href="../utils/export_laporan.php?type=<?php echo $report_type; ?>&start=<?php echo $start_date; ?>&end=<?php echo $end_date; ?>" class="w-full md:w-auto bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-center">
                            <i class="fas fa-file-excel mr-2"></i>Ekspor
                        </a>
                        <a href="../utils/cetak_laporan.php?type=<?php echo $report_type; ?>&start=<?php echo $start_date; ?>&end=<?php echo $end_date; ?>" target="_blank" class="w-full md:w-auto bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-center">
                            <i class="fas fa-print mr-2"></i>Cetak PDF
                        </a>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <?php
                    // ======================= MODIFIKASI LOGIKA DI SINI =======================
                    $total = 0;
                    $query = "";
                    $params = [$start_date, $end_date];

                    switch ($report_type) {
                        case 'penjualan':
                            // Query untuk mengambil kolom yang dibutuhkan untuk laporan penjualan
                            $query = "SELECT id_penjualan, tgl_jual, total_jual, bayar, kembali FROM penjualan WHERE tgl_jual BETWEEN ? AND ? ORDER BY tgl_jual";
                            // Definisikan header tabel sesuai gambar
                            $headers = ['No', 'Id Jual', 'Tanggal', 'Total', 'Bayar', 'Kembali'];
                            // Definisikan kolom dari database yang akan ditampilkan
                            $columns = ['id_penjualan', 'tgl_jual', 'total_jual', 'bayar', 'kembali'];
                            $total_column = 'total_jual'; // Kolom yang akan dijumlahkan untuk total
                            break;

                        case 'pembelian':
                            // Query untuk mengambil kolom yang dibutuhkan untuk laporan pembelian
                            $query = "SELECT p.id_pembelian, p.tgl_beli, s.nama_supplier, p.total_beli, p.bayar, p.kembali FROM pembelian p JOIN supplier s ON p.id_supplier = s.id_supplier WHERE p.tgl_beli BETWEEN ? AND ? ORDER BY p.tgl_beli";
                            // Definisikan header tabel sesuai gambar
                            $headers = ['No', 'Id Beli', 'Tanggal', 'Supplier', 'Total', 'Bayar', 'Kembali'];
                            // Definisikan kolom dari database yang akan ditampilkan
                            $columns = ['id_pembelian', 'tgl_beli', 'nama_supplier', 'total_beli', 'bayar', 'kembali'];
                            $total_column = 'total_beli'; // Kolom yang akan dijumlahkan untuk total
                            break;

                        // Laporan lain tetap sama
                        case 'penerimaan_kas':
                            // Query diubah untuk tidak mengambil ID lagi
                            $query = "SELECT tgl_terima_kas, uraian, total FROM penerimaan_kas WHERE tgl_terima_kas BETWEEN ? AND ? ORDER BY tgl_terima_kas";

                            // Header tabel disesuaikan dengan gambar
                            $headers = ['No.', 'Tanggal', 'Uraian', 'Jumlah (Rp)'];

                            // Kolom data yang akan ditampilkan (tanpa ID)
                            $columns = ['tgl_terima_kas', 'uraian', 'total'];
                            $total_column = 'total';
                            break;
                        case 'pengeluaran_kas':
                            // Query diubah untuk tidak mengambil ID
                            $query = "SELECT tgl_pengeluaran_kas, uraian, total FROM pengeluaran_kas WHERE tgl_pengeluaran_kas BETWEEN ? AND ? ORDER BY tgl_pengeluaran_kas";

                            // Header tabel disesuaikan dengan gambar
                            $headers = ['No.', 'Tanggal', 'Uraian', 'Jumlah (Rp)'];

                            // Kolom data yang akan ditampilkan (tanpa ID)
                            $columns = ['tgl_pengeluaran_kas', 'uraian', 'total'];
                            $total_column = 'total';
                            break;
                        case 'jurnal':
                            // Laporan jurnal umum: menggabungkan kas masuk dan kas keluar sebagai jurnal
                            $query = "
                                -- Transaksi Pembelian (Debit: Pembelian Bahan, Kredit: Kas)
                                SELECT 
                                    p.tgl_beli as tanggal,
                                    CONCAT('Pembelian Bahan ', SUBSTRING(dp.kd_bahan, 1, 10)) as keterangan,
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
                                    '     Kas' as keterangan,
                                    0 as debit,
                                    p.total_beli as kredit
                                FROM 
                                    pembelian p
                                WHERE 
                                    p.tgl_beli BETWEEN ? AND ?
                                
                                UNION ALL
                                
                                SELECT 
                                    p.tgl_beli as tanggal,
                                    CONCAT('     (Pembelian Bahan Baku - No.Transaksi: ', p.id_pembelian, ')') as keterangan,
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
                                    '     Kas' as keterangan,
                                    0 as debit,
                                    b.total as kredit
                                FROM 
                                    biaya b
                                WHERE 
                                    b.tgl_biaya BETWEEN ? AND ?
                                
                                UNION ALL
                                
                                SELECT 
                                    b.tgl_biaya as tanggal,
                                    CONCAT('     (Biaya Operasional untuk ', b.nama_biaya, ')') as keterangan,
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
                                    CONCAT('Penjualan Produk ', SUBSTRING(dp.kd_barang, 1, 10)) as keterangan,
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
                                    CONCAT('     (Penerimaan dari Penjualan No.', p.id_penjualan, ')') as keterangan,
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

                            // Header tabel disesuaikan dengan gambar
                            $headers = ['No', 'Tanggal', 'Keterangan', 'Debit', 'Kredit'];
                            $columns = ['tanggal', 'keterangan', 'debit', 'kredit'];
                            break;
                        case 'buku_besar':
                            // Tampilan buku besar berdasarkan kategori akun
                            // Untuk laporan buku besar, kita akan menggunakan pendekatan berbeda
                            // Data tidak langsung diambil dari database, tetapi dikelompokkan berdasarkan kategori akun

                            // 1. Dapatkan semua transaksi dari jurnal
                            $query = "
                                -- Transaksi Pembelian (Debit: Pembelian Bahan, Kredit: Kas)
                                SELECT 
                                    p.tgl_beli as tanggal,
                                    'Pembelian' as akun,
                                    CONCAT('Pembelian Bahan ', SUBSTRING(dp.kd_bahan, 1, 10), ' (No.', p.id_pembelian, ')') as keterangan,
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
                                    CONCAT('Penjualan Produk ', SUBSTRING(dp.kd_barang, 1, 10), ' (No.', p.id_penjualan, ')') as keterangan,
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

                            // Meskipun kita membuat format khusus, tetap berikan header default
                            // untuk menghindari error
                            $render_special = true;
                            $headers = ['Tanggal', 'Keterangan', 'Debit', 'Kredit', 'Saldo'];
                            $columns = [];
                            break;
                    }

                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // --- Render Tabel ---
                    echo '<table class="min-w-full responsive-table border border-gray-300">';
                    if ($report_type != 'buku_besar') {
                        echo '<thead><tr class="bg-gray-100">';
                        foreach ($headers as $header) {
                            $align = in_array($header, ['Debit', 'Kredit', 'Saldo', 'Jumlah (Rp)', 'Total', 'Bayar', 'Kembali']) ? 'text-right' : 'text-left';
                            $width = ($header == 'No.') ? 'w-12' : '';
                            echo "<th class='px-4 py-3 $align text-sm font-semibold text-gray-700 border $width'>$header</th>";
                        }
                        echo '</tr></thead>';
                    }

                    // --- Render Isi Tabel ---
                    if ($report_type == 'jurnal') {
                        echo '<tbody class="bg-white">';

                        if (empty($data)) {
                            echo "<tr><td colspan='" . count($headers) . "' class='px-6 py-12 text-center text-gray-500 border'>Tidak ada transaksi pada periode ini.</td></tr>";
                        } else {
                            $total_debit = 0;
                            $total_kredit = 0;

                            // Group transactions by date and type
                            $grouped_transactions = [];
                            $current_date = '';
                            $entry_counter = 1;

                            // First pass: group transactions by date and transaction type
                            $last_date = null;
                            $last_group_key = '';
                            $groups = [];
                            $current_group = null;

                            foreach ($data as $index => $row) {
                                // For grouping transactions
                                $transaction_date = $row['tanggal'];
                                $is_debit = ($row['debit'] > 0);
                                $transaction_type = '';

                                // Determine transaction type for grouping
                                if ($is_debit) {
                                    if (strpos($row['keterangan'], 'Pembelian Bahan') !== false) {
                                        $transaction_type = 'pembelian';
                                    } else if (strpos($row['keterangan'], 'Biaya Operasional') !== false) {
                                        $transaction_type = 'biaya';
                                    } else if ($row['keterangan'] === 'Kas') {
                                        $transaction_type = 'penjualan';
                                    }

                                    // Create a new transaction group
                                    $group_key = $transaction_date . '_' . $transaction_type;
                                    if ($group_key != $last_group_key) {
                                        $current_group = [
                                            'date' => $transaction_date,
                                            'display_date' => date('d/m/Y', strtotime($transaction_date)),
                                            'entries' => []
                                        ];
                                        $groups[$group_key] = $current_group;
                                        $last_group_key = $group_key;
                                    }
                                }

                                // Add this entry to the current group if exists
                                if ($current_group !== null) {
                                    $groups[$last_group_key]['entries'][] = $row;
                                }
                            }

                            // Now display the grouped transactions
                            foreach ($groups as $group) {
                                $first_entry = true;

                                foreach ($group['entries'] as $entry) {
                                    $is_credit_entry = ($entry['kredit'] > 0);

                                    echo '<tr class="divide-x divide-gray-200">';

                                    // Show transaction number only once per transaction group
                                    if ($first_entry) {
                                        echo "<td class='px-4 py-2 border text-center'>" . $entry_counter++ . "</td>";
                                    } else {
                                        echo "<td class='px-4 py-2 border'></td>";
                                    }

                                    // Show date only once per transaction group
                                    if ($first_entry) {
                                        echo "<td class='px-4 py-2 border'>" . $group['display_date'] . "</td>";
                                    } else {
                                        echo "<td class='px-4 py-2 border'></td>";
                                    }

                                    // Show description with indent for credit entries
                                    if ($is_credit_entry) {
                                        echo "<td class='px-4 py-2 border'><span style='padding-left: 40px; display: inline-block;'>" . htmlspecialchars($entry['keterangan']) . "</span></td>";
                                    } else {
                                        echo "<td class='px-4 py-2 border'>" . htmlspecialchars($entry['keterangan']) . "</td>";
                                    }

                                    // Show debit and credit values
                                    echo "<td class='px-4 py-2 border text-right'>" . ($entry['debit'] > 0 ? formatCurrency($entry['debit']) : '-') . "</td>";
                                    echo "<td class='px-4 py-2 border text-right'>" . ($entry['kredit'] > 0 ? formatCurrency($entry['kredit']) : '-') . "</td>";
                                    echo '</tr>';

                                    // Update totals and flags
                                    $total_debit += $entry['debit'];
                                    $total_kredit += $entry['kredit'];
                                    $first_entry = false;
                                }
                            }
                        }

                        echo '</tbody>';

                        // Footer for journal totals
                        if (!empty($data)) {
                            echo '<tfoot class="bg-gray-100 font-bold">
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-right border">Total</td>
                                    <td class="px-4 py-3 text-right border">' . formatCurrency($total_debit) . '</td>
                                    <td class="px-4 py-3 text-right border">' . formatCurrency($total_kredit) . '</td>
                                </tr>
                            </tfoot>';
                        }
                    } elseif ($report_type == 'buku_besar') {
                        // Special rendering for buku_besar
                        echo '<tbody class="bg-white">';

                        if (empty($data)) {
                            echo "<tr><td colspan='5' class='px-6 py-12 text-center text-gray-500 border'>Tidak ada transaksi pada periode ini.</td></tr>";
                        } else {
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
                            foreach ($accounts as $account => $account_data) {
                                $saldo = $account_data['total_debit'] - $account_data['total_kredit'];
                                $is_debit = $saldo >= 0;

                                // Account header
                                echo '<tr class="bg-gray-100 font-bold">';
                                echo '<td colspan="5" class="px-4 py-3 text-center border-t-2 border-b-2 border-gray-800">' . htmlspecialchars($account) . '</td>';
                                echo '</tr>';

                                // Headers row for this account
                                echo '<tr class="bg-gray-100 font-semibold">';
                                echo '<td class="px-4 py-2 border">Tanggal</td>';
                                echo '<td class="px-4 py-2 border">Keterangan</td>';
                                echo '<td class="px-4 py-2 border text-right">Debit</td>';
                                echo '<td class="px-4 py-2 border text-right">Kredit</td>';
                                echo '<td class="px-4 py-2 border text-right">Saldo</td>';
                                echo '</tr>';

                                // Account transactions
                                $running_balance = 0;
                                foreach ($account_data['transactions'] as $row) {
                                    $running_balance += ($row['debit'] - $row['kredit']);

                                    echo '<tr class="divide-x divide-gray-200">';
                                    echo "<td class='px-4 py-2 border'>" . date('d/m/Y', strtotime($row['tanggal'])) . "</td>";
                                    echo "<td class='px-4 py-2 border'>" . htmlspecialchars($row['keterangan']) . "</td>";
                                    echo "<td class='px-4 py-2 border text-right'>" . ($row['debit'] > 0 ? formatCurrency($row['debit']) : '-') . "</td>";
                                    echo "<td class='px-4 py-2 border text-right'>" . ($row['kredit'] > 0 ? formatCurrency($row['kredit']) : '-') . "</td>";
                                    echo "<td class='px-4 py-2 border text-right'>" . formatCurrency(abs($running_balance)) . ($running_balance >= 0 ? ' (D)' : ' (K)') . "</td>";
                                    echo '</tr>';
                                }

                                // Account totals
                                echo '<tr class="font-bold border-t border-gray-300">';
                                echo '<td colspan="2" class="px-4 py-2 text-center border">Total ' . htmlspecialchars($account) . '</td>';
                                echo '<td class="px-4 py-2 text-right border">' . formatCurrency($account_data['total_debit']) . '</td>';
                                echo '<td class="px-4 py-2 text-right border">' . formatCurrency($account_data['total_kredit']) . '</td>';
                                echo '<td class="px-4 py-2 border"></td>';
                                echo '</tr>';

                                // Account balance
                                echo '<tr class="font-bold border-b-2 border-gray-800">';
                                if ($is_debit) {
                                    echo '<td colspan="2" class="px-4 py-2 text-center border">Saldo Debit</td>';
                                    echo '<td class="px-4 py-2 text-right border">' . formatCurrency(abs($saldo)) . '</td>';
                                    echo '<td colspan="2" class="px-4 py-2 border"></td>';
                                } else {
                                    echo '<td colspan="3" class="px-4 py-2 text-center border">Saldo Kredit</td>';
                                    echo '<td class="px-4 py-2 text-right border">' . formatCurrency(abs($saldo)) . '</td>';
                                    echo '<td class="px-4 py-2 border"></td>';
                                }
                                echo '</tr>';
                            }
                        }
                        echo '</tbody>';
                        // No footer needed as each account has its own totals
                    } else { // Untuk laporan selain buku besar
                        echo '<tbody class="bg-white">';
                        if (empty($data)) {
                            echo "<tr><td colspan='" . count($headers) . "' class='px-6 py-12 text-center text-gray-500 border'>Tidak ada data untuk periode yang dipilih.</td></tr>";
                        } else {
                            $total = 0;
                            foreach ($data as $index => $row) {
                                echo '<tr class="divide-x divide-gray-200">';
                                if (in_array($report_type, ['penjualan', 'pembelian', 'penerimaan_kas', 'pengeluaran_kas'])) {
                                    echo "<td class='px-4 py-2 border text-center'>" . ($index + 1) . ".</td>";
                                }
                                foreach ($columns as $col) {
                                    $is_currency = in_array($col, ['total_jual', 'total_beli', 'bayar', 'kembali', 'total']);
                                    $is_date = in_array($col, ['tgl_jual', 'tgl_beli', 'tgl_terima_kas', 'tgl_pengeluaran_kas']);
                                    $value = $is_date ? date('d M Y', strtotime($row[$col])) : ($is_currency ? formatCurrency($row[$col]) : htmlspecialchars($row[$col]));
                                    $align = $is_currency ? 'text-right' : 'text-left';
                                    echo "<td data-label='$col' class='px-4 py-2 text-sm text-gray-800 $align border'>$value</td>";
                                }
                                echo '</tr>';
                                if (isset($total_column)) {
                                    $total += $row[$total_column];
                                }
                            }
                        }
                        echo '</tbody>';
                        if (!empty($data) && isset($total_column)) {
                            echo '<tfoot class="bg-gray-100 font-bold">';
                            if ($report_type === 'penjualan') {
                                echo "<tr><td colspan='3' class='px-4 py-3 text-right border-t'>Total</td><td class='px-4 py-3 text-right border-t'>" . formatCurrency($total) . "</td><td colspan='2' class='border-t'></td></tr>";
                            } elseif ($report_type === 'pembelian') {
                                echo "<tr><td colspan='4' class='px-4 py-3 text-right border-t'>Total</td><td class='px-4 py-3 text-right border-t'>" . formatCurrency($total) . "</td><td colspan='2' class='border-t'></td></tr>";
                            } elseif (in_array($report_type, ['penerimaan_kas', 'pengeluaran_kas'])) {
                                echo "<tr><td colspan='3' class='px-4 py-3 text-right border-t'>Total</td><td class='px-4 py-3 text-right border-t'>" . formatCurrency($total) . "</td></tr>";
                            }
                            echo '</tfoot>';
                        }
                    }

                    echo '</table>';
                    // ======================= AKHIR MODIFIKASI =======================
                    ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        // ... (JavaScript untuk grafik tetap sama) ...

        const comparisonCtx = document.getElementById('comparisonChart');

        if (comparisonCtx) {

            new Chart(comparisonCtx, {

                type: 'doughnut',

                data: {

                    labels: ['Penjualan', 'Pembelian'],

                    datasets: [{

                        data: [<?php echo $total_penjualan; ?>, <?php echo $total_pembelian; ?>],

                        backgroundColor: ['rgba(59, 130, 246, 0.8)', 'rgba(239, 68, 68, 0.8)'],

                        borderColor: ['#ffffff'],

                        borderWidth: 4

                    }]

                },

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    plugins: {

                        legend: {

                            position: 'bottom',

                            labels: {

                                padding: 20,

                                usePointStyle: true,

                                font: {

                                    size: 14

                                }

                            }

                        }

                    },

                    cutout: '70%'

                }

            });

        }

        <?php

        $sales_trend = [];

        $labels_trend = [];

        for ($i = 6; $i >= 0; $i--) {

            $date = date('Y-m-d', strtotime("-$i days"));

            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_jual), 0) as total FROM penjualan WHERE DATE(tgl_jual) = ?");

            $stmt->execute([$date]);

            $sales_trend[] = $stmt->fetchColumn();

            $labels_trend[] = date('d M', strtotime($date));
        }

        ?>

        const trendCtx = document.getElementById('trendChart');

        if (trendCtx) {

            new Chart(trendCtx, {

                type: 'line',

                data: {

                    labels: <?php echo json_encode($labels_trend); ?>,

                    datasets: [{

                        label: 'Penjualan',

                        data: <?php echo json_encode($sales_trend); ?>,

                        borderColor: 'rgba(22, 163, 74, 1)',

                        backgroundColor: 'rgba(22, 163, 74, 0.1)',

                        borderWidth: 3,

                        fill: true,

                        tension: 0.4

                    }]

                },

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    plugins: {

                        legend: {

                            display: false

                        },

                        tooltip: {

                            callbacks: {

                                label: function(context) {

                                    return ' ' + context.dataset.label + ': ' + new Intl.NumberFormat('id-ID', {

                                        style: 'currency',

                                        currency: 'IDR'

                                    }).format(context.parsed.y);

                                }

                            }

                        }

                    },

                    scales: {

                        y: {

                            beginAtZero: true,

                            ticks: {

                                callback: (value) => new Intl.NumberFormat('id-ID', {

                                    notation: 'compact'

                                }).format(value)

                            }

                        },

                        x: {

                            grid: {

                                display: false

                            }

                        }

                    }

                }

            });

        }

    });
</script>

<?php require_once '../includes/footer.php'; ?>