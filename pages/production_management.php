<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';
require_once '../includes/header.php';

if ($_SESSION['user']['level'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';
$search_query = $_GET['search'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo->beginTransaction();
        if ($_POST['action'] === 'add') {
            if (empty($_POST['kd_barang']) || empty($_POST['jumlah_produksi']) || empty($_POST['items'])) {
                throw new Exception('Semua field harus diisi dan minimal satu bahan harus dipilih');
            }
            $id_produksi = $_POST['id_produksi'] ?: generateId('PRD');
            $stmt = $pdo->prepare("INSERT INTO produksi (id_produksi, kd_barang, tgl_produksi, jumlah_produksi, status) VALUES (?, ?, ?, ?, 'Proses')");
            $stmt->execute([$id_produksi, $_POST['kd_barang'], date('Y-m-d'), $_POST['jumlah_produksi']]);
            foreach ($_POST['items'] as $item) {
                if (empty($item['kd_bahan']) || empty($item['jum_bahan'])) continue;
                $stmt_stock = $pdo->prepare("SELECT stok FROM bahan WHERE kd_bahan = ?");
                $stmt_stock->execute([$item['kd_bahan']]);
                $currentStock = $stmt_stock->fetchColumn();
                if ($currentStock < $item['jum_bahan']) {
                    throw new Exception("Stok bahan {$item['kd_bahan']} tidak mencukupi");
                }
                $id_detproduksi = generateId('DPR');
                $stmt_detail = $pdo->prepare("INSERT INTO detail_produksi (id_detproduksi, id_produksi, kd_bahan, satuan, jum_bahan) VALUES (?, ?, ?, ?, ?)");
                $stmt_detail->execute([$id_detproduksi, $id_produksi, $item['kd_bahan'], $item['satuan'], $item['jum_bahan']]);
                $stmt_update_bahan = $pdo->prepare("UPDATE bahan SET stok = stok - ? WHERE kd_bahan = ?");
                $stmt_update_bahan->execute([$item['jum_bahan'], $item['kd_bahan']]);
            }
            $pdo->commit();
            $message = 'Produksi berhasil ditambahkan dan sedang dalam proses.';
        } elseif ($_POST['action'] === 'finish') {
            $id_produksi = $_POST['id_produksi'];
            $stmt_prod = $pdo->prepare("SELECT kd_barang, jumlah_produksi, status FROM produksi WHERE id_produksi = ?");
            $stmt_prod->execute([$id_produksi]);
            $production = $stmt_prod->fetch(PDO::FETCH_ASSOC);
            if ($production && $production['status'] === 'Proses') {
                $stmt_update_status = $pdo->prepare("UPDATE produksi SET status = 'Selesai' WHERE id_produksi = ?");
                $stmt_update_status->execute([$id_produksi]);
                $stmt_update_barang = $pdo->prepare("UPDATE barang SET stok = stok + ? WHERE kd_barang = ?");
                $stmt_update_barang->execute([$production['jumlah_produksi'], $production['kd_barang']]);
                $pdo->commit();
                $message = 'Produksi telah selesai dan stok barang telah diperbarui.';
            } else {
                throw new Exception('Produksi ini tidak dapat diselesaikan atau sudah selesai.');
            }
        } elseif ($_POST['action'] === 'delete') {
            $id_produksi = $_POST['id_produksi'];
            $stmt = $pdo->prepare("SELECT kd_barang, jumlah_produksi, status FROM produksi WHERE id_produksi = ?");
            $stmt->execute([$id_produksi]);
            $production = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($production) {
                $stmt = $pdo->prepare("SELECT kd_bahan, jum_bahan FROM detail_produksi WHERE id_produksi = ?");
                $stmt->execute([$id_produksi]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($items as $item) {
                    $stmt = $pdo->prepare("UPDATE bahan SET stok = stok + ? WHERE kd_bahan = ?");
                    $stmt->execute([$item['jum_bahan'], $item['kd_bahan']]);
                }
                if ($production['status'] === 'Selesai') {
                    $stmt = $pdo->prepare("UPDATE barang SET stok = stok - ? WHERE kd_barang = ?");
                    $stmt->execute([$production['jumlah_produksi'], $production['kd_barang']]);
                }
                $stmt = $pdo->prepare("DELETE FROM detail_produksi WHERE id_produksi = ?");
                $stmt->execute([$id_produksi]);
                $stmt = $pdo->prepare("DELETE FROM produksi WHERE id_produksi = ?");
                $stmt->execute([$id_produksi]);
                $pdo->commit();
                $message = 'Produksi berhasil dihapus dan stok telah dikembalikan.';
            }
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Pagination logic
$perPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM produksi p JOIN barang b ON p.kd_barang = b.kd_barang WHERE p.id_produksi LIKE :search OR b.nama_barang LIKE :search");
$stmt->bindValue(':search', '%' . $search_query . '%');
$stmt->execute();
$totalItems = $stmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

$sql = "SELECT p.*, b.nama_barang AS product_name FROM produksi p JOIN barang b ON p.kd_barang = b.kd_barang WHERE p.id_produksi LIKE :search OR b.nama_barang LIKE :search ORDER BY p.tgl_produksi DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':search', '%' . $search_query . '%');
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$productions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT kd_barang, nama_barang FROM barang");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->query("SELECT kd_bahan, nama_bahan, satuan FROM bahan");
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<head>
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {}
            }
        }
    </script>
</head>

<div class="flex min-h-screen bg-gray-100">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <?php if ($message): ?>
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg notification">
                <div class="flex items-center"><i class="fas fa-check-circle mr-2"></i><span><?php echo htmlspecialchars($message); ?></span></div>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg notification">
                <div class="flex items-center"><i class="fas fa-exclamation-circle mr-2"></i><span><?php echo htmlspecialchars($error); ?></span></div>
            </div>
        <?php endif; ?>

        <div id="productionManagement" class="section active">
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-800">Manajemen Produksi</h2>
                <p class="text-gray-600 mt-2">Kelola data produksi barang jadi</p>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                    <div class="flex justify-between items-center card-header">
                        <div>
                            <h3 class="text-xl font-semibold text-white">Daftar Produksi</h3>
                            <p class="text-indigo-100 mt-1">Total: <?php echo $totalItems; ?> riwayat</p>
                        </div>
                        <button onclick="showAddProductionForm()" class="add-button bg-white text-indigo-600 hover:bg-indigo-50 px-6 py-2 rounded-lg font-medium transition duration-200 flex items-center">
                            <i class="fas fa-plus mr-2"></i>Tambah Produksi
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    <div class="mb-6 relative">
                        <input type="text" id="searchInput" name="search" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 pr-10" placeholder="Cari ID produksi atau nama barang..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                            <i class="fas fa-search"></i>
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full responsive-table border border-gray-200" id="productionsTable">
                            <thead>
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">No</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">ID Produksi</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Tanggal</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Nama Barang</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Jumlah</th>
                                    <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700">Status</th>
                                    <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="productionsTableBody" class="bg-white divide-y divide-gray-200">
                                <?php 
                                $i = 1;
                                foreach ($productions as $production): ?>
                                    <tr class="production-row" data-name="<?php echo strtolower(htmlspecialchars($production['id_produksi'] . ' ' . $production['product_name'])); ?>">
                                        <td data-label="No." class="px-6 py-4 text-sm text-gray-900"><?php echo $i++; ?></td>
                                        <td data-label="ID" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($production['id_produksi']); ?></td>
                                        <td data-label="Tanggal" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars(date('d M Y', strtotime($production['tgl_produksi']))); ?></td>
                                        <td data-label="Barang" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($production['product_name']); ?></td>
                                        <td data-label="Jumlah" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($production['jumlah_produksi']); ?></td>
                                        <td data-label="Status" class="px-6 py-4 text-center text-sm font-semibold">
                                            <?php if ($production['status'] == 'Selesai'): ?>
                                                <span class="px-3 py-1 bg-green-200 text-green-800 rounded-full text-xs">Selesai</span>
                                            <?php else: ?>
                                                <span class="px-3 py-1 bg-yellow-200 text-yellow-800 rounded-full text-xs">Proses</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-center actions-cell">
                                            <div class="flex justify-center items-center space-x-2">
                                                <?php if ($production['status'] == 'Proses'): ?>
                                                    <button onclick="finishProduction('<?php echo $production['id_produksi']; ?>')" class="text-green-600 hover:text-green-800 hover:bg-green-100 p-2 rounded-lg transition duration-200" title="Selesaikan Produksi">
                                                        <i class="fas fa-check-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button onclick="deleteProduction('<?php echo $production['id_produksi']; ?>')" class="text-red-600 hover:text-red-800 hover:bg-red-100 p-2 rounded-lg transition duration-200" title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div class="flex justify-end mt-2 p-2">
                        <div class="flex items-center space-x-2 text-sm">
                            <a href="?search=<?php echo urlencode($search_query); ?>&page=<?php echo max(1, $page - 1); ?>" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300 <?php echo $page <= 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>">Prev</a>
                            <span class="px-2"><?php echo $page; ?> / <?php echo $totalPages; ?></span>
                            <a href="?search=<?php echo urlencode($search_query); ?>&page=<?php echo min($totalPages, $page + 1); ?>" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300 <?php echo $page >= $totalPages ? 'opacity-50 cursor-not-allowed' : ''; ?>">Next</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-auto transform transition-all">
                <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 p-6 rounded-t-xl">
                    <div class="flex justify-between items-center">
                        <h3 id="modalTitle" class="text-xl font-semibold text-white"></h3>
                        <button onclick="closeModal()" class="text-white hover:text-gray-200 transition duration-200">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <div id="modalContent" class="p-8"></div>
            </div>
        </div>
    </main>
</div>

<script>
function showModal(title, content) {
    document.getElementById('modalTitle').innerHTML = title;
    document.getElementById('modalContent').innerHTML = content;
    document.getElementById('modal').classList.remove('hidden');
    document.getElementById('modal').classList.add('flex');
}

function closeModal() {
    document.getElementById('modal').classList.add('hidden');
    document.getElementById('modal').classList.remove('flex');
}

function showAddProductionForm() {
    let content = `
        <form method="POST" id="productionForm" onsubmit="return validateForm(this)">
            <input type="hidden" name="action" value="add">
            <div class="space-y-4 max-h-[calc(90vh-12rem)] overflow-y-auto">
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">ID Produksi</label>
                    <input type="text" name="id_produksi" value="<?php echo generateId('PRD'); ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" readonly>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Nama Barang <span class="text-red-500">*</span></label>
                    <select name="kd_barang" class="w-full px-4 py-3 border border-gray-300 rounded-lg" required>
                        <option value="">Pilih Barang</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo htmlspecialchars($product['kd_barang']); ?>">
                                <?php echo htmlspecialchars($product['nama_barang']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Jumlah Produksi <span class="text-red-500">*</span></label>
                    <input type="number" name="jumlah_produksi" min="1" required class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                </div>
                <div id="itemsContainer">
                    <div class="item-row space-y-2 mb-4">
                        <label class="block text-gray-700 text-sm font-semibold">Bahan Baku</label>
                        <select name="items[0][kd_bahan]" class="w-full px-4 py-3 border border-gray-300 rounded-lg mb-2" required>
                            <option value="">Pilih Bahan</option>
                            <?php foreach ($materials as $material): ?>
                                <option value="<?php echo htmlspecialchars($material['kd_bahan']); ?>" data-satuan="<?php echo htmlspecialchars($material['satuan']); ?>">
                                    <?php echo htmlspecialchars($material['nama_bahan']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="items[0][jum_bahan]" placeholder="Jumlah" class="w-full px-4 py-3 border border-gray-300 rounded-lg mb-2" min="1" required>
                        <input type="text" name="items[0][satuan]" class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" readonly>
                    </div>
                </div>
                <button type="button" onclick="addItem()" class="w-full bg-indigo-500 text-white px-4 py-2 rounded-lg hover:bg-indigo-600 transition duration-200">Tambah Bahan</button>
            </div>
            <div class="flex justify-end space-x-3 mt-8 border-t pt-6">
                <button type="button" onclick="closeModal()" class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200">Batal</button>
                <button type="submit" class="px-6 py-3 bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition duration-200"><i class="fas fa-save mr-2"></i>Simpan</button>
            </div>
        </form>
    `;
    showModal('Tambah Produksi Baru', content);
}

let itemCount = 1;
function addItem() {
    const container = document.getElementById('itemsContainer');
    const newItem = document.createElement('div');
    newItem.className = 'item-row space-y-2 mb-4';
    newItem.innerHTML = `
        <label class="block text-gray-700 text-sm font-semibold">Bahan Baku</label>
        <select name="items[${itemCount}][kd_bahan]" class="w-full px-4 py-3 border border-gray-300 rounded-lg mb-2" required>
            <option value="">Pilih Bahan</option>
            <?php foreach ($materials as $material): ?>
                <option value="<?php echo htmlspecialchars($material['kd_bahan']); ?>" data-satuan="<?php echo htmlspecialchars($material['satuan']); ?>">
                    <?php echo htmlspecialchars($material['nama_bahan']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="items[${itemCount}][jum_bahan]" placeholder="Jumlah" class="w-full px-4 py-3 border border-gray-300 rounded-lg mb-2" min="1" required>
        <input type="text" name="items[${itemCount}][satuan]" class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" readonly>
        <button type="button" onclick="this.parentElement.remove()" class="w-full bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition duration-200">Hapus Bahan</button>
    `;
    container.appendChild(newItem);
    itemCount++;
    updateSatuan();
}

function updateSatuan() {
    document.querySelectorAll('select[name$="[kd_bahan]"]').forEach(select => {
        const satuanInput = select.parentElement.querySelector('input[name$="[satuan]"]');
        const selectedOption = select.options[select.selectedIndex];
        if (selectedOption && selectedOption.dataset.satuan) {
            satuanInput.value = selectedOption.dataset.satuan;
        }
    });
}

document.querySelectorAll('select[name$="[kd_bahan]"]').forEach(select => {
    select.addEventListener('change', updateSatuan);
});

function finishProduction(id) {
    if (confirm('Apakah Anda yakin ingin menyelesaikan produksi ini?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="finish"><input type="hidden" name="id_produksi" value="${id}">`;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteProduction(id) {
    if (confirm('Apakah Anda yakin ingin menghapus produksi ini?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="id_produksi" value="${id}">`;
        document.body.appendChild(form);
        form.submit();
    }
}

function validateForm(form) {
    const kdBarang = form.querySelector('select[name="kd_barang"]').value;
    const jumlahProduksi = parseInt(form.querySelector('input[name="jumlah_produksi"]').value) || 0;
    const items = form.querySelectorAll('.item-row');
    let hasValidItem = false;

    if (!kdBarang) {
        alert('Pilih nama barang terlebih dahulu!');
        return false;
    }
    if (jumlahProduksi <= 0) {
        alert('Jumlah produksi harus lebih dari 0!');
        return false;
    }

    items.forEach(item => {
        const kdBahan = item.querySelector('select[name$="[kd_bahan]"]').value;
        const jumBahan = parseInt(item.querySelector('input[name$="[jum_bahan]"]').value) || 0;

        if (kdBahan && jumBahan > 0) {
            hasValidItem = true;
        }
    });

    if (!hasValidItem) {
        alert('Harap isi setidaknya satu bahan dengan jumlah yang valid!');
        return false;
    }
    return true;
}

document.getElementById('searchInput').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const tableRows = document.querySelectorAll('#productionsTableBody .production-row');
    tableRows.forEach(row => {
        const name = row.dataset.name.toLowerCase();
        row.style.display = name.includes(searchTerm) ? '' : 'none';
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const notifications = document.querySelectorAll('.notification');
    notifications.forEach(notification => {
        setTimeout(() => {
            notification.style.transition = 'opacity 0.5s ease';
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 500);
        }, 3000);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>