<?php
/**
 * Post router and privacy subsystem requires
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


// === ASHI: Post Router and Privacy ===
require_once BITCORE_PLUGIN_DIR . 'infrastructure/class-post-router.php';
require_once BITCORE_PLUGIN_DIR . 'infrastructure/class-privacy.php';
