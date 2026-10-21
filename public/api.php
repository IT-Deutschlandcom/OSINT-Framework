<?php
declare(strict_types=1);

ini_set('display_errors', '0');
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/database.php';
require __DIR__ . '/app/providers.php';

try {
    security_headers();
    start_session();
    $action = $_GET['action'] ?? '';
    if (!is_string($action)) {
        throw new ApiError(400, 'Ungültige Aktion.');
    }
    if (in_array($action, ['register', 'login', 'logout', 'query'], true)) {
        require_method('POST');
        $body = request_json();
        verify_csrf($body);
    } else {
        require_method('GET');
    }
    switch ($action) {
        case 'session':
            json_reply(['ok' => true, 'user' => current_user(), 'csrf' => $_SESSION['csrf']]);
        case 'register':
        case 'login':
            only_fields($body, ['email', 'password', 'csrf']);
            throttle($action . ':ip:' . client_ip(), $action === 'register' ? 5 : 10, $action === 'register' ? 3600 : 900);
            $email = strtolower(text_field($body, 'email', 254));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[\x21-\x7e]+$/D', $email)) {
                throw new ApiError(422, 'Bitte geben Sie eine gültige E-Mail-Adresse ein.');
            }
            // Passwords are deliberately not trimmed.
            $password = $body->password ?? null;
            if (!is_string($password) || strlen($password) < 12 || strlen($password) > 128 || str_contains($password, "\0")) {
                throw new ApiError(422, 'Das Passwort muss 12 bis 128 Byte lang sein.');
            }
            $password = password_material($password);
            throttle($action . ':email:' . $email, $action === 'register' ? 3 : 10, $action === 'register' ? 3600 : 900);
            if ($action === 'register') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $stmt = db()->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
                    $stmt->execute([$email, $hash]);
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000') {
                        throw $e;
                    }
                }
                json_reply(['ok' => true, 'message' => 'Falls die Adresse noch nicht registriert war, wurde das Konto angelegt. Eine manuelle Freischaltung ist vor der Anmeldung erforderlich.']);
            }
            $stmt = db()->prepare('SELECT id, email, password_hash, active FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            $valid = password_verify($password, $row ? $row['password_hash'] : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
            if (!$row || !$valid || (int) $row['active'] !== 1) {
                throw new ApiError(401, 'Anmeldung nicht möglich. Prüfen Sie Zugangsdaten und Freischaltung.');
            }
            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $row['id'];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            json_reply(['ok' => true, 'user' => ['id' => (int) $row['id'], 'email' => $row['email']], 'csrf' => $_SESSION['csrf']]);
        case 'logout':
            only_fields($body, ['csrf']);
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            json_reply(['ok' => true, 'csrf' => $_SESSION['csrf']]);
        case 'catalog':
            current_user(true);
            $raw = file_get_contents(__DIR__ . '/arf.json');
            if ($raw === false) {
                throw new ApiError(503, 'Der Katalog ist derzeit nicht verfügbar.');
            }
            json_reply(['ok' => true, 'catalog' => json_decode($raw, true, 128, JSON_THROW_ON_ERROR), 'providers' => providers()]);
        case 'query':
            $user = current_user(true);
            only_fields($body, ['csrf', 'provider', 'inputs']);
            $provider = provider_by_id(text_field($body, 'provider', 32));
            if (!isset($body->inputs) || !$body->inputs instanceof stdClass) {
                throw new ApiError(422, 'Die Eingaben müssen ein JSON-Objekt sein.');
            }
            $inputs = normalized_inputs($provider, $body->inputs);
            json_reply(['ok' => true, 'entry' => run_query($user, $provider, $inputs)]);
        case 'history':
            $user = current_user(true);
            $page = $_GET['page'] ?? '1';
            if (!is_string($page) || !preg_match('/^[1-9][0-9]{0,5}$/D', $page)) {
                throw new ApiError(422, 'Ungültige Seitennummer.');
            }
            $page = (int) $page;
            $stmt = db()->prepare('SELECT id, provider, category, subcategory, inputs, http_status, created_at, success FROM query_history WHERE user_id = ? ORDER BY id DESC LIMIT 21 OFFSET ?');
            $stmt->bindValue(1, $user['id'], PDO::PARAM_INT);
            $stmt->bindValue(2, ($page - 1) * 20, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            json_reply(['ok' => true, 'entries' => array_map(static fn(array $row): array => history_entry($row, false), array_slice($rows, 0, 20)), 'page' => $page, 'has_more' => count($rows) > 20]);
        case 'replay':
            $user = current_user(true);
            $id = $_GET['id'] ?? '';
            if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,17}$/D', $id)) {
                throw new ApiError(422, 'Ungültige Verlaufsnummer.');
            }
            $stmt = db()->prepare('SELECT * FROM query_history WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $user['id']]);
            $row = $stmt->fetch();
            if (!$row) {
                throw new ApiError(404, 'Dieser Verlaufseintrag wurde nicht gefunden.');
            }
            json_reply(['ok' => true, 'entry' => history_entry($row, true)]);
        default:
            throw new ApiError(404, 'Unbekannte Aktion.');
    }
} catch (ApiError $e) {
    json_reply(['ok' => false, 'error' => $e->getMessage()], $e->status);
} catch (Throwable $e) {
    // Never put exception messages, SQL, credentials or upstream request details in responses.
    error_log('OSINT API: ' . get_class($e));
    json_reply(['ok' => false, 'error' => 'Der Dienst ist derzeit nicht verfügbar. Bitte prüfen Sie die Server- und Datenbankeinrichtung.'], 503);
}
