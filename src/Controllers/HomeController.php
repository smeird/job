<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Documents\DocumentRepository;
use App\Generations\GenerationRepository;
use App\Views\Renderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HomeController
{
    /** @var Renderer */
    private $renderer;

    /** @var DocumentRepository */
    private $documentRepository;

    /** @var GenerationRepository */
    private $generationRepository;

    public function __construct(
        Renderer $renderer,
        DocumentRepository $documentRepository,
        GenerationRepository $generationRepository
    ) {
        $this->renderer = $renderer;
        $this->documentRepository = $documentRepository;
        $this->generationRepository = $generationRepository;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if ($user !== null) {
            $userId = (int) $user['user_id'];

            return $this->renderer->render($response, 'dashboard', [
                'title' => 'Dashboard',
                'subtitle' => 'Keep growing your career with confidence.',
                'email' => $user['email'],
                'jobDocuments' => array_map(
                    static function ($document) {
                        return [
                            'id' => $document->id(),
                            'filename' => $document->filename(),
                            'created_at' => $document->createdAt()->format('Y-m-d H:i'),
                        ];
                    },
                    $this->documentRepository->listForUserAndType($userId, 'job_description')
                ),
                'cvDocuments' => array_map(
                    static function ($document) {
                        return [
                            'id' => $document->id(),
                            'filename' => $document->filename(),
                            'created_at' => $document->createdAt()->format('Y-m-d H:i'),
                        ];
                    },
                    $this->documentRepository->listForUserAndType($userId, 'cv')
                ),
                'generations' => $this->generationRepository->listForUser($userId),
                'modelOptions' => GenerationController::availableModels(),
            ]);
        }

        return $this->renderer->render($response, 'home', [
            'title' => 'job.smeird.com',
        ]);
    }
}
