<?php

declare(strict_types=1);

namespace App\Conversion;

use App\DB;
use DateTimeImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\ConverterInterface;
use League\CommonMark\Output\RenderedContentInterface;
use PDO;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\SimpleType\NumberFormat;
use RuntimeException;
use Throwable;

class Converter
{
    /** @var ConverterInterface */
    private $markdownConverter;

    /** @var string|null */
    private $pdfFooterLine;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(?ConverterInterface $markdownConverter = null)
    {
        $this->markdownConverter = $markdownConverter ?? new CommonMarkConverter();
        $this->pdfFooterLine = null;
    }

    /**
     * Configure the optional footer line injected into generated PDF files.
     *
     * Centralising the state allows controllers to supply the caller specific
     * contact details that should appear at the bottom of each rendered page.
     */
    public function setPdfFooterLine(?string $footerLine): void
    {
        $this->pdfFooterLine = $footerLine !== null && trim($footerLine) !== ''
            ? $footerLine
            : null;
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
     * Render a cover letter specific PDF.
     *
     * The helper wraps the base PDF conversion with styling and formatting tweaks that
     * are specific to cover letters, such as injecting the current date.
     */
    public function renderCoverLetterPdf(string $markdown): string
    {
        return $this->convertMarkdownToPdf($markdown, true);
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
        $this->assertDocxDependencies();

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

        $html = $this->renderMarkdownToHtml($markdown);

        Html::addHtml($section, sprintf('<div class="markdown-doc">%s</div>', $html), false, false);

        $tempPath = $this->createTempFile('docx');

        try {
            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($tempPath);
            $contents = file_get_contents($tempPath);

            if ($contents === false) {
                throw new RuntimeException('Unable to read generated DOCX content.');
            }

            $this->assertValidDocxBinary($contents);
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }

        return $contents;
    }

    /**
     * Ensure the environment can generate DOCX files using PhpWord.
     *
     * Centralising the dependency check guarantees downloads fail fast when the
     * required library or extensions are unavailable, preventing the delivery of
     * corrupt Word documents to end users.
     */
    private function assertDocxDependencies(): void
    {
        if (!class_exists(PhpWord::class) || !class_exists(IOFactory::class)) {
            throw new RuntimeException('DOCX generation requires the PhpWord library.');
        }

        if (!class_exists('ZipArchive') && !extension_loaded('zip')) {
            throw new RuntimeException('DOCX generation requires the PHP zip extension.');
        }
    }

    /**
     * Confirm the generated binary resembles a valid DOCX archive.
     *
     * Verifying the ZIP signature protects downstream workflows from serving
     * malformed files that Microsoft Word cannot open.
     */
    private function assertValidDocxBinary(string $binary): void
    {
        if ($binary === '') {
            throw new RuntimeException('Generated DOCX file is empty.');
        }

        if (substr($binary, 0, 2) !== 'PK') {
            throw new RuntimeException('Generated DOCX content is not a valid archive.');
        }
    }

    /**
     * Convert the markdown to pdf into the desired format.
     *
     * Having a dedicated converter isolates formatting concerns while allowing
     * callers to optionally inject contextual elements such as the current date
     * for cover letters.
     */
    private function convertMarkdownToPdf(string $markdown, bool $includeDate = false): string
    {
        $html = $this->renderMarkdownToHtml($markdown);

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $styles = <<<'CSS'
@page {
    margin: 2.5cm 2.5cm 2.5cm 2.5cm;
}

body {
    margin: 0;
    font-family: 'Helvetica Neue', 'Calibri', 'Arial', sans-serif;
    font-size: 11pt;
    line-height: 1.6;
    color: #111827;
    background-color: #ffffff;
    padding-bottom: 3.5cm;
}

.letter {
    max-width: 17cm;
    margin: 0 auto;
    padding: 0 0 3.5cm;
    display: flex;
    flex-direction: column;
    gap: 12pt;
}

.letter-date {
    font-size: 11pt;
    color: #4b5563;
}

.letter-content p {
    margin: 0 0 12pt;
    text-align: justify;
    word-break: break-word;
}

.letter-content p:last-child {
    margin-bottom: 0;
}

.letter-content h1,
.letter-content h2,
.letter-content h3,
.letter-content h4,
.letter-content h5,
.letter-content h6 {
    font-family: 'Helvetica Neue', 'Calibri', 'Arial', sans-serif;
    font-weight: bold;
    color: #111827;
    margin: 0 0 8pt;
}

.letter-content h1 {
    font-size: 20pt;
}

.letter-content h2 {
    font-size: 16pt;
}

.letter-content h3,
.letter-content h4,
.letter-content h5,
.letter-content h6 {
    font-size: 13pt;
}

.letter-content ul,
.letter-content ol {
    margin: 0 0 6pt 1.1cm;
    padding-left: 0;
}

.letter-content li {
    margin: 0 0 6pt;
}

.letter-content li:last-child {
    margin-bottom: 0;
}

.letter-content li p {
    margin: 0;
}

.letter-content table th,
.letter-content table td {
    border: 1px solid #e5e7eb;
    padding: 6pt 8pt;
    text-align: left;
    vertical-align: top;
}

.letter-footer {
    position: fixed;
    left: 1cm;
    right: 1cm;
    bottom: 0.5cm;
    padding: 6pt 0;
    border-top: 1px solid #e5e7eb;
    font-size: 9pt;
    color: #6b7280;
    text-align: center;
    line-height: 1.4;
    letter-spacing: 0.2pt;
    background-color: #ffffff;
    display: flex;
    justify-content: center;
    align-items: center;
    white-space: nowrap;
}
CSS;

        $dateMarkup = '';

        if ($includeDate) {
            $formattedDate = (new DateTimeImmutable())->format('j F Y');
            $escapedDate = htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8');
            $dateMarkup = sprintf('<p class="letter-date">%s</p>', $escapedDate);
        }

        $footerMarkup = '';

        if ($this->pdfFooterLine !== null) {
            $escapedFooter = htmlspecialchars($this->pdfFooterLine, ENT_QUOTES, 'UTF-8');
            $footerMarkup = sprintf('<footer class="letter-footer">%s</footer>', $escapedFooter);
        }

        $template = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
{$styles}
</style>
</head>
<body>
<main class="letter">
{$dateMarkup}
<section class="letter-content">
{$html}
</section>
</main>
{$footerMarkup}
</body>
</html>
HTML;

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($template);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Render the markdown into HTML for downstream conversions.
     *
     * Centralising the markdown to HTML transformation keeps DOCX and PDF
     * outputs consistent by ensuring both rely on the same CommonMark
     * conversion pipeline before format specific styling is applied.
     */
    private function renderMarkdownToHtml(string $markdown): string
    {
        if (method_exists($this->markdownConverter, 'convertToHtml')) {
            return (string) $this->markdownConverter->convertToHtml($markdown);
        }

        $converted = $this->markdownConverter->convert($markdown);

        return $converted instanceof RenderedContentInterface
            ? $converted->getContent()
            : (string) $converted;
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
