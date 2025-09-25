<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Views\Renderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HomeController
{
    public function __construct(private readonly Renderer $renderer)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if ($user !== null) {
            return $this->renderer->render($response, 'dashboard', [
                'title' => 'Dashboard',
                'subtitle' => 'Keep growing your career with confidence.',
                'email' => $user['email'],
            ]);
        }

        return $this->renderer->render($response, 'home', [
            'title' => 'job.smeird.com',
        ]);
    }
}
