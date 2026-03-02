<?php
/**
 * WebProcessor implementation
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
 * BITCORE Logging Infrastructure - Web Processor
 *
 * @package BITCORE\Infrastructure\Logging
 * @since 1.0.0
 */

namespace BITCORE\Infrastructure\Logging;

/**
 * Web Request Context Processor
 *
 * Adds HTTP request information to log records.
 * Captures URL, method, user agent, and client IP for debugging.
 *
 * @since 1.0.0
 */
class WebProcessor implements ProcessorInterface
{
    /**
     * Process a log record by adding web request information.
     *
     * @param array<string, mixed> $record The log record to process.
     * @return array<string, mixed> The modified log record with request data.
     * @since 1.0.0
     */
    public function __invoke(array $record): array
    {
        $record['extra']['http'] = $this->getRequestData();

        return $record;
    }

    /**
     * Get current HTTP request data.
     *
     * @return array<string, mixed> Request data including URL, method, IP, user agent.
     * @since 1.0.0
     */
    private function getRequestData(): array
    {
        $data = [];

        // Request URL
        $data['url'] = $this->getRequestUrl();

        // Request Method
        $data['method'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

        // Client IP (respecting proxies)
        $data['ip'] = $this->getClientIp();

        // User Agent
        $data['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        // Referrer
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $data['referrer'] = $_SERVER['HTTP_REFERER'];
        }

        // Server name
        $data['server'] = $_SERVER['SERVER_NAME'] ?? 'Unknown';

        return $data;
    }

    /**
     * Get the full request URL.
     *
     * @return string The complete request URL.
     * @since 1.0.0
     */
    private function getRequestUrl(): string
    {
        $scheme   = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri      = $_SERVER['REQUEST_URI'] ?? '/';
        $port     = $_SERVER['SERVER_PORT'] ?? '';

        $portStr = (in_array($port, ['80', '443'], true) || empty($port))
            ? ''
            : ':' . $port;

        return $scheme . '://' . $host . $portStr . $uri;
    }

    /**
     * Get the client IP address, respecting proxy headers.
     *
     * @return string The client IP address.
     * @since 1.0.0
     */
    private function getClientIp(): string
    {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP', // CloudFlare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle multiple IPs (proxy chains)
                if (str_contains($ip, ',')) {
                    $ips = array_map('trim', explode(',', $ip));
                    $ip = $ips[0];
                }
                // Validate IP format
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
                return $ip; // Return even private IPs if no public IP found
            }
        }

        return '0.0.0.0';
    }
}
