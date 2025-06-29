<?php
require_once '../config/db_connect.php';
require_once '../includes/function.php';
require_once '../includes/header.php';

if (!in_array($_SESSION['user']['level'], ['admin', 'pegawai'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo->beginTransaction();
        
        if ($_POST['action'] === 'add') {
            if (empty($_POST['tgl_beli']) || empty($_POST['id_supplier']) || empty($_POST['total_beli']) || empty($_POST['bayar']) || empty($_POST['items'])) {
                throw new Exception('Semua field harus diisi dan minimal satu item harus dipilih');
            }
            
            if ($_POST['bayar'] < $_POST['total_beli']) {
                throw new Exception('Jumlah bayar tidak boleh kurang dari total');
            }
            
            $id_pembelian = $_POST['id_pembelian'] ?: generateId('BL');
            $stmt = $pdo->prepare("INSERT INTO pembelian (id_pembelian, tgl_beli, id_supplier, total_beli, bayar, kembali) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_pembelian, $_POST['tgl_beli'], $_POST['id_supplier'], $_POST['total_beli'], $_POST['bayar'], $_POST['kembali']]);

            foreach ($_POST['items'] as $item) {
                if (empty($item['kd_bahan']) || empty($item['qty'])) {
                    continue;
                }
                
                $id_detail_beli = generateId('DBL');
                $stmt = $pdo->prepare("INSERT INTO detail_pembelian (id_detail_beli, id_pembelian, kd_bahan, harga_beli, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$id_detail_beli, $id_pembelian, $item['kd_bahan'], $item['harga_beli'], $item['qty'], $item['subtotal']]);
                $stmt = $pdo->prepare("UPDATE bahan SET stok = stok + ? WHERE kd_bahan = ?");
                $stmt->execute([$item['qty'], $item['kd_bahan']]);
            }
            $stmt = $pdo->prepare("INSERT INTO pengeluaran_kas (id_pengeluaran_kas, id_pembelian, tgl_pengeluaran_kas, uraian, total) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([generateId('PKS'), $id_pembelian, $_POST['tgl_beli'], 'Pembelian Bahan', $_POST['total_beli']]);
            
            $pdo->commit();
            $message = 'Pembelian berhasil disimpan';
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("SELECT kd_bahan, qty FROM detail_pembelian WHERE id_pembelian = ?");
            $stmt->execute([$_POST['id_pembelian']]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as $item) {
                $stmt = $pdo->prepare("UPDATE bahan SET stok = stok - ? WHERE kd_bahan = ?");
                $stmt->execute([$item['qty'], $item['kd_bahan']]);
            }
            $stmt = $pdo->prepare("DELETE FROM pengeluaran_kas WHERE id_pembelian = ?");
            $stmt->execute([$_POST['id_pembelian']]);
            $stmt = $pdo->prepare("DELETE FROM detail_pembelian WHERE id_pembelian = ?");
            $stmt->execute([$_POST['id_pembelian']]);
            $stmt = $pdo->prepare("DELETE FROM pembelian WHERE id_pembelian = ?");
            $stmt->execute([$_POST['id_pembelian']]);
            
            $pdo->commit();
            $message = 'Pembelian berhasil dihapus';
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$stmt = $pdo->query("SELECT p.*, s.nama_supplier FROM pembelian p JOIN supplier s ON p.id_supplier = s.id_supplier");
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->query("SELECT id_supplier, nama_supplier FROM supplier");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->query("SELECT kd_bahan, nama_bahan FROM bahan");
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="flex min-h-screen">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="flex-1 p-6">
        <!-- Notifications -->
        <?php if ($message): ?>
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg notification">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg notification">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <div id="purchaseManagement" class="section active">
            <h2 class="text-2xl font-bold mb-6">Manajemen Pembelian</h2>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Daftar Pembelian</h3>
                    <button onclick="showAddPurchaseForm()" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-plus mr-2"></i>Tambah Pembelian
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID Pembelian</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="purchaseTableBody" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($purchases as $purchase): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($purchase['id_pembelian']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($purchase['tgl_beli']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($purchase['nama_supplier']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo formatCurrency($purchase['total_beli']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="deletePurchase('<?php echo $purchase['id_pembelian']; ?>')" class="text-red-600 hover:text-red-900">Hapus</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div id="modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center">
            <div class="bg-white p-6 rounded-lg shadow-xl max-w-lg w-full mx-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 id="modalTitle" class="text-lg font-semibold"></h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="modalContent"></div>
            </div>
        </div>
    </main>
</div>
<script>
let itemCount = 0;

function showAddPurchaseForm() {
    itemCount = 0;
    const content = `
        <form method="POST" onsubmit="prepareItems()">
            <input type="hidden" name="action" value="add">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">ID Pembelian</label>
                <input type="text" name="id_pembelian" value="<?php echo generateId('BL'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal</label>
                <input type="date" name="tgl_beli" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Supplier</label>
                <select name="id_supplier" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                    <option value="">Pilih Supplier</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?php echo $supplier['id_supplier']; ?>"><?php echo htmlspecialchars($supplier['nama_supplier']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="items" class="mb-4">
                <h4 class="text-sm font-bold mb-2">Item Pembelian</h4>
                <div id="item-list"></div>
                <button type="button" onclick="addItem()" class="text-blue-600 hover:text-blue-900 text-sm">+ Tambah Item</button>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Total</label>
                <input type="number" name="total_beli" id="total_beli" required readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Bayar</label>
                <input type="number" name="bayar" id="bayar" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" oninput="updateKembali()">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Kembali</label>
                <input type="text" name="kembali" id="kembali" required readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-700">Batal</button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-700">Simpan</button>
            </div>
        </form>
    `;
    showModal('Tambah Pembelian', content);
}

function addItem() {
    const itemHtml = `
        <div id="item-${itemCount}" class="mb-2 p-2 border rounded">
            <div class="mb-2">
                <label class="block text-gray-700 text-sm font-bold mb-1">Bahan</label>
                <select name="items[${itemCount}][kd_bahan]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" onchange="updateItemSubtotal(${itemCount})">
                    <option value="">Pilih Bahan</option>
                    <?php foreach ($materials as $material): ?>
                        <option value="<?php echo $material['kd_bahan']; ?>"><?php echo htmlspecialchars($material['nama_bahan']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-2">
                <label class="block text-gray-700 text-sm font-bold mb-1">Harga Beli</label>
                <input type="number" name="items[${itemCount}][harga_beli]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" oninput="updateItemSubtotal(${itemCount})">
            </div>
            <div class="mb-2">
                <label class="block text-gray-700 text-sm font-bold mb-1">Kuantitas</label>
                <input type="number" name="items[${itemCount}][qty]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" oninput="updateItemSubtotal(${itemCount})">
            </div>
            <div class="mb-2">
                <label class="block text-gray-700 text-sm font-bold mb-1">Subtotal</label>
                <input type="text" name="items[${itemCount}][subtotal]" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <button type="button" onclick="removeItem(${itemCount})" class="text-red-600 hover:text-red-900 text-sm">Hapus Item</button>
        </div>
    `;
    document.getElementById('item-list').insertAdjacentHTML('beforeend', itemHtml);
    itemCount++;
}

function removeItem(index) {
    document.getElementById(`item-${index}`).remove();
    updateTotal();
}

function updateItemSubtotal(index) {
    const harga = parseFloat(document.querySelector(`input[name="items[${index}][harga_beli]"]`).value) || 0;
    const qty = parseFloat(document.querySelector(`input[name="items[${index}][qty]"]`).value) || 0;
    const subtotal = harga * qty;
    document.querySelector(`input[name="items[${index}][subtotal]"]`).value = subtotal.toString();
    updateTotal();
}

function updateTotal() {
    let total = 0;
    for (let i = 0; i < itemCount; i++) {
        const subtotal = parseFloat(document.querySelector(`input[name="items[${i}][subtotal]"]`)?.value) || 0;
        total += subtotal;
    }
    document.getElementById('total_beli').value = total;
    updateKembali();
}

function updateKembali() {
    const total = parseFloat(document.getElementById('total_beli').value) || 0;
    const bayar = parseFloat(document.getElementById('bayar').value) || 0;
    document.getElementById('kembali').value = (bayar - total).toString();
}

function prepareItems() {
    // Ensure all items are included in the form submission
}

function deletePurchase(id_pembelian) {
    if (confirm('Apakah Anda yakin ingin menghapus pembelian ini?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id_pembelian" value="${id_pembelian}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
<?php require_once '../includes/footer.php'; ?>