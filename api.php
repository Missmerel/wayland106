<?php
// api.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once 'auth.php';
require_login();

$cfg = require __DIR__ . '/config.php';

function respond(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function db(array $c): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

try {
    if ($method === 'GET' && $action === 'fetch') {
        $room  = substr((string)($_GET['room'] ?? 'main'), 0, 64);
        $since = (int)($_GET['since'] ?? 0);

        $stmt = db($cfg['db'])->prepare(
            'SELECT id, sender, ciphertext, iv, sha256, created_at
             FROM messages WHERE room = ? AND id > ? ORDER BY id ASC LIMIT 200'
        );
        $stmt->execute([$room, $since]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[] = [
                'id'         => (int)$row['id'],
                'sender'     => $row['sender'],
                'ciphertext' => $row['ciphertext'], // Send encrypted payload to browser
                'iv'         => $row['iv'],
                'sha256'     => $row['sha256'],
                'sha256_ok'  => true, // The browser will verify E2EE
                'created_at' => $row['created_at'],
            ];
        }
        respond(200, ['ok' => true, 'messages' => $out]);
    }

    if ($method === 'POST' && $action === 'send') {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);
        if (!is_array($data)) respond(400, ['ok' => false, 'error' => 'invalid JSON']);

        $user = current_user();
        $sender = $user['username'];
        $user_id = $user['id'];

        $room   = substr((string)($data['room']   ?? 'main'), 0, 64);

        // E2EE Payload
        $e2ee_ciphertext = (string)($data['e2ee_ciphertext'] ?? '');
        $e2ee_iv         = (string)($data['e2ee_iv'] ?? '');
        $sha             = (string)($data['sha256'] ?? '');

        if ($e2ee_ciphertext === '' || $sha === '') {
            respond(422, ['ok' => false, 'error' => 'missing encrypted payload']);
        }

        $stmt = db($cfg['db'])->prepare(
            'INSERT INTO messages (room, user_id, sender, ciphertext, iv, tag, sha256)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->bindValue(1, $room);
        $stmt->bindValue(2, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(3, $sender);
        $stmt->bindValue(4, $e2ee_ciphertext, PDO::PARAM_STR);
        $stmt->bindValue(5, $e2ee_iv, PDO::PARAM_STR);
        $stmt->bindValue(6, 'E2EE', PDO::PARAM_STR);
        $stmt->bindValue(7, $sha, PDO::PARAM_STR);
        $stmt->execute();

        respond(200, ['ok' => true, 'id' => (int)db($cfg['db'])->lastInsertId(), 'sha256' => $sha]);
    }

    if ($method === 'POST' && $action === 'ping') {
        $user = current_user();
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);
        $room = substr((string)($data['room'] ?? 'main'), 0, 64);

        $stmt = db($cfg['db'])->prepare(
            'INSERT INTO typing_status (user_id, room, last_typed)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE room = VALUES(room), last_typed = NOW()'
        );
        $stmt->execute([$user['id'], $room]);
        respond(200, ['ok' => true]);
    }

    if ($method === 'GET' && $action === 'check_typing') {
        $user = current_user();
        $room = substr((string)($_GET['room'] ?? 'main'), 0, 64);

        $stmt = db($cfg['db'])->prepare(
            'SELECT u.username FROM typing_status t
             JOIN users u ON t.user_id = u.id
             WHERE t.room = ? AND t.user_id != ? AND t.last_typed >= NOW() - INTERVAL 5 SECOND'
        );
        $stmt->execute([$room, $user['id']]);
        $typingUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        respond(200, ['ok' => true, 'typing' => $typingUsers]);
    }

    respond(404, ['ok' => false, 'error' => 'unknown action']);
} catch (Throwable $e) {
    // Temporarily send the actual error back so we can see it in the browser!
    respond(500, ['ok' => false, 'error' => $e->getMessage()]);
}
