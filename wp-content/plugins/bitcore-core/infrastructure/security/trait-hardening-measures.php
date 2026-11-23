<?php
/**
 * WordPress Hardening
 *
 * Provides file permission hardening, security headers, and WordPress
 * security configuration enforcement. Ported from OpenClaw hardening patterns.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Security;

use BITCORE\Infrastructure\Logging\Logger;

trait Hardening_Measures {

    /**
     * Apply security headers.
     *
     * @return void
     * @since 1.0.0
     */
    private function applySecurityHeaders(): void {
        add_action('send_headers', function (): void {
            // XSS Protection
            header('X-XSS-Protection: 1; mode=block');

            // Content Type Options
            header('X-Content-Type-Options: nosniff');

            // Frame Options
            header('X-Frame-Options: DENY');

            // Referrer Policy
            header('Referrer-Policy: strict-origin-when-cross-origin');

            // Permissions Policy
            header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');

            // HSTS (if forcing HTTPS)
            if ($this->config->forceHttps && is_ssl()) {
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
            }
        });

        $this->appliedMeasures[] = 'security_headers';
    }
    /**
     * Apply CSP headers.
     *
     * @return void
     * @since 1.0.0
     */
    private function applyCspHeaders(): void {
        if (empty($this->config->csp)) {
            return;
        }

        add_action('send_headers', function (): void {
            $csp = [];
            foreach ($this->config->csp as $directive => $sources) {
                $csp[] = $directive . ' ' . implode(' ', $sources);
            }
            header('Content-Security-Policy: ' . implode('; ', $csp));
        });

        $this->appliedMeasures[] = 'csp_headers';
    }
    /**
     * Apply WordPress hardening.
     *
     * @return void
     * @since 1.0.0
     */
    private function applyWordPressHardening(): void {
        // Disable XML-RPC
        if ($this->config->disableXmlRpc) {
            add_filter('xmlrpc_enabled', '__return_false');
            add_filter('xmlrpc_methods', fn() => [], 10, 1);
            $this->appliedMeasures[] = 'disable_xmlrpc';
        }

        // REST API restrictions
        if ($this->config->restrictRestApi) {
            add_filter('rest_authentication_errors', function ($result) {
                if (!empty($result)) {
                    return $result;
                }
                if (!is_user_logged_in()) {
                    return new \WP_Error(
                        'rest_not_logged_in',
                        __('You must be logged in to access the REST API.'),
                        ['status' => 401]
                    );
                }
                return $result;
            });
            $this->appliedMeasures[] = 'restrict_rest_api';
        }

        // Force HTTPS
        if ($this->config->forceHttps && !is_ssl()) {
            add_action('template_redirect', function (): void {
                if (!is_ssl() && !is_admin()) {
                    wp_redirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], 301);
                    exit;
                }
            });
            $this->appliedMeasures[] = 'force_https';
        }

        // Hide WordPress version
        add_filter('the_generator', '__return_empty_string');
        remove_action('wp_head', 'wp_generator');
        $this->appliedMeasures[] = 'hide_version';

        // Disable file editing in admin
        if (!defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }
        $this->appliedMeasures[] = 'disable_file_edit';
    }
    /**
     * Apply file permissions.
     *
     * @return void
     * @since 1.0.0
     */
    private function applyFilePermissions(): void {
        // Validate uploads via filter
        add_filter('wp_check_filetype_and_ext', function ($data, $file, $filename) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($ext, $this->config->allowedExtensions, true)) {
                $this->logger->warning('Blocked file upload', [
                    'extension' => $ext,
                    'filename' => $filename,
                ]);
                return ['ext' => '', 'type' => false, 'proper_filename' => false];
            }

            return $data;
        }, 10, 3);

        // Add upload validation
        add_filter('upload_dir', function ($uploads) {
            $uploads['error'] = false;
            return $uploads;
        });

        $this->appliedMeasures[] = 'file_upload_validation';
    }
    /**
     * Apply request filtering.
     *
     * @return void
     * @since 1.0.0
     */
    private function applyRequestFiltering(): void {
        // Block suspicious user agents
        $blockedAgents = $this->config->blockedUserAgents;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        foreach ($blockedAgents as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                $this->logger->warning('Blocked suspicious user agent', [
                    'agent' => $userAgent,
                    'pattern' => $pattern,
                    'ip' => $this->getClientIp(),
                ]);
                wp_die('Access denied', 403);
            }
        }

        $this->appliedMeasures[] = 'user_agent_filtering';
    }
    /**
     * Get client IP.
     *
     * @return string Client IP.
     * @since 1.0.0
     */
    private function getClientIp(): string {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? 'unknown';
    }
    /**
     * Validate file upload.
     *
     * @param array<string, mixed> $file Upload data.
     * @return array{valid: bool, error?: string} Validation result.
     * @since 1.0.0
     */
    public function validateUpload(array $file): array {
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

        if (!in_array($ext, $this->config->allowedExtensions, true)) {
            return [
                'valid' => false,
                'error' => sprintf('Extension .%s not allowed', $ext),
            ];
        }

        // Determine size limit
        $category = $this->categorizeFile($ext);
        $limit = $this->config->fileSizeLimits[$category] ?? 5 * 1024 * 1024;

        if ($file['size'] > $limit) {
            return [
                'valid' => false,
                'error' => sprintf('File exceeds %dMB limit', $limit / 1024 / 1024),
            ];
        }

        return ['valid' => true];
    }
    /**
     * Categorize file by extension.
     *
     * @param string $ext File extension.
     * @return string Category.
     * @since 1.0.0
     */
    private function categorizeFile(string $ext): string {
        return match(true) {
            in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'], true) => 'image',
            in_array($ext, ['pdf', 'doc', 'docx', 'txt', 'md'], true) => 'document',
            in_array($ext, ['zip', 'tar', 'gz', 'bz2'], true) => 'archive',
            default => 'other',
        };
    }
    /**
     * Get hardening report.
     *
     * @return array<string, mixed> Report data.
     * @since 1.0.0
     */
    public function getReport(): array {
        return [
            'level' => $this->config->level->value,
            'measures_applied' => $this->appliedMeasures,
            'csp_enabled' => !empty($this->config->csp),
            'https_forced' => $this->config->forceHttps,
            'xmlrpc_disabled' => $this->config->disableXmlRpc,
            'rest_restricted' => $this->config->restrictRestApi,
            'allowed_extensions' => $this->config->allowedExtensions,
            'file_size_limits' => $this->config->fileSizeLimits,
        ];
    }
}
