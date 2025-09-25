<?php

declare(strict_types=1);

namespace App\Generations;

use PDO;
use PDOException;
use RuntimeException;

use function is_resource;
use function sprintf;
use function stream_get_contents;
use function trim;

final class GenerationDownloadService
{
    private const FORMAT_DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    private const FORMAT_PDF_MIME = 'application/pdf';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{filename: string, mime_type: string, content: string}
     */
    public function fetch(int $generationId, int $userId, string $format): array
    {
        $generation = $this->findGeneration($generationId);

        if ($generation === null) {
            throw new GenerationNotFoundException('Generation not found.');
        }

        if ((int) $generation['user_id'] !== $userId) {
            throw new GenerationAccessDeniedException('You do not have access to this generation.');
        }

        switch ($format) {
            case 'md':
                return $this->fetchMarkdown($generationId);
            case 'docx':
                return $this->fetchBinary($generationId, 'docx', self::FORMAT_DOCX_MIME);
            case 'pdf':
                return $this->fetchBinary($generationId, 'pdf', self::FORMAT_PDF_MIME);
            default:
                throw new RuntimeException('Unsupported format requested.');
        }
    }

    /**
     * @return array{filename: string, mime_type: string, content: string}
     */
    private function fetchMarkdown(int $generationId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT output_text FROM generation_outputs WHERE generation_id = :generation_id '
            . 'AND output_text IS NOT NULL ORDER BY created_at DESC, id DESC LIMIT 1'
        );
        $statement->bindValue(':generation_id', $generationId, PDO::PARAM_INT);
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
            'filename' => sprintf('generation-%d.md', $generationId),
            'mime_type' => 'text/markdown; charset=utf-8',
            'content' => $markdown,
        ];
    }

    /**
     * @return array{filename: string, mime_type: string, content: string}
     */
    private function fetchBinary(int $generationId, string $extension, string $expectedMime): array
    {
        $statement = $this->pdo->prepare(
            'SELECT mime_type, content FROM generation_outputs WHERE generation_id = :generation_id '
            . 'AND mime_type = :mime_type AND content IS NOT NULL ORDER BY created_at DESC, id DESC LIMIT 1'
        );
        $statement->bindValue(':generation_id', $generationId, PDO::PARAM_INT);
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
            'filename' => sprintf('generation-%d.%s', $generationId, $extension),
            'mime_type' => $row['mime_type'] ?? $expectedMime,
            'content' => (string) $content,
        ];
    }

    /**
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
