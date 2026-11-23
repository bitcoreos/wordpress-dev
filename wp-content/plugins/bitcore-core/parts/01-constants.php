<?php
/**
 * Plugin constants (entry-file values preserved)
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

// Defensive default: the entry file normally provides this variable before the
// part is included. Resolves to the same path the bootstrap passes via __FILE__.
if (!isset($bitcore_entry_file)) {
    $bitcore_entry_file = dirname(__DIR__) . '/bitcore-core.php';
}

// Define constants.
define('BITCORE_VERSION', '1.3.0');
define('BITCORE_DB_VERSION', '1.3.0');
define('BITCORE_PLUGIN_FILE', $bitcore_entry_file);
define('BITCORE_ENTRY', $bitcore_entry_file);
define('BITCORE_PLUGIN_DIR', plugin_dir_path($bitcore_entry_file));
define('BITCORE_PLUGIN_URL', plugin_dir_url($bitcore_entry_file));
define('BITCORE_PATH', plugin_dir_path($bitcore_entry_file));
define('BITCORE_URL', plugin_dir_url($bitcore_entry_file));
define('BITCORE_PREFIX', 'bitcore');
define('BITCORE_DOMAIN', 'bitcore');
define('BITCORE_TIMEOUT', 300);
define('BITCORE_FALLBACK_MODEL', 'coraline-v1');
define('BITCORE_FALLBACK_MODEL_FAST', 'hyperion-v1');
