-- Preserve every re-tailoring result as a comparable, user-scoped application-pack revision.
ALTER TABLE tailored_cvs
    ADD COLUMN parent_tailored_cv_id BIGINT UNSIGNED NULL,
    ADD COLUMN revision_group_key VARCHAR(64) NULL,
    ADD COLUMN revision_number INT UNSIGNED NOT NULL DEFAULT 1,
    ADD COLUMN tailoring_focus ENUM('balanced','management','technical','impact') NOT NULL DEFAULT 'balanced',
    ADD COLUMN tailoring_tone ENUM('professional','formal','concise','approachable') NOT NULL DEFAULT 'professional',
    ADD COLUMN tailoring_notes VARCHAR(500) NULL,
    ADD KEY tailored_cvs_parent_revision (parent_tailored_cv_id),
    ADD KEY tailored_cvs_user_revision_group (user_id,revision_group_key,revision_number),
    ADD UNIQUE KEY tailored_cvs_user_revision_version (user_id,revision_group_key,revision_number),
    ADD CONSTRAINT tailored_cvs_parent_revision_fk FOREIGN KEY (parent_tailored_cv_id) REFERENCES tailored_cvs(id) ON DELETE SET NULL;
