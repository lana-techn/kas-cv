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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(-45deg, #6366f1, #3b82f6);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        /* Floating Label Styles */
        .floating-label-group {
            position: relative;
        }

        .floating-label-group .floating-label {
            position: absolute;
            top: 50%;
            left: 3rem; /* Sesuaikan dengan padding kiri ikon */
            transform: translateY(-50%);
            transition: all 0.2s ease-in-out;
            pointer-events: none;
            color: #d1d5db; /* Warna placeholder */
        }

        .floating-label-group input:focus ~ .floating-label,
        .floating-label-group input:not(:placeholder-shown) ~ .floating-label {
            top: -0.75rem;
            left: 0.75rem;
            font-size: 0.75rem;
            color: #ffffff;
            background: #6366f1; /* Warna background label saat aktif */
            padding: 0 0.25rem;
            border-radius: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="min-h-screen flex items-center justify-center p-4">
        
        <div class="w-full max-w-md p-8 space-y-8 rounded-2xl glass-card text-white shadow-2xl">
            
            <div class="text-center">
                <i class="fas fa-warehouse text-4xl mb-3 text-indigo-300"></i>
                <h1 class="text-3xl font-bold">CV. Karya Wahana Sentosa</h1>
                <p class="text-indigo-200 mt-1">Sistem Informasi Pengelolaan Kas & Inventaris</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="bg-pink-500/50 border border-pink-400 text-white px-4 py-3 rounded-lg relative text-center" role="alert">
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="floating-label-group">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-4">
                        <i class="fas fa-user text-gray-300"></i>
                    </span>
                    <input id="username" type="text" name="username" class="w-full pl-12 pr-4 py-4 bg-white/10 border border-white/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 placeholder-transparent" required placeholder="Username">
                    <label for="username" class="floating-label">Username</label>
                </div>

                <div class="floating-label-group">
                     <span class="absolute inset-y-0 left-0 flex items-center pl-4">
                        <i class="fas fa-lock text-gray-300"></i>
                    </span>
                    <input id="password" type="password" name="password" class="w-full pl-12 pr-4 py-4 bg-white/10 border border-white/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 placeholder-transparent" required placeholder="Password">
                    <label for="password" class="floating-label">Password</label>
                </div>
                
                

                <div>
                    <button type="submit" class="w-full font-semibold bg-indigo-600 text-white py-3 px-4 rounded-lg transition-all duration-300 transform hover:bg-indigo-700 hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-indigo-500 shadow-lg shadow-indigo-600/50">
                        <i class="fas fa-sign-in-alt mr-2"></i>LOGIN
                    </button>
                </div>
            </form>
            
            <p class="text-center text-sm text-gray-300">
                Belum punya akun? <a href="#" class="font-medium text-indigo-300 hover:text-indigo-200">Hubungi Administrator</a>
            </p>

        </div>
    </div>
</body>
</html>






