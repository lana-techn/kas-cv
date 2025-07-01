<?php
// Memulai output buffering untuk mencegah error "headers already sent"
ob_start();

require_once '../config/db_connect.php';
require_once '../includes/function.php';

// Validasi input dari URL
$report_type = $_GET['type'] ?? 'penjualan';
$start_date = $_GET['start'] ?? date('Y-m-d');
$end_date = $_GET['end'] ?? date('Y-m-d');

// Atur header untuk memicu download file Excel
$filename = "Laporan_{$report_type}_" . date('Ymd') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");

// Logika untuk mengambil data berdasarkan jenis laporan
$query = "";
$params = [$start_date, $end_date];
$headers = [];
$columns = [];
$total_column = '';

switch ($report_type) {
    case 'penjualan':
        $query = "SELECT id_penjualan, tgl_jual, total_jual FROM penjualan WHERE tgl_jual BETWEEN ? AND ? ORDER BY tgl_jual";
        $headers = ['ID Penjualan', 'Tanggal', 'Total'];
        $columns = ['id_penjualan', 'tgl_jual', 'total_jual'];
        $total_column = 'total_jual';
        break;
    case 'pembelian':
        $query = "SELECT p.id_pembelian, p.tgl_beli, s.nama_supplier, p.total_beli FROM pembelian p JOIN supplier s ON p.id_supplier = s.id_supplier WHERE p.tgl_beli BETWEEN ? AND ? ORDER BY p.tgl_beli";
        $headers = ['ID Pembelian', 'Tanggal', 'Supplier', 'Total'];
        $columns = ['id_pembelian', 'tgl_beli', 'nama_supplier', 'total_beli'];
        $total_column = 'total_beli';
        break;
    case 'penerimaan_kas':
        $query = "SELECT id_penerimaan_kas, tgl_terima_kas, uraian, total FROM penerimaan_kas WHERE tgl_terima_kas BETWEEN ? AND ? ORDER BY tgl_terima_kas";
        $headers = ['ID Penerimaan', 'Tanggal', 'Uraian', 'Total'];
        $columns = ['id_penerimaan_kas', 'tgl_terima_kas', 'uraian', 'total'];
        $total_column = 'total';
        break;
    case 'pengeluaran_kas':
        $query = "SELECT id_pengeluaran_kas, tgl_pengeluaran_kas, uraian, total FROM pengeluaran_kas WHERE tgl_pengeluaran_kas BETWEEN ? AND ? ORDER BY tgl_pengeluaran_kas";
        $headers = ['ID Pengeluaran', 'Tanggal', 'Uraian', 'Total'];
        $columns = ['id_pengeluaran_kas', 'tgl_pengeluaran_kas', 'uraian', 'total'];
        $total_column = 'total';
        break;
    case 'buku_besar':
        $query = "(SELECT tgl_terima_kas as tanggal, uraian, total as debit, 0 as kredit FROM penerimaan_kas WHERE tgl_terima_kas BETWEEN ? AND ?) 
                  UNION ALL 
                  (SELECT tgl_pengeluaran_kas as tanggal, uraian, 0 as debit, total as kredit FROM pengeluaran_kas WHERE tgl_pengeluaran_kas BETWEEN ? AND ?) 
                  ORDER BY tanggal";
        $params = [$start_date, $end_date, $start_date, $end_date];
        $headers = ['Tanggal', 'Uraian', 'Debit', 'Kredit'];
        $columns = ['tanggal', 'uraian', 'debit', 'kredit'];
        break;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Membuat output tabel HTML untuk file Excel
echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
echo '<head><meta charset="UTF-8"></head><body>';
echo '<h2>Laporan ' . ucwords(str_replace('_', ' ', $report_type)) . '</h2>';
echo '<h4>Periode: ' . date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date)) . '</h4>';
echo '<table border="1">';
// Header tabel
echo '<thead><tr>';
foreach ($headers as $header) {
    echo '<th>' . htmlspecialchars($header) . '</th>';
}
echo '</tr></thead>';

// Isi tabel
echo '<tbody>';
if (empty($data)) {
    echo '<tr><td colspan="' . count($headers) . '" style="text-align:center;">Tidak ada data.</td></tr>';
} else {
    $total = 0;
    $total_debit = 0;
    $total_kredit = 0;

    foreach ($data as $row) {
        echo '<tr>';
        foreach ($columns as $col) {
            $value = $row[$col];
            $style = '';
            
            // Format tanggal dan angka
            if (in_array($col, ['tgl_jual', 'tgl_beli', 'tgl_terima_kas', 'tgl_pengeluaran_kas', 'tanggal'])) {
                $value = date('d-m-Y', strtotime($value));
            } elseif (is_numeric($value)) {
                $style = 'mso-number-format:"\#\,\#\#0"'; // Format angka untuk Excel
            }

            echo '<td style="' . $style . '">' . htmlspecialchars($value) . '</td>';
        }
        echo '</tr>';

        // Kalkulasi total
        if ($report_type === 'buku_besar') {
            $total_debit += $row['debit'];
            $total_kredit += $row['kredit'];
        } elseif (!empty($total_column)) {
            $total += $row[$total_column];
        }
    }
}
echo '</tbody>';

// Footer tabel (Total)
if (!empty($data)) {
    echo '<tfoot>';
    if ($report_type === 'buku_besar') {
        echo '<tr><td colspan="2" style="text-align:right; font-weight:bold;">Total</td>';
        echo '<td style="font-weight:bold; mso-number-format:\#\,\#\#0;">' . $total_debit . '</td>';
        echo '<td style="font-weight:bold; mso-number-format:\#\,\#\#0;">' . $total_kredit . '</td></tr>';
        echo '<tr><td colspan="3" style="text-align:right; font-weight:bold;">Saldo Akhir</td>';
        echo '<td style="font-weight:bold; mso-number-format:\#\,\#\#0;">' . ($total_debit - $total_kredit) . '</td></tr>';
    } else {
        echo '<tr><td colspan="' . (count($headers) - 1) . '" style="text-align:right; font-weight:bold;">Total</td>';
        echo '<td style="font-weight:bold; mso-number-format:\#\,\#\#0;">' . $total . '</td></tr>';
    }
    echo '</tfoot>';
}

echo '</table>';
echo '</body></html>';

// Membersihkan output buffer dan mengirimkannya ke browser
ob_end_flush();
exit();
?>