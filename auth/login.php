<?php
session_start();
require_once 'config/db_connect.php';

// Jika pengguna sudah login, langsung arahkan ke dashboard
if (isset($_SESSION['user'])) {
    header('Location: ./pages/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    // Peringatan Keamanan: MD5 sangat tidak aman. Gunakan password_hash() dan password_verify() untuk aplikasi production.
    $password = md5($_POST['password']); 

    $stmt = $pdo->prepare("SELECT * FROM user WHERE username = ? AND password = ?");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['user'] = $user;
        header('Location: ./pages/dashboard.php');
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
    <!-- Menggunakan versi Tailwind CSS yang lebih baru untuk utilitas yang lebih kaya -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col md:flex-row">
        <!-- Bagian Kiri (Visual/Branding ) - Tersembunyi di mobile -->
        <div class="hidden md:flex md:w-1/2 lg:w-2/3 bg-gradient-to-tr from-blue-600 to-purple-700 items-center justify-center p-12 text-white text-center relative overflow-hidden">
            <div class="z-10">
                <i class="fas fa-warehouse fa-5x mb-6"></i>
                <h1 class="text-5xl font-extrabold leading-tight">CV. Karya Wahana Sentosa</h1>
                <p class="text-2xl mt-4 opacity-80">Sistem Informasi Pengelolaan Kas & Inventaris</p>
            </div>
            <!-- Efek Latar Belakang -->
            <div class="absolute top-0 left-0 w-full h-full bg-black opacity-20"></div>
            <div class="absolute -bottom-32 -left-40 w-80 h-80 border-4 rounded-full border-opacity-20 border-t-8"></div>
            <div class="absolute -top-40 -right-0 w-80 h-80 border-4 rounded-full border-opacity-20 border-t-8"></div>
            <div class="absolute -bottom-40 -right-20 w-80 h-80 border-4 rounded-full border-opacity-20 border-t-8"></div>
        </div>

        <!-- Bagian Kanan (Form Login) -->
        <div class="w-full md:w-1/2 lg:w-1/3 flex items-center justify-center p-6 sm:p-12">
            <div class="w-full max-w-md">
                <div class="text-center md:text-left mb-8">
                    <h2 class="text-3xl font-bold text-gray-800">Selamat Datang</h2>
                    <p class="text-gray-600 mt-2">Silakan masuk untuk melanjutkan</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert">
                        <p><i class="fas fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <div>
                        <label for="username" class="block text-sm font-bold text-gray-700 mb-2">Username</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                <i class="fas fa-user text-gray-400"></i>
                            </span>
                            <input id="username" type="text" name="username" class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required placeholder="Masukkan username">
                        </div>
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-bold text-gray-700 mb-2">Password</label>
                        <div class="relative">
                             <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                <i class="fas fa-lock text-gray-400"></i>
                            </span>
                            <input id="password" type="password" name="password" class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required placeholder="Masukkan password">
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-300 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login
                    </button>
                </form>

                <div class="mt-8 p-4 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600">
                    <h4 class="font-bold mb-2">Akun Demo:</h4>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div><strong class="block">Admin:</strong> admin</div>
                        <div><strong class="block">Pegawai:</strong> pegawai</div>
                        <div><strong class="block">Pemilik:</strong> pemilik</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>