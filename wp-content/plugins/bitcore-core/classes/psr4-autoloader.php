<?php
/**
 * PSR-4 Autoloader for Paperclip Integration Modules
 * 
 * Handles namespace-based autoloading with WordPress class- prefix convention.
 */

if (!defined('ABSPATH')) exit;

spl_autoload_register(function ($class) {
    // PSR-4 prefix to directory mapping
    $maps = array(
        'BITCORE\\Infrastructure\\Budget\\' => BITCORE_PATH . '/infrastructure/budget/',
        'BITCORE\\Infrastructure\\Goals\\' => BITCORE_PATH . '/infrastructure/goals/',
        'BITCORE\\Infrastructure\\Governance\\' => BITCORE_PATH . '/infrastructure/governance/',
        'BITCORE\\Infrastructure\\Heartbeat\\' => BITCORE_PATH . '/infrastructure/heartbeat/',
        'BITCORE\\Infrastructure\\Coordination\\' => BITCORE_PATH . '/infrastructure/coordination/',
        'BITCORE\\Rest\\' => BITCORE_PATH . '/rest/',
    );

    foreach ($maps as $prefix => $dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            $relative = substr($class, $len);
            
            // Convert CamelCase to kebab-case for WordPress convention
            // e.g., CheckoutLock -> checkout-lock
            $kebab = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $relative));
            
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
