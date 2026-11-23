<?php

declare(strict_types=1);

namespace BITCORE\Infrastructure\Auth;

use BITCORE\Infrastructure\Auth\Contracts\TokenManagerInterface;
use BITCORE\Infrastructure\Auth\TokenPayload;
use InvalidArgumentException;
use RuntimeException;

require_once __DIR__ . '/TokenPayload.php';
require_once __DIR__ . '/contracts/TokenManagerInterface.php';

/**
 * JWT Token Manager implementation using HMAC-SHA256 signed JWTs.
 *
 * Implements strict header validation, base64url decoding, expiration checks,
 * revocation support, and per-token cryptographic JWT IDs.
 *
 * @since 1.0.0
 * @package BITCORE\Infrastructure\Auth
 * @version     1.0.0
 */
class JwtTokenManager implements TokenManagerInterface {

    /** @var string The secret key for token signing */
    private string $secretKey;

    /** @var string The algorithm for token signing */
    private string $algorithm = 'HS256';

    /** @var array<string, true> Revoked tokens cache */
    private array $revokedTokens = [];

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param string $secretKey The secret key for token signing.
     */
    public function __construct(string $secretKey) {
        if (empty($secretKey)) {
            throw new InvalidArgumentException('Secret key cannot be empty');
        }
        $this->secretKey = $secretKey;
    }

    /**
     * Generate a new token for the given payload.
     *
     * @since 1.0.0
     * @param array<string, mixed> $payload The data to encode in the token.
     * @param int                  $ttl Time-to-live in seconds.
     * @return string The generated token.
     */
    public function generate(array $payload, int $ttl = 3600): string {
        if (empty($payload)) {
            throw new InvalidArgumentException('Payload cannot be empty');
        }

        $issuedAt = time();
        $expiresAt = $issuedAt + $ttl;

        $tokenData = [
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'jti' => bin2hex(random_bytes(16)),
            'data' => $payload,
        ];

        $header = json_encode(['typ' => 'JWT', 'alg' => $this->algorithm]);
        $body = json_encode($tokenData);

        $base64Header = $this->base64UrlEncode($header);
        $base64Payload = $this->base64UrlEncode($body);

        $signature = hash_hmac('sha256', "$base64Header.$base64Payload", $this->secretKey, true);
        $base64Signature = $this->base64UrlEncode($signature);

        return "$base64Header.$base64Payload.$base64Signature";
    }

    /**
     * Validate a token and return its decoded payload.
     *
     * @since 1.0.0
     * @param string $token The token to validate.
     * @return TokenPayload The decoded token payload.
     * @throws InvalidArgumentException If token is invalid.
     * @throws RuntimeException If token has expired or been revoked.
     */
    public function validate(string $token): TokenPayload {
        if (empty($token)) {
            throw new InvalidArgumentException('Token cannot be empty');
        }

        if (isset($this->revokedTokens[$token])) {
            throw new RuntimeException('Token has been revoked');
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Invalid token format');
        }

        [$base64Header, $base64Payload, $base64Signature] = $parts;

        $header = json_decode($this->base64UrlDecode($base64Header), true);
        $payload = json_decode($this->base64UrlDecode($base64Payload), true);

        if (!is_array($header) || !is_array($payload)) {
            throw new InvalidArgumentException('Invalid token encoding');
        }

        if (($header['typ'] ?? null) !== 'JWT' || ($header['alg'] ?? null) !== $this->algorithm) {
            throw new RuntimeException('Invalid token header');
        }

        // Verify signature
        $expectedSignature = hash_hmac('sha256', "$base64Header.$base64Payload", $this->secretKey, true);
        $expectedSignature = $this->base64UrlEncode($expectedSignature);

        if (!hash_equals($expectedSignature, $base64Signature)) {
            throw new RuntimeException('Invalid token signature');
        }

        if (!isset($payload['iat'], $payload['exp'], $payload['jti'], $payload['data'])) {
            throw new InvalidArgumentException('Invalid token payload');
        }

        if (!is_int($payload['iat']) || !is_int($payload['exp'])) {
            throw new InvalidArgumentException('Invalid token timestamps');
        }

        if (!is_string($payload['jti']) || !preg_match('/^[a-f0-9]{32}$/', $payload['jti'])) {
            throw new InvalidArgumentException('Invalid token id');
        }

        if (!is_array($payload['data'])) {
            throw new InvalidArgumentException('Invalid token data');
        }

        // Check expiration
        if ($payload['exp'] < time()) {
            throw new RuntimeException('Token has expired');
        }

        $data = $payload['data'];

        return new TokenPayload(
            $data['user_id'] ?? 0,
            $data['roles'] ?? [],
            $payload['iat'] ?? time(),
            $payload['exp'] ?? (time() + 3600),
            $data
        );
    }

    /**
     * Revoke a token, making it invalid for future use.
     *
     * @since 1.0.0
     * @param string $token The token to revoke.
     * @return void
     */
    public function revoke(string $token): void {
        $this->revokedTokens[$token] = true;
    }

    /**
     * Refresh a token, generating a new one with extended expiration.
     *
     * @since 1.0.0
     * @param string $token The token to refresh.
     * @return string The new refreshed token.
     */
    public function refresh(string $token): string {
        $payload = $this->validate($token);

        $newPayload = $payload->data;
        $newPayload['user_id'] = $payload->userId;
        $newPayload['roles'] = $payload->roles;

        // Revoke old token
        $this->revoke($token);

        return $this->generate($newPayload);
    }

    /**
     * Base64 URL-safe encode.
     *
     * @param string $data The data to encode.
     * @return string The encoded string.
     */
    private function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-safe decode.
     *
     * @param string $data The data to decode.
     * @return string The decoded string.
     */
    private function base64UrlDecode(string $data): string {
        if ($data === '' || preg_match('/[^A-Za-z0-9_-]/', $data)) {
            throw new InvalidArgumentException('Invalid token encoding');
        }

        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid token encoding');
        }

        return $decoded;
    }
}
