<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';
require_once '../includes/header.php';

// ... (Logika PHP Anda tetap sama) ...
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user']) || $_SESSION['user']['level'] !== 'pemilik') {
    header('Location: dashboard.php');
    exit;
}
date_default_timezone_set('Asia/Jakarta');
$today = date('Y-m-d');
$default_start_date = date('Y-m-d', strtotime('-29 days'));
$default_end_date = $today;
$report_type = $_POST['report_type'] ?? 'penjualan';
$start_date = $_POST['start_date'] ?? $default_start_date;
$end_date = $_POST['end_date'] ?? $default_end_date;
if ($end_date > $today) $end_date = $today;
if ($start_date > $end_date) $start_date = $end_date;
$total_penjualan = $pdo->query("SELECT SUM(total_jual) as total FROM penjualan")->fetchColumn() ?? 0;
$total_pembelian = $pdo->query("SELECT SUM(total_beli) as total FROM pembelian")->fetchColumn() ?? 0;
$total_biaya = $pdo->query("SELECT SUM(total) as total FROM biaya")->fetchColumn() ?? 0;
$saldo_kas = $total_penjualan - ($total_pembelian + $total_biaya);
?>

<head>
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>

<div class="flex-container min-h-screen bg-gray-50">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <div id="reports" class="section active">
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-800">Laporan & Analitik</h2>
                <p class="text-gray-600 mt-2">Ringkasan, grafik, dan laporan periodik keuangan perusahaan.</p>
            </div>

            <!-- Kartu Ringkasan -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="font-semibold text-gray-500">Total Penjualan</h3>
                            <p class="text-2xl md:text-3xl font-bold text-gray-800 mt-2"><?php echo formatCurrency($total_penjualan); ?></p>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-lg"><i class="fas fa-shopping-cart text-2xl text-blue-500"></i></div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="font-semibold text-gray-500">Total Pengeluaran</h3>
                            <p class="text-2xl md:text-3xl font-bold text-gray-800 mt-2"><?php echo formatCurrency($total_pembelian + $total_biaya); ?></p>
                        </div>
                        <div class="p-3 bg-red-100 rounded-lg"><i class="fas fa-money-bill-wave text-2xl text-red-500"></i></div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="font-semibold text-gray-500">Saldo Kas</h3>
                            <p class="text-2xl md:text-3xl font-bold text-gray-800 mt-2"><?php echo formatCurrency($saldo_kas); ?></p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-lg"><i class="fas fa-wallet text-2xl text-green-500"></i></div>
                    </div>
                </div>
            </div>

            <!-- Grafik -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold mb-4"><i class="fas fa-chart-pie mr-2 text-blue-500"></i>Perbandingan Penjualan vs Pembelian</h3>
                    <div class="relative h-64 md:h-72"><canvas id="comparisonChart"></canvas></div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold mb-4"><i class="fas fa-chart-line mr-2 text-green-500"></i>Trend Penjualan (7 Hari Terakhir)</h3>
                    <div class="relative h-64 md:h-72"><canvas id="trendChart"></canvas></div>
                </div>
            </div>

            <!-- Form Filter Laporan -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <h3 class="text-xl font-semibold mb-4">Filter Laporan Periodik</h3>
                <form method="POST" action="reports.php" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Jenis Laporan</label>
                        <select name="report_type" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md">
                            <option value="penjualan" <?php if ($report_type == 'penjualan') echo 'selected'; ?>>Laporan Penjualan</option>
                            <option value="pembelian" <?php if ($report_type == 'pembelian') echo 'selected'; ?>>Laporan Pembelian</option>
                            <option value="penerimaan_kas" <?php if ($report_type == 'penerimaan_kas') echo 'selected'; ?>>Laporan Penerimaan Kas</option>
                            <option value="pengeluaran_kas" <?php if ($report_type == 'pengeluaran_kas') echo 'selected'; ?>>Laporan Pengeluaran Kas</option>
                            <option value="buku_besar" <?php if ($report_type == 'buku_besar') echo 'selected'; ?>>Laporan Buku Besar</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tanggal Mulai</label>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" max="<?php echo $today; ?>" class="mt-1 block w-full rounded-md border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tanggal Akhir</label>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" max="<?php echo $today; ?>" class="mt-1 block w-full rounded-md border-gray-300">
                    </div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md">
                        <i class="fas fa-filter mr-2"></i>Tampilkan
                    </button>
                </form>
            </div>

            <!-- Tabel Hasil Laporan -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex flex-col md:flex-row justify-between md:items-center mb-4 gap-4">
                    <h3 class="text-xl font-semibold">Hasil: Laporan <?php echo ucwords(str_replace('_', ' ', $report_type)); ?></h3>
                    <a href="../utils/export_laporan.php?type=<?php echo $report_type; ?>&start=<?php echo $start_date; ?>&end=<?php echo $end_date; ?>" class="w-full md:w-auto bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-center">
                        <i class="fas fa-file-excel mr-2"></i>Ekspor
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <?php
                    // ... (Logika PHP untuk generate tabel tetap sama) ...
                    $total = 0; $query = ""; $params = [$start_date, $end_date];
                    switch ($report_type) {
                        case 'penjualan':
                            $query = "SELECT id_penjualan, tgl_jual, total_jual FROM penjualan WHERE tgl_jual BETWEEN ? AND ? ORDER BY tgl_jual";
                            $headers = ['ID', 'Tanggal', 'Total'];
                            $columns = ['id_penjualan', 'tgl_jual', 'total_jual'];
                            $date_column = 'tgl_jual'; $total_column = 'total_jual'; break;
                        case 'pembelian':
                            $query = "SELECT p.id_pembelian, p.tgl_beli, s.nama_supplier, p.total_beli FROM pembelian p JOIN supplier s ON p.id_supplier = s.id_supplier WHERE p.tgl_beli BETWEEN ? AND ? ORDER BY p.tgl_beli";
                            $headers = ['ID', 'Tanggal', 'Supplier', 'Total'];
                            $columns = ['id_pembelian', 'tgl_beli', 'nama_supplier', 'total_beli'];
                            $date_column = 'tgl_beli'; $total_column = 'total_beli'; break;
                        case 'penerimaan_kas':
                            $query = "SELECT id_penerimaan_kas, tgl_terima_kas, uraian, total FROM penerimaan_kas WHERE tgl_terima_kas BETWEEN ? AND ? ORDER BY tgl_terima_kas";
                            $headers = ['ID', 'Tanggal', 'Uraian', 'Total'];
                            $columns = ['id_penerimaan_kas', 'tgl_terima_kas', 'uraian', 'total'];
                            $date_column = 'tgl_terima_kas'; $total_column = 'total'; break;
                        case 'pengeluaran_kas':
                             $query = "SELECT id_pengeluaran_kas, tgl_pengeluaran_kas, uraian, total FROM pengeluaran_kas WHERE tgl_pengeluaran_kas BETWEEN ? AND ? ORDER BY tgl_pengeluaran_kas";
                             $headers = ['ID', 'Tanggal', 'Uraian', 'Total'];
                             $columns = ['id_pengeluaran_kas', 'tgl_pengeluaran_kas', 'uraian', 'total'];
                             $date_column = 'tgl_pengeluaran_kas'; $total_column = 'total'; break;
                        case 'buku_besar':
                            $query = "(SELECT tgl_terima_kas as tanggal, uraian, total as debit, 0 as kredit FROM penerimaan_kas WHERE tgl_terima_kas BETWEEN ? AND ?) UNION ALL (SELECT tgl_pengeluaran_kas as tanggal, uraian, 0 as debit, total as kredit FROM pengeluaran_kas WHERE tgl_pengeluaran_kas BETWEEN ? AND ?) ORDER BY tanggal";
                            $params = [$start_date, $end_date, $start_date, $end_date];
                            $headers = ['Tanggal', 'Uraian', 'Debit', 'Kredit'];
                            $columns = ['tanggal', 'uraian', 'debit', 'kredit'];
                            $date_column = 'tanggal'; break;
                    }
                    $stmt = $pdo->prepare($query); $stmt->execute($params); $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo '<table class="min-w-full responsive-table">';
                    echo '<thead><tr>';
                    foreach ($headers as $header) {
                        $align = in_array($header, ['Total', 'Debit', 'Kredit']) ? 'text-right' : 'text-left';
                        echo "<th class='px-6 py-3 $align text-sm font-semibold text-gray-700'>$header</th>";
                    }
                    echo '</tr></thead><tbody class="bg-white divide-y divide-gray-200">';
                    
                    if (empty($data)) {
                        echo "<tr><td colspan='" . count($headers) . "' class='px-6 py-12 text-center text-gray-500'>Tidak ada data untuk periode yang dipilih.</td></tr>";
                    } else {
                        $total_debit = 0; $total_kredit = 0;
                        foreach ($data as $row) {
                            echo '<tr>';
                            foreach ($columns as $col) {
                                $is_currency = in_array($col, ['total_jual', 'total_beli', 'total', 'debit', 'kredit']);
                                $is_date = isset($date_column) && $col == $date_column;
                                $value = $is_date ? date('d M Y', strtotime($row[$col])) : ($is_currency ? formatCurrency($row[$col]) : htmlspecialchars($row[$col]));
                                $align = $is_currency ? 'text-right' : 'text-left';
                                echo "<td data-label='$col' class='px-6 py-4 text-sm text-gray-800 $align'>$value</td>";
                            }
                            echo '</tr>';
                            if ($report_type == 'buku_besar') {
                                $total_debit += $row['debit']; $total_kredit += $row['kredit'];
                            } elseif (isset($total_column)) {
                                $total += $row[$total_column];
                            }
                        }
                    }
                    echo '</tbody>';
                    
                    if (!empty($data) && $report_type != 'buku_besar') {
                        echo '<tfoot class="bg-gray-100 font-bold">';
                        echo "<tr><td colspan='" . (count($headers) - 1) . "' class='px-6 py-4 text-right text-gray-700'>Total</td><td class='px-6 py-4 text-right'>" . formatCurrency($total) . "</td></tr>";
                        echo '</tfoot>';
                    } elseif (!empty($data) && $report_type == 'buku_besar') {
                        echo '<tfoot class="bg-gray-100 font-bold">';
                        echo "<tr><td colspan='2' class='px-6 py-4 text-right text-gray-700'>Total</td><td class='px-6 py-4 text-right text-green-600'>" . formatCurrency($total_debit) . "</td><td class='px-6 py-4 text-right text-red-600'>" . formatCurrency($total_kredit) . "</td></tr>";
                        echo "<tr><td colspan='3' class='px-6 py-4 text-right text-gray-700'>Saldo Akhir</td><td class='px-6 py-4 text-right text-blue-600'>" . formatCurrency($total_debit - $total_kredit) . "</td></tr>";
                        echo '</tfoot>';
                    }
                    echo '</table>';
                    ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function( ) {
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
                    borderColor: ['#ffffff'], borderWidth: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { padding: 20, usePointStyle: true, font: { size: 14 } } } },
                cutout: '70%'
            }
        });
    }
    <?php
    $sales_trend = []; $labels_trend = [];
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
                    borderColor: 'rgba(22, 163, 74, 1)', backgroundColor: 'rgba(22, 163, 74, 0.1)',
                    borderWidth: 3, fill: true, tension: 0.4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' ' + context.dataset.label + ': ' + new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: (value) => new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(value) } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
