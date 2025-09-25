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
    public function __construct(
        private readonly Renderer $renderer,
        private readonly RetentionPolicyService $retentionPolicyService
    ) {
    }

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
            'policy' => $policy,
            'allowedResources' => $this->retentionPolicyService->getAllowedResources(),
            'resourceLabels' => $this->resourceLabels(),
            'errors' => [],
            'status' => $status,
        ]);
    }

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
}
