<?php

declare(strict_types=1);

namespace App\Extraction;

use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Central orchestrator for extracting text content from uploaded documents.
 */
class Extractor
{
    /** @var ReaderInterface[] */
    private array $readers = [];
    private PDO $connection;

    public function __construct(PDO $connection, iterable $readers = [])
    {
        $this->connection = $connection;

        foreach ($readers as $reader) {
            $this->addReader($reader);
        }

        if ($this->readers === []) {
            $this->registerDefaultReaders();
        }
    }

    /**
     * Add a reader to the extractor registry.
     */
    public function addReader(ReaderInterface $reader): void
    {
        $this->readers[] = $reader;
    }

    /**
     * Extracts the textual representation of an uploaded document and updates the persistence layer.
     *
     * @throws ExtractionException when extraction fails for any reason.
     */
    public function handleUpload(int $documentId, string $filePath, string $originalFilename, ?string $mimeType = null): void
    {
        $resolvedMime = $mimeType ?: $this->detectMimeType($filePath);
        $extension = strtolower((string) pathinfo($originalFilename !== '' ? $originalFilename : $filePath, PATHINFO_EXTENSION));

        try {
            $reader = $this->resolveReader($resolvedMime, $extension);
            $rawText = $reader->extract($filePath);
            $normalised = $this->normaliseText($rawText);

            $this->storeExtractedText($documentId, $normalised);
        } catch (Throwable $throwable) {
            $this->recordFailure($documentId, $throwable->getMessage());

            throw $throwable instanceof ExtractionException
                ? $throwable
                : new ExtractionException('Failed to extract document text.', 0, $throwable);
        }
    }

    private function detectMimeType(string $filePath): ?string
    {
        if (!is_file($filePath)) {
            return null;
        }

        $mime = mime_content_type($filePath);

        return $mime === false ? null : $mime;
    }

    private function normaliseText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\r\n?|\n/u', "\n", $text);
        $text = preg_replace('/[\t ]+\n/u', "\n", (string) $text);
        $text = preg_replace("/\n{3,}/u", "\n\n", (string) $text);

        return trim((string) $text);
    }

    private function registerDefaultReaders(): void
    {
        $this->addReader(new DocxReader());
        $this->addReader(new PdfReader());
        $this->addReader(new TextReader(['txt', 'md']));
    }

    /**
     * @throws ExtractionException
     */
    private function resolveReader(?string $mimeType, string $extension): ReaderInterface
    {
        foreach ($this->readers as $reader) {
            if ($reader->supports($mimeType, $extension)) {
                return $reader;
            }
        }

        throw new ExtractionException(sprintf('No reader registered for %s.', $extension ?: 'the provided file'));
    }

    private function storeExtractedText(int $documentId, string $text): void
    {
        $statement = $this->connection->prepare('UPDATE documents SET extracted_text = :text WHERE id = :id');
        $statement->execute([':text' => $text, ':id' => $documentId]);
    }

    private function recordFailure(int $documentId, string $reason): void
    {
        $details = mb_substr($reason, 0, 2000);

        try {
            $statement = $this->connection->prepare(
                'INSERT INTO audit_logs (document_id, action, details, created_at) VALUES (:document_id, :action, :details, :created_at)'
            );
            $statement->execute([
                ':document_id' => $documentId,
                ':action' => 'document_extraction_failed',
                ':details' => $details,
                ':created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            error_log('Unable to persist extraction failure to audit_logs: ' . $exception->getMessage());
        }
    }
}

interface ReaderInterface
{
    public function supports(?string $mimeType, string $extension): bool;

    /**
     * @throws ExtractionException
     */
    public function extract(string $filePath): string;
}

final class DocxReader implements ReaderInterface
{
    public function supports(?string $mimeType, string $extension): bool
    {
        return $extension === 'docx'
            || $mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    public function extract(string $filePath): string
    {
        if (!class_exists('PhpOffice\\PhpWord\\IOFactory')) {
            throw new ExtractionException('PHPWord library is required for DOCX extraction.');
        }

        try {
            /** @var \PhpOffice\PhpWord\PhpWord $phpWord */
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath, 'Word2007');
            $html = $this->convertToHtml($phpWord);

            return $this->convertHtmlToText($html);
        } catch (Throwable $throwable) {
            throw new ExtractionException('Unable to extract text from DOCX file.', 0, $throwable);
        }
    }

    private function convertToHtml(\PhpOffice\PhpWord\PhpWord $phpWord): string
    {
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');

        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }

    private function convertHtmlToText(string $html): string
    {
        $normalized = preg_replace('/<br\s*\/?\s*>/i', "\n", $html);
        $normalized = preg_replace('/<\/(p|div)>/i', "\n\n", (string) $normalized);
        $normalized = preg_replace('/<li[^>]*>/i', "\n• ", (string) $normalized);
        $normalized = preg_replace('/<\/(li|ul|ol)>/i', "\n", (string) $normalized);

        $text = strip_tags((string) $normalized);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/u", "\n\n", (string) $text);

        return trim((string) $text);
    }
}

final class PdfReader implements ReaderInterface
{
    public function supports(?string $mimeType, string $extension): bool
    {
        return $extension === 'pdf' || $mimeType === 'application/pdf';
    }

    public function extract(string $filePath): string
    {
        $parserException = null;

        if (class_exists('Smalot\\PdfParser\\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($filePath);

                return trim($pdf->getText());
            } catch (Throwable $throwable) {
                $parserException = $throwable;
            }
        }

        try {
            return $this->extractWithPdftotext($filePath);
        } catch (Throwable $throwable) {
            if ($parserException !== null) {
                throw new ExtractionException('Unable to extract text from PDF (parser and pdftotext both failed).', 0, $parserException);
            }

            throw new ExtractionException('Unable to extract text from PDF.', 0, $throwable);
        }
    }

    private function extractWithPdftotext(string $filePath): string
    {
        $command = sprintf('pdftotext -layout %s -', escapeshellarg($filePath));
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, null, null, ['bypass_shell' => false]);

        if (!\is_resource($process)) {
            throw new ExtractionException('pdftotext binary is not available.');
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $message = trim((string) $stderr);
            $reason = $message !== '' ? $message : 'pdftotext exited with status ' . $exitCode;

            throw new ExtractionException($reason);
        }

        return trim((string) $stdout);
    }
}

final class TextReader implements ReaderInterface
{
    /** @var string[] */
    private array $extensions;

    /**
     * @param string[] $extensions
     */
    public function __construct(array $extensions)
    {
        $this->extensions = $extensions;
    }

    public function supports(?string $mimeType, string $extension): bool
    {
        $extension = strtolower($extension);

        if ($extension !== '') {
            return in_array($extension, $this->extensions, true);
        }

        if ($mimeType === null) {
            return false;
        }

        return str_starts_with($mimeType, 'text/');
    }

    public function extract(string $filePath): string
    {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            throw new ExtractionException('Unable to read text file.');
        }

        $encoding = $this->detectEncoding($contents);
        $normalised = $encoding === 'UTF-8'
            ? $contents
            : mb_convert_encoding($contents, 'UTF-8', $encoding);

        $normalised = preg_replace('/^\xEF\xBB\xBF/u', '', (string) $normalised);
        $normalised = str_replace(["\r\n", "\r"], "\n", (string) $normalised);

        return trim((string) $normalised);
    }

    private function detectEncoding(string $contents): string
    {
        $encoding = mb_detect_encoding($contents, ['UTF-8', 'UTF-16LE', 'UTF-16BE', 'ISO-8859-1', 'WINDOWS-1252'], true);

        return $encoding ?: 'UTF-8';
    }
}

class ExtractionException extends RuntimeException
{
}
