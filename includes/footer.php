<script src="../assets/js/script.js"></script>
<script>
function confirmLogout() {
    if (confirm('Apakah Anda yakin ingin keluar?')) {
        // Send AJAX request to logout
        fetch('/kas-cv/auth/logout.php')
            .then(() => {
                window.location.href = '/kas-cv/index.php';
            })
            .catch(() => {
                // Fallback if AJAX fails
                window.location.href = '/kas-cv/auth/logout.php';
            });
    }
}
</script>
</body>
</html>
