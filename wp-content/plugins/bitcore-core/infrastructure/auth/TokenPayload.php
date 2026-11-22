<?php

declare(strict_types=1);

namespace BITCORE\Infrastructure\Auth;

/**
 * Data Transfer Object for token payload.
 *
 * Kept in a dedicated file so token manager contracts and implementations can
 * be loaded independently without relying on AuthManager load order.
 *
 * @since 1.0.0
 * @package BITCORE\Infrastructure\Auth
 */
final class TokenPayload {
    public function __construct(
        readonly public int $userId,
        readonly public array $roles = [],
        readonly public int $issuedAt = 0,
        readonly public int $expiresAt = 0,
        readonly public array $data = []
    ) {}

    public function isExpired(): bool {
        return time() > $this->expiresAt;
    }
}
