<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';

// Check if required parameters are provided
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    header('Location: ../pages/reports.php');
    exit;
}

$type = $_GET['type'];
$id = $_GET['id'];

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Faktur_' . ucfirst($type) . '_' . $id . '.xls"');
header('Cache-Control: max-age=0');

try {
    if ($type === 'penjualan') {
        // Get sales data
        $stmt = $pdo->prepare("SELECT * FROM penjualan WHERE id_penjualan = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get sales details
        $stmt_detail = $pdo->prepare("
            SELECT dp.*, b.nama_barang 
            FROM detail_penjualan dp
            JOIN barang b ON dp.kd_barang = b.kd_barang
            WHERE dp.id_penjualan = ?
        ");
        $stmt_detail->execute([$id]);
        $details = $stmt_detail->fetchAll(PDO::FETCH_ASSOC);
        
        echo '<table border="1">';
        echo '<tr><td colspan="5"><strong>FAKTUR PENJUALAN</strong></td></tr>';
        echo '<tr><td colspan="5">CV. Karya Wahana Sentosa</td></tr>';
        echo '<tr><td>ID Penjualan:</td><td>' . htmlspecialchars($data['id_penjualan']) . '</td><td></td><td></td><td></td></tr>';
        echo '<tr><td>Tanggal:</td><td>' . htmlspecialchars($data['tgl_jual']) . '</td><td></td><td></td><td></td></tr>';
        echo '<tr><td colspan="5"></td></tr>';
        echo '<tr><th>No.</th><th>Nama Barang</th><th>Harga Satuan</th><th>Jumlah</th><th>Subtotal</th></tr>';
        
        foreach ($details as $index => $detail) {
            echo '<tr>';
            echo '<td>' . ($index + 1) . '</td>';
            echo '<td>' . htmlspecialchars($detail['nama_barang']) . '</td>';
            echo '<td>' . $detail['harga_jual'] . '</td>';
            echo '<td>' . $detail['qty'] . '</td>';
            echo '<td>' . $detail['subtotal'] . '</td>';
            echo '</tr>';
        }
        
        echo '<tr><td colspan="4"><strong>Total</strong></td><td><strong>' . $data['total_jual'] . '</strong></td></tr>';
        echo '<tr><td colspan="4"><strong>Bayar</strong></td><td><strong>' . $data['bayar'] . '</strong></td></tr>';
        echo '<tr><td colspan="4"><strong>Kembali</strong></td><td><strong>' . $data['kembali'] . '</strong></td></tr>';
        echo '</table>';
        
    } elseif ($type === 'pembelian') {
        // Get purchase data
        $stmt = $pdo->prepare("SELECT * FROM pembelian WHERE id_pembelian = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get purchase details
        $stmt_detail = $pdo->prepare("
            SELECT dp.*, b.nama_barang 
            FROM detail_pembelian dp
            JOIN barang b ON dp.kd_barang = b.kd_barang
            WHERE dp.id_pembelian = ?
        ");
        $stmt_detail->execute([$id]);
        $details = $stmt_detail->fetchAll(PDO::FETCH_ASSOC);
        
        echo '<table border="1">';
        echo '<tr><td colspan="5"><strong>FAKTUR PEMBELIAN</strong></td></tr>';
        echo '<tr><td colspan="5">CV. Karya Wahana Sentosa</td></tr>';
        echo '<tr><td>ID Pembelian:</td><td>' . htmlspecialchars($data['id_pembelian']) . '</td><td></td><td></td><td></td></tr>';
        echo '<tr><td>Tanggal:</td><td>' . htmlspecialchars($data['tgl_jual']) . '</td><td></td><td></td><td></td></tr>';
        echo '<tr><td colspan="5"></td></tr>';
        echo '<tr><th>No.</th><th>Nama Barang</th><th>Harga Satuan</th><th>Jumlah</th><th>Subtotal</th></tr>';
        
        foreach ($details as $index => $detail) {
            echo '<tr>';
            echo '<td>' . ($index + 1) . '</td>';
            echo '<td>' . htmlspecialchars($detail['nama_barang']) . '</td>';
            echo '<td>' . $detail['harga_jual'] . '</td>';
            echo '<td>' . $detail['qty'] . '</td>';
            echo '<td>' . $detail['subtotal'] . '</td>';
            echo '</tr>';
        }
        
        echo '<tr><td colspan="4"><strong>Total</strong></td><td><strong>' . $data['total_jual'] . '</strong></td></tr>';
        echo '<tr><td colspan="4"><strong>Bayar</strong></td><td><strong>' . $data['bayar'] . '</strong></td></tr>';
        echo '<tr><td colspan="4"><strong>Kembali</strong></td><td><strong>' . $data['kembali'] . '</strong></td></tr>';
        echo '</table>';
    }
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
