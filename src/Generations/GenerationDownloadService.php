<?php

declare(strict_types=1);

namespace App\Generations;

use PDO;
use PDOException;
use RuntimeException;

use function in_array;
use function is_resource;
use function sprintf;
use function stream_get_contents;
use function str_replace;
use function strtolower;
use function trim;

final class GenerationDownloadService
{
    private const FORMAT_DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    private const FORMAT_PDF_MIME = 'application/pdf';
    private const ARTIFACT_CV = 'cv';
    private const ARTIFACT_COVER_LETTER = 'cover_letter';

    /** @var PDO */
    private $pdo;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Handle the fetch workflow.
     *
     * This helper keeps the fetch logic centralised for clarity and reuse.
     * @return array{filename: string, mime_type: string, content: string}
     */
    public function fetch(int $generationId, int $userId, string $artifact, string $format): array
    {
        $generation = $this->findGeneration($generationId);

        if ($generation === null) {
            throw new GenerationNotFoundException('Generation not found.');
        }

        if ((int) $generation['user_id'] !== $userId) {
            throw new GenerationAccessDeniedException('You do not have access to this generation.');
        }

        $artifactKey = strtolower(trim($artifact));

        if (!in_array($artifactKey, [self::ARTIFACT_CV, self::ARTIFACT_COVER_LETTER], true)) {
            throw new RuntimeException('Unsupported artifact requested.');
        }

        switch ($format) {
            case 'md':
                return $this->fetchMarkdown($generationId, $artifactKey);
            case 'docx':
                return $this->fetchBinary($generationId, $artifactKey, 'docx', self::FORMAT_DOCX_MIME);
            case 'pdf':
                return $this->fetchBinary($generationId, $artifactKey, 'pdf', self::FORMAT_PDF_MIME);
            default:
                throw new RuntimeException('Unsupported format requested.');
        }
    }

    /**
     * Determine which output formats are available for the supplied generation.
     *
     * Centralising the lookup keeps controllers from duplicating database checks
     * while ensuring the UI only exposes download links that will succeed.
     *
     * @return array<string, array<int, string>> Map of artifact to list of formats.
     */
    public function availableFormats(int $generationId): array
    {
        $availability = [];
        $artifacts = [self::ARTIFACT_CV, self::ARTIFACT_COVER_LETTER];

        foreach ($artifacts as $artifact) {
            $formats = [];

            if ($this->hasMarkdownOutput($generationId, $artifact)) {
                $formats[] = 'md';
            }

            if ($this->hasBinaryOutput($generationId, $artifact, self::FORMAT_DOCX_MIME)) {
                $formats[] = 'docx';
            }

            if ($this->hasBinaryOutput($generationId, $artifact, self::FORMAT_PDF_MIME)) {
                $formats[] = 'pdf';
            }

            if ($formats !== []) {
                $availability[$artifact] = $formats;
            }
        }

        return $availability;
    }

    /**
     * Fetch the markdown from its provider.
     *
     * Centralised fetching makes upstream integrations easier to evolve.
     * @return array{filename: string, mime_type: string, content: string}
     */
    private function fetchMarkdown(int $generationId, string $artifact): array
    {
        $statement = $this->pdo->prepare(
            'SELECT output_text FROM generation_outputs WHERE generation_id = :generation_id '
            . 'AND artifact = :artifact AND mime_type = :mime_type AND output_text IS NOT NULL '
            . 'ORDER BY created_at DESC, id DESC LIMIT 1'
        );
        $statement->bindValue(':generation_id', $generationId, PDO::PARAM_INT);
        $statement->bindValue(':artifact', $artifact);
        $statement->bindValue(':mime_type', 'text/markdown');
        $statement->execute();

        $row = $statement->fetch();

        if ($row === false) {
            throw new GenerationOutputUnavailableException('Markdown output is not available for this generation.');
        }

        $markdown = trim((string) $row['output_text']);

        if ($markdown === '') {
            throw new GenerationOutputUnavailableException('Markdown output is empty for this generation.');
        }

        return [
            'filename' => $this->buildFilename($generationId, $artifact, 'md'),
            'mime_type' => 'text/markdown; charset=utf-8',
            'content' => $markdown,
        ];
    }

    /**
     * Fetch the binary from its provider.
     *
     * Centralised fetching makes upstream integrations easier to evolve.
     * @return array{filename: string, mime_type: string, content: string}
     */
    private function fetchBinary(int $generationId, string $artifact, string $extension, string $expectedMime): array
    {
        $statement = $this->pdo->prepare(
            'SELECT mime_type, content FROM generation_outputs WHERE generation_id = :generation_id '
            . 'AND artifact = :artifact AND mime_type = :mime_type AND content IS NOT NULL '
            . 'ORDER BY created_at DESC, id DESC LIMIT 1'
        );
        $statement->bindValue(':generation_id', $generationId, PDO::PARAM_INT);
        $statement->bindValue(':artifact', $artifact);
        $statement->bindValue(':mime_type', $expectedMime);
        $statement->execute();

        $row = $statement->fetch();

        if ($row === false) {
            throw new GenerationOutputUnavailableException('Requested format is not available for this generation.');
        }

        $rawContent = $row['content'];

        if (is_resource($rawContent)) {
            $content = stream_get_contents($rawContent);
        } else {
            $content = (string) $rawContent;
        }

        if ($content === '' || $content === false) {
            throw new GenerationOutputUnavailableException('Stored file content is empty.');
        }

        return [
            'filename' => $this->buildFilename($generationId, $artifact, $extension),
            'mime_type' => $row['mime_type'] ?? $expectedMime,
            'content' => (string) $content,
        ];
    }

    /**
     * Confirm whether the generation stores a markdown variant.
     *
     * The helper mirrors fetchMarkdown without streaming the content so we can
     * expose download buttons only when the stored draft is usable.
     */
    private function hasMarkdownOutput(int $generationId, string $artifact): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT output_text FROM generation_outputs WHERE generation_id = :generation_id '
            . 'AND artifact = :artifact AND mime_type = :mime_type AND output_text IS NOT NULL '
            . 'ORDER BY created_at DESC, id DESC LIMIT 1'
        );
        $statement->bindValue(':generation_id', $generationId, PDO::PARAM_INT);
        $statement->bindValue(':artifact', $artifact);
        $statement->bindValue(':mime_type', 'text/markdown');
        $statement->execute();

        $row = $statement->fetch();

        if ($row === false) {
            return false;
        }

        $markdown = trim((string) $row['output_text']);

        return $markdown !== '';
    }

    /**
     * Confirm whether the generation stores the desired binary variant.
     *
     * Checking availability up-front avoids triggering download errors when the
     * requested format has not been generated by the background worker.
     */
    private function hasBinaryOutput(int $generationId, string $artifact, string $expectedMime): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM generation_outputs WHERE generation_id = :generation_id '
            . 'AND artifact = :artifact AND mime_type = :mime_type AND content IS NOT NULL '
            . 'ORDER BY created_at DESC, id DESC LIMIT 1'
        );
        $statement->bindValue(':generation_id', $generationId, PDO::PARAM_INT);
        $statement->bindValue(':artifact', $artifact);
        $statement->bindValue(':mime_type', $expectedMime);
        $statement->execute();

        $row = $statement->fetch();

        return $row !== false;
    }

    /**
     * Construct a descriptive filename for the generated artifact and format.
     */
    private function buildFilename(int $generationId, string $artifact, string $extension): string
    {
        $slug = $this->artifactSlug($artifact);

        return sprintf('generation-%d-%s.%s', $generationId, $slug, $extension);
    }

    /**
     * Produce a filesystem-friendly slug for the artifact identifier.
     */
    private function artifactSlug(string $artifact): string
    {
        if ($artifact === self::ARTIFACT_COVER_LETTER) {
            return 'cover-letter';
        }

        if ($artifact === self::ARTIFACT_CV) {
            return 'cv';
        }

        return str_replace('_', '-', trim($artifact));
    }

    /**
     * Handle the find generation workflow.
     *
     * This helper keeps the find generation logic centralised for clarity and reuse.
     * @return array{id: int, user_id: int}|null
     */
    private function findGeneration(int $generationId): ?array
    {
        try {
            $statement = $this->pdo->prepare('SELECT id, user_id FROM generations WHERE id = :id LIMIT 1');
            $statement->bindValue(':id', $generationId, PDO::PARAM_INT);
            $statement->execute();
        } catch (PDOException $exception) {
            throw new RuntimeException('Failed to query generation.', 0, $exception);
        }

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
        ];
    }
}
