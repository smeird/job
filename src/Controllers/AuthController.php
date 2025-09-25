<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Views\Renderer;
use DateTimeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthController
{
    private AuthService $authService;
    private Renderer $renderer;

    public function __construct(AuthService $authService, Renderer $renderer)
    {
        $this->authService = $authService;
        $this->renderer = $renderer;
    }

    public function showRegister(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $email = $request->getQueryParams()['email'] ?? '';
        $error = $request->getQueryParams()['error'] ?? null;
        $status = $request->getQueryParams()['status'] ?? null;

        return $this->renderer->render($response, 'auth/request', [
            'title' => 'Create your account',
            'subtitle' => 'We will email you a 6-digit passcode to finish setup.',
            'actionUrl' => '/auth/register',
            'buttonLabel' => 'Send registration code',
            'email' => $email,
            'error' => $error,
            'status' => $status,
            'links' => [
                ['href' => '/auth/login', 'label' => 'Already registered? Sign in.'],
            ],
        ]);
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $email = is_array($data) ? ($data['email'] ?? '') : '';
        $ip = $this->getClientIp($request);
        $userAgent = $request->getHeaderLine('User-Agent') ?: null;

        try {
            $this->authService->initiateRegistration($email, $ip, $userAgent);
            $status = 'We sent a 6-digit code to your email.';
            $error = null;
        } catch (\Throwable $throwable) {
            $status = null;
            $error = $throwable->getMessage();
        }

        return $this->renderer->render($response, 'auth/request', [
            'title' => 'Create your account',
            'subtitle' => 'We will email you a 6-digit passcode to finish setup.',
            'actionUrl' => '/auth/register',
            'buttonLabel' => 'Send registration code',
            'email' => $email,
            'error' => $error,
            'status' => $status,
            'links' => [
                ['href' => '/auth/register/verify?email=' . urlencode($email), 'label' => 'Have a code already? Verify it here.'],
                ['href' => '/auth/login', 'label' => 'Already registered? Sign in.'],
            ],
        ]);
    }

    public function showRegisterVerify(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $email = $request->getQueryParams()['email'] ?? '';
        $error = $request->getQueryParams()['error'] ?? null;
        $status = $request->getQueryParams()['status'] ?? null;

        return $this->renderVerify($response, 'Complete your registration', '/auth/register/verify', 'Create account', $email, $error, $status);
    }

    public function registerVerify(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $email = is_array($data) ? ($data['email'] ?? '') : '';
        $code = is_array($data) ? ($data['code'] ?? '') : '';
        $ip = $this->getClientIp($request);
        $userAgent = $request->getHeaderLine('User-Agent') ?: null;

        try {
            $session = $this->authService->verifyRegistration($email, $code, $ip, $userAgent);

            return $this->redirectWithSession($response, $session['token'], $session['expires_at']);
        } catch (\Throwable $throwable) {
            return $this->renderVerify($response, 'Complete your registration', '/auth/register/verify', 'Create account', $email, $throwable->getMessage());
        }
    }

    public function showLogin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $email = $request->getQueryParams()['email'] ?? '';
        $error = $request->getQueryParams()['error'] ?? null;
        $status = $request->getQueryParams()['status'] ?? null;

        return $this->renderer->render($response, 'auth/request', [
            'title' => 'Sign in',
            'subtitle' => 'We will email a 6-digit passcode for quick sign in.',
            'actionUrl' => '/auth/login',
            'buttonLabel' => 'Send login code',
            'email' => $email,
            'error' => $error,
            'status' => $status,
            'links' => [
                ['href' => '/auth/login/verify?email=' . urlencode($email), 'label' => 'Already have a code? Verify it here.'],
                ['href' => '/auth/register', 'label' => 'Need an account? Register.'],
            ],
        ]);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $email = is_array($data) ? ($data['email'] ?? '') : '';
        $ip = $this->getClientIp($request);
        $userAgent = $request->getHeaderLine('User-Agent') ?: null;

        try {
            $this->authService->initiateLogin($email, $ip, $userAgent);
            $status = 'We sent a 6-digit code to your email.';
            $error = null;
        } catch (\Throwable $throwable) {
            $status = null;
            $error = $throwable->getMessage();
        }

        return $this->renderer->render($response, 'auth/request', [
            'title' => 'Sign in',
            'subtitle' => 'We will email a 6-digit passcode for quick sign in.',
            'actionUrl' => '/auth/login',
            'buttonLabel' => 'Send login code',
            'email' => $email,
            'error' => $error,
            'status' => $status,
            'links' => [
                ['href' => '/auth/login/verify?email=' . urlencode($email), 'label' => 'Already have a code? Verify it here.'],
                ['href' => '/auth/register', 'label' => 'Need an account? Register.'],
            ],
        ]);
    }

    public function showLoginVerify(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $email = $request->getQueryParams()['email'] ?? '';
        $error = $request->getQueryParams()['error'] ?? null;
        $status = $request->getQueryParams()['status'] ?? null;

        return $this->renderVerify($response, 'Verify and sign in', '/auth/login/verify', 'Sign in', $email, $error, $status);
    }

    public function loginVerify(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $email = is_array($data) ? ($data['email'] ?? '') : '';
        $code = is_array($data) ? ($data['code'] ?? '') : '';
        $ip = $this->getClientIp($request);
        $userAgent = $request->getHeaderLine('User-Agent') ?: null;

        try {
            $session = $this->authService->verifyLogin($email, $code, $ip, $userAgent);

            return $this->redirectWithSession($response, $session['token'], $session['expires_at']);
        } catch (\Throwable $throwable) {
            return $this->renderVerify($response, 'Verify and sign in', '/auth/login/verify', 'Sign in', $email, $throwable->getMessage());
        }
    }

    public function showBackupCodes(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if ($user === null) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        return $this->renderer->render($response, 'auth/backup-codes-request', [
            'title' => 'Backup codes',
            'subtitle' => 'Generate one-time codes for emergency access.',
            'error' => null,
        ]);
    }

    public function backupCodes(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if ($user === null) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        $codes = $this->authService->generateBackupCodes((int) $user['user_id'], $this->getClientIp($request), $request->getHeaderLine('User-Agent') ?: null);

        return $this->renderer->render($response, 'auth/backup-codes', [
            'title' => 'Backup codes generated',
            'subtitle' => 'Save these codes. They will not be shown again.',
            'codes' => $codes,
        ]);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $sessionToken = $request->getAttribute('sessionToken');
        $user = $request->getAttribute('user');
        $ip = $this->getClientIp($request);
        $userAgent = $request->getHeaderLine('User-Agent') ?: null;

        if (is_string($sessionToken)) {
            $userId = is_array($user) && isset($user['user_id']) ? (int) $user['user_id'] : null;
            $email = is_array($user) && isset($user['email']) ? (string) $user['email'] : null;
            $this->authService->destroySession($sessionToken, $ip, $userAgent, $userId, $email);
        }

        return $this->expireSessionCookie($response)->withHeader('Location', '/')->withStatus(302);
    }

    private function renderVerify(ResponseInterface $response, string $title, string $actionUrl, string $buttonLabel, string $email, ?string $error = null, ?string $status = null): ResponseInterface
    {
        return $this->renderer->render($response, 'auth/verify', [
            'title' => $title,
            'subtitle' => 'Enter the 6-digit code from your email within 10 minutes.',
            'actionUrl' => $actionUrl,
            'buttonLabel' => $buttonLabel,
            'email' => $email,
            'error' => $error,
            'status' => $status,
        ]);
    }

    private function redirectWithSession(ResponseInterface $response, string $token, DateTimeInterface $expiresAt): ResponseInterface
    {
        $cookie = $this->createSessionCookie($token, $expiresAt);

        return $response
            ->withHeader('Set-Cookie', $cookie)
            ->withHeader('Location', '/')
            ->withStatus(302);
    }

    private function expireSessionCookie(ResponseInterface $response): ResponseInterface
    {
        $expires = gmdate('D, d M Y H:i:s T', time() - 3600);
        $cookie = sprintf('job_session=deleted; Path=/; Domain=%s; Expires=%s; HttpOnly; Secure; SameSite=Lax', $this->cookieDomain(), $expires);

        return $response->withHeader('Set-Cookie', $cookie);
    }

    private function createSessionCookie(string $token, DateTimeInterface $expiresAt): string
    {
        $expires = gmdate('D, d M Y H:i:s T', $expiresAt->getTimestamp());

        return sprintf('job_session=%s; Path=/; Domain=%s; Expires=%s; HttpOnly; Secure; SameSite=Lax', rawurlencode($token), $this->cookieDomain(), $expires);
    }

    private function cookieDomain(): string
    {
        return $_ENV['APP_COOKIE_DOMAIN'] ?? getenv('APP_COOKIE_DOMAIN') ?: 'job.smeird.com';
    }

    private function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        $forwarded = $request->getHeaderLine('X-Forwarded-For');

        if (!empty($forwarded)) {
            $parts = explode(',', $forwarded);
            $candidate = trim($parts[0]);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
