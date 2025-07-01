<?php
session_start();
if (!isset($_SESSION['user']) && !in_array(basename($_SERVER['PHP_SELF']), ['login.php', 'logout.php'])) {
    header('Location: /kas-cv/auth/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Informasi Pengelolaan Kas - CV. Karya Wahana Sentosa</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-gray-100">
    <header class="bg-blue-600 text-white p-4 shadow-lg">
        <div class="container mx-auto flex justify-between items-center">
            <div>
                <img src="../assets/images/logo.png" alt="Logo CV. Karya Wahana Sentosa" class="h-12 mb-1">
            </div>
            <?php if (isset($_SESSION['user'])): ?>
            <div class="flex items-center space-x-4">
                <span class="text-sm"><?php echo htmlspecialchars(strtoupper($_SESSION['user']['level']) . ': ' . $_SESSION['user']['username']); ?></span>
                <a href="#" onclick="confirmLogout()" class="bg-red-500 hover:bg-red-700 px-3 py-1 rounded text-sm">
                    <i class="fas fa-sign-out-alt mr-1"></i>Logout
                </a>
            </div>
            <?php endif; ?>
        </div>
    </header>