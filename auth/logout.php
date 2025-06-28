<?php
session_start();

// Clear all session data
$_SESSION = array();

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy session
session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Logout</title>
    <meta http-equiv="refresh" content="1;url=../index.php">
</head>
<body>
    <p>Logging out...</p>
    <script>
        setTimeout(function() {
            window.location.href = '../index.php';
        }, 1000);
    </script>
</body>
</html>
