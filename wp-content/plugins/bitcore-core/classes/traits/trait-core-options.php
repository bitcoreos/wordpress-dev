<?php
/**
 * Core options, model selection and lifecycle hooks
 *
 * Extracted verbatim from wp-content/plugins/bitcore-core/classes/core.php (BITCORE 500-LOC refactor); logic unchanged.
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


trait BITCORE_Core_Options_Trait {

    public function get_option(string $key, $default = false) {
        $encrypted_keys = ['api_key', 'secret', 'token', 'password'];
        foreach ($encrypted_keys as $encrypted_key) {
            if (strpos($key, $encrypted_key) !== false && $this->encrypted_options) {
                return $this->encrypted_options->get_encrypted_option($key, $default);
            }
        }
        $options = get_option('bitcore_options', []);
        return $options[$key] ?? $default;
    }

    public function update_option(string $key, $value): void {
        $encrypted_keys = ['api_key', 'secret', 'token', 'password'];
        foreach ($encrypted_keys as $encrypted_key) {
            if (strpos($key, $encrypted_key) !== false && $this->encrypted_options) {
                $this->encrypted_options->update_encrypted_option($key, $value);
                return;
            }
        }
        $options = get_option('bitcore_options', []);
        $options[$key] = $value;
        update_option('bitcore_options', $options);
    }

    /**
     * Get AI model for current user and task (Coraline)
     */
    public function get_model_for_task(string $task_type = 'chat') {
        if (!$this->model_selector) return false;
        return $this->model_selector->select_model_for_user(get_current_user_id(), $task_type);
    }

    /**
     * Get service from container
     */
    public function get_service(string $name) {
        return $this->container ? $this->container->get($name) : null;
    }

    private function is_rest_request(): bool {
        return defined('REST_REQUEST') && REST_REQUEST;
    }

    /**
     * Plugin activation handler
     */
    public static function activate(): void {
        if (class_exists('BITCORE_Modules_Capabilities')) {
            BITCORE_Modules_Capabilities::register();
        }
        flush_rewrite_rules();
        do_action('bitcore_activated');
    }

    /**
     * Plugin deactivation handler
     */
    public static function deactivate(): void {
        if (class_exists('BITCORE_Modules_Capabilities')) {
            BITCORE_Modules_Capabilities::unregister();
        }
        flush_rewrite_rules();
        do_action('bitcore_deactivated');
    }

}
