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
            th { background-color:#f2f2f2; font-weight:bold; padding: 8px; border: 1px solid #ddd; }
            td { padding: 6px; border: 1px solid #ddd; }
            .text-right { text-align: right; }
            .indent { padding-left: 40px; }
            .total-row { font-weight: bold; background-color: #f2f2f2; }
            .account-header { font-weight: bold; text-align: center; background-color: #e9e9e9; }
            .description-row { font-style: italic; color: #666; }
        </style>
      </head><body>';
echo '<table style="width:100%; margin-bottom:20px; border:none;">';
echo '<tr><td style="text-align:center; font-size:16pt; font-weight:bold; border:none;">CV. KARYA WAHANA SENTOSA</td></tr>';
echo '<tr><td style="text-align:center; font-size:14pt; font-weight:bold; border:none;">' . htmlspecialchars($report_title) . '</td></tr>';
echo '<tr><td style="text-align:center; font-size:11pt; border:none;">Periode: ' . date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date)) . '</td></tr>';
echo '</table>';
echo '<table border="1" style="width:100%; border-collapse:collapse;">';

// --- Header Tabel, tidak ditampilkan untuk buku besar ---
if ($report_type != 'buku_besar') {
    echo '<thead><tr>';
    if ($report_type == 'jurnal') {
        // Special formatting for jurnal headers
        echo '<th style="background-color:#f2f2f2; font-weight:bold; padding:8px; text-align:center;">No</th>';
        echo '<th style="background-color:#f2f2f2; font-weight:bold; padding:8px;">Tanggal</th>';
        echo '<th style="background-color:#f2f2f2; font-weight:bold; padding:8px;">Keterangan</th>';
        echo '<th style="background-color:#f2f2f2; font-weight:bold; padding:8px; text-align:right;">Debit</th>';
        echo '<th style="background-color:#f2f2f2; font-weight:bold; padding:8px; text-align:right;">Kredit</th>';
    } else {
        foreach ($headers as $header) {
            $align = in_array($header, ['Debit', 'Kredit', 'Saldo', 'Jumlah (Rp)', 'Total', 'Bayar', 'Kembali']) ? 'text-align:right;' : '';
            echo '<th style="background-color:#f2f2f2; font-weight:bold; padding:8px; ' . $align . '">' . htmlspecialchars($header) . '</th>';
        }
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
            echo '<td style="padding:8px;">Tanggal</td>';
            echo '<td style="padding:8px;">Keterangan</td>';
            echo '<td style="padding:8px; text-align:right;">Debit</td>';
            echo '<td style="padding:8px; text-align:right;">Kredit</td>';
            echo '<td style="padding:8px; text-align:right;">Saldo</td>';
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
            echo '<td colspan="2" style="text-align:center; padding:8px;">Total ' . htmlspecialchars($account) . '</td>';
            echo '<td style="text-align:right; padding:8px; mso-number-format:\#\,\#\#0;">' . $account_data['total_debit'] . '</td>';
            echo '<td style="text-align:right; padding:8px; mso-number-format:\#\,\#\#0;">' . $account_data['total_kredit'] . '</td>';
            echo '<td></td>';
            echo '</tr>';

            // Account balance
            echo '<tr style="font-weight:bold; border-bottom:2px solid #333;">';
            if ($is_debit) {
                echo '<td colspan="2" style="text-align:center; padding:8px;">Saldo Debit</td>';
                echo '<td style="text-align:right; padding:8px; mso-number-format:\#\,\#\#0;">' . abs($saldo) . '</td>';
                echo '<td colspan="2" style="text-align:right;"></td>';
            } else {
                echo '<td colspan="3" style="text-align:center; padding:8px;">Saldo Kredit</td>';
                echo '<td style="text-align:right; padding:8px; mso-number-format:\#\,\#\#0;">' . abs($saldo) . '</td>';
                echo '<td></td>';
            }
            echo '</tr>';
        }

        // No footer needed as each account has its own totals
    } else if ($report_type === 'jurnal') { // Laporan Jurnal Umum
        $currentDate = null;
        $entryNumber = 1;
        $total_debit = 0;
        $total_kredit = 0;

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
                echo '<tr>';
                echo '<td>' . $entryNumber++ . '.</td>';
                echo '<td>' . date('d/m/Y', strtotime($entry_date)) . '</td>';
                echo '<td style="font-weight:bold;">' . htmlspecialchars($debit_entry['keterangan']) . '</td>';
                echo '<td style="mso-number-format:\#\,\#\#0; text-align:right;">' . $debit_entry['debit'] . '</td>';
                echo '<td style="text-align:right;">-</td>';
                echo '</tr>';
            }

            // Output credit entry
            if ($credit_entry) {
                echo '<tr>';
                echo '<td></td>';
                echo '<td></td>';
                echo '<td style="padding-left: 40px;">' . htmlspecialchars($credit_entry['keterangan']) . '</td>';
                echo '<td style="text-align:right;">-</td>';
                echo '<td style="mso-number-format:\#\,\#\#0; text-align:right;">' . $credit_entry['kredit'] . '</td>';
                echo '</tr>';
            }

            // Output description entry
            if ($description_entry) {
                echo '<tr style="font-style:italic; color:#666;">';
                echo '<td></td>';
                echo '<td></td>';
                echo '<td colspan="3" style="padding-left: 40px;">' . htmlspecialchars($description_entry['keterangan']) . '</td>';
                echo '</tr>';
            }

            // Add a spacer row between transaction groups for better readability
            echo '<tr><td colspan="5" style="height:5px;"></td></tr>';
        }

        // Add footer with totals
        echo '<tfoot>';
        echo '<tr style="font-weight:bold; background-color:#f2f2f2;">';
        echo '<td colspan="3" style="text-align:right; padding:8px;">Total</td>';
        echo '<td style="text-align:right; padding:8px; mso-number-format:\#\,\#\#0;">' . $total_debit . '</td>';
        echo '<td style="text-align:right; padding:8px; mso-number-format:\#\,\#\#0;">' . $total_kredit . '</td>';
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
echo '<table style="width:100%; margin-top:20px; border:none;">';
echo '<tr><td style="text-align:center; font-size:10pt; color:#666; border:none;">Dicetak pada: ' . date('d F Y') . '</td></tr>';
echo '<tr><td style="text-align:center; font-size:10pt; color:#666; border:none;">CV. Karya Wahana Sentosa &copy; ' . date('Y') . '</td></tr>';
echo '</table>';

echo '</body></html>';

// Membersihkan output buffer dan mengirimkannya ke browser
ob_end_flush();
exit();
