<?php
declare(strict_types=1);

final class ApiError extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}

function app_config(): array
{
    static $config;
    return $config ??= require dirname(__DIR__) . '/config.php';
}

function security_headers(): void
{
    header_remove('X-Powered-By');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (app_config()['secure_cookie']) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

function start_session(): void
{
    $config = app_config();
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string) $config['session_lifetime']);
    session_name($config['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (bool) $config['secure_cookie'],
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    if (!session_start()) {
        throw new ApiError(503, 'Die Sitzung ist derzeit nicht verfügbar.');
    }
    if (isset($_SESSION['last_seen']) && time() - (int) $_SESSION['last_seen'] > $config['session_lifetime']) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = time();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function json_reply(array $body, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function request_json(): stdClass
{
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        throw new ApiError(415, 'Bitte senden Sie JSON-Daten.');
    }
    $length = $_SERVER['CONTENT_LENGTH'] ?? '0';
    if (!ctype_digit((string) $length) || (int) $length > 16384) {
        throw new ApiError(413, 'Die Anfrage ist zu groß.');
    }
    $raw = file_get_contents('php://input', false, null, 0, 16385);
    if ($raw === false || strlen($raw) > 16384) {
        throw new ApiError(413, 'Die Anfrage ist zu groß.');
    }
    try {
        $body = json_decode($raw, false, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new ApiError(400, 'Ungültige JSON-Daten.');
    }
    if (!$body instanceof stdClass) {
        throw new ApiError(400, 'Ein JSON-Objekt wird benötigt.');
    }
    return $body;
}

function only_fields(stdClass $body, array $allowed): void
{
    foreach (array_keys(get_object_vars($body)) as $key) {
        if (!in_array($key, $allowed, true)) {
            throw new ApiError(400, 'Die Anfrage enthält unbekannte Felder.');
        }
    }
}

function text_field(stdClass $body, string $key, int $max, bool $required = true): string
{
    $value = $body->$key ?? '';
    if (!is_string($value) || strlen($value) > $max || preg_match('/[\x00-\x1f\x7f]/', $value)) {
        throw new ApiError(422, 'Ein Eingabefeld ist ungültig: ' . $key . '.');
    }
    $value = trim($value);
    if ($required && $value === '') {
        throw new ApiError(422, 'Bitte füllen Sie das Feld ' . $key . ' aus.');
    }
    return $value;
}

function verify_csrf(stdClass $body): void
{
    if (!isset($body->csrf) || !is_string($body->csrf) || !hash_equals($_SESSION['csrf'], $body->csrf)) {
        throw new ApiError(403, 'Die Sitzung ist ungültig. Bitte laden Sie die Seite neu.');
    }
}

function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        throw new ApiError(405, 'Diese HTTP-Methode ist nicht erlaubt.');
    }
}

function password_material(string $password): string
{
    // Fixed-length ASCII input avoids bcrypt's silent 72-byte truncation.
    return 'osint:' . base64_encode(hash('sha384', $password, true));
}
