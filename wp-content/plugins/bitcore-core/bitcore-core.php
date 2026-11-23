<?php
/**
 * Plugin Name: BITCORE Core
 * Plugin URI: https://bitcore.org
 * Description: Core plugin for BITCORE Intelligence Factory. Modular AI system with MCP, REST API, and agent orchestration.
 * Version: 1.3.0
 * Author: BITCORE
 * Author URI: https://bitcore.org
 * Text Domain: bitcore-core
 * Domain Path: /languages
 * License: GPLv2+
 *
 * @package BITCORE
 * @since       1.0.0
 * @version     1.3.0
 */

declare(strict_types=1);

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Entry file passed to the constants segment so __FILE__-derived values
// (plugin dir, url, file, entry) stay byte-identical to the pre-split plugin.
$bitcore_entry_file = __FILE__;

require_once __DIR__ . '/parts/01-constants.php';
require_once __DIR__ . '/parts/02-autoloaders.php';
require_once __DIR__ . '/parts/03-infrastructure.php';
require_once __DIR__ . '/parts/04-cron.php';
require_once __DIR__ . '/parts/05-rest-assets.php';
require_once __DIR__ . '/parts/06-activation.php';
require_once __DIR__ . '/parts/07-paperclip-rest.php';
require_once __DIR__ . '/parts/08-post-types.php';
require_once __DIR__ . '/parts/09-final-requires.php';

unset($bitcore_entry_file);
