<?php
session_start();
require_once 'config/db_connect.php';

if (isset($_SESSION['user'])) {
    header('Location: ./pages/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Login - CV. Karya Wahana Sentosa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            animation: {
              'gradient-x': 'gradient-x 15s ease infinite',
            },
            keyframes: {
              'gradient-x': {
                '0%, 100%': { 'background-position': '0% 50%' },
                '50%': { 'background-position': '100% 50%' },
              },
            },
            backgroundSize: {
              '400': '400% 400%',
            }
          }
        }
      }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-screen flex items-center justify-center p-4 font-[Poppins] bg-[url('assets/images/bg.jpg')] bg-cover bg-center bg-no-repeat">

    <div class="w-full max-w-md p-8 space-y-8 rounded-2xl bg-white/10 backdrop-blur-lg border border-white/20 text-white shadow-2xl">
        
        <div class="text-center">
            <h1 class="text-3xl font-bold">CV. Karya Wahana Sentosa</h1>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-pink-500/50 border border-pink-400 text-white px-4 py-3 rounded-lg text-center" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4">
                    <i class="fas fa-user text-gray-300"></i>
                </span>
                <input id="username" name="username" type="text" placeholder="Username"
                    class="peer w-full pl-12 pr-4 py-4 bg-white/10 border border-white/20 rounded-lg text-white placeholder-transparent focus:outline-none focus:ring-2 focus:ring-indigo-400" required />
                <label for="username"
                    class="absolute left-12 top-1/2 -translate-y-1/2 text-sm text-gray-300 peer-placeholder-shown:top-1/2 peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-300 peer-focus:top-[-0.75rem] peer-focus:left-3 peer-focus:text-sm peer-focus:text-white peer-focus:bg-indigo-500 peer-focus:px-1 peer-focus:rounded-md transition-all">
                    Username
                </label>
            </div>

            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4">
                    <i class="fas fa-lock text-gray-300"></i>
                </span>
                <input id="password" name="password" type="password" placeholder="Password"
                    class="peer w-full pl-12 pr-4 py-4 bg-white/10 border border-white/20 rounded-lg text-white placeholder-transparent focus:outline-none focus:ring-2 focus:ring-indigo-400" required />
                <label for="password"
                    class="absolute left-12 top-1/2 -translate-y-1/2 text-sm text-gray-300 peer-placeholder-shown:top-1/2 peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-300 peer-focus:top-[-0.75rem] peer-focus:left-3 peer-focus:text-sm peer-focus:text-white peer-focus:bg-indigo-500 peer-focus:px-1 peer-focus:rounded-md transition-all">
                    Password
                </label>
            </div>

            <div>
                <button type="submit"
                    class="w-full font-semibold bg-indigo-600 text-white py-3 px-4 rounded-lg transition-all duration-300 transform hover:bg-indigo-700 hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-indigo-500 shadow-lg shadow-indigo-600/50">
                    <i class="fas fa-sign-in-alt mr-2"></i>LOGIN
                </button>
            </div>
        </form>

        <p class="text-center text-sm text-gray-300">
            Belum punya akun?
            <a href="#" class="font-medium text-indigo-300 hover:text-indigo-200">Hubungi Administrator</a>
        </p>

    </div>

</body>
</html>
