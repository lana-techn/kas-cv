<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user']) && !in_array(basename($_SERVER['PHP_SELF']), ['login.php', 'logout.php'])) {
    header('Location: /kas-cv/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KWS - <?php echo isset($_SESSION['user']) ? htmlspecialchars(ucfirst($_SESSION['user']['level'])) : 'Login'; ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/styles.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
    <script>
        function confirmLogout( ) {
            if (confirm('Apakah Anda yakin ingin keluar?')) {
                window.location.href = '/kas-cv/auth/logout.php';
            }
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            const closeSidebarButton = document.getElementById('close-sidebar-button');

            function toggleSidebar() {
                sidebar.classList.toggle('-translate-x-full');
                sidebarOverlay.classList.toggle('hidden');
            }

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', toggleSidebar);
            }

            if (closeSidebarButton) {
                closeSidebarButton.addEventListener('click', toggleSidebar);
            }

            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', toggleSidebar);
            }
        });
    </script>
</head>
<body class="bg-gray-50 antialiased">
    <?php if (isset($_SESSION['user'])): ?>
    <header class="bg-white shadow-md sticky top-0 z-40">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Tombol hamburger menu -->
                <button id="sidebar-toggle" class="lg:hidden p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500">
                    <i class="fas fa-bars fa-lg"></i>
                </button>

                <div class="flex-shrink-0">
                    <a href="../pages/dashboard.php" class="flex items-center space-x-2">
                         <img class="h-10 w-auto" src="../assets/images/logo.png" alt="Logo CV. Karya Wahana Sentosa">
                    </a>
                </div>

                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="font-semibold text-sm text-gray-800"><?php echo htmlspecialchars($_SESSION['user']['username']); ?></p>
                        <p class="text-xs text-gray-500 capitalize"><?php echo htmlspecialchars($_SESSION['user']['level']); ?></p>
                    </div>
                    <div class="w-10 h-10 flex items-center justify-center bg-blue-100 text-blue-600 rounded-full font-bold text-lg">
                        <?php echo strtoupper(substr($_SESSION['user']['username'], 0, 1)); ?>
                    </div>
                    <button onclick="confirmLogout()" title="Logout" class="text-gray-500 hover:text-red-600 transition-colors duration-200 p-2 rounded-full hover:bg-gray-100">
                        <i class="fas fa-sign-out-alt fa-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>
    <?php endif; ?>
