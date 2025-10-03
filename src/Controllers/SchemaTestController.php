<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DatabaseSchemaVerifier;
use App\Views\Renderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SchemaTestController
{
    /** @var Renderer */
    private $renderer;

    /** @var DatabaseSchemaVerifier */
    private $schemaVerifier;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(Renderer $renderer, DatabaseSchemaVerifier $schemaVerifier)
    {
        $this->renderer = $renderer;
        $this->schemaVerifier = $schemaVerifier;
    }

    /**
     * Display the schema verification report.
     *
     * Rendering the results through a controller keeps routing concerns and presentation logic tidy.
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if ($user === null) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        $report = $this->schemaVerifier->verify();

        return $this->renderer->render($response, 'schema-test', [
            'title' => 'Database schema test',
            'subtitle' => 'Verify production tables without leaving the dashboard.',
            'report' => $report,
        ]);
    }
}
