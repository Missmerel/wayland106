<?php
// register.php
declare(strict_types=1);
require_once 'auth.php';
$config = require 'config.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        try {
            $dsn = "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Check if user exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'OPERATOR ID ALREADY EXISTS';
            } else {
                // Create user
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
                $stmt->execute([$username, $hash]);
                $success = 'REGISTRATION COMPLETE. YOU MAY NOW INITIALIZE.';
            }
        } catch (Exception $e) {
            $error = 'SYSTEM ERROR: ' . $e->getMessage();
        }
    } else {
        $error = 'PLEASE ENTER USERNAME AND PASSWORD';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>WAYLAND // REGISTRATION</title>
<style>
    body { background: #000; color: #00ff66; font-family: "Courier New", monospace; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; text-shadow: 0 0 6px rgba(0,255,102,.55); }
    .box { border: 1px solid #00ff66; padding: 40px; background: rgba(0,20,8,.85); box-shadow: 0 0 20px rgba(0,255,102,.2); text-align: center; width: 320px; }
    h1 { font-size: 16px; letter-spacing: 3px; margin-bottom: 20px; }
    input { width: 100%; background: rgba(0,0,0,.65); color: #00ff66; border: 1px solid #00ff66; padding: 10px; margin-bottom: 10px; font-family: inherit; outline: none; box-sizing: border-box; }
    button { width: 100%; background: transparent; color: #00ff66; border: 1px solid #00ff66; padding: 10px; cursor: pointer; font-family: inherit; letter-spacing: 2px; transition: .15s; margin-bottom: 10px; }
    button:hover { background: #00ff66; color: #000; box-shadow: 0 0 10px #00ff66; }
    a { color: #00e5ff; text-decoration: none; font-size: 12px; display: block; margin-top: 15px; }
    .error { color: #ff3b3b; font-size: 12px; margin-bottom: 15px; }
    .success { color: #00ff66; font-size: 12px; margin-bottom: 15px; }
</style>
</head>
<body>
    <div class="box">
        <h1>WAYLAND CORP // REGISTRATION</h1>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="NEW OPERATOR ID" required autofocus>
            <input type="password" name="password" placeholder="NEW PASSPHRASE" required>
            <button type="submit">CREATE OPERATOR</button>
        </form>
        <a href="login.php">RETURN TO LOGIN</a>
    </div>
</body>
</html>
