<?php

declare(strict_types=1);

namespace App\Controllers;

use App\AI\ModelCatalogService;
use App\Settings\SiteSettingsRepository;
use App\Views\Renderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

/**
 * ModelSettingsController manages the shared OpenAI defaults and model catalogue.
 */
final class ModelSettingsController
{
    /** @var Renderer */
    private $renderer;

    /** @var ModelCatalogService */
    private $modelCatalog;

    /** @var SiteSettingsRepository */
    private $settingsRepository;

    /**
     * Construct the settings screen with its catalogue and persistence dependencies.
     */
    public function __construct(
        Renderer $renderer,
        ModelCatalogService $modelCatalog,
        SiteSettingsRepository $settingsRepository
    ) {
        $this->renderer = $renderer;
        $this->modelCatalog = $modelCatalog;
        $this->settingsRepository = $settingsRepository;
    }

    /**
     * Render current OpenAI model choices for an authenticated user.
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!is_array($user) || !isset($user['user_id'])) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        return $this->render($request, $response, $this->modelCatalog->models());
    }

    /**
     * Save the default planning and drafting models after validating them against the live catalogue.
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!is_array($user) || !isset($user['user_id'])) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        $payload = $request->getParsedBody();
        $planModel = is_array($payload) && isset($payload['plan_model'])
            ? trim((string) $payload['plan_model'])
            : '';
        $draftModel = is_array($payload) && isset($payload['draft_model'])
            ? trim((string) $payload['draft_model'])
            : '';

        if (!$this->modelCatalog->isSelectable($planModel) || !$this->modelCatalog->isSelectable($draftModel)) {
            return $this->render(
                $request,
                $response->withStatus(422),
                $this->modelCatalog->models(),
                'Choose models from the currently available catalogue.'
            );
        }

        try {
            $this->settingsRepository->saveValue('openai_model_plan', $planModel);
            $this->settingsRepository->saveValue('openai_model_draft', $draftModel);
        } catch (RuntimeException $exception) {
            return $this->render(
                $request,
                $response->withStatus(500),
                $this->modelCatalog->models(),
                'The model defaults could not be saved.'
            );
        }

        return $response
            ->withHeader('Location', '/settings/models?status=' . rawurlencode('Model defaults saved.'))
            ->withStatus(302);
    }

    /**
     * Force a new OpenAI models request and return to the settings page with a clear outcome.
     */
    public function refresh(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!is_array($user) || !isset($user['user_id'])) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        try {
            $models = $this->modelCatalog->models(true);
            $status = $this->modelCatalog->lastRefreshSucceeded()
                ? 'Model catalogue refreshed from OpenAI.'
                : 'OpenAI did not return a compatible catalogue; cached or built-in models remain active.';
        } catch (Throwable $exception) {
            $status = 'OpenAI could not be reached; the existing catalogue remains active.';
        }

        return $response
            ->withHeader('Location', '/settings/models?status=' . rawurlencode($status))
            ->withStatus(302);
    }

    /**
     * Build the shared settings view payload, including submitted values after validation errors.
     *
     * @param array<int, array{value: string, label: string, description: string}> $models
     */
    private function render(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $models,
        ?string $error = null
    ): ResponseInterface {
        $query = $request->getQueryParams();
        $payload = $request->getParsedBody();
        $configuredPlan = $this->settingsRepository->findValue('openai_model_plan');
        $configuredDraft = $this->settingsRepository->findValue('openai_model_draft');

        return $this->renderer->render($response, 'settings-models', [
            'title' => 'Model settings',
            'subtitle' => 'Manage OpenAI models used for CV analysis and drafting.',
            'fullWidth' => true,
            'models' => $models,
            'planModel' => is_array($payload) && isset($payload['plan_model'])
                ? (string) $payload['plan_model']
                : ($configuredPlan ?? $this->modelCatalog->defaultModel()),
            'draftModel' => is_array($payload) && isset($payload['draft_model'])
                ? (string) $payload['draft_model']
                : ($configuredDraft ?? $this->modelCatalog->defaultModel()),
            'refreshedAt' => $this->modelCatalog->refreshedAt(),
            'status' => isset($query['status']) ? (string) $query['status'] : null,
            'error' => $error,
            'navLinks' => $this->navLinks(),
        ]);
    }

    /**
     * Provide the authenticated workspace navigation with settings marked as active.
     *
     * @return array<int, array{href: string, label: string, current: bool}>
     */
    private function navLinks(): array
    {
        return [
            ['href' => '/', 'label' => 'Dashboard', 'current' => false],
            ['href' => '/tailor', 'label' => 'Tailor', 'current' => false],
            ['href' => '/documents', 'label' => 'Documents', 'current' => false],
            ['href' => '/applications', 'label' => 'Applications', 'current' => false],
            ['href' => '/usage', 'label' => 'Usage', 'current' => false],
            ['href' => '/settings/models', 'label' => 'Settings', 'current' => true],
        ];
    }
}
