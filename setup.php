<?php
// setup.php - Run this ONCE to fix the operator password
declare(strict_types=1);

$config = require 'config.php';

try {
    $dsn = "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $username = 'operator';
    $password = 'matrix';

    // Generate a fresh, perfect hash using PHP itself
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert or update the user
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password_hash)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE password_hash = ?
    ");
    $stmt->execute([$username, $hash, $hash]);

    echo "<h1 style='color:green;font-family:monospace;'>SUCCESS!</h1>";
    echo "<p style='font-family:monospace;'>Operator account created. Password is set to <b>matrix</b>.</p>";
    echo "<p style='font-family:monospace;'><a href='login.php'>Go to Login Page</a></p>";

} catch (Exception $e) {
    echo "<h1 style='color:red;font-family:monospace;'>ERROR</h1>";
    echo "<p style='font-family:monospace;'>" . $e->getMessage() . "</p>";
}
?>
