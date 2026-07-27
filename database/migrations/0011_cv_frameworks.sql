-- Retain the selected, allow-listed CV structure with every immutable tailored revision.
ALTER TABLE tailored_cvs
    ADD COLUMN cv_framework ENUM('experience_led','profile_led','skills_led','hybrid') NOT NULL DEFAULT 'experience_led' AFTER tailoring_tone;
