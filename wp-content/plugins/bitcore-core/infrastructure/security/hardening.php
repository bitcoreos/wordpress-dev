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

require_once __DIR__ . '/trait-hardening-measures.php';

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

    use Hardening_Measures;

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









}
