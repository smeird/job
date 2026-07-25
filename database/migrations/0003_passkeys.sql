-- Replace email passcodes with phishing-resistant WebAuthn passkeys.
DROP TABLE IF EXISTS login_passcodes;

ALTER TABLE users ADD COLUMN webauthn_user_id VARBINARY(64) NULL;
ALTER TABLE users ADD UNIQUE KEY users_webauthn_user_id_unique (webauthn_user_id);

CREATE TABLE IF NOT EXISTS webauthn_credentials (
    credential_id VARCHAR(512) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    credential_public_key BLOB NOT NULL,
    signature_counter BIGINT UNSIGNED NOT NULL DEFAULT 0,
    transports VARCHAR(255) NULL,
    device_type VARCHAR(32) NOT NULL,
    backed_up TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    PRIMARY KEY (credential_id),
    KEY webauthn_credentials_user_id (user_id),
    CONSTRAINT webauthn_credentials_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webauthn_challenges (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_hash CHAR(64) NOT NULL,
    ceremony ENUM('registration', 'authentication') NOT NULL,
    challenge VARCHAR(512) NOT NULL,
    email VARCHAR(255) NULL,
    user_id BIGINT UNSIGNED NULL,
    user_handle VARBINARY(64) NULL,
    request_ip_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY webauthn_challenges_token_unique (token_hash),
    KEY webauthn_challenges_expiry (expires_at),
    KEY webauthn_challenges_ip_created (request_ip_hash, created_at),
    CONSTRAINT webauthn_challenges_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
