<?php
/**
 * BITCORE Settings Registration
 * Must be loaded via admin_init hook
 */
declare(strict_types=1);

function bitcore_register_settings() {
    // n8n Integration
    register_setting("bitcore_options", "bitcore_n8n_webhook_url", ["type" => "string", "sanitize_callback" => "esc_url_raw"]);
    register_setting("bitcore_options", "bitcore_n8n_api_key", ["type" => "string", "sanitize_callback" => "sanitize_text_field"]);
    
    // BIThub
    register_setting("bitcore_options", "bitcore_bithub_api_url", ["type" => "string", "sanitize_callback" => "esc_url_raw"]);
    register_setting("bitcore_options", "bitcore_bithub_api_key", ["type" => "string", "sanitize_callback" => "sanitize_text_field"]);
    
    // BITwiki
    register_setting("bitcore_options", "bitcore_bitwiki_api_url", ["type" => "string", "sanitize_callback" => "esc_url_raw"]);
    
    // Hermes
    register_setting("bitcore_options", "bitcore_hermes_url", ["type" => "string", "sanitize_callback" => "esc_url_raw"]);
    register_setting("bitcore_options", "bitcore_callback_secret", ["type" => "string", "sanitize_callback" => "sanitize_text_field"]);
    
    // Model Configuration
    register_setting("bitcore_options", "bitcore_default_model", ["type" => "string", "sanitize_callback" => "sanitize_text_field"]);
    register_setting("bitcore_options", "bitcore_max_tokens", ["type" => "integer", "sanitize_callback" => "absint"]);
    register_setting("bitcore_options", "bitcore_temperature", ["type" => "float", "sanitize_callback" => "floatval"]);
    
    // Advanced
    register_setting("bitcore_options", "bitcore_debug_mode", ["type" => "boolean"]);
    register_setting("bitcore_options", "bitcore_rate_limit", ["type" => "integer", "sanitize_callback" => "absint"]);
    
    // Modules
    register_setting("bitcore_options", "bitcore_module_chatbot", ["type" => "boolean"]);
    register_setting("bitcore_options", "bitcore_module_discussions", ["type" => "boolean"]);
    register_setting("bitcore_options", "bitcore_module_mcp", ["type" => "boolean"]);
    register_setting("bitcore_options", "bitcore_module_search", ["type" => "boolean"]);
    register_setting("bitcore_options", "bitcore_module_forms", ["type" => "boolean"]);
}
add_action("admin_init", "bitcore_register_settings");
