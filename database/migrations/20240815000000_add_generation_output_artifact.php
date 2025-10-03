<?php

declare(strict_types=1);

return [
    'id' => '20240815000000_add_generation_output_artifact',
    'up' => [
        "ALTER TABLE generation_outputs ADD COLUMN IF NOT EXISTS artifact VARCHAR(64) NOT NULL DEFAULT 'cv' AFTER generation_id",
        "UPDATE generation_outputs SET artifact = 'cv' WHERE artifact IS NULL OR artifact = ''",
        'ALTER TABLE generation_outputs ADD INDEX idx_generation_outputs_artifact (artifact)',
    ],
    'down' => [
        'ALTER TABLE generation_outputs DROP INDEX idx_generation_outputs_artifact',
        'ALTER TABLE generation_outputs DROP COLUMN artifact',
    ],
];
