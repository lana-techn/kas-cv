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
        if ($_POST['action'] === 'add') {
            if (empty($_POST['nama_supplier']) || empty($_POST['alamat']) || empty($_POST['no_telpon'])) {
                throw new Exception('Semua field harus diisi');
            }
            $phone = trim($_POST['no_telpon']);
            if (!preg_match('/^(\+?[0-9\s\-()]{8,15})$/', $phone)) {
                throw new Exception('Format nomor telepon tidak valid. Contoh: +6281234567890 atau 081234567890');
            }
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($cleanPhone) < 8 || strlen($cleanPhone) > 15) {
                throw new Exception('Nomor telepon harus memiliki 8-15 digit angka');
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM supplier WHERE no_telpon = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Nomor telepon sudah digunakan oleh supplier lain');
            }
            $id_supplier = $_POST['id_supplier'] ?: generateId('SUP');
            $stmt = $pdo->prepare("INSERT INTO supplier (id_supplier, nama_supplier, alamat, no_telpon) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id_supplier, $_POST['nama_supplier'], $_POST['alamat'], $phone]);
            $message = 'Supplier berhasil ditambahkan';
        } elseif ($_POST['action'] === 'edit') {
            if (empty($_POST['nama_supplier']) || empty($_POST['alamat']) || empty($_POST['no_telpon'])) {
                throw new Exception('Semua field harus diisi');
            }
            $phone = trim($_POST['no_telpon']);
            if (!preg_match('/^(\+?[0-9\s\-()]{8,15})$/', $phone)) {
                throw new Exception('Format nomor telepon tidak valid. Contoh: +6281234567890 atau 081234567890');
            }
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($cleanPhone) < 8 || strlen($cleanPhone) > 15) {
                throw new Exception('Nomor telepon harus memiliki 8-15 digit angka');
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM supplier WHERE no_telpon = ? AND id_supplier != ?");
            $stmt->execute([$phone, $_POST['id_supplier']]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Nomor telepon sudah digunakan oleh supplier lain');
            }
            $stmt = $pdo->prepare("UPDATE supplier SET nama_supplier = ?, alamat = ?, no_telpon = ? WHERE id_supplier = ?");
            $stmt->execute([$_POST['nama_supplier'], $_POST['alamat'], $phone, $_POST['id_supplier']]);
            $message = 'Supplier berhasil diupdate';
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM supplier WHERE id_supplier = ?");
            $stmt->execute([$_POST['id_supplier']]);
            $message = 'Supplier berhasil dihapus';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Pagination logic
$perPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM supplier WHERE id_supplier LIKE :search OR nama_supplier LIKE :search OR alamat LIKE :search OR no_telpon LIKE :search");
$stmt->bindValue(':search', '%' . $search_query . '%');
$stmt->execute();
$totalItems = $stmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

$stmt = $pdo->prepare("SELECT * FROM supplier WHERE id_supplier LIKE :search OR nama_supplier LIKE :search OR alamat LIKE :search OR no_telpon LIKE :search ORDER BY nama_supplier LIMIT :limit OFFSET :offset");
$stmt->bindValue(':search', '%' . $search_query . '%');
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<head>
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>

<div class="flex min-h-screen">
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

        <div id="supplierManagement" class="section active">
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-800">Manajemen Supplier</h2>
                <p class="text-gray-600 mt-2">Kelola data supplier untuk kebutuhan pembelian bahan</p>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                    <div class="flex justify-between items-center card-header">
                        <div>
                            <h3 class="text-xl font-semibold text-white">Daftar Supplier</h3>
                            <p class="text-blue-100 mt-1">Total: <?php echo $totalItems; ?> supplier</p>
                        </div>
                        <button onclick="showAddSupplierForm()" class="add-button bg-white text-blue-600 hover:bg-blue-50 px-6 py-2 rounded-lg font-medium transition duration-200 flex items-center">
                            <i class="fas fa-plus mr-2"></i>Tambah Supplier
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="mb-6 relative">
                        <input type="text" id="searchInput" name="search" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10" placeholder="Cari ID, nama, alamat, atau no. telpon..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                            <i class="fas fa-search"></i>
                        </span>
                    </div>
                    <?php if (empty($suppliers)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-truck text-gray-400 text-6xl mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-600 mb-2">Belum ada supplier</h3>
                            <p class="text-gray-500">Mulai dengan menambahkan supplier pertama Anda</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full responsive-table border border-gray-200" id="suppliersTable">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700">No.</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">ID Supplier</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Nama Supplier</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Alamat</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">No. Telpon</th>
                                        <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="suppliersTableBody" class="divide-y divide-gray-200">
                                    <?php 
                                    $i = 1;
                                    foreach ($suppliers as $supplier): ?>
                                        <tr class="supplier-row" data-name="<?php echo strtolower(htmlspecialchars($supplier['id_supplier'] . ' ' . $supplier['nama_supplier'] . ' ' . $supplier['alamat'] . ' ' . $supplier['no_telpon'])); ?>">
                                            <td data-label="No." class="px-6 py-4 text-center text-sm text-gray-500"><?php echo $i++; ?></td>
                                            <td data-label="ID" class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($supplier['id_supplier']); ?></td>
                                            <td data-label="Nama" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($supplier['nama_supplier']); ?></td>
                                            <td data-label="Alamat" class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($supplier['alamat']); ?></td>
                                            <td data-label="No. Telpon" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($supplier['no_telpon']); ?></td>
                                            <td class="px-6 py-4 text-center actions-cell">
                                                <div class="flex justify-center space-x-2">
                                                    <button onclick="editSupplier('<?php echo $supplier['id_supplier']; ?>', '<?php echo htmlspecialchars(addslashes($supplier['nama_supplier'])); ?>', '<?php echo htmlspecialchars(addslashes($supplier['alamat'])); ?>', '<?php echo htmlspecialchars($supplier['no_telpon']); ?>')" 
                                                            class="text-blue-600 hover:text-blue-800 hover:bg-blue-100 p-2 rounded-lg transition duration-200">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button onclick="deleteSupplier('<?php echo $supplier['id_supplier']; ?>', '<?php echo htmlspecialchars(addslashes($supplier['nama_supplier'])); ?>')" 
                                                            class="text-red-600 hover:text-red-800 hover:bg-red-100 p-2 rounded-lg transition duration-200">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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

        <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 transform transition-all">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6 rounded-t-xl">
                    <div class="flex justify-between items-center">
                        <h3 id="modalTitle" class="text-xl font-semibold text-white"></h3>
                        <button onclick="closeModal()" class="text-white hover:text-gray-200 transition duration-200">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <div id="modalContent" class="p-6"></div>
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

function showAddSupplierForm() {
    const content = `
        <form id="supplierForm" method="POST" onsubmit="return validateForm(this)">
            <input type="hidden" name="action" value="add">
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">ID Supplier</label>
                    <input type="text" name="id_supplier" value="<?php echo generateId('SUP'); ?>" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50" readonly>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Nama Supplier <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_supplier" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Masukkan nama supplier">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Alamat <span class="text-red-500">*</span></label>
                    <textarea name="alamat" required rows="3"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Masukkan alamat lengkap"></textarea>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">No. Telpon <span class="text-red-500">*</span></label>
                    <input type="tel" name="no_telpon" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Contoh: +6281234567890 atau 081234567890">
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-8">
                <button type="button" onclick="closeModal()" 
                        class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200">
                    Batal
                </button>
                <button type="submit" 
                        class="px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                    <i class="fas fa-save mr-2"></i>Simpan
                </button>
            </div>
        </form>
    `;
    showModal('Tambah Supplier', content);
}

function editSupplier(id, nama, alamat, telpon) {
    const safeNama = nama.replace(/'/g, "\\'").replace(/"/g, '"');
    const safeAlamat = alamat.replace(/'/g, "\\'").replace(/"/g, '"');
    const safeTelpon = telpon.replace(/'/g, "\\'").replace(/"/g, '"');

    const content = `
        <form id="supplierForm" method="POST" onsubmit="return validateForm(this)">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id_supplier" value="${id}">
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">ID Supplier</label>
                    <input type="text" value="${id}" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" readonly>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Nama Supplier <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_supplier" value="${safeNama}" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Alamat <span class="text-red-500">*</span></label>
                    <textarea name="alamat" required rows="3"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">${safeAlamat}</textarea>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">No. Telpon <span class="text-red-500">*</span></label>
                    <input type="tel" name="no_telpon" value="${safeTelpon}" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Contoh: +6281234567890 atau 081234567890">
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-8">
                <button type="button" onclick="closeModal()" 
                        class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200">
                    Batal
                </button>
                <button type="submit" 
                        class="px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                    <i class="fas fa-save mr-2"></i>Update
                </button>
            </div>
        </form>
    `;
    showModal('Edit Supplier', content);
}

function deleteSupplier(id, nama) {
    if (confirm(`Apakah Anda yakin ingin menghapus supplier "${nama}"?\n\nTindakan ini tidak dapat dibatalkan.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id_supplier" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function validateForm(form) {
    const nama = form.querySelector('input[name="nama_supplier"]').value.trim();
    const alamat = form.querySelector('textarea[name="alamat"]').value.trim();
    const telpon = form.querySelector('input[name="no_telpon"]').value.trim();
    
    if (!nama || !alamat || !telpon) {
        alert('Semua field wajib diisi!');
        return false;
    }
    
    const phoneRegex = /^(\+?[0-9\s\-()]{8,15})$/;
    if (!phoneRegex.test(telpon)) {
        alert('Format nomor telepon tidak valid! Contoh: +6281234567890 atau 081234567890');
        return false;
    }
    
    const cleanPhone = telpon.replace(/[^0-9]/g, '');
    if (cleanPhone.length < 8 || cleanPhone.length > 15) {
        alert('Nomor telepon harus memiliki 8-15 digit angka!');
        return false;
    }
    
    return true;
}

document.getElementById('searchInput').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const tableRows = document.querySelectorAll('#suppliersTableBody .supplier-row');
    
    tableRows.forEach(row => {
        const name = row.dataset.name.toLowerCase();
        row.style.display = name.includes(searchTerm) ? '' : 'none';
    });
});

document.addEventListener('DOMContentLoaded', (event) => {
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