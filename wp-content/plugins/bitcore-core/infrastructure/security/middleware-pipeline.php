<?php
/**
 * for implementation
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

/**
 * Security Middleware Pipeline
 *
 * Provides request/response middleware chain processing for WordPress.
 * Ported from OpenClaw-OPS-Suite middleware patterns.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 */

namespace BITCORE\Infrastructure\Security;

use BITCORE\Infrastructure\RateLimit\RateLimitManager;
use BITCORE\Infrastructure\Auth\AuthManager;
use BITCORE\Infrastructure\Logging\Logger;

// ============================================================================
// MIDDLEWARE INTERFACES
// ============================================================================

/**
 * Middleware interface for request/response processing.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 */
interface MiddlewareInterface {

    /**
     * Process request through middleware.
     *
     * @param RequestContext $request The request context.
     * @param callable(RequestContext): ResponseContext $next The next middleware handler.
     * @return ResponseContext The processed response.
     * @since 1.0.0
     */
    public function process(RequestContext $request, callable $next): ResponseContext;
}

// ============================================================================
// DTOs
// ============================================================================

/**
 * Request context for middleware processing.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 */
readonly class RequestContext {

    /**
     * Request URI path.
     *
     * @var string
     * @since 1.0.0
     */
    public string $path;

    /**
     * Request method.
     *
     * @var string
     * @since 1.0.0
     */
    public string $method;

    /**
     * Request headers.
     *
     * @var array<string, string>
     * @since 1.0.0
     */
    public array $headers;

    /**
     * Query parameters.
     *
     * @var array<string, mixed>
     * @since 1.0.0
     */
    public array $queryParams;

    /**
     * Request body data.
     *
     * @var array<string, mixed>
     * @since 1.0.0
     */
    public array $body;

    /**
     * Client IP address.
     *
     * @var string
     * @since 1.0.0
     */
    public string $clientIp;

    /**
     * Constructor.
     *
     * @param string $path Request URI path.
     * @param string $method Request method.
     * @param array<string, string> $headers Request headers.
     * @param array<string, mixed> $queryParams Query parameters.
     * @param array<string, mixed> $body Request body.
     * @param string $clientIp Client IP.
     * @since 1.0.0
     */
    public function __construct(
        string $path,
        string $method,
        array $headers = [],
        array $queryParams = [],
        array $body = [],
        string $clientIp = 'unknown'
    ) {
        $this->path = $path;
        $this->method = $method;
        $this->headers = $headers;
        $this->queryParams = $queryParams;
        $this->body = $body;
        $this->clientIp = $clientIp;
    }

    /**
     * Get header value.
     *
     * @param string $name Header name.
     * @param string|null $default Default value.
     * @return string|null Header value or default.
     * @since 1.0.0
     */
    public function getHeader(string $name, ?string $default = null): ?string {
        $key = strtolower($name);
        foreach ($this->headers as $headerName => $value) {
            if (strtolower($headerName) === $key) {
                return $value;
            }
        }
        return $default;
    }
}

/**
 * Response context for middleware processing.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 */
readonly class ResponseContext {

    /**
     * HTTP status code.
     *
     * @var int
     * @since 1.0.0
     */
    public int $status;

    /**
     * Response headers.
     *
     * @var array<string, string>
     * @since 1.0.0
     */
    public array $headers;

    /**
     * Response body.
     *
     * @var array<string, mixed>|string
     * @since 1.0.0
     */
    public array|string $body;

    /**
     * Whether request should proceed.
     *
     * @var bool
     * @since 1.0.0
     */
    public bool $shouldProceed;

    /**
     * Error message if blocked.
     *
     * @var string|null
     * @since 1.0.0
     */
    public ?string $error;

    /**
     * Constructor.
     *
     * @param int $status HTTP status.
     * @param array<string, string> $headers Response headers.
     * @param array<string, mixed>|string $body Response body.
     * @param bool $shouldProceed Whether to proceed.
     * @param string|null $error Error message.
     * @since 1.0.0
     */
    public function __construct(
        int $status = 200,
        array $headers = [],
        array|string $body = [],
        bool $shouldProceed = true,
        ?string $error = null
    ) {
        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
        $this->shouldProceed = $shouldProceed;
        $this->error = $error;
    }

    /**
     * Create error response.
     *
     * @param int $status HTTP status.
     * @param string $message Error message.
     * @param array<string, string> $headers Additional headers.
     * @return self Error response.
     * @since 1.0.0
     */
    public static function error(int $status, string $message, array $headers = []): self {
        return new self(
            status: $status,
            headers: $headers,
            body: ['error' => $message],
            shouldProceed: false,
            error: $message
        );
    }
}

// ============================================================================
// MIDDLEWARE IMPLEMENTATIONS
// ============================================================================

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

// ============================================================================
// PIPELINE
// ============================================================================

/**
 * Middleware pipeline for request processing.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 */
class MiddlewarePipeline {

    /**
     * Middleware stack.
     *
     * @var array<MiddlewareInterface>
     * @since 1.0.0
     */
    private array $middleware = [];

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
     * @param Logger $logger Logger instance.
     * @since 1.0.0
     */
    public function __construct(Logger $logger) {
        $this->logger = $logger;
    }

    /**
     * Add middleware to pipeline.
     *
     * @param MiddlewareInterface $middleware Middleware to add.
     * @return self Fluent interface.
     * @since 1.0.0
     */
    public function add(MiddlewareInterface $middleware): self {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Process request through pipeline.
     *
     * @param RequestContext $request Request to process.
     * @param callable(RequestContext): ResponseContext $handler Final handler.
     * @return ResponseContext Final response.
     * @since 1.0.0
     */
    public function process(RequestContext $request, callable $handler): ResponseContext {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            function (callable $next, MiddlewareInterface $middleware): callable {
                return function (RequestContext $request) use ($next, $middleware): ResponseContext {
                    return $middleware->process($request, $next);
                };
            },
            $handler
        );

        return $pipeline($request);
    }

    /**
     * Get middleware count.
     *
     * @return int Number of middleware.
     * @since 1.0.0
     */
    public function count(): int {
        return count($this->middleware);
    }
}
