<?php

declare(strict_types=1);

namespace App\Conversion;

use App\DB;
use Dompdf\Dompdf;
use Dompdf\Options;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\ConverterInterface;
use League\CommonMark\Output\RenderedContentInterface;
use PDO;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\NumberFormat;
use RuntimeException;
use Throwable;

class Converter
{
    /** @var ConverterInterface */
    private $markdownConverter;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(?ConverterInterface $markdownConverter = null)
    {
        $this->markdownConverter = $markdownConverter ?? new CommonMarkConverter();
    }

    /**
     * Convert the and store into the desired format.
     *
     * Having a dedicated converter isolates formatting concerns.
     * @return array<string, array{ id: int, filename: string, format: string, mime_type: string, sha256: string, size_bytes: int }>
     */
    public function convertAndStore(int $generationId, string $markdown): array
    {
        $pdo = DB::getConnection();
        $rendered = $this->renderFormats($markdown);
        $docxBinary = $rendered['docx'];
        $pdfBinary = $rendered['pdf'];
        $markdownBinary = $this->normalizeMarkdown($markdown);

        $pdo->beginTransaction();

        try {
            $outputs = [];

            $outputs['md'] = $this->storeBinary(
                $pdo,
                $generationId,
                'md',
                'text/markdown',
                sprintf('generation-%d.md', $generationId),
                $markdownBinary
            );

            $outputs['docx'] = $this->storeBinary(
                $pdo,
                $generationId,
                'docx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                sprintf('generation-%d.docx', $generationId),
                $docxBinary
            );

            $outputs['pdf'] = $this->storeBinary(
                $pdo,
                $generationId,
                'pdf',
                'application/pdf',
                sprintf('generation-%d.pdf', $generationId),
                $pdfBinary
            );

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        return $outputs;
    }

    /**
     * Render the markdown into available binary formats without persistence.
     *
     * Exposing the conversion logic allows queue handlers to reuse the transformations when storing multiple artifacts.
     *
     * @return array<string, string>
     */
    public function renderFormats(string $markdown): array
    {
        return [
            'docx' => $this->renderFormat($markdown, 'docx'),
            'pdf' => $this->renderFormat($markdown, 'pdf'),
        ];
    }

    /**
     * Render the markdown into a single requested format.
     *
     * Centralising this helper ensures controllers can request specific binaries without converting unused formats.
     */
    public function renderFormat(string $markdown, string $format): string
    {
        $normalizedFormat = strtolower($format);

        if ($normalizedFormat === 'docx') {
            return $this->convertMarkdownToDocx($markdown);
        }

        if ($normalizedFormat === 'pdf') {
            return $this->convertMarkdownToPdf($markdown);
        }

        throw new RuntimeException('Unsupported format requested for rendering.');
    }

    /**
     * Handle the normalize markdown operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function normalizeMarkdown(string $markdown): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $markdown);

        return $normalized;
    }

    /**
     * Convert the markdown to docx into the desired format.
     *
     * Having a dedicated converter isolates formatting concerns.
     */
    private function convertMarkdownToDocx(string $markdown): string
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $phpWord->addTitleStyle(1, ['size' => 20, 'bold' => true], ['spaceAfter' => 240]);
        $phpWord->addTitleStyle(2, ['size' => 16, 'bold' => true], ['spaceAfter' => 160]);
        $phpWord->addParagraphStyle('Body', ['spaceAfter' => 240]);
        $phpWord->addParagraphStyle('Bullet', ['spaceAfter' => 120]);
        $phpWord->addNumberingStyle('Bullet', [
            'type' => 'multilevel',
            'levels' => [
                [
                    'format' => NumberFormat::BULLET,
                    'text' => '\u{2022}',
                    'left' => 360,
                    'hanging' => 360,
                ],
            ],
        ]);

        $section = $phpWord->addSection();

        $lines = preg_split('/\R/', $markdown) ?: [];
        $previousWasList = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                if (!$previousWasList) {
                    $section->addTextBreak();
                }

                $previousWasList = false;
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.*)$/', $trimmed, $matches) === 1) {
                $level = strlen($matches[1]);
                $text = $matches[2];

                if ($level === 1) {
                    $section->addTitle($text, 1);
                } elseif ($level === 2) {
                    $section->addTitle($text, 2);
                } else {
                    $section->addText($text, null, 'Body');
                }

                $previousWasList = false;
                continue;
            }

            if (preg_match('/^[-*]\s+(.*)$/', $trimmed, $matches) === 1) {
                $section->addListItem($matches[1], 0, null, 'Bullet');
                $previousWasList = true;
                continue;
            }

            $section->addText($trimmed, null, 'Body');
            $previousWasList = false;
        }

        $tempPath = $this->createTempFile('docx');

        try {
            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($tempPath);
            $contents = file_get_contents($tempPath);

            if ($contents === false) {
                throw new RuntimeException('Unable to read generated DOCX content.');
            }
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }

        return $contents;
    }

    /**
     * Convert the markdown to pdf into the desired format.
     *
     * Having a dedicated converter isolates formatting concerns.
     */
    private function convertMarkdownToPdf(string $markdown): string
    {
        if (method_exists($this->markdownConverter, 'convertToHtml')) {
            $html = (string) $this->markdownConverter->convertToHtml($markdown);
        } else {
            $converted = $this->markdownConverter->convert($markdown);
            $html = $converted instanceof RenderedContentInterface ? $converted->getContent() : (string) $converted;
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Handle the store binary workflow.
     *
     * This helper keeps the store binary logic centralised for clarity and reuse.
     * @return array{id: int, filename: string, format: string, mime_type: string, sha256: string, size_bytes: int}
     */
    private function storeBinary(
        PDO $pdo,
        int $generationId,
        string $format,
        string $mimeType,
        string $filename,
        string $binary
    ): array {
        $sha256 = hash('sha256', $binary);
        $sizeBytes = strlen($binary);

        $metadata = [
            'filename' => $filename,
            'format' => $format,
            'sha256' => $sha256,
            'size_bytes' => $sizeBytes,
        ];

        $statement = $pdo->prepare(
            'INSERT INTO generation_outputs (generation_id, mime_type, content, output_text) VALUES (:generation_id, :mime_type, :content, :output_text)'
        );

        $statement->bindValue(':generation_id', $generationId, PDO::PARAM_INT);
        $statement->bindValue(':mime_type', $mimeType);
        $statement->bindValue(':content', $binary, PDO::PARAM_LOB);
        $statement->bindValue(':output_text', json_encode($metadata, JSON_THROW_ON_ERROR));
        $statement->execute();

        $id = (int) $pdo->lastInsertId();

        return [
            'id' => $id,
            'filename' => $filename,
            'format' => $format,
            'mime_type' => $mimeType,
            'sha256' => $sha256,
            'size_bytes' => $sizeBytes,
        ];
    }

    /**
     * Create the temp file instance.
     *
     * This method standardises construction so other code can rely on it.
     */
    private function createTempFile(string $extension): string
    {
        $basePath = tempnam(sys_get_temp_dir(), 'conv_');

        if ($basePath === false) {
            throw new RuntimeException('Unable to create a temporary file.');
        }

        $targetPath = sprintf('%s.%s', $basePath, $extension);

        if (!@rename($basePath, $targetPath)) {
            @unlink($basePath);
            throw new RuntimeException('Unable to prepare a temporary file for writing.');
        }

        return $targetPath;
    }
}
