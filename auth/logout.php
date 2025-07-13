<?php
session_start();

// Clear all session data
$_SESSION = array();

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy session
session_destroy();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Logout Berhasil</title>
    <meta http-equiv="refresh" content="2;url=../index.php">
    <style>
        body {
            background-color: #f0f4f8;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }

        .logout-container {
            background-color: #ffffff;
            padding: 40px 50px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            text-align: center;
            animation: fadeIn 0.6s ease;
        }

        h1 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 12px;
        }

        p {
            color: #7f8c8d;
            font-size: 1rem;
        }

        .spinner {
            margin: 20px auto;
            width: 40px;
            height: 40px;
            border: 4px solid #ecf0f1;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <h1>Logout Berhasil</h1>
        <div class="spinner"></div>
        <p>Anda akan diarahkan ke halaman utama...</p>
    </div>
</body>
</html>
