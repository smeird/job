-- Convert password accounts to passwordless, single-use email passcodes.
ALTER TABLE users MODIFY password_hash VARCHAR(255) NULL;
UPDATE users SET password_hash = NULL WHERE password_hash IS NOT NULL;

CREATE TABLE IF NOT EXISTS login_passcodes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    challenge_hash CHAR(64) NOT NULL,
    code_hash CHAR(64) NOT NULL,
    request_ip_hash CHAR(64) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY login_passcodes_challenge_unique (challenge_hash),
    KEY login_passcodes_email_created (email, created_at),
    KEY login_passcodes_ip_created (request_ip_hash, created_at),
    KEY login_passcodes_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
