<?php

declare(strict_types=1);

namespace App\Documents;

use DateTimeImmutable;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

class DocumentService
{
    /** @var DocumentRepository */
    private $repository;

    /** @var DocumentValidator */
    private $validator;

    public function __construct(
        DocumentRepository $repository,
        DocumentValidator $validator,
    ) {
        $this->repository = $repository;
        $this->validator = $validator;
    }

    public function storeUploadedDocument(UploadedFileInterface $uploadedFile, int $userId, string $documentType): Document
    {
        $error = $uploadedFile->getError();

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new DocumentValidationException('The uploaded file exceeds the maximum allowed size.', 413);
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload failed.');
        }

        $clientFilename = $uploadedFile->getClientFilename();

        if ($clientFilename === null || $clientFilename === '') {
            throw new RuntimeException('A file name is required.');
        }

        $stream = $uploadedFile->getStream();
        $temporaryPath = $stream->getMetadata('uri');

        $stream->rewind();
        $hashContext = hash_init('sha256');
        $buffer = '';

        while (!$stream->eof()) {
            $chunk = $stream->read(65536);

            if ($chunk === '') {
                break;
            }

            $buffer .= $chunk;
            hash_update($hashContext, $chunk);
        }

        $sha256 = hash_final($hashContext);

        $stream->close();

        try {
            $validation = $this->validator->validate($clientFilename, $buffer, is_string($temporaryPath) ? $temporaryPath : null);

            $document = new Document(
                null,
                $userId,
                $documentType,
                $clientFilename,
                $validation['mime'],
                $validation['size'],
                $sha256,
                $buffer,
                new DateTimeImmutable(),
            );

            return $this->repository->save($document);
        } finally {
            if (is_string($temporaryPath) && !str_starts_with($temporaryPath, 'php://') && file_exists($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    public function find(int $id): ?Document
    {
        return $this->repository->find($id);
    }
}
