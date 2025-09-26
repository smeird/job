<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\RetentionPolicyService;
use App\Views\Renderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use JsonException;
use RuntimeException;

class RetentionController
{
    /** @var Renderer */
    private $renderer;

    /** @var RetentionPolicyService */
    private $retentionPolicyService;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        Renderer $renderer,
        RetentionPolicyService $retentionPolicyService
    ) {
        $this->renderer = $renderer;
        $this->retentionPolicyService = $retentionPolicyService;
    }

    /**
     * Handle the show operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if ($user === null) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        $policy = $this->retentionPolicyService->getPolicy();
        $status = $request->getQueryParams()['status'] ?? null;

        return $this->renderer->render($response, 'retention', [
            'title' => 'Data retention',
            'subtitle' => 'Control how long sensitive records are kept before purge.',
            'fullWidth' => true,
            'navLinks' => $this->navLinks('retention'),
            'policy' => $policy,
            'allowedResources' => $this->retentionPolicyService->getAllowedResources(),
            'resourceLabels' => $this->resourceLabels(),
            'errors' => [],
            'status' => $status,
        ]);
    }

    /**
     * Handle the update operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if ($user === null) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        $data = $request->getParsedBody();
        $errors = [];
        $purgeAfterDays = 0;
        $applyTo = [];

        if (is_array($data)) {
            $purgeAfterDays = isset($data['purge_after_days']) ? (int) $data['purge_after_days'] : 0;
            $applyToRaw = $data['apply_to'] ?? [];

            if (is_string($applyToRaw)) {
                $applyTo = [$applyToRaw];
            } elseif (is_array($applyToRaw)) {
                $applyTo = array_map('strval', $applyToRaw);
            }
        }

        try {
            $this->retentionPolicyService->updatePolicy($purgeAfterDays, $applyTo);
        } catch (RuntimeException | JsonException $exception) {
            $errors[] = $exception->getMessage();
        }

        if ($errors !== []) {
            $policy = [
                'purge_after_days' => $purgeAfterDays,
                'apply_to' => $applyTo,
            ];

            return $this->renderer->render($response, 'retention', [
                'title' => 'Data retention',
                'subtitle' => 'Control how long sensitive records are kept before purge.',
                'fullWidth' => true,
                'navLinks' => $this->navLinks('retention'),
                'policy' => $policy,
                'allowedResources' => $this->retentionPolicyService->getAllowedResources(),
                'resourceLabels' => $this->resourceLabels(),
                'errors' => $errors,
                'status' => null,
            ], 422);
        }

        return $response->withHeader('Location', '/retention?status=Retention+policy+saved')->withStatus(302);
    }

    /**
     * Handle the resource labels workflow.
     *
     * This helper keeps the resource labels logic centralised for clarity and reuse.
     * @return array<string, string>
     */
    private function resourceLabels(): array
    {
        return [
            'documents' => 'Uploaded documents',
            'generation_outputs' => 'Generation outputs',
            'api_usage' => 'API usage metrics',
            'audit_logs' => 'Audit logs',
        ];
    }

    /**
     * Handle the nav links workflow.
     *
     * This helper keeps the nav links logic centralised for clarity and reuse.
     * @return array<int, array{href: string, label: string, current: bool}>
     */
    private function navLinks(string $current): array
    {
        $links = [
            'dashboard' => ['href' => '/', 'label' => 'Dashboard'],
            'documents' => ['href' => '/documents', 'label' => 'Documents'],
            'usage' => ['href' => '/usage', 'label' => 'Usage'],
            'retention' => ['href' => '/retention', 'label' => 'Retention'],
        ];

        return array_map(function ($key, $link) use ($current) {
            return [
                'href' => $link['href'],
                'label' => $link['label'],
                'current' => $key === $current,
            ];
        }, array_keys($links), $links);
    }
}
