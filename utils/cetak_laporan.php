<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';
// Updated path to use Composer's autoloader
require_once '../vendor/autoload.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Import FPDF class if using setasign/fpdf via Composer
use setasign\Fpdi\Fpdi;
use setasign\Fpdf\Fpdf;

// Verify if FPDF class exists
if (!class_exists('setasign\Fpdf\Fpdf')) {
    die('Error: FPDF library is not properly installed. Please run "composer require setasign/fpdf" in the project root directory.');
}

// Ambil parameter dari URL
$report_type = $_GET['type'] ?? 'penjualan';
$start_date = $_GET['start'] ?? date('Y-m-d');
$end_date = $_GET['end'] ?? date('Y-m-d');

// Ambil data dari database sesuai jenis laporan
$params = [$start_date, $end_date];
$title = "Laporan " . ucwords(str_replace('_', ' ', $report_type));
$query = "";
$headers = [];
$columns = [];
$column_widths = [];
$total_column = '';
$date_column = '';
$total = 0;

switch ($report_type) {
    case 'penjualan':
        $query = "SELECT id_penjualan, tgl_jual, total_jual FROM penjualan WHERE tgl_jual BETWEEN ? AND ? ORDER BY tgl_jual";
        $headers = ['ID Penjualan', 'Tanggal', 'Total'];
        $columns = ['id_penjualan', 'tgl_jual', 'total_jual'];
        $column_widths = [40, 50, 80];
        $total_column = 'total_jual';
        $date_column = 'tgl_jual';
        break;
    case 'pembelian':
        $query = "SELECT p.id_pembelian, p.tgl_beli, s.nama_supplier, p.total_beli FROM pembelian p JOIN supplier s ON p.id_supplier = s.id_supplier WHERE p.tgl_beli BETWEEN ? AND ? ORDER BY p.tgl_beli";
        $headers = ['ID Pembelian', 'Tanggal', 'Supplier', 'Total'];
        $columns = ['id_pembelian', 'tgl_beli', 'nama_supplier', 'total_beli'];
        $column_widths = [35, 35, 60, 40];
        $total_column = 'total_beli';
        $date_column = 'tgl_beli';
        break;
    case 'penerimaan_kas':
        $query = "SELECT id_penerimaan_kas, tgl_terima_kas, uraian, total FROM penerimaan_kas WHERE tgl_terima_kas BETWEEN ? AND ? ORDER BY tgl_terima_kas";
        $headers = ['ID', 'Tanggal', 'Uraian', 'Total'];
        $columns = ['id_penerimaan_kas', 'tgl_terima_kas', 'uraian', 'total'];
        $column_widths = [30, 35, 85, 40];
        $total_column = 'total';
        $date_column = 'tgl_terima_kas';
        break;
    case 'pengeluaran_kas':
        $query = "SELECT id_pengeluaran_kas, tgl_pengeluaran_kas, uraian, total FROM pengeluaran_kas WHERE tgl_pengeluaran_kas BETWEEN ? AND ? ORDER BY tgl_pengeluaran_kas";
        $headers = ['ID', 'Tanggal', 'Uraian', 'Total'];
        $columns = ['id_pengeluaran_kas', 'tgl_pengeluaran_kas', 'uraian', 'total'];
        $column_widths = [30, 35, 85, 40];
        $total_column = 'total';
        $date_column = 'tgl_pengeluaran_kas';
        break;
    case 'buku_besar':
        $query = "(SELECT tgl_terima_kas as tanggal, uraian, total as debit, 0 as kredit FROM penerimaan_kas WHERE tgl_terima_kas BETWEEN ? AND ?) UNION ALL (SELECT tgl_pengeluaran_kas as tanggal, uraian, 0 as debit, total as kredit FROM pengeluaran_kas WHERE tgl_pengeluaran_kas BETWEEN ? AND ?) ORDER BY tanggal";
        $params = [$start_date, $end_date, $start_date, $end_date];
        $headers = ['Tanggal', 'Uraian', 'Debit', 'Kredit'];
        $columns = ['tanggal', 'uraian', 'debit', 'kredit'];
        $column_widths = [35, 85, 35, 35];
        $date_column = 'tanggal';
        break;
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error: Unable to fetch data from database. ' . $e->getMessage());
    $pdf = new Fpdf('P', 'mm', 'A4');

// Buat PDF
try {
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();

    // Header Laporan
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'CV. KARYA WAHANA SENTOSA', 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 7, $title, 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, 'Periode: ' . date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date)), 0, 1, 'C');
    $pdf->Ln(10);

    // Header Tabel
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(230, 230, 230);
    for ($i = 0; $i < count($headers); $i++) {
        $align = in_array($headers[$i], ['Total', 'Debit', 'Kredit']) ? 'R' : 'L';
        $pdf->Cell($column_widths[$i], 10, $headers[$i], 1, 0, $align, true);
    }
    $pdf->Ln();

    // Isi Tabel
    $pdf->SetFont('Arial', '', 9);
    if (empty($data)) {
        $pdf->Cell(0, 10, 'Tidak ada data untuk periode ini.', 1, 1, 'C');
    } else {
        $total_debit = 0;
        $total_kredit = 0;

        foreach ($data as $row) {
            for ($i = 0; $i < count($columns); $i++) {
                $col = $columns[$i];
                $value = $row[$col];
                $align = 'L';

                if (in_array($col, ['total_jual', 'total_beli', 'total', 'debit', 'kredit'])) {
                    $value = formatCurrency($value);
                    $align = 'R';
                } elseif ($col === $date_column) {
                    $value = date('d/m/Y', strtotime($value));
                }

                $pdf->Cell($column_widths[$i], 8, htmlspecialchars_decode($value), 1, 0, $align);
            }
            $pdf->Ln();

            // Akumulasi total
            if ($report_type == 'buku_besar') {
                $total_debit += $row['debit'];
                $total_kredit += $row['kredit'];
            } elseif (!empty($total_column)) {
                $total += $row[$total_column];
            }
        }
    }

    // Footer Tabel (Total)
    $pdf->SetFont('Arial', 'B', 10);
    if ($report_type != 'buku_besar' && !empty($data)) {
        $pdf->Cell(array_sum(array_slice($column_widths, 0, -1)), 10, 'Total', 1, 0, 'R');
        $pdf->Cell(end($column_widths), 10, formatCurrency($total), 1, 1, 'R');
    } elseif ($report_type == 'buku_besar' && !empty($data)) {
        // Total Debit & Kredit
        $pdf->Cell($column_widths[0] + $column_widths[1], 10, 'Total', 1, 0, 'R');
        $pdf->Cell($column_widths[2], 10, formatCurrency($total_debit), 1, 0, 'R');
        $pdf->Cell($column_widths[3], 10, formatCurrency($total_kredit), 1, 1, 'R');

        // Saldo Akhir
        $pdf->Cell(array_sum($column_widths) - $column_widths[3], 10, 'Saldo Akhir', 1, 0, 'R');
        $pdf->Cell($column_widths[3], 10, formatCurrency($total_debit - $total_kredit), 1, 1, 'R');
    }


    $pdf->Output('I', 'Laporan_' . str_replace(' ', '_', $title) . '.pdf');
} catch (Exception $e) {
    die('Error: Unable to generate PDF. ' . $e->getMessage());
}
