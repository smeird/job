-- Add the professional application tracker, reusable document metadata, profile, and preferences.

CREATE TABLE IF NOT EXISTS user_profiles (
    user_id BIGINT UNSIGNED NOT NULL,
    full_name VARCHAR(255) NULL,
    phone VARCHAR(80) NULL,
    address_line_1 VARCHAR(255) NULL,
    address_line_2 VARCHAR(255) NULL,
    city VARCHAR(120) NULL,
    region VARCHAR(120) NULL,
    postal_code VARCHAR(40) NULL,
    country VARCHAR(120) NULL,
    linkedin_url VARCHAR(500) NULL,
    portfolio_url VARCHAR(500) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT user_profiles_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_preferences (
    user_id BIGINT UNSIGNED NOT NULL,
    ai_model VARCHAR(191) NOT NULL,
    theme ENUM('system', 'light', 'dark') NOT NULL DEFAULT 'system',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT user_preferences_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_applications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    job_title VARCHAR(255) NOT NULL,
    location VARCHAR(255) NULL,
    source_url VARCHAR(1000) NULL,
    status ENUM('interested','preparing','applied','interview','offer','accepted','rejected','withdrawn') NOT NULL DEFAULT 'interested',
    application_date DATE NULL,
    cv_document_id BIGINT UNSIGNED NULL,
    tailored_cv_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY job_applications_user_status (user_id, status),
    KEY job_applications_user_updated (user_id, updated_at),
    CONSTRAINT job_applications_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT job_applications_cv_fk FOREIGN KEY (cv_document_id) REFERENCES cv_documents(id) ON DELETE SET NULL,
    CONSTRAINT job_applications_tailored_fk FOREIGN KEY (tailored_cv_id) REFERENCES tailored_cvs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sent_document_emails (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    tailored_cv_id BIGINT UNSIGNED NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    included_cv TINYINT(1) NOT NULL DEFAULT 1,
    included_cover_letter TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY sent_document_emails_user_created (user_id, created_at),
    CONSTRAINT sent_document_emails_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT sent_document_emails_tailored_fk FOREIGN KEY (tailored_cv_id) REFERENCES tailored_cvs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Keep the only non-idempotent schema change atomic and last so interrupted retries remain safe.
ALTER TABLE tailored_cvs
    ADD COLUMN company_name VARCHAR(255) NULL,
    ADD COLUMN job_title VARCHAR(255) NULL,
    ADD COLUMN model_name VARCHAR(191) NULL,
    ADD COLUMN cover_letter_text MEDIUMTEXT NULL;
