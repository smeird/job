-- Preserve every CV source for a fact when duplicate roles or imports are combined.

CREATE TABLE IF NOT EXISTS career_fact_sources (
    user_id BIGINT UNSIGNED NOT NULL,
    fact_id BIGINT UNSIGNED NOT NULL,
    source_cv_id BIGINT UNSIGNED NOT NULL,
    source_document_name VARCHAR(255) NOT NULL,
    source_excerpt TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, fact_id, source_cv_id),
    KEY career_fact_sources_cv (source_cv_id),
    CONSTRAINT career_fact_sources_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT career_fact_sources_fact_fk FOREIGN KEY (fact_id) REFERENCES career_facts(id) ON DELETE CASCADE,
    CONSTRAINT career_fact_sources_cv_fk FOREIGN KEY (source_cv_id) REFERENCES cv_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill provenance captured before multi-source fact tracking was introduced.
INSERT IGNORE INTO career_fact_sources (user_id, fact_id, source_cv_id, source_document_name, source_excerpt)
SELECT user_id, id, source_cv_id, COALESCE(source_document_name, 'Imported CV'), COALESCE(source_excerpt, fact_text)
FROM career_facts
WHERE source_cv_id IS NOT NULL;
