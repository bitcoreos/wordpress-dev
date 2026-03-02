<?php
/**
 * TokenManagerInterface implementation
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

namespace BITCORE\Infrastructure\Auth\Contracts;

use BITCORE\Infrastructure\Auth\TokenPayload;

/**
 * Interface for token management operations.
 *
 * @since 1.0.0
 * @package BITCORE\Infrastructure\Auth\Contracts
 */
interface TokenManagerInterface {

/**
 * Generate a new token for the given payload.
 *
 * @since 1.0.0
 * @param array<string, mixed> $payload The data to encode in the token.
 * @param int                  $ttl Time-to-live in seconds (default: 3600).
 * @return string The generated token.
 * @throws \InvalidArgumentException If payload is invalid.
 */
public function generate(array $payload, int $ttl = 3600): string;

/**
 * Validate a token and return its decoded payload.
 *
 * @since 1.0.0
 * @param string $token The token to validate.
 * @return TokenPayload The decoded token payload.
 * @throws \InvalidArgumentException If token is invalid.
 * @throws \RuntimeException If token validation fails.
 */
public function validate(string $token): TokenPayload;

/**
 * Revoke a token, making it invalid for future use.
 *
 * @since 1.0.0
 * @param string $token The token to revoke.
 * @return void
 */
public function revoke(string $token): void;

/**
 * Refresh a token, generating a new one with extended expiration.
 *
 * @since 1.0.0
 * @param string $token The token to refresh.
 * @return string The new refreshed token.
 * @throws \InvalidArgumentException If token is invalid or cannot be refreshed.
 */
public function refresh(string $token): string;
}
