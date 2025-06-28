<?php
require_once '../config/db_connect.php';
require_once '../includes/header.php';

$stmt = $pdo->query("SELECT COUNT(*) as total FROM barang");
$totalBarang = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT SUM(total_jual) as total FROM penjualan");
$totalPenjualan = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $pdo->query("SELECT SUM(total_beli) as total FROM pembelian");
$totalPembelian = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $pdo->query("SELECT SUM(total) as total FROM biaya");
$totalBiaya = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$totalPengeluaran = $totalPembelian + $totalBiaya;
$saldoKas = $totalPenjualan - $totalPengeluaran;
?>
<div class="flex min-h-screen">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <div id="dashboard" class="section active">
            <h2 class="text-2xl font-bold mb-6">Dashboard</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Barang</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $totalBarang; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-500">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Penjualan</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($totalPenjualan, 0, ',', '.'); ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100 text-red-500">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Pengeluaran</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($totalPengeluaran, 0, ',', '.'); ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-500">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Saldo Kas</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($saldoKas, 0, ',', '.'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold mb-4">Grafik Penjualan & Pembelian</h3>
                <div style="height:400px;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>
    </main>
</div>
<!-- Tambahkan CDN Chart.js sebelum script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    <?php
    $salesData = array_fill(0, 12, 0);
    $purchasesData = array_fill(0, 12, 0);

    $stmt = $pdo->query("SELECT MONTH(tgl_jual) as month, SUM(total_jual) as total FROM penjualan GROUP BY MONTH(tgl_jual)");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $salesData[$row['month'] - 1] = (float)$row['total'];
    }

    $stmt = $pdo->query("SELECT MONTH(tgl_beli) as month, SUM(total_beli) as total FROM pembelian GROUP BY MONTH(tgl_beli)");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $purchasesData[$row['month'] - 1] = (float)$row['total'];
    }
    ?>
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [
                {
                    label: 'Penjualan',
                    data: <?php echo json_encode($salesData); ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                },
                {
                    label: 'Pembelian',
                    data: <?php echo json_encode($purchasesData); ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.8)',
                    borderColor: 'rgb(239, 68, 68)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
</script>
<?php require_once '../includes/footer.php'; ?>