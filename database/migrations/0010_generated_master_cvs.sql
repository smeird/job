ALTER TABLE cv_documents
  ADD COLUMN source_mode ENUM('upload','career') NOT NULL DEFAULT 'upload' AFTER version_number,
  ADD COLUMN career_snapshot MEDIUMTEXT NULL AFTER source_mode,
  ADD COLUMN generation_focus ENUM('balanced','management','technical','impact') NULL AFTER career_snapshot,
  ADD COLUMN generation_summary TEXT NULL AFTER generation_focus,
  ADD COLUMN profile_snapshot TEXT NULL AFTER generation_summary;
