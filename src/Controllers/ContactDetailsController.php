<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contacts\ContactDetailsService;
use App\Views\Renderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function is_array;
use function rawurlencode;
use function trim;

/**
 * ContactDetailsController manages the form used to store reusable address and
 * contact information for cover letters.
 */
final class ContactDetailsController
{
    /** @var Renderer */
    private $renderer;

    /** @var ContactDetailsService */
    private $contactDetailsService;

    /**
     * Construct the controller with its rendering and service dependencies.
     */
    public function __construct(Renderer $renderer, ContactDetailsService $contactDetailsService)
    {
        $this->renderer = $renderer;
        $this->contactDetailsService = $contactDetailsService;
    }

    /**
     * Display the contact details form with any existing values pre-filled.
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!is_array($user) || !isset($user['user_id'])) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        $userId = (int) $user['user_id'];
        $details = $this->contactDetailsService->getContactDetails($userId);
        $status = $request->getQueryParams()['status'] ?? null;

        $oldInput = [
            'address' => $details['address'] ?? '',
            'phone' => $details['phone'] ?? '',
            'email' => $details['email'] ?? '',
        ];

        return $this->renderer->render($response, 'contact-details', [
            'title' => 'Contact details',
            'subtitle' => 'Store the address and contact details you want on your cover letters.',
            'fullWidth' => true,
            'navLinks' => $this->navLinks('contact'),
            'details' => $details,
            'errors' => [],
            'status' => $status,
            'oldInput' => $oldInput,
        ]);
    }

    /**
     * Persist the submitted contact details after validating the payload.
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!is_array($user) || !isset($user['user_id'])) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        $userId = (int) $user['user_id'];
        $data = $request->getParsedBody();
        $address = '';
        $phone = '';
        $email = '';

        if (is_array($data)) {
            $address = isset($data['address']) ? trim((string) $data['address']) : '';
            $phone = isset($data['phone']) ? trim((string) $data['phone']) : '';
            $email = isset($data['email']) ? trim((string) $data['email']) : '';
        }

        $errors = $this->contactDetailsService->validate($address, $phone, $email);

        if ($errors !== []) {
            $saved = $this->contactDetailsService->getContactDetails($userId);

            return $this->renderer->render($response->withStatus(422), 'contact-details', [
                'title' => 'Contact details',
                'subtitle' => 'Store the address and contact details you want on your cover letters.',
                'fullWidth' => true,
                'navLinks' => $this->navLinks('contact'),
                'details' => $saved,
                'errors' => $errors,
                'status' => null,
                'oldInput' => [
                    'address' => $address,
                    'phone' => $phone,
                    'email' => $email,
                ],
            ]);
        }

        try {
            $this->contactDetailsService->saveContactDetails(
                $userId,
                $address,
                $phone !== '' ? $phone : null,
                $email !== '' ? $email : null
            );
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();
            $saved = $this->contactDetailsService->getContactDetails($userId);

            return $this->renderer->render($response->withStatus(500), 'contact-details', [
                'title' => 'Contact details',
                'subtitle' => 'Store the address and contact details you want on your cover letters.',
                'fullWidth' => true,
                'navLinks' => $this->navLinks('contact'),
                'details' => $saved,
                'errors' => $errors,
                'status' => null,
                'oldInput' => [
                    'address' => $address,
                    'phone' => $phone,
                    'email' => $email,
                ],
            ]);
        }

        $message = rawurlencode('Saved your contact details for cover letters.');

        return $response->withHeader('Location', '/profile/contact-details?status=' . $message)->withStatus(302);
    }

    /**
     * Build the navigation items with the correct active state applied.
     *
     * @return array<int, array{href: string, label: string, current: bool}>
     */
    private function navLinks(string $current): array
    {
        $links = [
            'dashboard' => ['href' => '/', 'label' => 'Dashboard'],
            'tailor' => ['href' => '/tailor', 'label' => 'Tailor CV & letter'],
            'documents' => ['href' => '/documents', 'label' => 'Documents'],
            'applications' => ['href' => '/applications', 'label' => 'Applications'],
            'contact' => ['href' => '/profile/contact-details', 'label' => 'Contact details'],
            'usage' => ['href' => '/usage', 'label' => 'Usage'],
            'retention' => ['href' => '/retention', 'label' => 'Retention'],
        ];

        return array_map(static function ($key, $link) use ($current) {
            return [
                'href' => $link['href'],
                'label' => $link['label'],
                'current' => $key === $current,
            ];
        }, array_keys($links), $links);
    }
}
