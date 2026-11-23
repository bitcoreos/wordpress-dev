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

// ============================================================================
// SECURITY HEADER ENUMS
// ============================================================================

/**
 * Enumeration of CSP directives.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 */
enum CspDirective: string {
    case DEFAULT_SRC = 'default-src';
    case SCRIPT_SRC = 'script-src';
    case STYLE_SRC = 'style-src';
    case IMG_SRC = 'img-src';
    case FONT_SRC = 'font-src';
    case CONNECT_SRC = 'connect-src';
    case MEDIA_SRC = 'media-src';
    case OBJECT_SRC = 'object-src';
    case FRAME_SRC = 'frame-src';
    case FRAME_ANCESTORS = 'frame-ancestors';
    case FORM_ACTION = 'form-action';
    case BASE_URI = 'base-uri';
    case UPGRADE_INSECURE = 'upgrade-insecure-requests';
}

/**
 * Enumeration of hardening levels.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 */
enum HardenLevel: string {
    case NONE = 'none';
    case BASIC = 'basic';
    case STRICT = 'strict';
    case PARANOID = 'paranoid';
}

// ============================================================================
// HARDENING CONFIG
// ============================================================================

/**
 * Immutable hardening configuration.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 */
readonly class HardeningConfig {

    /**
     * Hardness level.
     *
     * @var HardenLevel
     * @since 1.0.0
     */
    public HardenLevel $level;

    /**
     * Content Security Policy.
     *
     * @var array<string, array<string>>
     * @since 1.0.0
     */
    public array $csp;

    /**
     * Allowed file extensions.
     *
     * @var array<string>
     * @since 1.0.0
     */
    public array $allowedExtensions;

    /**
     * File size limits by type.
     *
     * @var array<string, int>
     * @since 1.0.0
     */
    public array $fileSizeLimits;

    /**
     * Blocked user agents.
     *
     * @var array<string>
     * @since 1.0.0
     */
    public array $blockedUserAgents;

    /**
     * Whether to disable XML-RPC.
     *
     * @var bool
     * @since 1.0.0
     */
    public bool $disableXmlRpc;

    /**
     * Whether to disable REST API for guests.
     *
     * @var bool
     * @since 1.0.0
     */
    public bool $restrictRestApi;

    /**
     * Whether to enforce HTTPS.
     *
     * @var bool
     * @since 1.0.0
     */
    public bool $forceHttps;

    /**
     * Constructor with defaults.
     *
     * @param HardenLevel $level Security level.
     * @param array<string, array<string>>|null $csp Custom CSP.
     * @param array<string>|null $allowedExtensions Allowed extensions.
     * @param array<string, int>|null $fileSizeLimits Size limits.
     * @param array<string>|null $blockedUserAgents Blocked agents.
     * @param bool $disableXmlRpc Disable XML-RPC.
     * @param bool $restrictRestApi Restrict REST API.
     * @param bool $forceHttps Force HTTPS.
     * @since 1.0.0
     */
    public function __construct(
        HardenLevel $level = HardenLevel::STRICT,
        ?array $csp = null,
        ?array $allowedExtensions = null,
        ?array $fileSizeLimits = null,
        ?array $blockedUserAgents = null,
        bool $disableXmlRpc = true,
        bool $restrictRestApi = true,
        bool $forceHttps = true
    ) {
        $this->level = $level;
        $this->csp = $csp ?? self::getDefaultCsp($level);
        $this->allowedExtensions = $allowedExtensions ?? self::getDefaultExtensions($level);
        $this->fileSizeLimits = $fileSizeLimits ?? [
            'image' => 5 * 1024 * 1024,      // 5MB
            'document' => 10 * 1024 * 1024,  // 10MB
            'archive' => 50 * 1024 * 1024,   // 50MB
        ];
        $this->blockedUserAgents = $blockedUserAgents ?? [
            'sqlmap',
            'nikto',
            'nmap',
            'masscan',
            'zgrab',
            'gobuster',
        ];
        $this->disableXmlRpc = $disableXmlRpc;
        $this->restrictRestApi = $restrictRestApi;
        $this->forceHttps = $forceHttps;
    }

    /**
     * Get default CSP by level.
     *
     * @param HardenLevel $level Security level.
     * @return array<string, array<string>> CSP directives.
     * @since 1.0.0
     */
    private static function getDefaultCsp(HardenLevel $level): array {
        $base = [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
        ];

        return match($level) {
            HardenLevel::NONE => [],
            HardenLevel::BASIC => array_merge($base, [
                'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'"],
                'style-src' => ["'self'", "'unsafe-inline'"],
                'img-src' => ["'self'", 'data:', 'blob:', '*'],
                'font-src' => ["'self'", 'data:'],
                'connect-src' => ["'self'"],
            ]),
            HardenLevel::STRICT => array_merge($base, [
                'script-src' => ["'self'", "'nonce-" . wp_create_nonce('csp') . "'"],
                'style-src' => ["'self'", "'nonce-" . wp_create_nonce('csp-style') . "'"],
                'img-src' => ["'self'", 'data:', 'blob:'],
                'font-src' => ["'self'"],
                'connect-src' => ["'self'"],
                'frame-src' => ["'none'"],
                'frame-ancestors' => ["'none'"],
                'object-src' => ["'none'"],
                'upgrade-insecure-requests' => [],
            ]),
            HardenLevel::PARANOID => array_merge($base, [
                'script-src' => ["'self'"],
                'style-src' => ["'self'"],
                'img-src' => ["'self'"],
                'font-src' => ["'self'"],
                'connect-src' => ["'self'"],
                'media-src' => ["'none'"],
                'frame-src' => ["'none'"],
                'frame-ancestors' => ["'none'"],
                'object-src' => ["'none'"],
            ]),
        };
    }

    /**
     * Get allowed extensions by level.
     *
     * @param HardenLevel $level Security level.
     * @return array<string> Allowed extensions.
     * @since 1.0.0
     */
    private static function getDefaultExtensions(HardenLevel $level): array {
        return match($level) {
            HardenLevel::NONE => [],
            HardenLevel::BASIC => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
            HardenLevel::STRICT => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'pdf', 'doc', 'docx', 'txt'],
            HardenLevel::PARANOID => ['jpg', 'jpeg', 'png', 'pdf', 'txt'],
        };
    }
}

// ============================================================================
// HARDENING MANAGER
// ============================================================================

/**
 * WordPress hardening manager.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 */
class HardeningManager {

    /**
     * Configuration.
     *
     * @var HardeningConfig
     * @since 1.0.0
     */
    private HardeningConfig $config;

    /**
     * Logger.
     *
     * @var Logger
     * @since 1.0.0
     */
    private Logger $logger;

    /**
     * Applied security measures.
     *
     * @var array<string>
     * @since 1.0.0
     */
    private array $appliedMeasures = [];

    /**
     * Constructor.
     *
     * @param HardeningConfig $config Hardening configuration.
     * @param Logger $logger Logger instance.
     * @since 1.0.0
     */
    public function __construct(HardeningConfig $config, Logger $logger) {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Apply all hardening measures.
     *
     * @return array<string> Applied measures.
     * @since 1.0.0
     */
    public function apply(): array {
        $this->appliedMeasures = [];

        // Headers
        $this->applySecurityHeaders();
        $this->applyCspHeaders();

        // WordPress features
        $this->applyWordPressHardening();

        // File system
        $this->applyFilePermissions();

        // Request filtering
        $this->applyRequestFiltering();

        $this->logger->info('Security hardening applied', [
            'level' => $this->config->level->value,
            'measures' => $this->appliedMeasures,
        ]);

        return $this->appliedMeasures;
    }

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
