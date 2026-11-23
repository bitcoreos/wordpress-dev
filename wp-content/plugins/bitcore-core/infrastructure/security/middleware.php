<?php
/**
 * BITCORE Security Middleware Pipeline
 * Request/response filtering and hardening
 * @package BITCORE_Core
 * @since 1.0.0
 * @version     1.0.0
 */
declare(strict_types=1);

interface BITCORE_Security_MiddlewareInterface {
    public function handle(\WP_REST_Request $request, callable $next): \WP_REST_Response|\WP_Error;
}

class BITCORE_Security_MiddlewarePipeline {
    private array $middlewares = [];

    public function add(BITCORE_Security_MiddlewareInterface $middleware): void {
        $this->middlewares[] = $middleware;
    }

    public function process(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $index = 0;
        $runner = function($req) use (&$index, &$runner) {
            if (!isset($this->middlewares[$index])) {
                return rest_ensure_response(['success' => true]);
            }
            $mw = $this->middlewares[$index++];
            return $mw->handle($req, $runner);
        };
        return $runner($request);
    }
}

class BITCORE_Security_AuditLogger implements BITCORE_Security_MiddlewareInterface {
    public function handle(\WP_REST_Request $request, callable $next): \WP_REST_Response|\WP_Error {
        $start = microtime(true);
        $response = $next($request);
        $this->log($request, $response, microtime(true) - $start);
        return $response;
    }

    private function log(\WP_REST_Request $request, $response, float $duration): void {
        if (!defined('BITCORE_AUDIT_LOG')) return;
        $log = [
            'timestamp' => gmdate('c'),
            'ip' => $request->get_header('X-Forwarded-For') ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'method' => $request->get_method(),
            'route' => $request->get_route(),
            'user' => get_current_user_id(),
            'duration' => round($duration * 1000, 2),
            'status' => is_wp_error($response) ? 'error' : $response->get_status(),
        ];
        error_log('[BITCORE_AUDIT] ' . wp_json_encode($log));
    }
}

class BITCORE_Security_IpFilter implements BITCORE_Security_MiddlewareInterface {
    private array $blocklist = [];
    private array $allowlist = [];

    public function __construct(BITCORE_Core $core) {
        $this->blocklist = $core->get_option('security_ip_blocklist', []);
        $this->allowlist = $core->get_option('security_ip_allowlist', []);
    }

    public function handle(\WP_REST_Request $request, callable $next): \WP_REST_Response|\WP_Error {
        $ip = $request->get_header('X-Forwarded-For') ?? $_SERVER['REMOTE_ADDR'] ?? '';
        if (in_array($ip, $this->blocklist, true)) {
            return new \WP_Error('ip_blocked', 'Access denied', ['status' => 403]);
        }
        if (!empty($this->allowlist) && !in_array($ip, $this->allowlist, true)) {
            return new \WP_Error('ip_not_allowed', 'Access denied', ['status' => 403]);
        }
        return $next($request);
    }
}
