-- Import into an existing MySQL 8+ or MariaDB 10.5+ database.
-- All application timestamps are UTC. Use a dedicated, least-privilege SQL account.
CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    active TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS query_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(32) NOT NULL,
    category VARCHAR(100) NOT NULL,
    subcategory VARCHAR(100) NOT NULL,
    inputs LONGTEXT NOT NULL,
    response LONGTEXT NOT NULL,
    http_status SMALLINT UNSIGNED NOT NULL,
    success TINYINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY history_owner_date (user_id, id),
    CONSTRAINT history_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    window_start BIGINT UNSIGNED NOT NULL,
    hits INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS provider_limits (
    provider VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    next_allowed_ms BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- Accounts NEVER activate automatically. A database administrator must run:
-- UPDATE users SET active = 1 WHERE email = 'person@example.org';
-- Revocation takes effect on the next protected request:
-- UPDATE users SET active = 0 WHERE email = 'person@example.org';
-- Optional scheduled cleanup (choose a retention period appropriate for your policy):
-- DELETE FROM query_history WHERE created_at < UTC_TIMESTAMP() - INTERVAL 90 DAY;
-- DELETE FROM rate_limits WHERE window_start < UNIX_TIMESTAMP() - 86400;
