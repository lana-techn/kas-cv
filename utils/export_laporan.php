<?php
// Memulai output buffering untuk mencegah error "headers already sent"
ob_start();

require_once '../config/db_connect.php';
require_once '../includes/function.php';

// Validasi input dari URL
$report_type = $_GET['type'] ?? 'penjualan';
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-d');

// Atur header untuk memicu download file Excel
$filename = "Laporan_{$report_type}_" . date('Ymd') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");

// ======================= LOGIKA PENGAMBILAN DATA =======================
$query = "";
$params = [];
$headers = [];
$columns = [];
$data = [];

// Set the report title based on report type
if ($report_type == 'jurnal') {
    $report_title = "Laporan Jurnal Umum";
} else {
    $report_title = "Laporan " . ucwords(str_replace('_', ' ', $report_type));
}

switch ($report_type) {
    case 'penjualan':
        $query = "SELECT id_penjualan, tgl_jual, total_jual, bayar, kembali FROM penjualan WHERE tgl_jual BETWEEN ? AND ? ORDER BY tgl_jual";
        $params = [$start_date, $end_date];
        $headers = ['No.', 'Id Jual', 'Tanggal', 'Total', 'Bayar', 'Kembali'];
        $columns = ['id_penjualan', 'tgl_jual', 'total_jual', 'bayar', 'kembali'];
        break;

    case 'pembelian':
        $query = "SELECT p.id_pembelian, p.tgl_beli, s.nama_supplier, p.total_beli, p.bayar, p.kembali FROM pembelian p JOIN supplier s ON p.id_supplier = s.id_supplier WHERE p.tgl_beli BETWEEN ? AND ? ORDER BY p.tgl_beli";
        $params = [$start_date, $end_date];
        $headers = ['No.', 'Id Beli', 'Tanggal', 'Supplier', 'Total', 'Bayar', 'Kembali'];
        $columns = ['id_pembelian', 'tgl_beli', 'nama_supplier', 'total_beli', 'bayar', 'kembali'];
        break;

    case 'penerimaan_kas':
        $query = "SELECT tgl_terima_kas, uraian, total FROM penerimaan_kas WHERE tgl_terima_kas BETWEEN ? AND ? ORDER BY tgl_terima_kas";
        $params = [$start_date, $end_date];
        $headers = ['No.', 'Tanggal', 'Uraian', 'Jumlah (Rp)'];
        $columns = ['tgl_terima_kas', 'uraian', 'total'];
        break;

    case 'pengeluaran_kas':
        $query = "SELECT tgl_pengeluaran_kas, uraian, total FROM pengeluaran_kas WHERE tgl_pengeluaran_kas BETWEEN ? AND ? ORDER BY tgl_pengeluaran_kas";
        $params = [$start_date, $end_date];
        $headers = ['No.', 'Tanggal', 'Uraian', 'Jumlah (Rp)'];
        $columns = ['tgl_pengeluaran_kas', 'uraian', 'total'];
        break;

    case 'jurnal':
        // Laporan jurnal umum: menggabungkan kas masuk dan kas keluar sebagai jurnal
        $query = "
            -- Transaksi Pembelian (Debit: Pembelian Bahan, Kredit: Kas)
            SELECT 
                p.tgl_beli as tanggal,
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
                CONCAT('     (Pembelian Bahan Baku - No.', p.id_pembelian, ')') as keterangan,
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

        // Tidak perlu header dan columns seperti format laporan biasa
        // karena kita akan membuat format khusus di bagian render
        $render_special = true;
        $headers = ['Tanggal', 'Keterangan', 'Debit', 'Kredit', 'Saldo'];
        $columns = [];
        break;
}

if (!empty($query)) {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ======================= MEMBUAT OUTPUT HTML UNTUK EXCEL =======================
echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
echo '<head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($report_title) . '</title>
        <style>
            th { background-color:#f2f2f2; font-weight:bold; }
            .text-right { text-align: right; }
            .indent { padding-left: 40px; }
        </style>
      </head><body>';
echo '<h2 style="text-align:center; font-size:16pt; font-weight:bold;">CV. KARYA WAHANA SENTOSA</h2>';
echo '<h3 style="text-align:center; font-size:14pt; font-weight:bold;">' . htmlspecialchars($report_title) . '</h3>';
echo '<h4 style="text-align:center; font-size:11pt;">Periode: ' . date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date)) . '</h4>';
echo '<table border="1" style="width:100%; border-collapse:collapse;">';

// --- Header Tabel, tidak ditampilkan untuk buku besar ---
if ($report_type != 'buku_besar') {
    echo '<thead><tr>';
    foreach ($headers as $header) {
        echo '<th style="background-color:#f2f2f2; font-weight:bold; padding:6px;">' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr></thead>';
}

// --- Isi Tabel ---
echo '<tbody>';
if (empty($data) && $report_type != 'buku_besar') {
    echo '<tr><td colspan="' . count($headers) . '" style="text-align:center;">Tidak ada data.</td></tr>';
} else {
    $grand_total = 0;

    // Logika Khusus untuk Buku Besar
    if ($report_type === 'buku_besar') {
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
            echo '<tr style="background-color:#f2f2f2; font-weight:bold;">';
            echo '<td colspan="5" style="text-align:center; border-top:2px solid #333; border-bottom:2px solid #333; padding:8px;">' . htmlspecialchars($account) . '</td>';
            echo '</tr>';

            // Headers row for this account
            echo '<tr style="background-color:#f2f2f2; font-weight:bold;">';
            echo '<td style="padding:6px;">Tanggal</td>';
            echo '<td style="padding:6px;">Keterangan</td>';
            echo '<td style="padding:6px;">Debit</td>';
            echo '<td style="padding:6px;">Kredit</td>';
            echo '<td style="padding:6px;">Saldo</td>';
            echo '</tr>';

            // Account transactions
            $running_balance = 0;
            foreach ($account_data['transactions'] as $row) {
                $running_balance += ($row['debit'] - $row['kredit']);

                echo '<tr>';
                echo '<td>' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>';
                echo '<td>' . htmlspecialchars($row['keterangan']) . '</td>';
                echo '<td style="text-align:right; mso-number-format:\#\,\#\#0;">' . ($row['debit'] > 0 ? $row['debit'] : '-') . '</td>';
                echo '<td style="text-align:right; mso-number-format:\#\,\#\#0;">' . ($row['kredit'] > 0 ? $row['kredit'] : '-') . '</td>';
                echo '<td style="text-align:right; mso-number-format:\#\,\#\#0;">' . abs($running_balance) . ($running_balance >= 0 ? ' (D)' : ' (K)') . '</td>';
                echo '</tr>';
            }

            // Account totals
            echo '<tr style="font-weight:bold; border-top:1px solid #999;">';
            echo '<td colspan="2" style="text-align:center;">Total ' . htmlspecialchars($account) . '</td>';
            echo '<td style="text-align:right; mso-number-format:\#\,\#\#0;">' . $account_data['total_debit'] . '</td>';
            echo '<td style="text-align:right; mso-number-format:\#\,\#\#0;">' . $account_data['total_kredit'] . '</td>';
            echo '<td></td>';
            echo '</tr>';

            // Account balance
            echo '<tr style="font-weight:bold; border-bottom:2px solid #333;">';
            if ($is_debit) {
                echo '<td colspan="2" style="text-align:center;">Saldo Debit</td>';
                echo '<td style="text-align:right; mso-number-format:\#\,\#\#0;">' . abs($saldo) . '</td>';
                echo '<td colspan="2" style="text-align:right;"></td>';
            } else {
                echo '<td colspan="3" style="text-align:center;">Saldo Kredit</td>';
                echo '<td style="text-align:right; mso-number-format:\#\,\#\#0;">' . abs($saldo) . '</td>';
                echo '<td></td>';
            }
            echo '</tr>';
        }

        // No footer needed as each account has its own totals
    } else if ($report_type === 'jurnal') { // Laporan Jurnal Umum
        $currentDate = null;
        $entryNumber = 1;

        foreach ($data as $index => $row) {
            // Periksa apakah ini adalah tanggal baru untuk menentukan nomor entri dan tampilan tanggal
            $showDate = false;
            $showNumber = false;

            if ($currentDate != $row['tanggal']) {
                $currentDate = $row['tanggal'];
                $showDate = true;
                $showNumber = true;
            } else if ($row['debit'] > 0 && $index > 0 && $data[$index - 1]['debit'] > 0) {
                // Jika baris ini dan baris sebelumnya sama-sama debit, berarti ini entri baru
                $showNumber = true;
                $entryNumber++;
            } else if ($row['debit'] > 0 && $index > 0 && $data[$index - 1]['kredit'] > 0) {
                // Jika baris ini debit dan sebelumnya kredit, ini entri baru
                $showNumber = true;
                $entryNumber++;
            }

            echo '<tr>';

            // Kolom Nomor
            if ($showNumber) {
                echo '<td>' . $entryNumber . '.</td>';
            } else {
                echo '<td></td>';
            }

            // Kolom Tanggal
            if ($showDate) {
                echo '<td>' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>';
            } else {
                echo '<td></td>';
            }

            // Kolom Keterangan dengan indentasi untuk kredit
            if ($row['kredit'] > 0 || (strpos($row['keterangan'], '     ') === 0)) {
                echo '<td style="padding-left: 40px;">' . htmlspecialchars($row['keterangan']) . '</td>';
            } else {
                echo '<td>' . htmlspecialchars($row['keterangan']) . '</td>';
            }

            // Kolom Debit & Kredit
            echo '<td style="mso-number-format:\#\,\#\#0; text-align:right;">' . ($row['debit'] > 0 ? $row['debit'] : '') . '</td>';
            echo '<td style="mso-number-format:\#\,\#\#0; text-align:right;">' . ($row['kredit'] > 0 ? $row['kredit'] : '') . '</td>';
            echo '</tr>';
        }

        // Add footer with totals
        $total_debit = array_sum(array_column($data, 'debit'));
        $total_kredit = array_sum(array_column($data, 'kredit'));

        echo '<tfoot>';
        echo '<tr>';
        echo '<td colspan="3" style="text-align:right; font-weight:bold;">Total</td>';
        echo '<td style="text-align:right; font-weight:bold; mso-number-format:\#\,\#\#0;">' . $total_debit . '</td>';
        echo '<td style="text-align:right; font-weight:bold; mso-number-format:\#\,\#\#0;">' . $total_kredit . '</td>';
        echo '</tr>';
        echo '</tfoot>';
    } else { // Untuk Laporan Lainnya
        foreach ($data as $index => $row) {
            echo '<tr>';
            echo '<td>' . ($index + 1) . '.</td>'; // Kolom Nomor
            foreach ($columns as $col) {
                $value = $row[$col] ?? '';
                $style = '';

                if (strpos($col, 'tgl_') !== false || $col === 'tanggal') {
                    $value = date('d-m-Y', strtotime($value));
                } elseif (is_numeric($value)) {
                    $style = 'mso-number-format:"\#\,\#\#0"'; // Format angka untuk Excel
                }
                echo '<td style="' . $style . '">' . htmlspecialchars($value) . '</td>';
            }
            echo '</tr>';
            // Kalkulasi grand total
            $total_col_name = $total_column ?? '';
            if (!empty($total_col_name)) $grand_total += (float)($row[$total_col_name] ?? 0);
        }
        // Footer untuk Total
        if (!empty($data) && !empty($total_column)) {
            $colspan = count($headers) - 1;
            echo '<tfoot><tr><td colspan="' . $colspan . '" style="text-align:right; font-weight:bold;">Total</td><td style="font-weight:bold; mso-number-format:\#\,\#\#0;">' . $grand_total . '</td></tr></tfoot>';
        }
    }
}
echo '</tbody></table>';

// Add document footer
echo '<div style="margin-top:20px; text-align:center; font-size:10pt; color:#666;">';
echo '<p>Dicetak pada: ' . date('d F Y') . '</p>';
echo '<p>CV. Karya Wahana Sentosa &copy; ' . date('Y') . '</p>';
echo '</div>';

echo '</body></html>';

// Membersihkan output buffer dan mengirimkannya ke browser
ob_end_flush();
exit();
