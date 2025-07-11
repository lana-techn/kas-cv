<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php'; // Pastikan fungsi formatCurrency() ada di sini
require_once '../includes/header.php';
// === DATA FETCHING LOGIC ===
// Semua query dan pengolahan data dikumpulkan di sini untuk memisahkan logika dari tampilan.
$userLevel = $_SESSION['user']['level'] ?? 'guest';
$data = []; // Inisialisasi array untuk menampung semua data yang akan dikirim ke view.
// Fungsi untuk format waktu "time ago"
// Didefinisikan sekali di level atas untuk menghindari duplikasi.
if (!function_exists('time_ago')) {
    function time_ago($timestamp)
    {
        $time_ago = time() - $timestamp;
        if ($time_ago < 60) return 'beberapa detik lalu';
        $minutes = round($time_ago / 60);
        if ($minutes < 60) return "$minutes menit lalu";
        $hours = round($time_ago / 3600);
        if ($hours < 24) return "$hours jam lalu";
        $days = round($time_ago / 86400);
        return "$days hari lalu";
    }
}
// --- Data Fetching Berdasarkan Role ---

if ($userLevel === 'admin') {
    // --- Data untuk Statistik Admin ---
    $data['totalUsers'] = $pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
    $data['totalSuppliers'] = $pdo->query("SELECT COUNT(*) FROM supplier")->fetchColumn();
    $data['stokMenipis'] = $pdo->query("SELECT COUNT(*) FROM bahan WHERE stok < 20")->fetchColumn();
    $data['totalProduksi'] = $pdo->query("SELECT COUNT(*) FROM produksi WHERE status = 'Selesai'")->fetchColumn() ?? 0;
    // --- Data untuk Aktivitas Terbaru Admin ---
    $latestActivities = [];
    $stmt_users = $pdo->query("SELECT username, id_user FROM user ORDER BY id_user DESC LIMIT 5");
    while ($row = $stmt_users->fetch(PDO::FETCH_ASSOC)) {
        $latestActivities[] = [
            'timestamp' => time(), // Placeholder, ganti dengan kolom tanggal jika ada.
            'icon' => 'fas fa-user-plus text-green-500',
            'description' => 'User baru <strong class="text-gray-900">' . htmlspecialchars($row['username']) . '</strong> telah ditambahkan.'
        ];
    }
    $stmt_prod = $pdo->query("SELECT p.tgl_produksi, b.nama_barang FROM produksi p JOIN barang b ON p.kd_barang = b.kd_barang ORDER BY p.tgl_produksi DESC LIMIT 5");
    while ($row = $stmt_prod->fetch(PDO::FETCH_ASSOC)) {
        $latestActivities[] = [
            'timestamp' => strtotime($row['tgl_produksi']),
            'icon' => 'fas fa-industry text-blue-500',
            'description' => 'Produksi <strong class="text-gray-900">' . htmlspecialchars($row['nama_barang']) . '</strong> telah dicatat.'
        ];
    }
    $stmt_bahan = $pdo->query("SELECT nama_bahan, kd_bahan FROM bahan ORDER BY kd_bahan DESC LIMIT 5");
    while ($row = $stmt_bahan->fetch(PDO::FETCH_ASSOC)) {
        $latestActivities[] = [
            'timestamp' => strtotime($row['kd_bahan']),
            'icon' => 'fas fa-box-open text-yellow-500',
            'description' => 'Bahan <strong class="text-gray-900">' . htmlspecialchars($row['nama_bahan']) . '</strong> telah ditambahkan.'
        ];
    }
    $stmt_supplier = $pdo->query("SELECT nama_supplier, id_supplier FROM supplier ORDER BY id_supplier DESC LIMIT 5");
    while ($row = $stmt_supplier->fetch(PDO::FETCH_ASSOC)) {
        $latestActivities[] = [
            'timestamp' => strtotime($row['id_supplier']),
            'icon' => 'fas fa-truck text-orange-500',
            'description' => 'Supplier <strong class="text-gray-900">' . htmlspecialchars($row['nama_supplier']) . '</strong> telah ditambahkan.'
        ];
    }

    // Urutkan dan potong array aktivitas
    $data['latestActivities'] = array_slice($latestActivities, 0, 5);
} elseif ($userLevel === 'pegawai') {
    // --- Data untuk Statistik Pegawai ---
    $today = date('Y-m-d');
    $stmt_sales_today = $pdo->prepare("SELECT COUNT(*) FROM penjualan WHERE tgl_jual = ?");
    $stmt_sales_today->execute([$today]);
    $data['salesToday'] = $stmt_sales_today->fetchColumn();

    $stmt_purchases_today = $pdo->prepare("SELECT COUNT(*) FROM pembelian WHERE tgl_beli = ?");
    $stmt_purchases_today->execute([$today]);
    $data['purchasesToday'] = $stmt_purchases_today->fetchColumn();

    $stmt_costs_month = $pdo->prepare("SELECT SUM(total) FROM biaya WHERE MONTH(tgl_biaya) = MONTH(CURDATE()) AND YEAR(tgl_biaya) = YEAR(CURDATE())");
    $stmt_costs_month->execute();
    $data['costsThisMonth'] = $stmt_costs_month->fetchColumn() ?? 0;

    // --- Data untuk Transaksi Terbaru Pegawai ---
    $data['recentTransactions'] = $pdo->query("
        (SELECT id_penjualan as id, tgl_jual as tanggal, total_jual as total, 'penjualan' as tipe, '' as partner FROM penjualan)
        UNION ALL
        (SELECT p.id_pembelian as id, p.tgl_beli as tanggal, p.total_beli as total, 'pembelian' as tipe, s.nama_supplier as partner FROM pembelian p JOIN supplier s ON p.id_supplier = s.id_supplier)
        ORDER BY tanggal DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    $stmtBahan = $pdo->query("SELECT COUNT(*) FROM bahan");
    $data['totalMaterials'] = $stmtBahan->fetchColumn();

    $stmtProduk = $pdo->query("SELECT COUNT(*) FROM barang");
    $data['totalProducts'] = $stmtProduk->fetchColumn();

    $stmtSupplier = $pdo->query("SELECT COUNT(*) FROM supplier");
    $data['totalSuppliers'] = $stmtSupplier->fetchColumn();
    // --- Tambahkan kode ini di bagian atas file dashboard.php ---

    // Tentukan batas stok rendah
    $lowStockThreshold = 20;

    // Hitung bahan baku yang stoknya menipis
    $stmtLowStockBahan = $pdo->prepare("SELECT COUNT(*) FROM bahan WHERE stok <= ?");
    $stmtLowStockBahan->execute([$lowStockThreshold]);
    $data['lowStockMaterials'] = $stmtLowStockBahan->fetchColumn();

    // Hitung produk jadi yang stoknya menipis
    $stmtLowStockProduk = $pdo->prepare("SELECT COUNT(*) FROM barang WHERE stok <= ?");
    $stmtLowStockProduk->execute([$lowStockThreshold]);
    $data['lowStockProducts'] = $stmtLowStockProduk->fetchColumn();
} elseif ($userLevel === 'pemilik') {
    // --- Data untuk Statistik Pemilik ---
    $data['totalBarang'] = $pdo->query("SELECT COUNT(*) FROM barang")->fetchColumn();
    $data['totalPenjualan'] = $pdo->query("SELECT SUM(total_jual) FROM penjualan")->fetchColumn() ?? 0;
    $totalPembelian = $pdo->query("SELECT SUM(total_beli) FROM pembelian")->fetchColumn() ?? 0;
    $totalBiaya = $pdo->query("SELECT SUM(total) FROM biaya")->fetchColumn() ?? 0;
    $data['totalPengeluaran'] = $totalPembelian + $totalBiaya;
    $data['saldoKas'] = $data['totalPenjualan'] - $data['totalPengeluaran'];

    // --- PERBAIKAN 1: Logika dan Batasan Tanggal yang Ditingkatkan ---
date_default_timezone_set('Asia/Jakarta');
$today = date('Y-m-d'); // Mendapatkan tanggal hari ini untuk batasan

// Nilai default yang lebih dinamis: 30 hari terakhir dari hari ini
$default_start_date = date('Y-m-d', strtotime('-29 days'));
$default_end_date = $today;

$report_type = $_POST['report_type'] ?? 'penjualan';
$start_date = $_POST['start_date'] ?? $default_start_date;
$end_date = $_POST['end_date'] ?? $default_end_date;

// Validasi di sisi server untuk memastikan tanggal tidak melebihi hari ini
if ($end_date > $today) {
    $end_date = $today;
}
// Memastikan tanggal mulai tidak lebih besar dari tanggal akhir
if ($start_date > $end_date) {
    $start_date = $end_date;
}
// --- AKHIR PERBAIKAN 1 ---

// Query untuk ringkasan di bagian atas (TETAP SAMA)
$stmt_total_penjualan = $pdo->query("SELECT SUM(total_jual) as total FROM penjualan");
$total_penjualan = $stmt_total_penjualan->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt_total_pembelian = $pdo->query("SELECT SUM(total_beli) as total FROM pembelian");
$total_pembelian = $stmt_total_pembelian->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt_total_biaya = $pdo->query("SELECT SUM(total) as total FROM biaya");
$total_biaya = $stmt_total_biaya->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$saldo_kas = $total_penjualan - ($total_pembelian + $total_biaya);

// Get filtered data for charts (TETAP SAMA)
$stmt_filtered_penjualan = $pdo->prepare("SELECT SUM(total_jual) as total FROM penjualan WHERE tgl_jual BETWEEN ? AND ?");
$stmt_filtered_penjualan->execute([$start_date, $end_date]);
$filtered_penjualan = $stmt_filtered_penjualan->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt_filtered_pembelian = $pdo->prepare("SELECT SUM(total_beli) as total FROM pembelian WHERE tgl_beli BETWEEN ? AND ?");
$stmt_filtered_pembelian->execute([$start_date, $end_date]);
$filtered_pembelian = $stmt_filtered_pembelian->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    


}
?>
<div class="flex min-h-screen">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <div id="dashboard" class="section active">
            <h2 class="text-2xl font-bold mb-6">Dashboard</h2>
            <?php if ($userLevel === 'admin'): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 items-start">
                    <div class="group relative bg-gradient-to-br from-blue-500 to-blue-600 p-6 rounded-2xl shadow-lg hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300 overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 bg-white/10 w-24 h-24 rounded-full opacity-50 group-hover:scale-125 transition-transform duration-500"></div>
                        <div class="relative z-10">
                            <h3 class="text-white font-semibold text-lg">Manajemen User</h3>
                            <p class="text-3xl font-bold text-white mt-4"><?php echo $data['totalUsers']; ?> <span class="text-base font-normal opacity-80">Pengguna</span></p>
                            <a href="user_management.php" class="inline-block mt-4 bg-white text-blue-600 font-bold py-2 px-4 rounded-lg text-sm">Kelola</a>
                        </div>
                    </div>
                    <div class="group relative bg-gradient-to-br from-yellow-400 to-orange-500 p-6 rounded-2xl shadow-lg hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300 overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 bg-white/10 w-24 h-24 rounded-full opacity-50 group-hover:scale-125 transition-transform duration-500"></div>
                        <div class="relative z-10">
                            <h3 class="text-white font-semibold text-lg">Manajemen Bahan</h3>
                            <p class="text-3xl font-bold text-white mt-4"><?php echo $data['stokMenipis']; ?> <span class="text-base font-normal opacity-80">Stok Menipis</span></p>
                            <a href="material_management.php" class="inline-block mt-4 bg-white text-orange-600 font-bold py-2 px-4 rounded-lg text-sm">Cek Inventaris</a>
                        </div>
                    </div>
                    <div class="group relative bg-gradient-to-br from-green-500 to-teal-600 p-6 rounded-2xl shadow-lg hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300 overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 bg-white/10 w-24 h-24 rounded-full opacity-50 group-hover:scale-125 transition-transform duration-500"></div>
                        <div class="relative z-10">
                            <h3 class="text-white font-semibold text-lg">Manajemen Supplier</h3>
                            <p class="text-3xl font-bold text-white mt-4"><?php echo $data['totalSuppliers']; ?> <span class="text-base font-normal opacity-80">Supplier</span></p>
                            <a href="supplier_management.php" class="inline-block mt-4 bg-white text-green-600 font-bold py-2 px-4 rounded-lg text-sm">Lihat</a>
                        </div>
                    </div>
                    <div class="group relative bg-gradient-to-br from-purple-500 to-pink-500 p-6 rounded-2xl shadow-lg hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300 overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 bg-white/10 w-24 h-24 rounded-full opacity-50 group-hover:scale-125 transition-transform duration-500"></div>
                        <div class="relative z-10">
                            <h3 class="text-white font-semibold text-lg">Manajemen Produksi</h3>
                            <p class="text-3xl font-bold text-white mt-4"><?php echo $data['totalProduksi']; ?> <span class="text-base font-normal opacity-80">Produksi Aktif</span></p>
                            <a href="production_management.php" class="inline-block mt-4 bg-white text-purple-600 font-bold py-2 px-4 rounded-lg text-sm">Mulai Produksi</a>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-1 bg-white p-6 rounded-2xl shadow-md">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Akses Cepat</h3>
                        <div class="space-y-3">
                            <a href="user_management.php" class="flex items-center justify-between p-4 bg-blue-50 hover:bg-blue-100 rounded-lg">
                                <p class="font-semibold text-blue-800">Tambah User Baru</p><i class="fas fa-user-plus text-blue-500"></i>
                            </a>
                            <a href="supplier_management.php" class="flex items-center justify-between p-4 bg-green-50 hover:bg-green-100 rounded-lg">
                                <p class="font-semibold text-green-800">Tambah Supplier</p><i class="fas fa-plus-circle text-green-500"></i>
                            </a>
                        </div>
                    </div>
                    <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-md">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Aktivitas Sistem Terbaru</h3>
                        <table class="min-w-full">
                            <tbody>
                                <?php if (empty($data['latestActivities'])): ?>
                                    <tr>
                                        <td class="py-4 text-center text-gray-500">Belum ada aktivitas.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($data['latestActivities'] as $activity): ?>
                                        <tr class="border-b last:border-b-0">
                                            <td class="py-3 px-2 text-center w-10"><i class="<?php echo $activity['icon']; ?>"></i></td>
                                            <td class="py-3 px-2 text-gray-600"><?php echo $activity['description']; ?></td>
                                           
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($userLevel === 'pegawai'): ?>
                 <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Pintasan Cepat</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <a href="sales_management.php" class="flex flex-col items-center p-4 rounded-lg border border-gray-200 hover:bg-blue-50 transition-colors">
                            <i class="fas fa-cash-register text-2xl text-blue-500 mb-2"></i>
                            <span class="text-sm font-medium text-gray-700">Input Penjualan</span>
                        </a>
                        <a href="purchase_management.php" class="flex flex-col items-center p-4 rounded-lg border border-gray-200 hover:bg-green-50 transition-colors">
                            <i class="fas fa-shopping-cart text-2xl text-green-500 mb-2"></i>
                            <span class="text-sm font-medium text-gray-700">Input Pembelian</span>
                        </a>
                        <a href="cost_management.php" class="flex flex-col items-center p-4 rounded-lg border border-gray-200 hover:bg-yellow-50 transition-colors">
                            <i class="fas fa-file-invoice-dollar text-2xl text-yellow-500 mb-2"></i>
                            <span class="text-sm font-medium text-gray-700">Input Biaya</span>
                        </a>
                    </div>
                </div>

                <!-- Stock Alerts -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <div class="flex items-center mb-4">
                        <div class="p-2 bg-red-100 rounded-lg mr-3">
                            <i class="fas fa-exclamation-triangle text-red-500"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800">Peringatan Stok</h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                            <div class="flex-shrink-0 p-3 bg-red-100 rounded-full">
                                <i class="fas fa-box-open text-red-500"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500">Bahan Baku Menipis</p>
                                <p class="text-lg font-bold text-gray-800"><?php echo $data['lowStockMaterials']; ?> Item</p>
                                <a href="material_list.php" class="text-sm text-blue-500 hover:underline">Lihat Detail →</a>
                            </div>
                        </div>
                        <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                            <div class="flex-shrink-0 p-3 bg-red-100 rounded-full">
                                <i class="fas fa-boxes text-red-500"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500">Produk Menipis</p>
                                <p class="text-lg font-bold text-gray-800"><?php echo $data['lowStockProducts']; ?> Item</p>
                                <a href="product_list.php?type=barang" class="text-sm text-blue-500 hover:underline">Lihat Detail →</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-md overflow-hidden">
                    <h3 class="text-lg sm:text-xl font-bold text-gray-800 mb-4">5 Transaksi Terakhir Anda</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-auto">
                            <tbody class="divide-y divide-gray-200">
                                <?php
                               if (empty($data['recentTransactions'])): ?>
                                    <tr>
                                        <td class="py-4 text-center text-gray-500" colspan="4">Belum ada transaksi.</td>
                                    </tr>
                                <?php else: ?>
                                     <?php foreach ($data['recentTransactions'] as $trans): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="py-3 px-2 sm:px-3 text-center w-8 sm:w-10">
                                            <i class="fas <?php echo $trans['tipe'] === 'penjualan' ? 'fa-arrow-up text-green-500' : 'fa-arrow-down text-red-500'; ?>"></i>
                                        </td>
                                        <td class="py-3 px-2 sm:px-3 whitespace-nowrap">
                                            <p class="font-semibold text-gray-800 text-sm sm:text-base">#<?php echo htmlspecialchars($trans['id']); ?></p>
                                        </td>
                                        <td class="py-3 px-2 sm:px-3 text-right whitespace-nowrap">
                                            <span class="font-semibold text-gray-700 text-sm sm:text-base"><?php echo formatCurrency($trans['total']); ?></span>
                                        </td>
                                        <td class="py-3 px-2 sm:px-3 text-right whitespace-nowrap">
                                            <span class="text-xs sm:text-sm text-gray-500"><?php echo date('d M Y', strtotime($trans['tanggal'])); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($userLevel === 'pemilik'): ?>
               <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-lg shadow">
                        <p class="text-sm font-medium text-gray-600">Total Penjualan</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo formatCurrency($data['totalPenjualan']); ?></p>
                    </div>
                                        <div class="bg-white p-6 rounded-lg shadow">
                        <p class="text-sm font-medium text-gray-600">Total Pengeluaran</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo formatCurrency($data['totalPengeluaran']); ?></p>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow">
                        <p class="text-sm font-medium text-gray-600">Saldo Kas</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo formatCurrency($data['saldoKas']); ?></p>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow">
                        <p class="text-sm font-medium text-gray-600">Total Barang</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $data['totalBarang']; ?></p>
                    </div>
                </div>
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
            <?php endif; ?>

        </div>
    </main>
</div>
<?php if ($userLevel === 'pemilik'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
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

const comparisonCtx = document.getElementById('comparisonChart').getContext('2d');
new Chart(comparisonCtx, {
    type: 'doughnut',
    data: {
        labels: ['Penjualan', 'Pembelian'],
        datasets: [{
            data: [<?php echo $total_penjualan; ?>, <?php echo $total_pembelian; ?>],
            backgroundColor: ['rgba(34, 197, 94, 0.8)','rgba(239, 68, 68, 0.8)'],
            borderColor: ['rgba(34, 197, 94, 1)','rgba(239, 68, 68, 1)'],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { padding: 20, usePointStyle: true } },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(context.parsed);
                        return label + ': ' + value;
                    }
                }
            }
        },
        animation: { animateRotate: true, duration: 2000 }
    }
});

<?php
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
new Chart(trendCtx, {
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
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const value = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(context.parsed.y);
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
                        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value);
                    }
                },
                grid: { color: 'rgba(0, 0, 0, 0.1)' }
            },
            x: { grid: { display: false } }
        },
        animation: { duration: 2000, easing: 'easeInOutQuart' }
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const tableRows = document.querySelectorAll('tbody tr');
    tableRows.forEach(row => { row.classList.add('table-row'); });
});

const reportForm = document.querySelector('form');
if (reportForm) {
    reportForm.addEventListener('submit', function() {
        const submitButton = this.querySelector('button[type="submit"]');
        const originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<div class="loading"></div> Loading...';
        submitButton.disabled = true;
        setTimeout(() => {
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }, 3000);
    });
}
    </script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>