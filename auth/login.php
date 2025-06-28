<?php
session_start();
require_once 'config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = md5($_POST['password']); // Note: MD5 is used for simplicity; use password_hash() in production

    $stmt = $pdo->prepare("SELECT * FROM user WHERE username = ? AND password = ?");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['user'] = $user;
        header('Location:./pages/dashboard.php');
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CV. Karya Wahana Sentosa</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div id="loginSection" class="min-h-screen flex items-center justify-center bg-gradient-to-r from-blue-500 to-purple-600">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-96">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">CV. Karya Wahana Sentosa</h1>
                <p class="text-gray-600 mt-2">Sistem Informasi Pengelolaan Kas</p>
            </div>
            <?php if (isset($error)): ?>
                <p class="text-red-500 text-sm mb-4"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Username</label>
                    <input type="text" name="username" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" required>
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                    <input type="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" required>
                </div>
                <button type="submit" class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                </button>
            </form>
            <div class="mt-4 text-sm text-gray-600">
                <p>Demo Accounts:</p>
                <p>Admin: admin/admin</p>
                <p>Pegawai: pegawai/pegawai</p>
                <p>Pemilik: pemilik/pemilik</p>
            </div>
        </div>
    </div>
</body>
</html>