<?php

declare(strict_types=1);

namespace App\Documents;

use ZipArchive;

class DocumentValidator
{
    private const MAX_FILE_SIZE = 1048576; // 1 MiB

    /**
     * @return array{mime: string, size: int}
     */
    public function validate(string $filename, string $content, ?string $temporaryPath): array
    {
        $size = strlen($content);

        if ($size > self::MAX_FILE_SIZE) {
            throw new DocumentValidationException('The uploaded file exceeds the maximum allowed size.', 413);
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($extension, ['docx', 'pdf', 'md', 'txt'], true)) {
            throw new DocumentValidationException('Unsupported file type.');
        }

        switch ($extension) {
            case 'docx':
                $mime = $this->validateDocx($content, $temporaryPath);
                break;
            case 'pdf':
                $mime = $this->validatePdf($content);
                break;
            case 'md':
                $mime = $this->validateTextLike($content, 'text/markdown');
                break;
            default:
                $mime = $this->validateTextLike($content, 'text/plain');
                break;
        }

        return [
            'mime' => $mime,
            'size' => $size,
        ];
    }

    private function validateDocx(string $content, ?string $temporaryPath): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new DocumentValidationException('DOCX validation is unavailable because the PHP zip extension is missing.');
        }

        if (!str_starts_with($content, "PK")) {
            throw new DocumentValidationException('The DOCX archive is malformed.');
        }

        $path = $temporaryPath;

        if (!$path || !is_string($path) || !file_exists($path)) {
            $resource = tmpfile();

            if ($resource === false) {
                throw new DocumentValidationException('Unable to create a temporary file for validation.');
            }

            fwrite($resource, $content);
            $meta = stream_get_meta_data($resource);
            $path = $meta['uri'] ?? null;
        } else {
            $resource = null;
        }

        if (!$path) {
            if (isset($resource)) {
                fclose($resource);
            }

            throw new DocumentValidationException('Unable to inspect DOCX archive.');
        }

        $zip = new ZipArchive();

        $openResult = $zip->open($path);

        if ($openResult !== true) {
            if (isset($resource)) {
                fclose($resource);
            }

            throw new DocumentValidationException('Unable to open DOCX archive.');
        }

        if ($zip->locateName('word/vbaProject.bin', ZipArchive::FL_NODIR) !== false) {
            $zip->close();

            if (isset($resource)) {
                fclose($resource);
            }

            throw new DocumentValidationException('DOCX files containing macros are not allowed.');
        }

        $contentTypes = $zip->getFromName('[Content_Types].xml');

        if ($contentTypes !== false && str_contains($contentTypes, 'macroEnabled')) {
            $zip->close();

            if (isset($resource)) {
                fclose($resource);
            }

            throw new DocumentValidationException('DOCX files containing macros are not allowed.');
        }

        if ($zip->getFromName('word/document.xml') === false) {
            $zip->close();

            if (isset($resource)) {
                fclose($resource);
            }

            throw new DocumentValidationException('The DOCX archive is missing required components.');
        }

        $zip->close();

        if (isset($resource)) {
            fclose($resource);
        }

        return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    private function validatePdf(string $content): string
    {
        if (!str_starts_with($content, "%PDF")) {
            throw new DocumentValidationException('Invalid PDF file.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            throw new DocumentValidationException('Unable to inspect file type.');
        }

        $mime = finfo_buffer($finfo, $content);
        finfo_close($finfo);

        if ($mime !== 'application/pdf') {
            throw new DocumentValidationException('Invalid PDF file.');
        }

        return 'application/pdf';
    }

    private function validateTextLike(string $content, string $expectedMime): string
    {
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $content)) {
            throw new DocumentValidationException('Text files must not contain binary data.');
        }

        return $expectedMime;
    }
}
