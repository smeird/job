<?php

declare(strict_types=1);

namespace App\Services;

use DateInterval;
use DateTimeImmutable;
use PDO;
use ParagonIE\ConstantTime\Base32;
use Ramsey\Uuid\Uuid;

class AuthService
{
    private const REGISTER_REQUEST_ACTION = 'register.request';
    private const REGISTER_VERIFY_ACTION = 'register.verify';
    private const LOGIN_REQUEST_ACTION = 'login.request';
    private const LOGIN_VERIFY_ACTION = 'login.verify';

    private const OTP_PERIOD_SECONDS = 600;
    private const OTP_DIGITS = 6;
    private const OTP_ALGORITHM = 'sha1';
    private const OTP_ISSUER = 'job.smeird.com';

    private PDO $pdo;
    private RateLimiter $requestLimiter;
    private RateLimiter $verifyLimiter;
    private AuditLogger $auditLogger;

    public function __construct(
        PDO $pdo,
        RateLimiter $requestLimiter,
        RateLimiter $verifyLimiter,
        AuditLogger $auditLogger
    ) {
        $this->pdo = $pdo;
        $this->requestLimiter = $requestLimiter;
        $this->verifyLimiter = $verifyLimiter;
        $this->auditLogger = $auditLogger;
    }

    /**
     * @return array{code: string, secret: string, uri: string, expires_at: DateTimeImmutable}
     */
    public function initiateRegistration(string $email, string $ip, ?string $userAgent = null): array
    {
        $email = strtolower(trim($email));
        $this->validateEmail($email);
        $this->applyRateLimiting($this->requestLimiter, $ip, $email, self::REGISTER_REQUEST_ACTION, $userAgent);

        if ($this->userExists($email)) {
            $this->auditLogger->log('auth.register.denied', ['reason' => 'account_exists'], null, $email, $ip, $userAgent);
            throw new \RuntimeException('An account with that email already exists. Please log in.');
        }

        $challenge = $this->createTotpChallenge($email);
        $expiresAt = (new DateTimeImmutable())->add(new DateInterval('PT10M'));

        $this->storePasscode($email, 'register', $challenge, $expiresAt);
        $this->logAttempt($this->requestLimiter, $ip, $email, self::REGISTER_REQUEST_ACTION, $userAgent, ['stage' => 'request']);

        $this->auditLogger->log('auth.register.requested', ['status' => 'qr_generated'], null, $email, $ip, $userAgent);

        return [
            'code' => $challenge['code'],
            'secret' => $challenge['secret'],
            'uri' => $challenge['uri'],
            'expires_at' => $expiresAt,
        ];
    }

    public function verifyRegistration(string $email, string $code, string $ip, ?string $userAgent = null): array
    {
        $email = strtolower(trim($email));
        $this->validateEmail($email);
        $code = trim($code);
        $this->applyRateLimiting($this->verifyLimiter, $ip, $email, self::REGISTER_VERIFY_ACTION, $userAgent);

        $this->logAttempt($this->verifyLimiter, $ip, $email, self::REGISTER_VERIFY_ACTION, $userAgent, ['stage' => 'verify']);

        try {
            $this->assertPasscodeValid($email, 'register', $code);
        } catch (\RuntimeException $exception) {
            $this->auditLogger->log('auth.register.verify_failed', ['reason' => $exception->getMessage()], null, $email, $ip, $userAgent);

            throw $exception;
        }

        $userId = $this->findOrCreateUser($email);

        $session = $this->createSession($userId);

        $this->auditLogger->log('auth.register.completed', [
            'session_expires_at' => $session['expires_at']->format('c'),
        ], $userId, $email, $ip, $userAgent);

        return $session;
    }

    /**
     * @return array{code: string, secret: string, uri: string, expires_at: DateTimeImmutable}
     */
    public function initiateLogin(string $email, string $ip, ?string $userAgent = null): array
    {
        $email = strtolower(trim($email));
        $this->validateEmail($email);
        $this->applyRateLimiting($this->requestLimiter, $ip, $email, self::LOGIN_REQUEST_ACTION, $userAgent);

        if (!$this->userExists($email)) {
            $this->auditLogger->log('auth.login.denied', ['reason' => 'account_missing'], null, $email, $ip, $userAgent);
            throw new \RuntimeException('No account was found with that email. Please register first.');
        }

        $challenge = $this->createTotpChallenge($email);
        $expiresAt = (new DateTimeImmutable())->add(new DateInterval('PT10M'));

        $this->storePasscode($email, 'login', $challenge, $expiresAt);
        $this->logAttempt($this->requestLimiter, $ip, $email, self::LOGIN_REQUEST_ACTION, $userAgent, ['stage' => 'request']);

        $this->auditLogger->log('auth.login.requested', ['status' => 'qr_generated'], null, $email, $ip, $userAgent);

        return [
            'code' => $challenge['code'],
            'secret' => $challenge['secret'],
            'uri' => $challenge['uri'],
            'expires_at' => $expiresAt,
        ];
    }

    public function verifyLogin(string $email, string $code, string $ip, ?string $userAgent = null): array
    {
        $email = strtolower(trim($email));
        $this->validateEmail($email);
        $code = trim($code);
        $this->applyRateLimiting($this->verifyLimiter, $ip, $email, self::LOGIN_VERIFY_ACTION, $userAgent);

        $userId = $this->getUserIdByEmail($email);

        if ($userId === null) {
            $this->auditLogger->log('auth.login.denied', ['reason' => 'account_missing'], null, $email, $ip, $userAgent);
            throw new \RuntimeException('No account was found with that email.');
        }

        $this->logAttempt($this->verifyLimiter, $ip, $email, self::LOGIN_VERIFY_ACTION, $userAgent, ['stage' => 'verify']);

        try {
            $this->assertPasscodeValid($email, 'login', $code);
        } catch (\RuntimeException $exception) {
            $this->auditLogger->log('auth.login.verify_failed', ['reason' => $exception->getMessage()], $userId, $email, $ip, $userAgent);

            throw $exception;
        }

        $session = $this->createSession($userId);

        $this->auditLogger->log('auth.login.success', [
            'session_expires_at' => $session['expires_at']->format('c'),
        ], $userId, $email, $ip, $userAgent);

        return $session;
    }

    public function generateBackupCodes(int $userId, ?string $ip = null, ?string $userAgent = null): array
    {
        $this->pdo->prepare('DELETE FROM backup_codes WHERE user_id = :user_id')->execute(['user_id' => $userId]);

        $codes = [];
        $stmt = $this->pdo->prepare('INSERT INTO backup_codes (user_id, code_hash, created_at) VALUES (:user_id, :code_hash, :created_at)');
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        for ($i = 0; $i < 10; $i++) {
            $code = strtoupper(bin2hex(random_bytes(4)));
            $hash = password_hash($code, PASSWORD_ARGON2ID);
            $stmt->execute([
                'user_id' => $userId,
                'code_hash' => $hash,
                'created_at' => $now,
            ]);
            $codes[] = $code;
        }

        $this->auditLogger->log('auth.backup_codes.generated', ['count' => count($codes)], $userId, null, $ip, $userAgent);

        return $codes;
    }

    public function authenticateWithSession(?string $token): ?array
    {
        if ($token === null || $token === '') {
            return null;
        }

        $hash = hash('sha256', $token, true);

        $statement = $this->pdo->prepare('SELECT sessions.id, sessions.user_id, users.email FROM sessions INNER JOIN users ON users.id = sessions.user_id WHERE sessions.token_hash = :token_hash AND sessions.expires_at > :now LIMIT 1');
        $statement->execute([
            'token_hash' => $hash,
            'now' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $session = $statement->fetch();

        if ($session === false) {
            return null;
        }

        return $session;
    }

    public function touchSession(string $token): void
    {
        $hash = hash('sha256', $token, true);

        $statement = $this->pdo->prepare('UPDATE sessions SET expires_at = :expires_at WHERE token_hash = :token_hash');
        $statement->execute([
            'token_hash' => $hash,
            'expires_at' => (new DateTimeImmutable())->add(new DateInterval('P30D'))->format('Y-m-d H:i:s'),
        ]);
    }

    public function destroySession(string $token, ?string $ip = null, ?string $userAgent = null, ?int $userId = null, ?string $email = null): void
    {
        $hash = hash('sha256', $token, true);
        $statement = $this->pdo->prepare('DELETE FROM sessions WHERE token_hash = :token_hash');
        $statement->execute(['token_hash' => $hash]);

        $this->auditLogger->log('auth.logout', [], $userId, $email, $ip, $userAgent);
    }

    private function validateEmail(string $email): void
    {
        if ($email === '' || mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('Please enter a valid email address.');
        }
    }

    private function applyRateLimiting(
        RateLimiter $limiter,
        string $ip,
        string $email,
        string $action,
        ?string $userAgent = null
    ): void {
        if ($limiter->tooManyAttempts($ip, $email, $action)) {
            $this->auditLogger->log('security.auth.rate_limited', [
                'action' => $action,
            ], null, $email, $ip, $userAgent);

            throw new \RuntimeException('Too many attempts. Please try again later.');
        }
    }

    private function userExists(string $email): bool
    {
        $statement = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);

        return $statement->fetchColumn() !== false;
    }

    private function findOrCreateUser(string $email): int
    {
        $existingId = $this->getUserIdByEmail($email);

        if ($existingId !== null) {
            return $existingId;
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $statement = $this->pdo->prepare('INSERT INTO users (email, created_at, updated_at) VALUES (:email, :created_at, :updated_at)');
        $statement->execute([
            'email' => $email,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function getUserIdByEmail(string $email): ?int
    {
        $statement = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * @return array{code: string, secret: string, uri: string, period: int, digits: int}
     */
    private function createTotpChallenge(string $email): array
    {
        $secret = Base32::encodeUpper(random_bytes(20));
        $period = self::OTP_PERIOD_SECONDS;
        $digits = self::OTP_DIGITS;
        $code = $this->calculateTotp($secret, $period, $digits, self::OTP_ALGORITHM, time());
        $uri = $this->buildTotpUri($secret, $email, $period, $digits);

        return [
            'code' => $code,
            'secret' => $secret,
            'uri' => $uri,
            'period' => $period,
            'digits' => $digits,
        ];
    }

    /**
     * @param array{code: string, secret: string, uri: string, period: int, digits: int} $challenge
     */
    private function storePasscode(string $email, string $action, array $challenge, DateTimeImmutable $expiresAt): void
    {
        $this->pdo->prepare('DELETE FROM pending_passcodes WHERE email = :email AND action = :action')->execute([
            'email' => $email,
            'action' => $action,
        ]);

        $statement = $this->pdo->prepare('INSERT INTO pending_passcodes (email, action, code_hash, totp_secret, period_seconds, digits, expires_at, created_at) VALUES (:email, :action, :code_hash, :totp_secret, :period_seconds, :digits, :expires_at, :created_at)');
        $statement->execute([
            'email' => $email,
            'action' => $action,
            'code_hash' => password_hash($challenge['code'], PASSWORD_ARGON2ID),
            'totp_secret' => $challenge['secret'],
            'period_seconds' => $challenge['period'],
            'digits' => $challenge['digits'],
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    private function assertPasscodeValid(string $email, string $action, string $code): void
    {
        $this->pdo->prepare('DELETE FROM pending_passcodes WHERE expires_at < :now')->execute([
            'now' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $statement = $this->pdo->prepare('SELECT id, code_hash, totp_secret, period_seconds, digits FROM pending_passcodes WHERE email = :email AND action = :action LIMIT 1');
        $statement->execute([
            'email' => $email,
            'action' => $action,
        ]);

        $row = $statement->fetch();

        if ($row === false) {
            throw new \RuntimeException('Invalid or expired code.');
        }

        $isValid = false;

        if (isset($row['totp_secret']) && is_string($row['totp_secret']) && $row['totp_secret'] !== '') {
            $period = isset($row['period_seconds']) ? (int) $row['period_seconds'] : self::OTP_PERIOD_SECONDS;
            $digits = isset($row['digits']) ? (int) $row['digits'] : self::OTP_DIGITS;

            if ($this->verifyTotpCode($row['totp_secret'], $code, $period, $digits)) {
                $isValid = true;
            }
        }

        if (!$isValid && isset($row['code_hash']) && password_verify($code, $row['code_hash'])) {
            $isValid = true;
        }

        if (!$isValid) {
            throw new \RuntimeException('Invalid or expired code.');
        }

        $delete = $this->pdo->prepare('DELETE FROM pending_passcodes WHERE id = :id');
        $delete->execute(['id' => $row['id']]);
    }

    private function verifyTotpCode(string $secret, string $code, int $period, int $digits): bool
    {
        $now = time();

        foreach ([-1, 0, 1] as $window) {
            $timestamp = $now + ($window * $period);
            $expected = $this->calculateTotp($secret, $period, $digits, self::OTP_ALGORITHM, $timestamp);

            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    private function calculateTotp(string $secret, int $period, int $digits, string $algorithm, int $timestamp): string
    {
        $key = Base32::decodeUpper($secret);
        $counter = intdiv($timestamp, $period);
        $binaryCounter = pack('N*', ($counter >> 32) & 0xffffffff, $counter & 0xffffffff);
        $hash = hash_hmac($algorithm, $binaryCounter, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0f;
        $segment = substr($hash, $offset, 4);
        $value = unpack('N', $segment)[1] & 0x7fffffff;
        $modulo = 10 ** $digits;
        $code = $value % $modulo;

        return str_pad((string) $code, $digits, '0', STR_PAD_LEFT);
    }

    private function buildTotpUri(string $secret, string $email, int $period, int $digits): string
    {
        $issuer = self::OTP_ISSUER;
        $label = $issuer;

        if ($email !== '') {
            $label .= ':' . $email;
        }

        $params = [
            'secret' => $secret,
            'issuer' => $issuer,
            'period' => $period,
            'digits' => $digits,
            'algorithm' => strtoupper(self::OTP_ALGORITHM),
        ];

        return sprintf(
            'otpauth://totp/%s?%s',
            rawurlencode($label),
            http_build_query($params, '', '&', PHP_QUERY_RFC3986)
        );
    }

    private function createSession(int $userId): array
    {
        $token = str_replace('-', '', Uuid::uuid4()->toString()) . bin2hex(random_bytes(16));
        $hash = hash('sha256', $token, true);
        $now = new DateTimeImmutable();
        $expiresAt = $now->add(new DateInterval('P30D'));

        $statement = $this->pdo->prepare('INSERT INTO sessions (user_id, token_hash, created_at, expires_at) VALUES (:user_id, :token_hash, :created_at, :expires_at)');
        $statement->execute([
            'user_id' => $userId,
            'token_hash' => $hash,
            'created_at' => $now->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    private function logAttempt(
        RateLimiter $limiter,
        string $ip,
        string $email,
        string $action,
        ?string $userAgent = null,
        array $details = []
    ): void {
        $limiter->hit($ip, $email, $action, $userAgent, $details);
    }
}
