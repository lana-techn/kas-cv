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

$sql = "SELECT p.*, b.nama_barang AS product_name 
        FROM produksi p 
        JOIN barang b ON p.kd_barang = b.kd_barang";
if (!empty($search_query)) {
    $sql .= " WHERE p.id_produksi LIKE :search OR b.nama_barang LIKE :search";
}
$sql .= " ORDER BY p.tgl_produksi DESC";

$stmt = $pdo->prepare($sql);
if (!empty($search_query)) {
    $stmt->bindValue(':search', '%' . $search_query . '%');
}
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
                <div class="bg-gradient-to-r from-indigo-500 to-purple-500 p-6">
                    <div class="flex justify-between items-center card-header">
                        <div>
                            <h3 class="text-xl font-semibold text-white">Daftar Produksi</h3>
                            <p class="text-indigo-100 mt-1">Total: <?php echo count($productions); ?> riwayat</p>
                        </div>
                        <button onclick="showAddProductionForm()" class="add-button bg-white text-indigo-600 hover:bg-indigo-50 px-6 py-2 rounded-lg font-medium transition duration-200 flex items-center">
                            <i class="fas fa-plus mr-2"></i>Tambah Produksi
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    <form method="GET" action="" class="mb-4">
                        <div class="flex items-center">
                            <input type="text" name="search" class="w-full px-4 py-2 border rounded-l-lg" placeholder="Cari ID produksi atau nama barang..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-r-lg">Cari</button>
                        </div>
                    </form>
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
        
        <div id="modal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50 px-4 py-6">
            <div class="bg-gray-50 rounded-xl shadow-2xl w-full max-w-2xl mx-auto transform transition-all max-h-[90vh] flex flex-col modal-container">
                <form method="POST" class="flex flex-col flex-grow">
                    <div class="bg-gradient-to-r from-indigo-500 to-purple-500 p-5 rounded-t-xl flex-shrink-0 flex justify-between items-center">
                        <h3 id="modalTitle" class="text-xl font-semibold text-white"></h3>
                        <button type="button" onclick="closeModal()" class="text-white hover:text-gray-200 text-2xl" title="Tutup"><i class="fas fa-times-circle"></i></button>
                    </div>

                    <div id="modalContent" class="p-6 max-h-[calc(70vh-6rem)] overflow-y-auto modal-content">
                    </div>

                    <div class="flex justify-end items-center space-x-4 p-4 bg-gray-100 border-t rounded-b-xl flex-shrink-0 button-container">
                        <button type="button" onclick="closeModal()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-6 rounded-lg h-10 min-w-[120px] transition-transform transform hover:scale-105">Batal</button>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg h-10 min-w-[120px] transition-transform transform hover:scale-105">Simpan</button>
                    </div>
                </form>
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
            <input type="hidden" name="action" value="add">
            <div class="space-y-6">
                <div class="bg-white p-5 rounded-lg shadow">
                    <h4 class="font-semibold text-gray-700 mb-4 border-b pb-2">Detail Produksi</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">ID Produksi</label>
                            <input type="text" name="id_produksi" value="<?php echo generateId('PRD'); ?>" class="w-full px-4 py-2 border rounded-lg bg-gray-100 text-sm" readonly>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Produk Jadi</label>
                            <select name="kd_barang" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                                <option value="">Pilih Produk</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['kd_barang']; ?>"><?php echo htmlspecialchars($product['nama_barang']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Jumlah Produksi</label>
                            <input type="number" name="jumlah_produksi" required min="1" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm" placeholder="Masukkan jumlah yang akan diproduksi">
                        </div>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-lg shadow">
                    <div class="flex justify-between items-center mb-4 border-b pb-2">
                        <h4 class="font-semibold text-gray-700">Bahan yang Digunakan</h4>
                        <button type="button" onclick="addItem()" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-md text-sm flex items-center min-w-[120px]">
                            <i class="fas fa-plus mr-2"></i>Tambah Bahan
                        </button>
                    </div>
                    <div id="item-list" class="space-y-4"></div>
                </div>
            </div>
    `;
        showModal('Tambah Data Produksi', content);
        addItem();
    }

    function addItem() {
        const itemHtml = `
        <div id="item-${itemCount}" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-center p-3 border rounded-lg bg-gray-50">
            <div class="md:col-span-2">
                <label class="block text-gray-700 text-sm font-bold mb-1">Bahan</label>
                <select name="items[${itemCount}][kd_bahan]" required class="w-full p-2 border border-gray-300 rounded-md text-sm" onchange="updateItemSatuan(${itemCount})">
                    <option value="">Pilih Bahan</option>
                    ${materialsData.map(material => `<option value="${material.kd_bahan}" data-satuan="${material.satuan}">${material.nama_bahan}</option>`).join('')}
                </select>
            </div>
            <div class="md:col-span-1">
                <label class="block text-gray-700 text-sm font-bold mb-1">Satuan</label>
                <input type="text" name="items[${itemCount}][satuan]" readonly class="w-full p-2 border bg-gray-200 border-gray-300 rounded-md text-sm">
            </div>
            <div class="md:col-span-1">
                <label class="block text-gray-700 text-sm font-bold mb-1">Jumlah</label>
                <input type="number" name="items[${itemCount}][jum_bahan]" required class="w-full p-2 border border-gray-300 rounded-md text-sm">
            </div>
            <div class="md:col-span-1 flex items-end justify-center">
                <button type="button" onclick="removeItem(${itemCount})" class="text-red-500 hover:text-red-700 p-2 rounded-lg" title="Hapus"><i class="fas fa-trash-alt"></i></button>
            </div>
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