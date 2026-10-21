<?php
declare(strict_types=1);

// Edit this file on the server; never publish real credentials in version control.
return [
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'osint_framework',
        'user' => 'osint_app',
        'password' => '',
    ],
    // Keep true in production and serve the entire site over HTTPS.
    // Set false only when testing locally over HTTP.
    'secure_cookie' => true,
    'session_name' => 'osint_session',
    'session_lifetime' => 7200,
    // Run DELETE FROM query_history WHERE created_at < UTC_TIMESTAMP() - INTERVAL 90 DAY;
    // in a scheduled database job to opt into 90-day retention. No automatic deletion.
];
