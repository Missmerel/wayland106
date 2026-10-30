<?php
// genkey.php
declare(strict_types=1);

// We use random_bytes() which is cryptographically secure
// 32 bytes = 256 bits (Perfect for AES-256-GCM)
$raw_bytes = random_bytes(32);

// We encode it to Base64 so it's safe to paste into your config file
$base64_key = base64_encode($raw_bytes);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WAYLAND // KEY GENERATOR</title>
    <style>
        body {
            background: #000;
            color: #00ff66;
            font-family: "Courier New", monospace;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            text-shadow: 0 0 6px rgba(0,255,102,.55);
        }
        .box {
            border: 1px solid #00ff66;
            padding: 30px;
            background: rgba(0, 20, 8, .55);
            text-align: center;
            box-shadow: 0 0 20px rgba(0,255,102,.2);
        }
        h1 { font-size: 18px; letter-spacing: 3px; margin-bottom: 20px; }
        .key {
            font-size: 20px;
            color: #00e5ff;
            background: #000;
            padding: 15px;
            border: 1px dashed #00e5ff;
            margin: 20px 0;
            word-break: break-all;
            text-shadow: 0 0 10px rgba(0,229,255,.6);
        }
        p { font-size: 12px; color: #0a3d1f; }
        .warning { color: #ff3b3b; font-weight: bold; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>WAYLAND // AES-256 KEY</h1>
        <p>Copy the string below and paste it into your <b>config.php</b></p>

        <div class="key"><?= htmlspecialchars($base64_key) ?></div>

        <p>Length: <?= strlen($base64_key) ?> characters</p>

        <div class="warning">DELETE THIS FILE AFTER COPYING!</div>
    </div>
</body>
</html>
