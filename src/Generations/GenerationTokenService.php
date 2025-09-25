<?php

declare(strict_types=1);

namespace App\Generations;

use DateInterval;
use DateTimeImmutable;
use RuntimeException;

use function base64_decode;
use function base64_encode;
use function ctype_digit;
use function explode;
use function hash_equals;
use function hash_hmac;
use function implode;
use function is_string;
use function rtrim;
use function sprintf;
use function str_repeat;
use function strtr;
use function strlen;
use function strtolower;
use function trim;

final class GenerationTokenService
{
    public function __construct(private readonly string $secret, private readonly int $ttlSeconds = 300)
    {
        if ($secret === '') {
            throw new RuntimeException('Download token secret must be configured.');
        }

        if ($ttlSeconds <= 0) {
            throw new RuntimeException('Token TTL must be positive.');
        }
    }

    public function getTtl(): int
    {
        return $this->ttlSeconds;
    }

    public function createToken(int $userId, int $generationId, string $format, ?DateTimeImmutable $now = null): string
    {
        $now ??= new DateTimeImmutable();
        $expiresAt = $now->add(new DateInterval(sprintf('PT%dS', $this->ttlSeconds)))->getTimestamp();

        $normalizedFormat = strtolower(trim($format));

        $data = implode(':', [
            (string) $userId,
            (string) $generationId,
            $normalizedFormat,
            (string) $expiresAt,
        ]);

        $signature = hash_hmac('sha256', $data, $this->secret, true);

        return $this->encode($data) . '.' . $this->encode($signature);
    }

    /**
     * @return array{user_id: int, generation_id: int, format: string, expires_at: int}|null
     */
    public function validateToken(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return null;
        }

        [$encodedData, $encodedSignature] = $parts;
        $data = $this->decode($encodedData);
        $signature = $this->decode($encodedSignature);

        if ($data === null || $signature === null) {
            return null;
        }

        $expectedSignature = hash_hmac('sha256', $data, $this->secret, true);

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $segments = explode(':', $data);

        if (count($segments) !== 4) {
            return null;
        }

        [$userId, $generationId, $format, $expiresAt] = $segments;

        if (!ctype_digit($userId) || !ctype_digit($generationId) || !ctype_digit($expiresAt)) {
            return null;
        }

        return [
            'user_id' => (int) $userId,
            'generation_id' => (int) $generationId,
            'format' => strtolower(trim($format)),
            'expires_at' => (int) $expiresAt,
        ];
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): ?string
    {
        $remainder = strlen($value) % 4;

        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : null;
    }
}
