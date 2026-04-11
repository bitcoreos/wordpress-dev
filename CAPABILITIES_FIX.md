# WordPress Capabilities Registration Fix

## Problem
User 'coraline' (ID: 3) has role 'administrator' but is missing custom capabilities:
- bitcore_read_tasks
- bitcore_create_tasks
- bitcore_edit_tasks
- bitcore_delete_tasks
- bitcore_dispatch_tasks
- bitcore_manage_tasks

## Root Cause
`bitcore_activate()` function in `bitcore-core.php` did NOT call `Capabilities::register()`.

## Fix Options

### Option A: WP-CLI Eval (Immediate Fix - No Code Change Required)
```bash
# SSH to ASHI, then run inside Docker container:
wp eval "require_once '/var/www/html/wp-content/plugins/bitcore-core/classes/class-capabilities.php'; \\Bitcore\\Tasks\\Capabilities::register();"

# Verify:
wp user list-caps coraline | grep bitcore
```

### Option B: Deactivate/Reactivate Plugin (Requires Code Patch First)
```bash
# After patching bitcore-core.php:
wp plugin deactivate bitcore-core
wp plugin activate bitcore-core

# Verify:
wp user list-caps coraline | grep bitcore
```

### Option C: One-Time REST Endpoint
Add to functions.php temporarily, then call via curl with Application Password:
```php
add_action('rest_api_init', function() {
    register_rest_route('bitcore/v1', '/fix-caps', [
        'methods' => 'POST',
        'callback' => function() {
            require_once BITCORE_PLUGIN_DIR . 'classes/class-capabilities.php';
            \Bitcore\Tasks\Capabilities::register();
            return ['status' => 'ok', 'caps_registered' => \Bitcore\Tasks\Capabilities::get_capabilities()];
        },
        'permission_callback' => function() { return current_user_can('manage_options'); }
    ]);
});
```

```bash
curl -X POST "https://ashi.bitwiki.org/wp-json/bitcore/v1/fix-caps" \
  -u "hyperion:YOUR_APP_PASSWORD"
```

## Code Patch Applied (Local)

File: `bitcore-core.php` (lines 277-281)

```php
    // Flush rewrite rules
    flush_rewrite_rules();

    // Register custom capabilities (Hyperion)
    require_once BITCORE_PLUGIN_DIR . 'classes/class-capabilities.php';
    \Bitcore\Tasks\Capabilities::register();
}
register_activation_hook(__FILE__, 'bitcore_activate');
```

## Verification Commands
```bash
# List all capabilities for user 'coraline'
wp user list-caps coraline

# Check specific capabilities
wp eval "print_r(array_keys(get_user_by('id', 3)->allcaps));"
```

---
*Created: 2026-03-30*
*Status: PATCH READY, AWAITING EXECUTION*
