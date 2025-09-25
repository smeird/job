<?php

declare(strict_types=1);

namespace App\Services;

use DateInterval;
use DateTimeImmutable;
use PDO;
use Ramsey\Uuid\Uuid;

class AuthService
{
    private const REGISTER_REQUEST_ACTION = 'register.request';
    private const REGISTER_VERIFY_ACTION = 'register.verify';
    private const LOGIN_REQUEST_ACTION = 'login.request';
    private const LOGIN_VERIFY_ACTION = 'login.verify';

    public function __construct(
        private readonly PDO $pdo,
        private readonly MailerInterface $mailer,
        private readonly RateLimiter $requestLimiter,
        private readonly RateLimiter $verifyLimiter
    ) {
    }

    public function initiateRegistration(string $email, string $ip): void
    {
        $email = strtolower(trim($email));
        $this->validateEmail($email);
        $this->applyRateLimiting($this->requestLimiter, $ip, $email, self::REGISTER_REQUEST_ACTION);

        if ($this->userExists($email)) {
            throw new \RuntimeException('An account with that email already exists. Please log in.');
        }

        $code = $this->generatePasscode();
        $expiresAt = (new DateTimeImmutable())->add(new DateInterval('PT10M'));

        $this->storePasscode($email, 'register', $code, $expiresAt);
        $this->logAttempt($ip, $email, self::REGISTER_REQUEST_ACTION);

        $message = <<<TEXT
        Your registration code is: {$code}

        It expires in 10 minutes. If you did not request this code, please ignore this email.
        TEXT;

        $this->mailer->send($email, 'Your job.smeird.com registration code', trim($message));
    }

    public function verifyRegistration(string $email, string $code, string $ip): array
    {
        $email = strtolower(trim($email));
        $this->validateEmail($email);
        $code = trim($code);
        $this->applyRateLimiting($this->verifyLimiter, $ip, $email, self::REGISTER_VERIFY_ACTION);

        $this->logAttempt($ip, $email, self::REGISTER_VERIFY_ACTION);
        $this->assertPasscodeValid($email, 'register', $code);
        $userId = $this->findOrCreateUser($email);

        return $this->createSession($userId);
    }

    public function initiateLogin(string $email, string $ip): void
    {
        $email = strtolower(trim($email));
        $this->validateEmail($email);
        $this->applyRateLimiting($this->requestLimiter, $ip, $email, self::LOGIN_REQUEST_ACTION);

        if (!$this->userExists($email)) {
            throw new \RuntimeException('No account was found with that email. Please register first.');
        }

        $code = $this->generatePasscode();
        $expiresAt = (new DateTimeImmutable())->add(new DateInterval('PT10M'));

        $this->storePasscode($email, 'login', $code, $expiresAt);
        $this->logAttempt($ip, $email, self::LOGIN_REQUEST_ACTION);

        $message = <<<TEXT
        Your login code is: {$code}

        It expires in 10 minutes. If you did not request this code, please ignore this email.
        TEXT;

        $this->mailer->send($email, 'Your job.smeird.com login code', trim($message));
    }

    public function verifyLogin(string $email, string $code, string $ip): array
    {
        $email = strtolower(trim($email));
        $this->validateEmail($email);
        $code = trim($code);
        $this->applyRateLimiting($this->verifyLimiter, $ip, $email, self::LOGIN_VERIFY_ACTION);

        $userId = $this->getUserIdByEmail($email);

        if ($userId === null) {
            throw new \RuntimeException('No account was found with that email.');
        }

        $this->logAttempt($ip, $email, self::LOGIN_VERIFY_ACTION);
        $this->assertPasscodeValid($email, 'login', $code);

        return $this->createSession($userId);
    }

    public function generateBackupCodes(int $userId): array
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

    public function destroySession(string $token): void
    {
        $hash = hash('sha256', $token, true);
        $statement = $this->pdo->prepare('DELETE FROM sessions WHERE token_hash = :token_hash');
        $statement->execute(['token_hash' => $hash]);
    }

    private function validateEmail(string $email): void
    {
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('Please enter a valid email address.');
        }
    }

    private function applyRateLimiting(RateLimiter $limiter, string $ip, string $email, string $action): void
    {
        if ($limiter->tooManyAttempts($ip, $email, $action)) {
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

    private function generatePasscode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function storePasscode(string $email, string $action, string $code, DateTimeImmutable $expiresAt): void
    {
        $this->pdo->prepare('DELETE FROM pending_passcodes WHERE email = :email AND action = :action')->execute([
            'email' => $email,
            'action' => $action,
        ]);

        $statement = $this->pdo->prepare('INSERT INTO pending_passcodes (email, action, code_hash, expires_at, created_at) VALUES (:email, :action, :code_hash, :expires_at, :created_at)');
        $statement->execute([
            'email' => $email,
            'action' => $action,
            'code_hash' => password_hash($code, PASSWORD_ARGON2ID),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    private function assertPasscodeValid(string $email, string $action, string $code): void
    {
        $this->pdo->prepare('DELETE FROM pending_passcodes WHERE expires_at < :now')->execute([
            'now' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $statement = $this->pdo->prepare('SELECT id, code_hash FROM pending_passcodes WHERE email = :email AND action = :action LIMIT 1');
        $statement->execute([
            'email' => $email,
            'action' => $action,
        ]);

        $row = $statement->fetch();

        if ($row === false || !password_verify($code, $row['code_hash'])) {
            throw new \RuntimeException('Invalid or expired code.');
        }

        $delete = $this->pdo->prepare('DELETE FROM pending_passcodes WHERE id = :id');
        $delete->execute(['id' => $row['id']]);
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

    private function logAttempt(string $ip, string $email, string $action): void
    {
        $this->pdo->prepare('INSERT INTO audit_logs (ip_address, email, action, created_at) VALUES (:ip, :email, :action, :created_at)')->execute([
            'ip' => $ip,
            'email' => $email,
            'action' => $action,
            'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
