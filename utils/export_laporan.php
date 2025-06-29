<?php
require '../vendor/autoload.php';
require_once '../config/db_connect.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$type = $_GET['type'] ?? 'penjualan';
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-t');

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Judul Laporan
$sheet->mergeCells('A1:D1');
$sheet->setCellValue('A1', 'Laporan ' . ucwords(str_replace('_', ' ', $type)));
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->setCellValue('A2', 'Periode: ' . $start_date . ' s/d ' . $end_date);
$sheet->mergeCells('A2:D2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$headerRow = 4;
$dataRow = 5;

// Logika berdasarkan jenis laporan
if ($type == 'penjualan') {
    // Header Tabel
    $sheet->setCellValue('A'.$headerRow, 'ID Penjualan')->getColumnDimension('A')->setWidth(20);
    $sheet->setCellValue('B'.$headerRow, 'Tanggal')->getColumnDimension('B')->setWidth(15);
    $sheet->setCellValue('C'.$headerRow, 'Total Jual')->getColumnDimension('C')->setWidth(20);
    $sheet->getStyle('A'.$headerRow.':C'.$headerRow)->getFont()->setBold(true);

    // Query Data
    $stmt = $pdo->prepare("SELECT * FROM penjualan WHERE tgl_jual BETWEEN ? AND ?"); //
    $stmt->execute([$start_date, $end_date]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Isi Data
    $total = 0;
    foreach ($results as $row) {
        $sheet->setCellValue('A'.$dataRow, $row['id_penjualan']);
        $sheet->setCellValue('B'.$dataRow, $row['tgl_jual']);
        $sheet->setCellValue('C'.$dataRow, $row['total_jual'])->getStyle('C'.$dataRow)->getNumberFormat()->setFormatCode('#,##0');
        $total += $row['total_jual'];
        $dataRow++;
    }

    // Total
    $sheet->setCellValue('B'.$dataRow, 'Total')->getStyle('B'.$dataRow)->getFont()->setBold(true);
    $sheet->setCellValue('C'.$dataRow, $total)->getStyle('C'.$dataRow)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle('C'.$dataRow)->getFont()->setBold(true);

} 
// ... Tambahkan blok elseif untuk laporan lain (pembelian, kas, dll)

// Set Header untuk Download
$filename = 'laporan-' . $type . '-' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;