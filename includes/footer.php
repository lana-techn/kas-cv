        </div> <!-- Close flex-1 flex flex-col min-h-screen -->
    </div> <!-- Close flex min-h-screen -->
</body>
</html>

<script src="../assets/js/script.js"></script>
    </div> <!-- Ini adalah penutup dari <div class="relative min-h-screen lg:flex"> di header.php -->

    <!-- JavaScript untuk fungsionalitas sidebar -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const hamburgerButton = document.getElementById('hamburger-button');
            const closeSidebarButton = document.getElementById('close-sidebar-button');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');

            function openSidebar() {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            }

            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            }

            // Pastikan elemen ada sebelum menambahkan event listener
            if (hamburgerButton) {
                hamburgerButton.addEventListener('click', openSidebar);
            }
            if (closeSidebarButton) {
                closeSidebarButton.addEventListener('click', closeSidebar);
            }
            if (overlay) {
                overlay.addEventListener('click', closeSidebar);
            }
        });
    </script>
</body>
</html>
