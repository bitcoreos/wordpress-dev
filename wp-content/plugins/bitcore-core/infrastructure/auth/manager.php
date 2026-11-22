<?php

declare(strict_types=1);

namespace BITCORE\Infrastructure\Auth;

use BITCORE\Infrastructure\Auth\Contracts\TokenManagerInterface;
use BITCORE\Infrastructure\Auth\Contracts\SessionManagerInterface;
use InvalidArgumentException;
use RuntimeException;

require_once __DIR__ . '/TokenPayload.php';

/**
 * Data Transfer Object for authentication results.
 *
 * @since 1.0.0
 * @package BITCORE\Infrastructure\Auth
 */
final class AuthResult {
    public function __construct(
        readonly public bool $success,
        readonly public ?UserData $user = null,
        readonly public ?string $token = null,
        readonly public ?string $error = null,
        readonly public array $metadata = []
    ) {}
}

/**
 * Data Transfer Object for session data.
 *
 * @since 1.0.0
 * @package BITCORE\Infrastructure\Auth
 */
final class SessionData {
    public function __construct(
        readonly public string $id,
        readonly public int $userId,
        readonly public string $token,
        readonly public int $expiresAt
    ) {}
}

/**
 * Data Transfer Object for OAuth redirect.
 *
 * @since 1.0.0
 * @package BITCORE\Infrastructure\Auth
 */
final class OAuthRedirect {
    public function __construct(
        readonly public string $url,
        readonly public string $state,
        readonly public string $provider
    ) {}
}

/**
 * Data Transfer Object for user data.
 *
 * @since 1.0.0
 * @package BITCORE\Infrastructure\Auth
 */
final class UserData {
    public function __construct(
        readonly public int $id,
        readonly public string $email,
        readonly public array $roles = [],
        readonly public array $capabilities = []
    ) {}
}

/**
 * Authentication Manager.
 *
 * Central coordinator for authentication, session management, and OAuth flows.
 *
 * @since 1.0.0
 * @package BITCORE\Infrastructure\Auth
 */
class AuthManager {
    private TokenManagerInterface $tokenManager;
    private SessionManagerInterface $sessionManager;
    private CapabilityManager $capabilityManager;
    private array $authHandlers = [];
    private array $oauthProviders = [];
    private ?UserData $currentUser = null;

    public function __construct(
        TokenManagerInterface $tokenManager,
        SessionManagerInterface $sessionManager,
        CapabilityManager $capabilityManager
    ) {
        $this->tokenManager = $tokenManager;
        $this->sessionManager = $sessionManager;
        $this->capabilityManager = $capabilityManager;
        $this->registerDefaultHandlers();
    }

    private function registerDefaultHandlers(): void {
        $this->authHandlers['password'] = function (array $credentials): ?UserData {
            if (empty($credentials['username']) || empty($credentials['password'])) {
                return null;
            }
            $user = wp_authenticate($credentials['username'], $credentials['password']);
            if (is_wp_error($user)) {
                return null;
            }
            return $this->createUserData($user);
        };
    }

    private function createUserData(\WP_User $user): UserData {
        return new UserData(
            id: $user->ID,
            email: $user->user_email,
            roles: $user->roles,
            capabilities: $user->allcaps
        );
    }

    public function authenticate(string $method, array $credentials): AuthResult {
        if (!isset($this->authHandlers[$method])) {
            return new AuthResult(
                success: false,
                error: "Unknown authentication method: {$method}"
            );
        }

        $handler = $this->authHandlers[$method];
        $user = $handler($credentials);

        if ($user === null) {
            return new AuthResult(
                success: false,
                error: 'Authentication failed'
            );
        }

        $token = $this->tokenManager->generate([
            'user_id' => $user->id,
            'roles' => $user->roles
        ]);

        $this->currentUser = $user;

        return new AuthResult(
            success: true,
            user: $user,
            token: $token
        );
    }

    public function validateToken(string $token): TokenPayload {
        return $this->tokenManager->validate($token);
    }

    public function refreshToken(string $token): string {
        return $this->tokenManager->refresh($token);
    }

    public function revokeToken(string $token): void {
        $this->tokenManager->revoke($token);
    }

    public function startSession(int $userId): SessionData {
        $this->sessionManager->start();
        $token = $this->tokenManager->generate(['user_id' => $userId], 86400);
        $sessionId = $this->sessionManager->regenerate();

        return new SessionData(
            id: $sessionId,
            userId: $userId,
            token: $token,
            expiresAt: time() + 86400
        );
    }

    public function endSession(string $sessionId): void {
        $this->sessionManager->destroy();
        $this->currentUser = null;
    }

    public function getCurrentUser(): ?UserData {
        return $this->currentUser;
    }

    public function initiateOAuth(string $provider, array $scopes): OAuthRedirect {
        if (!isset($this->oauthProviders[$provider])) {
            throw new InvalidArgumentException("Unknown OAuth provider: {$provider}");
        }

        $state = wp_create_nonce('bitcore_oauth_' . $provider);
        $url = $this->oauthProviders[$provider]($scopes, $state);

        return new OAuthRedirect(
            url: $url,
            state: $state,
            provider: $provider
        );
    }

    public function handleOAuthCallback(string $provider, array $params): AuthResult {
        if (!isset($this->oauthProviders[$provider])) {
            return new AuthResult(
                success: false,
                error: "Unknown OAuth provider: {$provider}"
            );
        }

        // Provider-specific callback handling
        return new AuthResult(success: false, error: 'Not implemented');
    }

    public function registerAuthHandler(string $method, callable $handler): void {
        $this->authHandlers[$method] = $handler;
    }

    public function registerOAuthProvider(string $provider, callable $handler): void {
        $this->oauthProviders[$provider] = $handler;
    }
}
