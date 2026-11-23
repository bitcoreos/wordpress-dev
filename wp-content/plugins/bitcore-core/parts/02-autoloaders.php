<?php
/**
 * Class and pattern autoloader registration
 *
 * Extracted verbatim from bitcore-core.php; require_once order is the original
 * execution order. Bootstrap remains the plugin entry point.
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     coraline
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// === FROM CORALINE: Class Autoloader ===
spl_autoload_register(function ($class) {
    $file = null;

    if (strpos($class, 'BITCORE_Modules') !== false) {
        $filename = str_replace('BITCORE_Modules_', '', $class);
        $filename = strtolower(str_replace('_', '-', $filename));
        $file = BITCORE_PATH . '/classes/modules/' . $filename . '.php';
    } elseif (strpos($class, 'BITCORE_Services') !== false) {
        $filename = str_replace('BITCORE_Services_', '', $class);
        $filename = strtolower(str_replace('_', '-', $filename));
        // Try without class- prefix first, then with prefix
        $file = BITCORE_PATH . '/classes/services/' . $filename . '.php';
        if (!file_exists($file)) {
            $file = BITCORE_PATH . '/classes/services/class-' . $filename . '.php';
        }
    } elseif (strpos($class, 'BITCORE_Engines') !== false) {
        $filename = str_replace('BITCORE_Engines_', '', $class);
        $filename = strtolower(str_replace('_', '-', $filename));
        $file = BITCORE_PATH . '/classes/engines/' . $filename . '.php';
    } elseif (strpos($class, 'BITCORE_Rest') !== false) {
        $filename = str_replace('BITCORE_Rest_', '', $class);
        $filename = strtolower(str_replace('_', '-', $filename));
        $file = BITCORE_PATH . '/rest/' . $filename . '.php';
        if (!file_exists($file)) {
            $file = BITCORE_PATH . '/rest/class-' . $filename . '.php';
        }
    } elseif (strpos($class, 'BITCORE_MCP') !== false) {
        $filename = str_replace('BITCORE_MCP_', '', $class);
        $filename = strtolower(str_replace('_', '-', $filename));
        $file = BITCORE_PATH . '/mcp/' . $filename . '.php';
    } elseif (strpos($class, 'BITCORE') !== false) {
        $filename = str_replace('BITCORE_', '', $class);
        $filename = strtolower(str_replace('_', '-', $filename));
        // Try class- prefixed path first (WordPress coding standard), then fallback
        $file = BITCORE_PATH . '/classes/class-' . $filename . '.php';
        if (!file_exists($file)) {
            $file = BITCORE_PATH . '/classes/' . $filename . '.php';
        }
    }

    if ($file && file_exists($file)) {
        require_once $file;
    }
});

// === FROM HYPERION: Pattern Autoloader ===
if (!function_exists("bitcore_load_patterns")) {
function bitcore_load_patterns(): void {
    $autoloader_file = BITCORE_PLUGIN_DIR . 'classes/patterns-autoloader.php';
    if (file_exists($autoloader_file)) {
        require_once $autoloader_file;
        \BITCORE\Patterns\PatternAutoloader::instance()->load();
    }
}
add_action('plugins_loaded', 'bitcore_load_patterns', 5);
}

// === FROM HYPERION: Initialize Infrastructure ===
    // PSR-4 Autoloader for Paperclip Integration Modules
    require_once BITCORE_PLUGIN_DIR . 'classes/psr4-autoloader.php';
