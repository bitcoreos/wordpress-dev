#!/bin/bash
# Fix script to be run INSIDE wordpress-app Docker container
# Fixes: WebhookManager fatal error + Missions_Api route registration

PLUGIN_DIR="/var/www/html/wp-content/plugins/bitcore-core"

echo "=== Fixing core.php WebhookManager registration ==="

# Keep backup
cp "$PLUGIN_DIR/classes/core.php" "$PLUGIN_DIR/classes/core.php.bak2"

# Replace the WebhookManager registration with a commented version
# Line pattern: $this->container->register('webhooks.manager', ...)
sed -i 's|require_once BITCORE_PATH . '\''infrastructure/webhooks/WebhookManager.php'\'';|// DISABLED: require_once BITCORE_PATH . '\''infrastructure/webhooks/WebhookManager.php'\'';|' "$PLUGIN_DIR/classes/core.php"

sed -i 's|$this->container->register('\''webhooks.manager'\'', function() { return new \\BITCORE\\Infrastructure\\Webhooks\\WebhookManager(); });|// DISABLED: webhooks.manager requires 4 constructor args not available|' "$PLUGIN_DIR/classes/core.php"

echo "WebhookManager disabled in core.php"

echo ""
echo "=== Fixing bitcore-core.php Missions_Api registration ==="

cp "$PLUGIN_DIR/bitcore-core.php" "$PLUGIN_DIR/bitcore-core.php.bak2"

# Check if the closure already exists
if grep -q "Missions_Api" "$PLUGIN_DIR/bitcore-core.php"; then
    echo "Missions_Api already registered"
else
    # Add to the closure that already has MCP_Tools + Coordination
    # Find the closure and add Missions_Api inside it
    awk '
/Coordination_API.*register_routes/{ 
    print
    print "    if (class_exists(\"\\BITCORE\\Rest\\Missions_Api\")) {"
    print "        (new \\BITCORE\\Rest\\Missions_Api())->register_routes();"
    print "    }"
    next
}
{print}
' "$PLUGIN_DIR/bitcore-core.php" > /tmp/bitcore-core-patched.php
    mv /tmp/bitcore-core-patched.php "$PLUGIN_DIR/bitcore-core.php"
    echo "Missions_Api registration added"
fi

echo ""
echo "=== Syntax validation ==="
php -l "$PLUGIN_DIR/classes/core.php"
php -l "$PLUGIN_DIR/bitcore-core.php"
echo ""
echo "All fixes applied"
