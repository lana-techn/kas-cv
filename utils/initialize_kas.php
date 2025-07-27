<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';

// Batasi akses hanya untuk admin
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['level'] !== 'admin') {
    die('Akses ditolak. Hanya administrator yang dapat menjalankan script ini.');
}

// Periksa apakah tabel kas sudah berisi data
$kas_count = $pdo->query("SELECT COUNT(*) FROM kas")->fetchColumn();
if ($kas_count > 0) {
    echo "<h2>Tabel kas sudah berisi data</h2>";
    echo "<p>Ada $kas_count record dalam tabel kas.</p>";
    echo "<p>Jika Anda ingin menginisialisasi ulang, kosongkan tabel terlebih dahulu dengan menjalankan:</p>";
    echo "<pre>TRUNCATE TABLE kas;</pre>";
    echo "<p><a href='../pages/reports.php?report_type=buku_besar'>Kembali ke Laporan Buku Besar</a></p>";
    exit;
}

try {
    $pdo->beginTransaction();

    // Ambil semua transaksi penerimaan kas
    $stmt_penerimaan = $pdo->query("
        SELECT id_penerimaan_kas, tgl_terima_kas as tanggal, uraian, total as nilai, 'penerimaan' as jenis
        FROM penerimaan_kas 
        ORDER BY tgl_terima_kas, id_penerimaan_kas
    ");
    $penerimaan_data = $stmt_penerimaan->fetchAll(PDO::FETCH_ASSOC);

    // Ambil semua transaksi pengeluaran kas
    $stmt_pengeluaran = $pdo->query("
        SELECT id_pengeluaran_kas, tgl_pengeluaran_kas as tanggal, uraian, total as nilai, 'pengeluaran' as jenis 
        FROM pengeluaran_kas 
        ORDER BY tgl_pengeluaran_kas, id_pengeluaran_kas
    ");
    $pengeluaran_data = $stmt_pengeluaran->fetchAll(PDO::FETCH_ASSOC);

    // Gabungkan semua data dan urutkan berdasarkan tanggal
    $all_transactions = array_merge($penerimaan_data, $pengeluaran_data);
    usort($all_transactions, function ($a, $b) {
        return strtotime($a['tanggal']) - strtotime($b['tanggal']);
    });

    // Inisialisasi saldo
    $saldo = 0;
    $counter = 0;

    echo "<h2>Inisialisasi Tabel Kas</h2>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>No</th><th>ID Kas</th><th>Tanggal</th><th>Keterangan</th><th>Debit</th><th>Kredit</th><th>Saldo</th></tr>";

    // Proses setiap transaksi dan masukkan ke tabel kas
    foreach ($all_transactions as $tx) {
        $counter++;
        $id_kas = generateId('KAS');
        $debit = $tx['jenis'] == 'penerimaan' ? $tx['nilai'] : 0;
        $kredit = $tx['jenis'] == 'pengeluaran' ? $tx['nilai'] : 0;

        // Update saldo
        if ($tx['jenis'] == 'penerimaan') {
            $saldo += $tx['nilai'];
        } else {
            $saldo -= $tx['nilai'];
        }

        // Insert ke tabel kas
        $stmt = $pdo->prepare("
            INSERT INTO kas (
                id_kas, 
                id_penerimaan_kas, 
                id_pengeluaran_kas, 
                tanggal, 
                keterangan, 
                debit, 
                kredit, 
                saldo
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $id_kas,
            $tx['jenis'] == 'penerimaan' ? $tx['id_penerimaan_kas'] : null,
            $tx['jenis'] == 'pengeluaran' ? $tx['id_pengeluaran_kas'] : null,
            $tx['tanggal'],
            $tx['uraian'],
            $debit,
            $kredit,
            $saldo
        ]);

        // Tampilkan data yang dimasukkan
        echo "<tr>";
        echo "<td>$counter</td>";
        echo "<td>$id_kas</td>";
        echo "<td>" . date('d/m/Y', strtotime($tx['tanggal'])) . "</td>";
        echo "<td>" . htmlspecialchars($tx['uraian']) . "</td>";
        echo "<td align='right'>" . number_format($debit, 0, ',', '.') . "</td>";
        echo "<td align='right'>" . number_format($kredit, 0, ',', '.') . "</td>";
        echo "<td align='right'>" . number_format($saldo, 0, ',', '.') . "</td>";
        echo "</tr>";
    }

    echo "</table>";

    $pdo->commit();
    echo "<h3>Berhasil menginisialisasi tabel kas dengan $counter transaksi.</h3>";
    echo "<p>Saldo Akhir: Rp " . number_format($saldo, 0, ',', '.') . "</p>";
    echo "<p><a href='../pages/reports.php?report_type=buku_besar'>Lihat Laporan Buku Besar</a></p>";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h2>Error!</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
