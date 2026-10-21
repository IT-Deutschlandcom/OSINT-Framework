<?php
declare(strict_types=1);

function providers(): array
{
    $domain = ['name' => 'domain', 'label' => 'Öffentliche Domain', 'type' => 'text', 'required' => true, 'placeholder' => 'example.org'];
    return [
        ['id' => 'crtsh', 'name' => 'crt.sh', 'category' => 'Domains & Infrastruktur', 'subcategory' => 'Zertifikate',
            'description' => 'Öffentliche Certificate-Transparency-Einträge einer Domain.',
            'docs' => 'https://crt.sh/', 'limits' => 'Ohne API-Schlüssel; keine Verfügbarkeitsgarantie. Lokal maximal eine gleichzeitige Anfrage und 30 Anfragen/Minute; maximal 2 MB.',
            'fields' => [$domain]],
        ['id' => 'mnemonic', 'name' => 'mnemonic Passive DNS', 'category' => 'Domains & Infrastruktur', 'subcategory' => 'Passive DNS',
            'description' => 'Öffentliche Passive-DNS-Beobachtungen, maximal 50 Datensätze.',
            'docs' => 'https://docs.mnemonic.no/pdns/', 'limits' => 'Öffentlicher Dienst mit eigenen Nutzungsvorgaben. Lokal maximal eine Anfrage/Sekunde, eine gleichzeitige Anfrage und 30/Minute.',
            'fields' => [$domain]],
        ['id' => 'nhtsa', 'name' => 'NHTSA vPIC', 'category' => 'Öffentliche Daten', 'subcategory' => 'Fahrzeuge',
            'description' => 'Fahrzeugidentifikationsnummer über den öffentlichen US-vPIC-Dienst dekodieren.',
            'docs' => 'https://vpic.nhtsa.dot.gov/api/', 'limits' => 'Keine Garantie vollständiger Fahrzeugdaten. Lokal maximal eine gleichzeitige Anfrage und 30/Minute.',
            'fields' => [
                ['name' => 'vin', 'label' => 'VIN (17 Zeichen)', 'type' => 'text', 'required' => true],
                ['name' => 'year', 'label' => 'Modelljahr (optional)', 'type' => 'number', 'required' => false],
            ]],
        ['id' => 'worldbank', 'name' => 'World Bank', 'category' => 'Öffentliche Daten', 'subcategory' => 'Wirtschaft & Länder',
            'description' => 'Öffentliche Indikatoren, bis zu 50 Datensätze mit den jüngsten verfügbaren Werten.',
            'docs' => 'https://datahelpdesk.worldbank.org/knowledgebase/topics/125589-developer-information',
            'limits' => 'Datenabdeckung je Indikator unterschiedlich. Lokal maximal eine gleichzeitige Anfrage und 30/Minute.',
            'fields' => [
                ['name' => 'country', 'label' => 'Ländercode (ISO2 oder all)', 'type' => 'text', 'required' => true, 'placeholder' => 'DE'],
                ['name' => 'indicator', 'label' => 'Indikator', 'type' => 'text', 'required' => true, 'placeholder' => 'SP.POP.TOTL'],
            ]],
        ['id' => 'openlibrary', 'name' => 'Open Library', 'category' => 'Öffentliche Daten', 'subcategory' => 'Publikationen',
            'description' => 'Öffentliche Buchsuche, maximal 20 Treffer mit Titel, Autoren und Erscheinungsjahr.',
            'docs' => 'https://openlibrary.org/dev/docs/api/search',
            'limits' => 'Öffentlicher Suchdienst ohne Vollständigkeitsgarantie. Lokal maximal eine Anfrage/Sekunde, eine gleichzeitige Anfrage und 30/Minute.',
            'fields' => [['name' => 'q', 'label' => 'Suchbegriff', 'type' => 'text', 'required' => true]]],
        ['id' => 'wayback', 'name' => 'Wayback Machine', 'category' => 'Domains & Infrastruktur', 'subcategory' => 'Webarchive',
            'description' => 'Einen verfügbaren archivierten Snapshot der URL ermitteln. Die Ziel-URL wird nicht direkt abgerufen.',
            'docs' => 'https://archive.org/help/wayback_api.php',
            'limits' => 'Der Archivdienst kann Anfragen begrenzen oder ablehnen. Lokal maximal eine gleichzeitige Anfrage und 30/Minute.',
            'fields' => [['name' => 'url', 'label' => 'Öffentliche HTTP(S)-URL', 'type' => 'url', 'required' => true, 'placeholder' => 'https://example.org/']]],
    ];
}

function provider_by_id(string $id): array
{
    foreach (providers() as $provider) {
        if ($provider['id'] === $id) {
            return $provider;
        }
    }
    throw new ApiError(422, 'Unbekannter Anbieter.');
}

function public_domain(string $domain): string
{
    $domain = strtolower($domain);
    if (strlen($domain) > 253 || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $domain)) {
        throw new ApiError(422, 'Bitte geben Sie einen öffentlichen Domainnamen ohne Pfad ein.');
    }
    foreach (['localhost', 'local', 'internal', 'lan', 'home', 'test', 'invalid', 'onion', 'arpa'] as $suffix) {
        if ($domain === $suffix || str_ends_with($domain, '.' . $suffix)) {
            throw new ApiError(422, 'Lokale oder reservierte Domainnamen sind nicht erlaubt.');
        }
    }
    return $domain;
}

function normalized_inputs(array $provider, stdClass $inputs): array
{
    $allowed = array_column($provider['fields'], 'name');
    only_fields($inputs, $allowed);
    $result = [];
    foreach ($provider['fields'] as $field) {
        $max = match ($field['name']) {
            'domain' => 253, 'vin' => 17, 'year' => 4, 'country' => 3,
            'indicator' => 80, 'url' => 2048, default => 200,
        };
        $value = text_field($inputs, $field['name'], $max, $field['required']);
        switch ($field['name']) {
            case 'domain':
                $value = public_domain($value);
                break;
            case 'vin':
                $value = strtoupper($value);
                if (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/D', $value)) {
                    throw new ApiError(422, 'Eine VIN benötigt 17 Zeichen ohne I, O oder Q.');
                }
                break;
            case 'year':
                if ($value !== '' && (!preg_match('/^[0-9]{4}$/D', $value) || (int) $value < 1886 || (int) $value > (int) gmdate('Y') + 1)) {
                    throw new ApiError(422, 'Das Modelljahr ist ungültig.');
                }
                break;
            case 'country':
                $value = strtolower($value);
                $countries = 'ad ae af ag ai al am ao aq ar as at au aw ax az ba bb bd be bf bg bh bi bj bl bm bn bo bq br bs bt bv bw by bz ca cc cd cf cg ch ci ck cl cm cn co cr cu cv cw cx cy cz de dj dk dm do dz ec ee eg eh er es et fi fj fk fm fo fr ga gb gd ge gf gg gh gi gl gm gn gp gq gr gs gt gu gw gy hk hm hn hr ht hu id ie il im in io iq ir is it je jm jo jp ke kg kh ki km kn kp kr kw ky kz la lb lc li lk lr ls lt lu lv ly ma mc md me mf mg mh mk ml mm mn mo mp mq mr ms mt mu mv mw mx my mz na nc ne nf ng ni nl no np nr nu nz om pa pe pf pg ph pk pl pm pn pr ps pt pw py qa re ro rs ru rw sa sb sc sd se sg sh si sj sk sl sm sn so sr ss st sv sx sy sz tc td tf tg th tj tk tl tm tn to tr tt tv tw tz ua ug um us uy uz va vc ve vg vi vn vu wf ws ye yt za zm zw';
                if ($value !== 'all' && !in_array($value, explode(' ', $countries), true)) {
                    throw new ApiError(422, 'Bitte verwenden Sie einen ISO2-Ländercode oder all.');
                }
                break;
            case 'indicator':
                $value = strtoupper($value);
                if (!preg_match('/^[A-Z][A-Z0-9_]*(?:\.[A-Z0-9_]+)*$/D', $value)) {
                    throw new ApiError(422, 'Der Indikatorcode ist ungültig.');
                }
                break;
            case 'url':
                $parts = parse_url($value);
                if (!filter_var($value, FILTER_VALIDATE_URL) || !$parts
                    || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
                    || isset($parts['user']) || isset($parts['pass'])
                    || (isset($parts['port']) && !in_array($parts['port'], [80, 443], true))
                    || isset($parts['fragment'])) {
                    throw new ApiError(422, 'Bitte geben Sie eine öffentliche HTTP(S)-URL ohne Zugangsdaten oder Fragment ein.');
                }
                public_domain($parts['host'] ?? '');
                break;
        }
        if ($value !== '') {
            $result[$field['name']] = $value;
        }
    }
    return $result;
}

function provider_url(string $id, array $inputs): string
{
    [$base, $params] = match ($id) {
        'crtsh' => ['https://crt.sh/', ['q' => '%.' . $inputs['domain'], 'output' => 'json']],
        'mnemonic' => ['https://api.mnemonic.no/pdns/v3/' . rawurlencode($inputs['domain']), ['limit' => 50]],
        'nhtsa' => ['https://vpic.nhtsa.dot.gov/api/vehicles/DecodeVinValues/' . rawurlencode($inputs['vin']), array_filter(['format' => 'json', 'modelyear' => $inputs['year'] ?? null], static fn($v) => $v !== null)],
        'worldbank' => ['https://api.worldbank.org/v2/country/' . rawurlencode($inputs['country']) . '/indicator/' . rawurlencode($inputs['indicator']), ['format' => 'json', 'per_page' => 50, 'mrnev' => 10]],
        'openlibrary' => ['https://openlibrary.org/search.json', ['q' => $inputs['q'], 'limit' => 20, 'fields' => 'key,title,author_name,first_publish_year']],
        'wayback' => ['https://archive.org/wayback/available', ['url' => $inputs['url']]],
        default => throw new ApiError(422, 'Unbekannter Anbieter.'),
    };
    return $base . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function upstream_request(string $url): array
{
    if (!extension_loaded('curl')) {
        throw new ApiError(503, 'Der Abfragedienst ist derzeit nicht verfügbar.');
    }
    $body = '';
    $tooLarge = false;
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_PROXY => '',
        CURLOPT_USERAGENT => 'OSINT-Framework/1.0 (public-data lookup)',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$tooLarge): int {
            if (strlen($body) + strlen($chunk) > 2097152) {
                $tooLarge = true;
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $executed = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($executed === false) {
        return ['response' => ['error' => $tooLarge ? 'Die Antwort überschreitet 2 MB.' : 'Der externe Dienst ist derzeit nicht erreichbar.'], 'http_status' => $status, 'success' => false];
    }
    if ($status < 200 || $status >= 300) {
        return ['response' => ['error' => 'Der externe Dienst hat die Anfrage abgelehnt.'], 'http_status' => $status, 'success' => false];
    }
    try {
        $response = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return ['response' => ['error' => 'Der externe Dienst lieferte keine gültige JSON-Antwort.'], 'http_status' => $status, 'success' => false];
    }
    return ['response' => $response, 'http_status' => $status, 'success' => true];
}

function run_query(array $user, array $provider, array $inputs): array
{
    $pdo = db();
    throttle('query:user:' . $user['id'], 10, 60);
    throttle('query:provider:' . $provider['id'], 30, 60);
    $lock = 'osint:' . substr(hash('sha256', app_config()['database']['name']), 0, 24) . ':' . $provider['id'];
    $stmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $stmt->execute([$lock]);
    if ((int) $stmt->fetchColumn() !== 1) {
        header('Retry-After: 2');
        throw new ApiError(429, 'Dieser Anbieter ist gerade beschäftigt. Bitte warten Sie kurz.');
    }
    try {
        $stmt = $pdo->prepare('INSERT IGNORE INTO provider_limits (provider) VALUES (?)');
        $stmt->execute([$provider['id']]);
        $stmt = $pdo->prepare('SELECT next_allowed_ms FROM provider_limits WHERE provider = ?');
        $stmt->execute([$provider['id']]);
        $now = (int) floor(microtime(true) * 1000);
        if ((int) $stmt->fetchColumn() > $now) {
            header('Retry-After: 1');
            throw new ApiError(429, 'Bitte warten Sie mindestens eine Sekunde zwischen Anbieteranfragen.');
        }
        $stmt = $pdo->prepare('UPDATE provider_limits SET next_allowed_ms = ? WHERE provider = ?');
        $stmt->execute([$now + 1000, $provider['id']]);
        $result = upstream_request(provider_url($provider['id'], $inputs));
        $stmt = $pdo->prepare('INSERT INTO query_history (user_id, provider, category, subcategory, inputs, response, http_status, success) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $user['id'], $provider['id'], $provider['category'], $provider['subcategory'],
            json_encode($inputs, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
            json_encode($result['response'], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
            $result['http_status'], (int) $result['success'],
        ]);
        $id = $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM query_history WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        return history_entry($stmt->fetch(), true);
    } finally {
        $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->execute([$lock]);
    }
}
