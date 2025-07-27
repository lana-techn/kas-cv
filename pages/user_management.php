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
            if (empty($_POST['username']) || empty($_POST['password']) || empty($_POST['level'])) {
                throw new Exception('Semua field harus diisi');
            }

            // Cek username sudah ada atau belum
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE username = ?");
            $stmt->execute([$_POST['username']]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Username sudah digunakan');
            }

            // Generate ID user otomatis berdasarkan level
            $level_prefix = '';
            switch ($_POST['level']) {
                case 'admin':
                    $level_prefix = 'ADM';
                    break;
                case 'pegawai':
                    $level_prefix = 'PGW';
                    break;
                case 'pemilik':
                    $level_prefix = 'PMK';
                    break;
                default:
                    $level_prefix = 'USR'; // Default fallback
            }

            // Cari ID tertinggi untuk prefix tersebut
            $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(id_user, 4) AS UNSIGNED)) AS max_id FROM user WHERE id_user LIKE ?");
            $stmt->execute([$level_prefix . '%']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $next_id = ($result['max_id'] ? $result['max_id'] + 1 : 1);
            $new_id_user = $level_prefix . str_pad($next_id, 3, '0', STR_PAD_LEFT); // Format: ADM001, PGW002, PMK003, dst.

            // proses input user baru
            $stmt = $pdo->prepare("INSERT INTO user (id_user, username, password, level) VALUES (?, ?, ?, ?)");
            $stmt->execute([$new_id_user, $_POST['username'], password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['level']]);
            $message = 'User berhasil ditambahkan dengan ID: ' . $new_id_user;

            //Edit User
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

            //Hapus User
        } elseif ($_POST['action'] === 'delete') {
            // Prevent deleting current user
            if ($_POST['id_user'] === $_SESSION['user']['id_user']) {
                throw new Exception('Tidak dapat menghapus user yang sedang login');
            }
            // PROSES HAPUS USER
            $stmt = $pdo->prepare("DELETE FROM user WHERE id_user = ?");
            $stmt->execute([$_POST['id_user']]);
            $message = 'User berhasil dihapus';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
// Menampilkan Daftar User
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
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-800">Manajemen User</h2>
                <p class="text-gray-600 mt-2">Kelola data user untuk akses sistem</p>
            </div>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-xl font-semibold text-white">Daftar User</h3>
                            <p class="text-blue-100 mt-1">Total: <?php echo count($users); ?> user</p>
                        </div>
                        <button onclick="showAddUserForm()" class="bg-white text-blue-600 hover:bg-blue-50 px-6 py-2 rounded-lg font-medium transition duration-200 flex items-center">
                            <i class="fas fa-plus mr-2"></i>Tambah User
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full responsive-table">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">No</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">ID User</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Username</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Level</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php
                                $i = 1;
                                foreach ($users as $user): ?>
                                    <tr class="hover:bg-gray-50 transition duration-200">
                                        <td data-label="No." class="px-6 py-4 text-sm text-gray-900"><?php echo $i++; ?></td>
                                        <td data-label="ID User" class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['id_user']); ?></td>
                                        <td data-label="Username" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td data-label="Level" class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($user['level']); ?></td>
                                        <td data-label="Aksi" class="px-6 py-4 text-sm font-medium">
                                            <button onclick="editUser('<?php echo $user['id_user']; ?>')" class="text-blue-600 hover:text-blue-900 mr-2"><i class="fas fa-edit"></i></button>
                                            <button onclick="deleteUser('<?php echo $user['id_user']; ?>')" class="text-red-600 hover:text-red-900"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div id="modalContent" class="p-6"></div>
            </div>
        </div>
    </main>
</div>
<script>
    function showAddUserForm() {
        const content = `
        <form method="POST" onsubmit="return validateUserForm(this)">
            <input type="hidden" name="action" value="add">
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Username <span class="text-red-500">*</span></label>
                    <input type="text" name="username" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Masukkan username">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Masukkan password">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Level <span class="text-red-500">*</span></label>
                    <select name="level" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Pilih Level</option>
                        <option value="admin">Admin</option>
                        <option value="pegawai">Pegawai</option>
                        <option value="pemilik">Pemilik</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-8">
                <button type="button" onclick="closeModal()" class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200">Batal</button>
                <button type="submit" class="px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200"><i class="fas fa-save mr-2"></i>Simpan</button>
            </div>
        </form>
    `;
        showModal('Tambah User', content);
    }

    function editUser(id_user) {
        const userRow = document.querySelector(`button[onclick=\"editUser('${id_user}')\"]`).closest('tr');
        const cells = userRow.querySelectorAll('td');
        const username = cells[2].textContent;
        const level = cells[3].textContent;
        const content = `
        <form method="POST" onsubmit="return validateUserForm(this)">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id_user" value="${id_user}">
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">ID User</label>
                    <input type="text" value="${id_user}" class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" readonly>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Username <span class="text-red-500">*</span></label>
                    <input type="text" name="username" value="${username}" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Masukkan username">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Password (biarkan kosong jika tidak ingin mengubah)</label>
                    <input type="password" name="password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Masukkan password baru">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Level <span class="text-red-500">*</span></label>
                    <select name="level" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="admin" ${level === 'admin' ? 'selected' : ''}>Admin</option>
                        <option value="pegawai" ${level === 'pegawai' ? 'selected' : ''}>Pegawai</option>
                        <option value="pemilik" ${level === 'pemilik' ? 'selected' : ''}>Pemilik</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-8">
                <button type="button" onclick="closeModal()" class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200">Batal</button>
                <button type="submit" class="px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200"><i class="fas fa-save mr-2"></i>Update</button>
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

    // Notifikasi untuk field semua harus di isi
    function validateUserForm(form) {
        const action = form.querySelector('input[name="action"]').value;
        const username = form.querySelector('input[name="username"]').value.trim();
        const level = form.querySelector('select[name="level"]').value;

        if (action === 'add') {
            const password = form.querySelector('input[name="password"]').value;
            if (!username || !password || !level) {
                alert('Semua field wajib diisi!');
                return false;
            }
        } else if (action === 'edit') {
            const id = form.querySelector('input[name="id_user"]').value.trim();
            if (!id || !username || !level) {
                alert('Semua field wajib diisi!');
                return false;
            }
        }
        return true;
    }

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

    // Menutup notifikasi setelah beberapa detik
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