<?php

declare(strict_types=1);

namespace App\Views;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class Renderer
{
    /** @var string */
    private $basePath;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * Handle the render operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function render(
        ResponseInterface $response,
        string $template,
        array $data = [],
        int $httpStatus = 200
    ): ResponseInterface
    {
        $templatePath = $this->basePath . DIRECTORY_SEPARATOR . $template . '.php';

        if (!file_exists($templatePath)) {
            throw new RuntimeException(sprintf('Template "%s" not found.', $template));
        }

        if (!array_key_exists('csrfToken', $data)) {
            $data['csrfToken'] = $_SESSION['csrf_token'] ?? null;
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $templatePath;
        $content = ob_get_clean();

        $response->getBody()->write($content);

        return $response
            ->withStatus($httpStatus)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
