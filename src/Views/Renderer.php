<?php

declare(strict_types=1);

namespace App\Views;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class Renderer
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function render(ResponseInterface $response, string $template, array $data = [], int $status = 200): ResponseInterface
    {
        $templatePath = $this->basePath . DIRECTORY_SEPARATOR . $template . '.php';

        if (!file_exists($templatePath)) {
            throw new RuntimeException(sprintf('Template "%s" not found.', $template));
        }

        if (!array_key_exists('csrfToken', $data)) {
            $data['csrfToken'] = $_SESSION['csrf_token'] ?? null;
        }

        extract($data);

        ob_start();
        include $templatePath;
        $content = ob_get_clean();

        $response->getBody()->write($content);

        return $response->withStatus($status)->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
