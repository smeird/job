<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Generations\GenerationStreamPoller;
use App\Generations\GenerationStreamRepository;
use GuzzleHttp\Psr7\PumpStream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;

class GenerationStreamController
{
    /**
     * Handle the invoke operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'] ?? null;

        if (!is_string($id) || !ctype_digit($id)) {
            throw new HttpNotFoundException($request, 'Generation not found.');
        }

        $generationId = (int) $id;
        $repository = new GenerationStreamRepository();
        $snapshot = $repository->fetchSnapshot($generationId);

        if ($snapshot === null) {
            throw new HttpNotFoundException($request, 'Generation not found.');
        }

        $poller = new GenerationStreamPoller($repository, $generationId, 1, 15, 300, $snapshot);

        $stream = new PumpStream(function (int $length) use ($poller) {
            static $buffer = '';

            if ($buffer === '') {
                $chunk = $poller->nextChunk();

                if ($chunk === null) {
                    return false;
                }

                $buffer = $chunk;
            }

            $emit = substr($buffer, 0, $length);
            $buffer = substr($buffer, strlen($emit));

            return $emit;
        });

        return $response
            ->withHeader('Content-Type', 'text/event-stream; charset=utf-8')
            ->withHeader('Cache-Control', 'no-cache, no-transform')
            ->withHeader('Connection', 'keep-alive')
            ->withBody($stream);
    }
}
