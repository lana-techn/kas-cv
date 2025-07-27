<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) && !in_array(basename($_SERVER['PHP_SELF']), ['login.php', 'logout.php'])) {
    header('Location: /kas-cv/index.php'); 
    exit;
}

if (!defined('APP_NAME')) {
    define('APP_NAME', 'KAS CV');
}

$currentUser = isset($_SESSION['user']) ? $_SESSION['user'] : null;
if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}
?>
<!DOCTYPE html>
<!-- Warna full ubah disini -->
<html lang="id" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle) . ' - ' . APP_NAME; ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 
                            50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd', 
                            400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8', 
                            800: '#1e40af', 900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    <script src="//unpkg.com/alpinejs" defer></script>
    
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-full-width { width: 100% !important; margin: 0 !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="h-full">
    <div x-data="{ sidebarOpen: false }" class="min-h-full">
        <?php if ($currentUser): ?>
            <!-- Off-canvas menu for mobile -->
            <div x-show="sidebarOpen" class="relative z-50 lg:hidden">
                <div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-900/80"></div>
                
                <div class="fixed inset-0 flex">
                    <div x-show="sidebarOpen" x-transition:enter="transition ease-in-out duration-300 transform" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in-out duration-300 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="relative mr-16 flex w-full max-w-xs flex-1">
                        <div x-show="sidebarOpen" x-transition:enter="ease-in-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in-out duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="absolute left-full top-0 flex w-16 justify-center pt-5">
                            <button type="button" class="-m-2.5 p-2.5" @click="sidebarOpen = false">
                                <span class="sr-only">Close sidebar</span>
                                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        
                        <!-- Mobile Sidebar (No Logo Here) -->
                        <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-white px-6 pb-4 pt-5">
                            <nav class="flex flex-1 flex-col">
                                <?php include 'sidebar.php'; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Static sidebar for desktop (No Logo Here) -->
            <div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-72 lg:flex-col">
                <div class="flex grow flex-col gap-y-5 overflow-y-auto border-r border-gray-200 bg-white px-6 pb-4 pt-5">
                    <nav class="flex flex-1 flex-col">
                        <?php include 'sidebar.php'; ?>
                    </nav>
                </div>
            </div>
            
            <!-- Main content area -->
            <div class="lg:pl-72">
                <!-- Top navigation (Logo is now here) -->
                <div class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 bg-white px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8 no-print">
                    <button type="button" class="-m-2.5 p-2.5 text-gray-700 lg:hidden" @click="sidebarOpen = true">
                        <span class="sr-only">Open sidebar</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                    </button>
                    
                    <div class="h-6 w-px bg-gray-200 lg:hidden" aria-hidden="true"></div>
                    
                    <div class="flex flex-1 items-center justify-between">
                        <!-- Logo Section -->
                        <div class="flex items-center">
                           
                        </div>
                        
                        <!-- User Menu Section -->
                        <div class="flex items-center gap-x-4 lg:gap-x-6">
                            <div class="relative" x-data="{ open: false }">
                                <button type="button" class="flex items-center gap-x-2 text-sm font-semibold leading-6 text-gray-900" @click="open = !open">
                                    <span class="sr-only">Open user menu</span>
                                    <div class="h-8 w-8 rounded-full bg-primary-500 flex items-center justify-center">
                                        <span class="text-sm font-medium text-white"><?php echo strtoupper(substr($currentUser['username'], 0, 1)); ?></span>
                                    </div>
                                    <span class="hidden lg:flex lg:items-center">
                                        <span class="ml-2 text-sm font-medium text-gray-700"><?php echo htmlspecialchars($currentUser['username']); ?> (<?php echo htmlspecialchars(ucfirst($currentUser['level'])); ?>)</span>
                                        <svg class="ml-2 h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                                    </span>
                                </button>
                                
                                <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 z-10 mt-2.5 w-32 origin-top-right rounded-md bg-white py-2 shadow-lg ring-1 ring-gray-900/5">
                                    <a href="../auth/logout.php" class="block px-3 py-1 text-sm leading-6 text-gray-900 hover:bg-gray-50">Logout</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Page content starts here -->
                <main class="py-6 lg:py-8">

        <?php else: ?>
            <!-- Fallback for login page layout -->
            <main class="flex min-h-full flex-col justify-center py-12 sm:px-6 lg:px-8">
                <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <?php endif; ?>
