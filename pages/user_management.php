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
        if ($_POST['action'] === 'add') {
            if (empty($_POST['id_user']) || empty($_POST['username']) || empty($_POST['password']) || empty($_POST['level'])) {
                throw new Exception('Semua field harus diisi');
            }
            
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE username = ?");
            $stmt->execute([$_POST['username']]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Username sudah digunakan');
            }
            
            // Check if id_user already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE id_user = ?");
            $stmt->execute([$_POST['id_user']]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('ID User sudah digunakan');
            }
            
            $stmt = $pdo->prepare("INSERT INTO user (id_user, username, password, level) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['id_user'], $_POST['username'], password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['level']]);
            $message = 'User berhasil ditambahkan';
        } elseif ($_POST['action'] === 'edit') {
            if (empty($_POST['id_user']) || empty($_POST['username']) || empty($_POST['level'])) {
                throw new Exception('Semua field harus diisi');
            }
            
            // Check if username already exists (except current user)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE username = ? AND id_user != ?");
            $stmt->execute([$_POST['username'], $_POST['id_user']]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Username sudah digunakan');
            }
            
            if (!empty($_POST['password'])) {
                $stmt = $pdo->prepare("UPDATE user SET username = ?, password = ?, level = ? WHERE id_user = ?");
                $stmt->execute([$_POST['username'], password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['level'], $_POST['id_user']]);
            } else {
                $stmt = $pdo->prepare("UPDATE user SET username = ?, level = ? WHERE id_user = ?");
                $stmt->execute([$_POST['username'], $_POST['level'], $_POST['id_user']]);
            }
            $message = 'User berhasil diupdate';
        } elseif ($_POST['action'] === 'delete') {
            // Prevent deleting current user
            if ($_POST['id_user'] === $_SESSION['user']['id_user']) {
                throw new Exception('Tidak dapat menghapus user yang sedang login');
            }
            
            $stmt = $pdo->prepare("DELETE FROM user WHERE id_user = ?");
            $stmt->execute([$_POST['id_user']]);
            $message = 'User berhasil dihapus';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$stmt = $pdo->query("SELECT * FROM user ORDER BY username");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        <div id="userManagement" class="section active">
            <h2 class="text-2xl font-bold mb-6">Manajemen User</h2>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Daftar User</h3>
                    <button onclick="showAddUserForm()" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-plus mr-2"></i>Tambah User
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Level</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="userTableBody" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['id_user']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['level']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="editUser('<?php echo $user['id_user']; ?>')" class="text-blue-600 hover:text-blue-900 mr-2">Edit</button>
                                        <button onclick="deleteUser('<?php echo $user['id_user']; ?>')" class="text-red-600 hover:text-red-900">Hapus</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div id="modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center">
            <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full mx-4">
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
function showAddUserForm() {
    const content = `
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">ID User</label>
                <input type="text" name="id_user" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Username</label>
                <input type="text" name="username" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <input type="password" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Level</label>
                <select name="level" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                    <option value="">Pilih Level</option>
                    <option value="admin">Admin</option>
                    <option value="pegawai">Pegawai</option>
                    <option value="pemilik">Pemilik</option>
                </select>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-700">Batal</button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-700">Simpan</button>
            </div>
        </form>
    `;
    showModal('Tambah User', content);
}

function editUser(id_user) {
    // Fetch user data via AJAX or use existing data
    const userRow = document.querySelector(`button[onclick="editUser('${id_user}')"]`).closest('tr');
    const cells = userRow.querySelectorAll('td');
    const username = cells[1].textContent;
    const level = cells[2].textContent;
    
    const content = `
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id_user" value="${id_user}">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">ID User</label>
                <input type="text" value="${id_user}" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Username</label>
                <input type="text" name="username" value="${username}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Password (biarkan kosong jika tidak ingin mengubah)</label>
                <input type="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Level</label>
                <select name="level" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                    <option value="admin" ${level === 'admin' ? 'selected' : ''}>Admin</option>
                    <option value="pegawai" ${level === 'pegawai' ? 'selected' : ''}>Pegawai</option>
                    <option value="pemilik" ${level === 'pemilik' ? 'selected' : ''}>Pemilik</option>
                </select>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-700">Batal</button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-700">Update</button>
            </div>
        </form>
    `;
    showModal('Edit User', content);
}

function deleteUser(id) {
    if (confirm('Apakah Anda yakin ingin menghapus user ini?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id_user" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
<?php require_once '../includes/footer.php'; ?>