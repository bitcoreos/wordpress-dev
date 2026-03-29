<?php
/**
 * PSR-4 Autoloader for Paperclip Integration Modules
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
        'BITCORE\\REST\\' => BITCORE_PATH . '/rest/',
    );

    foreach ($maps as $prefix => $dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            $relative = substr($class, $len);
            $file = $dir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});
