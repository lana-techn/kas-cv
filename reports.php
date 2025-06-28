<?php
require_once 'config/db_connect.php';
require_once 'includes/function.php';
require_once 'includes/header.php';

$stmt = $pdo->query("SELECT SUM(total_jual) as total_penjualan FROM sales");
$total_penjualan = $stmt->fetch(PDO::FETCH_ASSOC)['total_penjualan'] ?? 0;

$stmt = $pdo->query("SELECT SUM(total_beli) as total_pembelian FROM purchases");
$total_pembelian = $stmt->fetch(PDO::FETCH_ASSOC)['total_pembelian'] ?? 0;

$stmt = $pdo->query("SELECT SUM(total) as total_biaya FROM costs");
$total_biaya = $stmt->fetch(PDO::FETCH_ASSOC)['total_biaya'] ?? 0;

$saldo_kas = $total_penjualan - ($total_pembelian + $total_biaya);

$stmt = $pdo->query("SELECT * FROM products ORDER BY stok ASC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="flex min-h-screen">
    <?php require_once 'includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <div id="reports" class="section active">
            <h2 class="text-2xl font-bold mb-6">Laporan</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-semibold mb-2">Total Penjualan</h3>
                    <p class="text-2xl font-bold"><?php echo formatCurrency($total_penjualan); ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-semibold mb-2">Total Pengeluaran</h3>
                    <p class="text-2xl font-bold"><?php echo formatCurrency($total_pembelian + $total_biaya); ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-semibold mb-2">Saldo Kas</h3>
                    <p class="text-2xl font-bold"><?php echo formatCurrency($saldo_kas); ?></p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 mb-8">
                <h3 class="text-lg font-semibold mb-4">Laporan Stok Produk</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode Barang</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Barang</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stok</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($product['kd_barang']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($product['nama_barang']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($product['stok']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Grafik Keuangan</h3>
                <canvas id="financialChart" style="height: 400px;"></canvas>
            </div>
        </div>
    </main>
</div>
<script>
    const ctx = document.getElementById('financialChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Penjualan', 'Pembelian', 'Biaya', 'Saldo Kas'],
            datasets: [{
                label: 'Keuangan (IDR)',
                data: [
                    <?php echo $total_penjualan; ?>,
                    <?php echo $total_pembelian; ?>,
                    <?php echo $total_biaya; ?>,
                    <?php echo $saldo_kas; ?>
                ],
                backgroundColor: [
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(255, 159, 64, 0.8)',
                    'rgba(75, 192, 192, 0.8)'
                ],
                borderColor: [
                    'rgb(59, 130, 246)',
                    'rgb(239, 68, 68)',
                    'rgb(255, 159, 64)',
                    'rgb(75, 192, 192)'
                ],
                borderWidth: 1
            }]
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
<?php require_once 'includes/footer.php'; ?>