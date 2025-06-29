<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';
require_once '../includes/header.php';

// Menentukan variabel untuk filter, dengan nilai default jika tidak ada POST request
$report_type = $_POST['report_type'] ?? 'penjualan'; // Default report
$start_date = $_POST['start_date'] ?? date('Y-m-01'); // Default: awal bulan ini
$end_date = $_POST['end_date'] ?? date('Y-m-t'); // Default: akhir bulan ini

// Query untuk ringkasan di bagian atas tetap sama
$stmt_total_penjualan = $pdo->query("SELECT SUM(total_jual) as total FROM penjualan");
$total_penjualan = $stmt_total_penjualan->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt_total_pembelian = $pdo->query("SELECT SUM(total_beli) as total FROM pembelian");
$total_pembelian = $stmt_total_pembelian->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt_total_biaya = $pdo->query("SELECT SUM(total) as total FROM biaya");
$total_biaya = $stmt_total_biaya->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$saldo_kas = $total_penjualan - ($total_pembelian + $total_biaya);
?>

<div class="flex min-h-screen">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <div id="reports" class="section active">
            <h2 class="text-2xl font-bold mb-6">Laporan</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-semibold mb-2">Total Seluruh Penjualan</h3>
                    <p class="text-2xl font-bold"><?php echo formatCurrency($total_penjualan); ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-semibold mb-2">Total Seluruh Pengeluaran</h3>
                    <p class="text-2xl font-bold"><?php echo formatCurrency($total_pembelian + $total_biaya); ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-semibold mb-2">Saldo Kas Keseluruhan</h3>
                    <p class="text-2xl font-bold"><?php echo formatCurrency($saldo_kas); ?></p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6 mb-8">
                <h3 class="text-lg font-semibold mb-4">Filter Laporan Periodik</h3>
                <form method="POST" action="reports.php">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Jenis Laporan</label>
                            <select name="report_type" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="penjualan" <?php if ($report_type == 'penjualan') echo 'selected'; ?>>Laporan Penjualan</option>
                                <option value="pembelian" <?php if ($report_type == 'pembelian') echo 'selected'; ?>>Laporan Pembelian</option>
                                <option value="penerimaan_kas" <?php if ($report_type == 'penerimaan_kas') echo 'selected'; ?>>Laporan Penerimaan Kas</option>
                                <option value="pengeluaran_kas" <?php if ($report_type == 'pengeluaran_kas') echo 'selected'; ?>>Laporan Pengeluaran Kas</option>
                                <option value="buku_besar" <?php if ($report_type == 'buku_besar') echo 'selected'; ?>>Laporan Buku Besar</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tanggal Mulai</label>
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tanggal Akhir</label>
                            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-sm">
                                <i class="fas fa-filter mr-2"></i>Tampilkan
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold">Laporan <?php echo ucwords(str_replace('_', ' ', $report_type)); ?></h3>
                    <a href="../utils/export_laporan.php?type=<?php echo $report_type; ?>&start=<?php echo $start_date; ?>&end=<?php echo $end_date; ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md shadow-sm">
                        <i class="fas fa-file-excel mr-2"></i>Ekspor ke Excel
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <?php
                    // Logika untuk menampilkan tabel berdasarkan jenis laporan yang dipilih
                    $total = 0;
                    $query = "";
                    $params = [$start_date, $end_date];

                    // Tentukan query dan header tabel berdasarkan jenis laporan
                    switch ($report_type) {
                        case 'penjualan':
                            $query = "SELECT id_penjualan, tgl_jual, total_jual FROM penjualan WHERE tgl_jual BETWEEN ? AND ? ORDER BY tgl_jual";
                            $headers = ['ID Penjualan', 'Tanggal', 'Total'];
                            $columns = ['id_penjualan', 'tgl_jual', 'total_jual'];
                            $total_column = 'total_jual';
                            break;
                        case 'pembelian':
                            $query = "SELECT p.id_pembelian, p.tgl_beli, s.nama_supplier, p.total_beli 
                                      FROM pembelian p JOIN supplier s ON p.id_supplier = s.id_supplier
                                      WHERE p.tgl_beli BETWEEN ? AND ? ORDER BY p.tgl_beli";
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

                    // Render a Tabela
                    echo '<table class="min-w-full table-auto">';
                    echo '<thead class="bg-gray-50"><tr>';
                    foreach ($headers as $header) {
                        echo "<th class='px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider'>$header</th>";
                    }
                    echo '</tr></thead><tbody class="bg-white divide-y divide-gray-200">';

                    if (empty($data)) {
                        echo "<tr><td colspan='" . count($headers) . "' class='px-6 py-4 text-center text-gray-500'>Tidak ada data untuk periode ini.</td></tr>";
                    } else {
                        $total_debit = 0;
                        $total_kredit = 0;
                        
                        foreach ($data as $row) {
                            echo '<tr>';
                            foreach ($columns as $col) {
                                $is_currency = in_array($col, ['total_jual', 'total_beli', 'total', 'debit', 'kredit']);
                                $value = $is_currency ? formatCurrency($row[$col]) : htmlspecialchars($row[$col]);
                                $align = $is_currency ? 'text-right' : 'text-left';
                                echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900 $align'>$value</td>";
                            }
                            echo '</tr>';
                            
                            if ($report_type == 'buku_besar') {
                                $total_debit += $row['debit'];
                                $total_kredit += $row['kredit'];
                            } elseif (isset($total_column)) {
                                $total += $row[$total_column];
                            }
                        }
                    }

                    echo '</tbody>';

                    // Tampilkan total di footer tabel
                    if (!empty($data)) {
                        echo '<tfoot class="bg-gray-50 font-bold">';
                        if ($report_type == 'buku_besar') {
                            echo "<tr><td colspan='2' class='px-6 py-3 text-right'>Total</td>
                                     <td class='px-6 py-3 text-right'>" . formatCurrency($total_debit) . "</td>
                                     <td class='px-6 py-3 text-right'>" . formatCurrency($total_kredit) . "</td></tr>";
                            echo "<tr><td colspan='3' class='px-6 py-3 text-right'>Saldo Akhir</td>
                                     <td class='px-6 py-3 text-right'>" . formatCurrency($total_debit - $total_kredit) . "</td></tr>";
                        } else {
                            echo "<tr><td colspan='" . (count($headers) - 1) . "' class='px-6 py-3 text-right'>Total</td>
                                     <td class='px-6 py-3 text-right'>" . formatCurrency($total) . "</td></tr>";
                        }
                        echo '</tfoot>';
                    }
                    
                    echo '</table>';
                    ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once '../includes/footer.php'; ?>