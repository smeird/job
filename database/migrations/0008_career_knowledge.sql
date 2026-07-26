-- Store a user-owned career evidence library with traceable CV provenance and gap questions.

CREATE TABLE IF NOT EXISTS career_roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    employer_name VARCHAR(255) NOT NULL,
    job_title VARCHAR(255) NOT NULL,
    location VARCHAR(255) NULL,
    start_date_text VARCHAR(80) NULL,
    end_date_text VARCHAR(80) NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    summary TEXT NULL,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY career_roles_user_order (user_id, display_order, id),
    KEY career_roles_user_employer (user_id, employer_name),
    CONSTRAINT career_roles_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS career_role_sources (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    source_cv_id BIGINT UNSIGNED NOT NULL,
    source_document_name VARCHAR(255) NOT NULL,
    source_excerpt TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id, source_cv_id),
    CONSTRAINT career_role_sources_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT career_role_sources_role_fk FOREIGN KEY (role_id) REFERENCES career_roles(id) ON DELETE CASCADE,
    CONSTRAINT career_role_sources_cv_fk FOREIGN KEY (source_cv_id) REFERENCES cv_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS career_facts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    category ENUM('achievement','responsibility','project','leadership','commercial','technical','other') NOT NULL DEFAULT 'other',
    fact_text TEXT NOT NULL,
    fact_hash CHAR(64) NOT NULL,
    source_type ENUM('cv','user') NOT NULL,
    source_cv_id BIGINT UNSIGNED NULL,
    source_document_name VARCHAR(255) NULL,
    source_excerpt TEXT NULL,
    user_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    archived_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY career_facts_user_role_hash (user_id, role_id, fact_hash),
    KEY career_facts_user_active (user_id, archived_at),
    KEY career_facts_role_category (role_id, category),
    CONSTRAINT career_facts_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT career_facts_role_fk FOREIGN KEY (role_id) REFERENCES career_roles(id) ON DELETE CASCADE,
    CONSTRAINT career_facts_source_cv_fk FOREIGN KEY (source_cv_id) REFERENCES cv_documents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS career_questions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    rationale VARCHAR(500) NULL,
    question_hash CHAR(64) NOT NULL,
    status ENUM('open','answered','dismissed') NOT NULL DEFAULT 'open',
    answered_fact_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY career_questions_user_role_hash (user_id, role_id, question_hash),
    KEY career_questions_user_status (user_id, status),
    CONSTRAINT career_questions_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT career_questions_role_fk FOREIGN KEY (role_id) REFERENCES career_roles(id) ON DELETE CASCADE,
    CONSTRAINT career_questions_answer_fk FOREIGN KEY (answered_fact_id) REFERENCES career_facts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS career_imports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    source_cv_id BIGINT UNSIGNED NOT NULL,
    model_name VARCHAR(191) NOT NULL,
    role_count INT UNSIGNED NOT NULL DEFAULT 0,
    fact_count INT UNSIGNED NOT NULL DEFAULT 0,
    question_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY career_imports_user_created (user_id, created_at),
    CONSTRAINT career_imports_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT career_imports_cv_fk FOREIGN KEY (source_cv_id) REFERENCES cv_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Retain the exact career-evidence snapshot used by each generated application pack.
ALTER TABLE tailored_cvs
    ADD COLUMN source_mode ENUM('cv','career') NOT NULL DEFAULT 'cv' AFTER source_cv_id,
    ADD COLUMN career_snapshot MEDIUMTEXT NULL AFTER source_mode;
