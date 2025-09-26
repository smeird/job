<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Documents\DocumentRepository;
use App\Generations\GenerationRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;

final class GenerationController
{
    /** @var array<int, array{value: string, label: string}> */
    private const MODELS = [
        ['value' => 'gpt-4o-mini', 'label' => 'GPT-4o mini · Fast and affordable'],
        ['value' => 'gpt-4o', 'label' => 'GPT-4o · Highest quality'],
        ['value' => 'claude-3-5-sonnet', 'label' => 'Claude 3.5 Sonnet · Balanced reasoning'],
    ];

    /** @var GenerationRepository */
    private $generationRepository;

    /** @var DocumentRepository */
    private $documentRepository;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        GenerationRepository $generationRepository,
        DocumentRepository $documentRepository
    ) {
        $this->generationRepository = $generationRepository;
        $this->documentRepository = $documentRepository;
    }

    /**
     * Handle the create operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!is_array($user) || !isset($user['user_id'])) {
            return $this->json($response->withStatus(401), ['error' => 'Authentication required.']);
        }

        $payload = $request->getParsedBody();

        if (!is_array($payload)) {
            throw new HttpBadRequestException($request, 'Invalid request payload.');
        }

        $jobDocumentId = $this->extractInt($payload['job_document_id'] ?? null);
        $cvDocumentId = $this->extractInt($payload['cv_document_id'] ?? null);
        $model = isset($payload['model']) ? trim((string) $payload['model']) : '';
        $thinkingTime = $this->extractInt($payload['thinking_time'] ?? null);

        if ($jobDocumentId === null || $cvDocumentId === null) {
            throw new HttpBadRequestException($request, 'Both job and CV documents are required.');
        }

        if (!in_array($model, array_column(self::MODELS, 'value'), true)) {
            throw new HttpBadRequestException($request, 'Unknown model selection.');
        }

        if ($thinkingTime === null || $thinkingTime < 5 || $thinkingTime > 60) {
            throw new HttpBadRequestException($request, 'Thinking time must be between 5 and 60 seconds.');
        }

        $userId = (int) $user['user_id'];

        $jobDocument = $this->documentRepository->findForUserByType($userId, $jobDocumentId, 'job_description');
        $cvDocument = $this->documentRepository->findForUserByType($userId, $cvDocumentId, 'cv');

        if ($jobDocument === null || $cvDocument === null) {
            return $this->json($response->withStatus(422), ['error' => 'Document selection is invalid.']);
        }

        $generation = $this->generationRepository->create($userId, $jobDocument->id(), $cvDocument->id(), $model, $thinkingTime);

        return $this->json($response->withStatus(201), $generation);
    }

    /**
     * Handle the show operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!is_array($user) || !isset($user['user_id'])) {
            return $this->json($response->withStatus(401), ['error' => 'Authentication required.']);
        }

        $generationId = $this->extractInt($args['id'] ?? null);

        if ($generationId === null) {
            throw new HttpBadRequestException($request, 'Invalid generation identifier.');
        }

        $generation = $this->generationRepository->findForUser((int) $user['user_id'], $generationId);

        if ($generation === null) {
            return $this->json($response->withStatus(404), ['error' => 'Generation not found.']);
        }

        return $this->json($response, $generation);
    }

    /**
     * Handle the available models workflow.
     *
     * This helper keeps the available models logic centralised for clarity and reuse.
     * @return array<int, array{value: string, label: string}>
     */
    public static function availableModels(): array
    {
        return self::MODELS;
    }

    /**
     * Handle the extract int workflow.
     *
     * This helper keeps the extract int logic centralised for clarity and reuse.
     * @param mixed $value
     */
    private function extractInt($value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value) && (string) (int) $value === trim((string) $value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Handle the json operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function json(ResponseInterface $response, array $payload): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
