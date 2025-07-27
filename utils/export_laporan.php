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
$report_title = "Laporan " . ucwords(str_replace('_', ' ', $report_type));

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

    case 'buku_besar':
        $report_title = "Laporan Buku Besar";

        // Cek apakah ada data dalam tabel kas
        $check_kas = $pdo->query("SELECT COUNT(*) FROM kas")->fetchColumn();

        if ($check_kas > 0) {
            // Jika ada data dalam tabel kas, gunakan itu untuk buku besar

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
        $columns = ['tanggal', 'uraian', 'debit', 'kredit', 'saldo']; // 'saldo' adalah kolom virtual
        break;
}

if (!empty($query)) {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ======================= MEMBUAT OUTPUT HTML UNTUK EXCEL =======================
echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
echo '<head><meta charset="UTF-8"><title>' . htmlspecialchars($report_title) . '</title></head><body>';
echo '<h2>' . htmlspecialchars($report_title) . '</h2>';
echo '<h4>Periode: ' . date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date)) . '</h4>';
echo '<table border="1">';

// --- Header Tabel ---
echo '<thead><tr>';
foreach ($headers as $header) {
    echo '<th style="background-color:#f2f2f2; font-weight:bold;">' . htmlspecialchars($header) . '</th>';
}
echo '</tr></thead>';

// --- Isi Tabel ---
echo '<tbody>';
if (empty($data) && $report_type != 'buku_besar') {
    echo '<tr><td colspan="' . count($headers) . '" style="text-align:center;">Tidak ada data.</td></tr>';
} else {
    $grand_total = 0;

    // Logika Khusus untuk Buku Besar
    if ($report_type === 'buku_besar') {
        echo '<tr><td colspan="' . (count($headers) - 1) . '" style="text-align:right; font-weight:bold;">Saldo Awal</td><td style="mso-number-format:\#\,\#\#0; font-weight:bold;">' . $saldo_awal . '</td></tr>';
        $saldo_berjalan = $saldo_awal;
        foreach ($data as $index => $row) {
            $saldo_berjalan += $row['debit'] - $row['kredit'];
            echo '<tr>';
            echo '<td>' . ($index + 1) . '.</td>';
            echo '<td>' . date('d-m-Y', strtotime($row['tanggal'])) . '</td>';
            echo '<td>' . htmlspecialchars($row['uraian']) . '</td>';
            echo '<td style="mso-number-format:\#\,\#\#0;">' . ($row['debit'] > 0 ? $row['debit'] : '-') . '</td>';
            echo '<td style="mso-number-format:\#\,\#\#0;">' . ($row['kredit'] > 0 ? $row['kredit'] : '-') . '</td>';
            echo '<td style="mso-number-format:\#\,\#\#0; font-weight:bold;">' . $saldo_berjalan . '</td>';
            echo '</tr>';
        }
        // Footer untuk Saldo Akhir
        echo '<tfoot><tr><td colspan="' . (count($headers) - 1) . '" style="text-align:right; font-weight:bold;">Saldo Akhir</td><td style="font-weight:bold; mso-number-format:\#\,\#\#0;">' . $saldo_berjalan . '</td></tr></tfoot>';
    } else { // Untuk Laporan Lainnya
        foreach ($data as $index => $row) {
            echo '<tr>';
            echo '<td>' . ($index + 1) . '.</td>'; // Kolom Nomor
            foreach ($columns as $col) {
                $value = $row[$col] ?? '';
                $style = '';

                if (strpos($col, 'tgl_') !== false) {
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
echo '</tbody></table></body></html>';

// Membersihkan output buffer dan mengirimkannya ke browser
ob_end_flush();
exit();
