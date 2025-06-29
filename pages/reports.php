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

// Get filtered data for charts
$stmt_filtered_penjualan = $pdo->prepare("SELECT SUM(total_jual) as total FROM penjualan WHERE tgl_jual BETWEEN ? AND ?");
$stmt_filtered_penjualan->execute([$start_date, $end_date]);
$filtered_penjualan = $stmt_filtered_penjualan->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt_filtered_pembelian = $pdo->prepare("SELECT SUM(total_beli) as total FROM pembelian WHERE tgl_beli BETWEEN ? AND ?");
$stmt_filtered_pembelian->execute([$start_date, $end_date]);
$filtered_pembelian = $stmt_filtered_pembelian->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>

<div class="flex min-h-screen">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <div id="reports" class="section active">
            <h2 class="text-2xl font-bold mb-6">Laporan</h2>

            <!-- Enhanced Summary Cards dengan Gradients dan Icons -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6 rounded-lg shadow-lg card text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold mb-2 opacity-90">Total Seluruh Penjualan</h3>
                            <p class="text-3xl font-bold"><?php echo formatCurrency($total_penjualan); ?></p>
                        </div>
                        <div class="text-4xl opacity-75">
                            <i class="fas fa-cash-register"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-r from-red-500 to-red-600 p-6 rounded-lg shadow-lg card text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold mb-2 opacity-90">Total Seluruh Pengeluaran</h3>
                            <p class="text-3xl font-bold"><?php echo formatCurrency($total_pembelian + $total_biaya); ?></p>
                        </div>
                        <div class="text-4xl opacity-75">
                            <i class="fas fa-credit-card"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-r from-green-500 to-green-600 p-6 rounded-lg shadow-lg card text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold mb-2 opacity-90">Saldo Kas Keseluruhan</h3>
                            <p class="text-3xl font-bold"><?php echo formatCurrency($saldo_kas); ?></p>
                        </div>
                        <div class="text-4xl opacity-75">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-lg p-6 card">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <i class="fas fa-chart-pie mr-2 text-blue-500"></i>Perbandingan Penjualan vs Pembelian
                    </h3>
                    <div class="relative">
                        <canvas id="comparisonChart" width="400" height="200"></canvas>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-lg p-6 card">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <i class="fas fa-chart-line mr-2 text-green-500"></i>Trend Penjualan (7 Hari Terakhir)
                    </h3>
                    <div class="relative">
                        <canvas id="trendChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Section untuk Faktur -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8 card">
                <h3 class="text-lg font-semibold mb-4 flex items-center">
                    <i class="fas fa-file-invoice mr-2 text-purple-500"></i>Akses Cepat Faktur
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                        <h4 class="font-semibold mb-2 text-blue-600">Faktur Penjualan Terbaru</h4>
                        <?php
                        $stmt_latest_sales = $pdo->query("SELECT id_penjualan, tgl_jual, total_jual FROM penjualan ORDER BY tgl_jual DESC LIMIT 5");
                        $latest_sales = $stmt_latest_sales->fetchAll(PDO::FETCH_ASSOC);
                        if ($latest_sales): ?>
                            <div class="space-y-2">
                                <?php foreach ($latest_sales as $sale): ?>
                                    <div class="flex justify-between items-center text-sm">
                                        <span><?php echo htmlspecialchars($sale['id_penjualan']); ?> - <?php echo date('d/m/Y', strtotime($sale['tgl_jual'])); ?></span>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-green-600 font-medium"><?php echo formatCurrency($sale['total_jual']); ?></span>
                                            <a href="faktur_penjualan.php?id=<?php echo $sale['id_penjualan']; ?>" class="text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-sm">Belum ada data penjualan</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                        <h4 class="font-semibold mb-2 text-red-600">Faktur Pembelian Terbaru</h4>
                        <?php
                        $stmt_latest_purchases = $pdo->query("SELECT p.id_pembelian, p.tgl_beli, p.total_beli, s.nama_supplier FROM pembelian p JOIN supplier s ON p.id_supplier = s.id_supplier ORDER BY p.tgl_beli DESC LIMIT 5");
                        $latest_purchases = $stmt_latest_purchases->fetchAll(PDO::FETCH_ASSOC);
                        if ($latest_purchases): ?>
                            <div class="space-y-2">
                                <?php foreach ($latest_purchases as $purchase): ?>
                                    <div class="flex justify-between items-center text-sm">
                                        <span><?php echo htmlspecialchars($purchase['id_pembelian']); ?> - <?php echo date('d/m/Y', strtotime($purchase['tgl_beli'])); ?></span>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-red-600 font-medium"><?php echo formatCurrency($purchase['total_beli']); ?></span>
                                            <a href="faktur_pembelian.php?id=<?php echo $purchase['id_pembelian']; ?>" class="text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-sm">Belum ada data pembelian</p>
                        <?php endif; ?>
                    </div>
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
                    <div class="flex gap-2">
                        <a href="../utils/export_laporan.php?type=<?php echo $report_type; ?>&start=<?php echo $start_date; ?>&end=<?php echo $end_date; ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md shadow-sm">
                            <i class="fas fa-file-excel mr-2"></i>Ekspor ke Excel
                        </a>
                    </div>
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

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Animasi untuk cards saat loading
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.6s ease';
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        }, index * 200);
    });
});

// Pie Chart untuk Perbandingan Penjualan vs Pembelian
const comparisonCtx = document.getElementById('comparisonChart').getContext('2d');
const comparisonChart = new Chart(comparisonCtx, {
    type: 'doughnut',
    data: {
        labels: ['Penjualan', 'Pembelian'],
        datasets: [{
            data: [<?php echo $total_penjualan; ?>, <?php echo $total_pembelian; ?>],
            backgroundColor: [
                'rgba(34, 197, 94, 0.8)',
                'rgba(239, 68, 68, 0.8)'
            ],
            borderColor: [
                'rgba(34, 197, 94, 1)',
                'rgba(239, 68, 68, 1)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = new Intl.NumberFormat('id-ID', {
                            style: 'currency',
                            currency: 'IDR'
                        }).format(context.parsed);
                        return label + ': ' + value;
                    }
                }
            }
        },
        animation: {
            animateRotate: true,
            duration: 2000
        }
    }
});

// Line Chart untuk Trend Penjualan 7 Hari Terakhir
<?php
// Get sales data for last 7 days
$sales_trend = [];
$labels_trend = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_jual), 0) as total FROM penjualan WHERE DATE(tgl_jual) = ?");
    $stmt->execute([$date]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sales_trend[] = $total;
    $labels_trend[] = date('d M', strtotime($date));
}
?>

const trendCtx = document.getElementById('trendChart').getContext('2d');
const trendChart = new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($labels_trend); ?>,
        datasets: [{
            label: 'Penjualan',
            data: <?php echo json_encode($sales_trend); ?>,
            borderColor: 'rgba(59, 130, 246, 1)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: 'rgba(59, 130, 246, 1)',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 6,
            pointHoverRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const value = new Intl.NumberFormat('id-ID', {
                            style: 'currency',
                            currency: 'IDR'
                        }).format(context.parsed.y);
                        return 'Penjualan: ' + value;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return new Intl.NumberFormat('id-ID', {
                            style: 'currency',
                            currency: 'IDR',
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 0
                        }).format(value);
                    }
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.1)'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        },
        animation: {
            duration: 2000,
            easing: 'easeInOutQuart'
        }
    }
});

// Hover effects untuk tabel rows
document.addEventListener('DOMContentLoaded', function() {
    const tableRows = document.querySelectorAll('tbody tr');
    tableRows.forEach(row => {
        row.classList.add('table-row');
    });
});

// Loading animation untuk form submit
const reportForm = document.querySelector('form');
if (reportForm) {
    reportForm.addEventListener('submit', function() {
        const submitButton = this.querySelector('button[type="submit"]');
        const originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<div class="loading"></div> Loading...';
        submitButton.disabled = true;
        
        // Re-enable after 3 seconds as fallback
        setTimeout(() => {
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }, 3000);
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
