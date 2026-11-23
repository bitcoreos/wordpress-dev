<?php
/**
 * PSR-4 Autoloader for Paperclip Integration Modules
 *
 * Handles namespace-based autoloading with WordPress class- prefix convention.
 * @package     Bitcore
 * @since       1.0.0
 * @version     1.0.0
 */

if (!defined('ABSPATH')) exit;

spl_autoload_register(function ($class) {
    // PSR-4 prefix to directory mapping
    $maps = array(
        'BITCORE\\Infrastructure\\Budget\\' => BITCORE_PATH . '/infrastructure/budget/',
        'BITCORE\\Infrastructure\\Governance\\' => BITCORE_PATH . '/infrastructure/governance/',
        'BITCORE\\Infrastructure\\Heartbeat\\' => BITCORE_PATH . '/infrastructure/heartbeat/',
        'BITCORE\\Infrastructure\\Coordination\\' => BITCORE_PATH . '/infrastructure/coordination/',
        'BITCORE\\Rest\\' => BITCORE_PATH . '/rest/',
    );

    foreach ($maps as $prefix => $dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            $relative = substr($class, $len);
            
            // Step 1: Replace underscores with dashes
            $clean = str_replace('_', '-', $relative);
            // Step 2: Convert all-uppercase sequences to lowercase (API -> api)
            $clean = preg_replace_callback('/[A-Z]{2,}/', function($m) {
                return strtolower($m[0]);
            }, $clean);
            // Step 3: Convert remaining CamelCase to kebab-case
            $kebab = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $clean));
            // Step 4: Collapse multiple dashes
            $kebab = preg_replace('/-+/', '-', $kebab);
            
            // Try WordPress class- prefix convention first
            $file_wp = $dir . 'class-' . str_replace('\\', '-', $kebab) . '.php';
            if (file_exists($file_wp)) {
                require_once $file_wp;
                return;
            }
            
            // Fallback to standard PSR-4
            $file_psr4 = $dir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file_psr4)) {
                require_once $file_psr4;
                return;
            }
        }
    }
});
