<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = app_config()['database'];
    $pdo = new PDO(
        'mysql:host=' . $c['host'] . ';port=' . (int) $c['port'] . ';dbname=' . $c['name'] . ';charset=utf8mb4',
        $c['user'],
        $c['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]
    );
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}

function current_user(bool $required = false): ?array
{
    $user = null;
    if (isset($_SESSION['user_id'])) {
        $stmt = db()->prepare('SELECT id, email FROM users WHERE id = ? AND active = 1');
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch();
        if ($row) {
            $user = ['id' => (int) $row['id'], 'email' => $row['email']];
        } else {
            unset($_SESSION['user_id']);
            session_regenerate_id(true);
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
    }
    if ($required && $user === null) {
        throw new ApiError(401, 'Bitte melden Sie sich mit einem freigeschalteten Konto an.');
    }
    return $user;
}

function throttle(string $key, int $limit, int $seconds): void
{
    $pdo = db();
    $bucket = hash('sha256', $key);
    $now = time();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT IGNORE INTO rate_limits (bucket, window_start, hits) VALUES (?, ?, 0)');
        $stmt->execute([$bucket, $now]);
        $stmt = $pdo->prepare('SELECT window_start, hits FROM rate_limits WHERE bucket = ? FOR UPDATE');
        $stmt->execute([$bucket]);
        $row = $stmt->fetch();
        $start = (int) $row['window_start'];
        $hits = (int) $row['hits'];
        if ($now - $start >= $seconds) {
            $start = $now;
            $hits = 0;
        }
        if ($hits >= $limit) {
            $pdo->rollBack();
            header('Retry-After: ' . max(1, $seconds - ($now - $start)));
            throw new ApiError(429, 'Zu viele Anfragen. Bitte versuchen Sie es später erneut.');
        }
        $stmt = $pdo->prepare('UPDATE rate_limits SET window_start = ?, hits = ? WHERE bucket = ?');
        $stmt->execute([$start, $hits + 1, $bucket]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function client_ip(): string
{
    // Do not trust client-controlled forwarding headers.
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function history_entry(array $row, bool $full): array
{
    $entry = [
        'id' => (int) $row['id'],
        'provider' => $row['provider'],
        'category' => $row['category'],
        'subcategory' => $row['subcategory'],
        'inputs' => json_decode($row['inputs'], true, 16, JSON_THROW_ON_ERROR),
        'http_status' => (int) $row['http_status'],
        'created_at' => str_replace(' ', 'T', $row['created_at']) . 'Z',
        'success' => (bool) $row['success'],
    ];
    if ($full) {
        $entry['response'] = json_decode($row['response'], true, 64, JSON_THROW_ON_ERROR);
    }
    return $entry;
}
