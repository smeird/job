<?php

declare(strict_types=1);

namespace App\Documents;

use Smalot\PdfParser\Parser;
use ZipArchive;

class DocumentPreviewer
{
    private Parser $pdfParser;

    public function __construct(?Parser $parser = null)
    {
        $this->pdfParser = $parser ?? new Parser();
    }

    public function render(Document $document): string
    {
        $content = $document->content();

        return match ($document->mimeType()) {
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => $this->renderDocx($content),
            'application/pdf' => $this->renderPdf($content),
            'text/markdown', 'text/plain' => $this->renderText($content),
            default => '',
        };
    }

    private function renderDocx(string $content): string
    {
        $resource = tmpfile();

        if ($resource === false) {
            return '';
        }

        fwrite($resource, $content);
        $meta = stream_get_meta_data($resource);
        $path = $meta['uri'] ?? null;

        if (!$path) {
            fclose($resource);

            return '';
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            fclose($resource);

            return '';
        }

        $xml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();
        fclose($resource);

        if ($xml === '') {
            return '';
        }

        $xml = preg_replace('/<w:p[^>]*>/', '', $xml);
        $xml = preg_replace('/<\/w:p>/', "\n", $xml);
        $xml = preg_replace('/<w:tab[^>]*\/>/', "\t", $xml);
        $xml = preg_replace('/<w:br[^>]*\/>/', "\n", $xml);

        $text = strip_tags($xml ?? '');

        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function renderPdf(string $content): string
    {
        try {
            $pdf = $this->pdfParser->parseContent($content);

            return $pdf->getText();
        } catch (\Throwable) {
            return '';
        }
    }

    private function renderText(string $content): string
    {
        return $content;
    }
}
