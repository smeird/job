-- Store user-defined document names and recoverable archive state without changing source files.
CREATE TABLE IF NOT EXISTS document_metadata (
    user_id BIGINT UNSIGNED NOT NULL,
    document_type ENUM('master_cv','tailored_cv') NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    display_name VARCHAR(255) NULL,
    archived_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, document_type, document_id),
    KEY document_metadata_user_archive (user_id, archived_at),
    CONSTRAINT document_metadata_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
