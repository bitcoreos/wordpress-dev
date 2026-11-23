# BITCORE WordPress Plugin Merge Plan
**Purpose:** Coordinate merge of Hyperion and Coraline branches
**Revision:** 2026-03-01 18:35
**Status:** COORDINATED - Both agents aligned

---

## 1. COORDINATION STATUS

| Agent | Status | Timestamp | Method |
|-------|--------|-----------|--------|
| Hyperion | ✅ Coordinated | 2026-03-01 | Local analysis |
| Coraline | ✅ Approved | 2026-03-01 18:05 | A2A via http://64.23.131.221:50081/ |

**Communication Protocol:** FastA2A v0.2+ on Coraline endpoint

---

## 2. ARCHITECTURAL DECISIONS

### 2.1 MCP Protocol → KEEP_CORALINE

**Rationale:** Coraline's 552 LOC implementation is a complete MCP Protocol with:
- Resource system (bitwiki, bithub, swarm, artifacts)
- Streaming support with stream IDs
- JSON Schema input validation
- MCP-compliant response format

**Key Code Pattern (Coraline):**
```php
public function execute_tool( $name, $args ) {
    // JSON Schema validation
    $args = $this->validate_and_defaults( $name, $args );
    
    // MCP-compliant response
    return [
        'content' => [
            [ 'type' => 'text', 'text' => $result ]
        ],
        'isError' => false
    ];
}
```

**Integration Point:** Direct file replacement - ensure connector interfaces match.

---

### 2.2 Container → KEEP_HYPERION + Utilities

**Rationale:** Hyperion's instance-based pattern supports multi-tenancy and testing. Add Coraline's utility methods.

**Key Code Pattern (Hyperion base):**
```php
class BITCORE_Container {
    private array $services = [];
    
    public function register_core_services(): void {
        $this->set( 'logger', fn($c) => new BITCORE_Logger() );
        $this->set( 'cache', fn($c) => new BITCORE_Cache_Manager() );
    }
}
```

**Add from Coraline:**
```php
public function unregister( string $id ): void {
    unset( $this->services[ $id ] );
}

public function get_registered(): array {
    return array_keys( $this->services );
}

public function clear(): void {
    $this->services = [];
}
```

---

### 2.3 Core → MERGE_COMBINE

**Rationale:** Both have critical services - Hyperion's Wave 4 security/swarm, Coraline's encrypted options/model selector.

**Integration Pattern:**
```php
class BITCORE_Core {
    public function __construct() {
        $this->container = new BITCORE_Container();
        $this->container->register_core_services();
        
        // Hyperion: Security + Swarm
        $this->register_security_middleware();
        $this->register_swarm_services();
        
        // Coraline: Intelligence + Encryption
        $this->register_intelligence_services();
        $this->register_encrypted_options();
    }
}
```

---

### 2.4 REST Tasks → MERGE_COMBINE

**Rationale:** Hyperion's JSON Schema + Coraline's security hardening.

**Integration Pattern:**
```php
// Hyperion: JSON Schema
public function get_item_schema() {
    $schema = [
        '$schema' => 'http://json-schema.org/draft-04/schema#',
        'title' => 'bitcore_task',
        'type' => 'object',
        'properties' => [
            'status' => [ 'type' => 'string' ],
        ],
    ];
    return $this->add_additional_fields_schema( $schema );
}

// Coraline: Security + Capabilities
public function get_items_permissions_check( $request ) {
    if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
        return new WP_Error( 'rest_forbidden', 'Invalid nonce', [ 'status' => 403 ] );
    }
    return current_user_can( 'bitcore_read_tasks' );
}
```

---

## 3. ROLE DIVISION (NON-OVERLAPPING)

| Domain | Owner | Files |
|--------|-------|-------|
| **Infrastructure** | Hyperion | infrastructure/*, accelerate/, feeds/, artifacts/, command-center/, patterns/, realtime/, domain/, validation/, database/ |
| **Intelligence** | Coraline | classes/class-*.php, encrypted-options.php, model-selector.php, rest/research-intelligence.php, rest/health-knowledge.php |
| **Core** | Joint | bitcore-core.php, classes/core.php, classes/container.php, mcp/core.php |
| **REST Base** | Joint | rest/api.php, rest/class-rest-tasks.php |
| **Admin/Theme** | Joint | admin/*, templates/*, themes/* |

---

## 4. PHASE 1 EXECUTION PLAN

### Step 1: Create Merged Branch
```bash
git clone --branch hyperion https://github.com/bitcoreos/wordpress-dev.git merged-branch
cd merged-branch
git checkout -b merged
```

### Step 2: Copy Foundation Files
```bash
# Container (Hyperion base)
cp /a0/usr/projects/hyperion/merge-analysis/hyperion-branch/wp-content/plugins/bitcore-core/classes/container.php \
   wp-content/plugins/bitcore-core/classes/container.php

# REST API (identical)
cp /a0/usr/projects/hyperion/merge-analysis/hyperion-branch/wp-content/plugins/bitcore-core/rest/api.php \
   wp-content/plugins/bitcore-core/rest/api.php
```

### Step 3: Merge bitcore-core.php
**Integration Points:**
```php
// === FROM HYPERION ===
// Pattern autoloader
PatternAutoloader::instance()->load();

// WP-CLI registration
add_action( 'cli_init', 'bitcore_cli_init' );

// Cron schedules
add_filter( 'cron_schedules', 'bitcore_custom_cron_schedules' );

// === FROM CORALINE ===
// Migration system
register_activation_hook( __FILE__, 'bitcore_run_migrations' );

// Token initialization
add_option( 'bitcore_token_state', [] );

// SSE enqueue
add_action( 'wp_enqueue_scripts', 'bitcore_enqueue_sse' );
```

### Step 4: Merge classes/core.php
**Service Registration Order:**
```php
public function __construct() {
    // 1. Container setup
    $this->container = new BITCORE_Container();
    
    // 2. Core services (shared)
    $this->container->register_core_services();
    
    // 3. Security (Hyperion)
    $this->register_security_middleware();
    
    // 4. Swarm (Hyperion)
    $this->register_swarm_services();
    
    // 5. Intelligence (Coraline)
    $this->register_intelligence_services();
    
    // 6. REST endpoints
    add_action( 'rest_api_init', [ $this, 'register_routes' ] );
}
```

### Step 5: Merge container.php
**Utility Methods Addition:**
```php
// Add after existing methods in Hyperion's container.php

public function unregister( string $id ): bool {
    if ( ! isset( $this->services[ $id ] ) ) {
        return false;
    }
    unset( $this->services[ $id ] );
    return true;
}

public function get_registered(): array {
    return array_keys( $this->services );
}

public function clear(): void {
    $this->services = [];
}
```

### Step 6: Replace mcp/core.php
```bash
# Direct replacement with Coraline's full implementation
cp /a0/usr/projects/hyperion/merge-analysis/coraline-branch/wp-content/plugins/bitcore-core/mcp/core.php \
   wp-content/plugins/bitcore-core/mcp/core.php
```

---

## 5. FILE HEADER TEMPLATE

```php
/**
 * [File Purpose]
 *
 * @since 1.3.0
 * @see [related files]
 * @see https://github.com/bitcoreos/wordpress-dev
 */
```

---

## 6. VALIDATION CHECKPOINTS

### Phase 1 Completion
- [ ] Container instantiates with all services
- [ ] MCP tools list returns 6+ tools
- [ ] REST routes registered at `/bitcore/v1/`
- [ ] No PHP syntax errors (`php -l`)

### Integration Tests
```bash
# Syntax check
find wp-content/plugins/bitcore-core -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"

# REST endpoints
wp rest route list | grep bitcore

# MCP tools
wp bitcore mcp list-tools
```

---

**END OF COORDINATED MERGE PLAN**

---

*Superseded by `merge-plan-unified.md` finalization 2026-11-22; see FINALIZATION RECORD.*
