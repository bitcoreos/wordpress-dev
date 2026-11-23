<?php
/**
 * Security Middleware Pipeline
 *
 * Provides request/response middleware chain processing for WordPress.
 * Ported from OpenClaw-OPS-Suite middleware patterns.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Security;

use BITCORE\Infrastructure\RateLimit\RateLimitManager;
use BITCORE\Infrastructure\Auth\AuthManager;
use BITCORE\Infrastructure\Logging\Logger;

/**
 * Rate limiting middleware.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 */
class RateLimitMiddleware implements MiddlewareInterface {

    /**
     * Rate limit manager.
     *
     * @var RateLimitManager
     * @since 1.0.0
     */
    private RateLimitManager $rateLimiter;

    /**
     * Logger.
     *
     * @var Logger
     * @since 1.0.0
     */
    private Logger $logger;

    /**
     * Constructor.
     *
     * @param RateLimitManager $rateLimiter Rate limit manager.
     * @param Logger $logger Logger instance.
     * @since 1.0.0
     */
    public function __construct(RateLimitManager $rateLimiter, Logger $logger) {
        $this->rateLimiter = $rateLimiter;
        $this->logger = $logger;
    }

    /**
     * Process request through rate limiting.
     *
     * @param RequestContext $request Request context.
     * @param callable(RequestContext): ResponseContext $next Next handler.
     * @return ResponseContext Response.
     * @since 1.0.0
     */
    public function process(RequestContext $request, callable $next): ResponseContext {
        $key = 'ip:' . $request->clientIp;

        if (!$this->rateLimiter->attempt($key)) {
            $this->logger->warning('Rate limit exceeded', [
                'ip' => $request->clientIp,
                'path' => $request->path,
            ]);

            return ResponseContext::error(
                status: 429,
                message: 'Rate limit exceeded. Please slow down.',
                headers: ['Retry-After' => '60']
            );
        }

        return $next($request);
    }
}
/**
 * Authentication middleware.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 */
class AuthMiddleware implements MiddlewareInterface {

    /**
     * Auth manager.
     *
     * @var AuthManager
     * @since 1.0.0
     */
    private AuthManager $auth;

    /**
     * Logger.
     *
     * @var Logger
     * @since 1.0.0
     */
    private Logger $logger;

    /**
     * Protected route patterns.
     *
     * @var array<string>
     * @since 1.0.0
     */
    private array $protectedRoutes;

    /**
     * Public route patterns.
     *
     * @var array<string>
     * @since 1.0.0
     */
    private array $publicRoutes;

    /**
     * Constructor.
     *
     * @param AuthManager $auth Auth manager.
     * @param Logger $logger Logger instance.
     * @param array<string> $protectedRoutes Protected patterns.
     * @param array<string> $publicRoutes Public patterns.
     * @since 1.0.0
     */
    public function __construct(
        AuthManager $auth,
        Logger $logger,
        array $protectedRoutes = [],
        array $publicRoutes = ['/api/health', '/api/setup/status']
    ) {
        $this->auth = $auth;
        $this->logger = $logger;
        $this->protectedRoutes = $protectedRoutes;
        $this->publicRoutes = $publicRoutes;
    }

    /**
     * Process request through authentication.
     *
     * @param RequestContext $request Request context.
     * @param callable(RequestContext): ResponseContext $next Next handler.
     * @return ResponseContext Response.
     * @since 1.0.0
     */
    public function process(RequestContext $request, callable $next): ResponseContext {
        // Skip non-API routes
        if (!str_starts_with($request->path, '/api/')) {
            return $next($request);
        }

        // Allow public routes
        foreach ($this->publicRoutes as $route) {
            if (str_starts_with($request->path, $route)) {
                return $next($request);
            }
        }

        // Check if protected
        $isProtected = false;
        foreach ($this->protectedRoutes as $route) {
            if (str_starts_with($request->path, $route)) {
                $isProtected = true;
                break;
            }
        }

        if (!$isProtected) {
            return $next($request);
        }

        // Validate API key
        $apiKey = $request->getHeader('x-api-key')
            ?? $request->queryParams['api_key']
            ?? null;

        $expectedKey = getenv('DASHBOARD_API_KEY');

        if (!$expectedKey) {
            $this->logger->info('API key not configured - allowing unauthenticated', [
                'path' => $request->path,
            ]);
            return $next($request);
        }

        if ($apiKey !== $expectedKey) {
            $this->logger->warning('Invalid API key', [
                'ip' => $request->clientIp,
                'path' => $request->path,
            ]);
            return ResponseContext::error(401, 'Unauthorized');
        }

        return $next($request);
    }
}
/**
 * Security headers middleware.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 */
class SecurityHeadersMiddleware implements MiddlewareInterface {

    /**
     * Security headers to add.
     *
     * @var array<string, string>
     * @since 1.0.0
     */
    private array $headers;

    /**
     * Constructor.
     *
     * @param array<string, string>|null $headers Custom headers.
     * @since 1.0.0
     */
    public function __construct(?array $headers = null) {
        $this->headers = $headers ?? [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ];
    }

    /**
     * Process request adding security headers.
     *
     * @param RequestContext $request Request context.
     * @param callable(RequestContext): ResponseContext $next Next handler.
     * @return ResponseContext Response with headers.
     * @since 1.0.0
     */
    public function process(RequestContext $request, callable $next): ResponseContext {
        $response = $next($request);

        // Merge headers
        $headers = array_merge($response->headers, $this->headers);

        return new ResponseContext(
            status: $response->status,
            headers: $headers,
            body: $response->body,
            shouldProceed: $response->shouldProceed,
            error: $response->error
        );
    }
}