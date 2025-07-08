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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo->beginTransaction();

        if ($_POST['action'] === 'add') {
            if (empty($_POST['kd_barang']) || empty($_POST['jumlah_produksi']) || empty($_POST['items'])) {
                throw new Exception('Semua field harus diisi dan minimal satu bahan harus dipilih');
            }

            $id_produksi = $_POST['id_produksi'] ?: generateId('PRD');
            // Status diatur ke 'Proses' saat pertama kali dibuat
            $stmt = $pdo->prepare("INSERT INTO produksi (id_produksi, kd_barang, tgl_produksi, jumlah_produksi, status) VALUES (?, ?, ?, ?, 'Proses')");
            $stmt->execute([$id_produksi, $_POST['kd_barang'], date('Y-m-d'), $_POST['jumlah_produksi']]);

            foreach ($_POST['items'] as $item) {
                if (empty($item['kd_bahan']) || empty($item['jum_bahan'])) {
                    continue;
                }

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
            // Stok barang jadi TIDAK ditambahkan di sini, tapi saat konfirmasi selesai

            $pdo->commit();
            $message = 'Produksi berhasil ditambahkan dan sedang dalam proses.';
        } elseif ($_POST['action'] === 'finish') {
            $id_produksi = $_POST['id_produksi'];
            
            // Ambil detail produksi untuk menambah stok barang
            $stmt_prod = $pdo->prepare("SELECT kd_barang, jumlah_produksi, status FROM produksi WHERE id_produksi = ?");
            $stmt_prod->execute([$id_produksi]);
            $production = $stmt_prod->fetch(PDO::FETCH_ASSOC);

            if ($production && $production['status'] === 'Proses') {
                // Update status produksi menjadi 'Selesai'
                $stmt_update_status = $pdo->prepare("UPDATE produksi SET status = 'Selesai' WHERE id_produksi = ?");
                $stmt_update_status->execute([$id_produksi]);

                // Tambah stok barang jadi
                $stmt_update_barang = $pdo->prepare("UPDATE barang SET stok = stok + ? WHERE kd_barang = ?");
                $stmt_update_barang->execute([$production['jumlah_produksi'], $production['kd_barang']]);
                
                $pdo->commit();
                $message = 'Produksi telah selesai dan stok barang telah diperbarui.';
            } else {
                throw new Exception('Produksi ini tidak dapat diselesaikan atau sudah selesai.');
            }
        } elseif ($_POST['action'] === 'delete') {
            $id_produksi = $_POST['id_produksi'];
            
            // Ambil data produksi yang akan dihapus
            $stmt = $pdo->prepare("SELECT kd_barang, jumlah_produksi, status FROM produksi WHERE id_produksi = ?");
            $stmt->execute([$id_produksi]);
            $production = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($production) {
                // Kembalikan stok bahan baku
                $stmt = $pdo->prepare("SELECT kd_bahan, jum_bahan FROM detail_produksi WHERE id_produksi = ?");
                $stmt->execute([$id_produksi]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($items as $item) {
                    $stmt = $pdo->prepare("UPDATE bahan SET stok = stok + ? WHERE kd_bahan = ?");
                    $stmt->execute([$item['jum_bahan'], $item['kd_bahan']]);
                }

                // Jika produksi sudah 'Selesai', kurangi stok barang jadi yang sudah ditambahkan
                if ($production['status'] === 'Selesai') {
                    $stmt = $pdo->prepare("UPDATE barang SET stok = stok - ? WHERE kd_barang = ?");
                    $stmt->execute([$production['jumlah_produksi'], $production['kd_barang']]);
                }

                // Hapus detail dan data produksi utama
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


$stmt = $pdo->query("SELECT p.*, b.nama_barang AS product_name FROM produksi p JOIN barang b ON p.kd_barang = b.kd_barang ORDER BY p.tgl_produksi DESC");
$productions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->query("SELECT kd_barang, nama_barang FROM barang");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->query("SELECT kd_bahan, nama_bahan, satuan FROM bahan");
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<head>
    <link rel="stylesheet" href="../assets/css/responsive.css">
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
                            <p class="text-blue-100 mt-1">Total: <?php echo count($productions); ?> riwayat</p>
                        </div>
                        <button onclick="showAddProductionForm()" class="add-button bg-white text-blue-600 hover:bg-blue-50 px-6 py-2 rounded-lg font-medium transition duration-200 flex items-center">
                            <i class="fas fa-plus mr-2"></i>Tambah Produksi
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full responsive-table">
                            <thead>
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">ID Produksi</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Tanggal</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Nama Barang</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Jumlah</th>
                                    <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700">Status</th>
                                    <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($productions as $production): ?>
                                    <tr>
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
                </div>
            </div>
        </div>

        <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-auto transform transition-all max-h-[90vh] flex flex-col">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6 rounded-t-xl flex-shrink-0">
                    <div class="flex justify-between items-center">
                        <h3 id="modalTitle" class="text-xl font-semibold text-white"></h3>
                        <button onclick="closeModal()" class="text-white hover:text-gray-200 transition duration-200">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <div id="modalContent" class="p-6 overflow-y-auto"></div>
            </div>
        </div>
    </main>
</div>

<script>
    let itemCount = 0;
    const materialsData = <?php echo json_encode($materials); ?>;

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
        itemCount = 0;
        const content = `
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">ID Produksi</label>
                    <input type="text" name="id_produksi" value="<?php echo generateId('PRD'); ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" readonly>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Produk Jadi</label>
                    <select name="kd_barang" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Pilih Produk</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['kd_barang']; ?>"><?php echo htmlspecialchars($product['nama_barang']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Jumlah Produksi</label>
                    <input type="number" name="jumlah_produksi" required min="1" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Masukkan jumlah">
                </div>
                <div class="border-t pt-4">
                    <h4 class="text-lg font-semibold mb-2 text-gray-800">Bahan yang Digunakan</h4>
                    <div id="item-list" class="space-y-4"></div>
                    <button type="button" onclick="addItem()" class="mt-4 text-blue-600 hover:text-blue-800 font-medium flex items-center">
                        <i class="fas fa-plus-circle mr-2"></i>Tambah Bahan
                    </button>
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-8 border-t pt-6">
                <button type="button" onclick="closeModal()" class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200">Batal</button>
                <button type="submit" class="px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200"><i class="fas fa-save mr-2"></i>Simpan Produksi</button>
            </div>
        </form>
    `;
        showModal('Tambah Data Produksi', content);
        addItem();
    }

    function addItem() {
        const itemHtml = `
        <div id="item-${itemCount}" class="mb-2 p-2 border rounded">
            <div class="mb-2">
                <label class="block text-gray-700 text-sm font-bold mb-1">Bahan</label>
                <select name="items[${itemCount}][kd_bahan]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" onchange="updateItemSatuan(${itemCount})">
                    <option value="">Pilih Bahan</option>
                    ${materialsData.map(material => `<option value="${material.kd_bahan}" data-satuan="${material.satuan}">${material.nama_bahan}</option>`).join('')}
                </select>
            </div>
            <div class="mb-2">
                <label class="block text-gray-700 text-sm font-bold mb-1">Satuan</label>
                <input type="text" name="items[${itemCount}][satuan]" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-2">
                <label class="block text-gray-700 text-sm font-bold mb-1">Jumlah Bahan</label>
                <input type="number" name="items[${itemCount}][jum_bahan]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <button type="button" onclick="removeItem(${itemCount})" class="text-red-600 hover:text-red-900 text-sm">Hapus Bahan</button>
        </div>
    `;
        document.getElementById('item-list').insertAdjacentHTML('beforeend', itemHtml);
        itemCount++;
    }

    function updateItemSatuan(index) {
        const select = document.querySelector(`select[name="items[${index}][kd_bahan]"]`);
        const selectedOption = select.options[select.selectedIndex];
        document.querySelector(`input[name="items[${index}][satuan]"]`).value = selectedOption.dataset.satuan || '';
    }

    function removeItem(index) {
        document.getElementById(`item-${index}`).remove();
    }

    function finishProduction(id_produksi) {
        if (confirm('Apakah Anda yakin ingin menyelesaikan proses produksi ini? Stok barang jadi akan diperbarui.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
            <input type="hidden" name="action" value="finish">
            <input type="hidden" name="id_produksi" value="${id_produksi}">
        `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function deleteProduction(id_produksi) {
        if (confirm('Apakah Anda yakin ingin menghapus produksi ini? Stok bahan akan dikembalikan.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id_produksi" value="${id_produksi}">
        `;
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
<?php require_once '../includes/footer.php'; ?>