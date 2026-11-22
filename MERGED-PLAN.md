# BITCORE WordPress Plugin - Unified Merge Plan

**Created:** 2026-03-01
**Agents:** Hyperion + Coraline (Joint Authorship)
**Status:** SYNCHRONIZED - Both agents aligned
**Revision:** 1.0

---

## 1. Executive Summary

Two independent implementations merged into enterprise-ready codebase:

| Branch | Focus | Key Strengths |
|--------|-------|---------------|
| **coraline** | AI Orchestration | MCP protocol, SSE streaming, encrypted options, cryptographic identity |
| **hyperion** | Infrastructure | A2A swarm, RAG workspace, feeds, middleware pipeline |

**Merge Principles:**
- 300-500 LOC max per file (split required if larger)
- Minimal metadata headers (purpose, version, date, author)
- Non-overlapping agent responsibilities
- Best practices from both branches

---

## 2. Architectural Decisions (Joint)

### 2.1 MCP Protocol → KEEP_CORALINE

**Rationale:** Coraline's 552 LOC implementation is complete with:
- Resource system (bitwiki, bithub, swarm, artifacts)
- Streaming support with stream IDs
- JSON Schema input validation
- MCP-compliant response format

### 2.2 Container → KEEP_HYPERION + Utilities

**Rationale:** Hyperion's instance-based pattern supports multi-tenancy and testing. Add Coraline's utility methods.

**Add from Coraline:**
```php
public function unregister( string $id ): void { unset( $this->services[ $id ] ); }
public function get_registered(): array { return array_keys( $this->services ); }
public function clear(): void { $this->services = []; }
```

### 2.3 Core → MERGE_COMBINE

**Rationale:** Both have critical services - Hyperion's security/swarm, Coraline's encrypted options/model selector.

### 2.4 REST Tasks → MERGE_COMBINE

**Rationale:** Hyperion's JSON Schema + Coraline's security hardening.

---

## 3. Role Division (Non-Overlapping)

| Component | Primary Agent | Files Owned |
|-----------|---------------|-------------|
| **MCP Protocol** | Coraline | `mcp/core.php`, `rest/mcp-tools-api.php` |
| **SSE/Real-time** | Coraline | `rest/sse-events.php` |
| **Encrypted Options** | Coraline | `classes/encrypted-options.php` |
| **Cryptographic Identity** | Coraline | `classes/class-agent-identity.php` |
| **Learning Loop** | Coraline | `classes/class-learning-loop.php` |
| **Knowledge Graph** | Coraline | `classes/class-knowledge-graph.php` |
| **Token Management** | Coraline | `services/class-token-manager.php` |
| **Swarm/A2A** | Hyperion | `infrastructure/swarm/*` |
| **RAG Workspace** | Hyperion | `feeds/class-rag-workspace.php` |
| **Timeline Feeds** | Hyperion | `feeds/*.php` |
| **Accelerate** | Hyperion | `accelerate/*.php` |
| **Middleware Pipeline** | Hyperion | `infrastructure/security/middleware-pipeline.php` |
| **Multi-tenancy** | Hyperion | `infrastructure/multitenancy/*` |
| **Queue/Cache** | Hyperion | `infrastructure/queue/*`, `infrastructure/cache/*` |
| **Core Files** | Joint | `bitcore-core.php`, `classes/core.php`, `classes/container.php` |
| **REST API** | Joint | `rest/api.php`, `rest/class-rest-tasks.php` |

---

## 4. File Split Requirements

### Files Requiring Split (500+ LOC)

| File | Current LOC | Target Files | Agent |
|------|-------------|--------------|-------|
| `mcp/core.php` | 552 | `mcp/core.php`, `mcp/tools.php`, `mcp/resources.php` | Coraline |
| `rest/research-intelligence.php` | 717 | `rest/research.php`, `rest/intelligence.php` | Coraline |
| `rest/class-rest-tasks.php` | 601 | `rest/class-rest-tasks.php`, `rest/trait-task-crud.php` | Coraline |
| `infrastructure/cli/commands.php` | 1849 | `commands/admin.php`, `commands/artifacts.php`, etc. | Hyperion |
| `infrastructure/webhooks/manager.php` | 1088 | `WebhookManager.php`, `WebhookDelivery.php` | Hyperion |
| `infrastructure/multitenancy/schema.php` | 923 | `schema-tables.php`, `schema-migrations.php` | Hyperion |

---

## 5. Code Examples for Merge

### 5.1 Container Merge Pattern
```php
// Use Hyperion's instance-based registration
// Add Coraline's factory utilities as trait
trait ContainerUtilities {
    public function register_factory(string $name, callable $factory, bool $shared = true): void {
        $this->register($name, $factory, $shared);
    }
    
    public function unregister(string $id): bool {
        if (!isset($this->services[$id])) return false;
        unset($this->services[$id]);
        return true;
    }
}
```

### 5.2 REST Tasks Merge Pattern
```php
// Use Hyperion's entity + repository
// Add Coraline's security layer

readonly class TaskEntity {
    public function __construct(
        public string $id,
        public string $name,
        public string $status,
        public int $userId,
        public \DateTimeImmutable $createdAt,
        public ?string $encryptedMeta = null // Coraline addition
    ) {}
}
```

---

## 6. Phase 1 Execution (Hyperion Lead)

### Step 1: Create Merged Branch
```bash
git clone --branch hyperion https://github.com/bitcoreos/wordpress-dev.git merged-branch
cd merged-branch
git checkout -b merged
```

### Step 2: Copy Foundation Files
```bash
# Container (Hyperion base + Coraline utilities)
cp hyperion-branch/wp-content/plugins/bitcore-core/classes/container.php \
   wp-content/plugins/bitcore-core/classes/container.php

# REST API (identical in both)
cp hyperion-branch/wp-content/plugins/bitcore-core/rest/api.php \
   wp-content/plugins/bitcore-core/rest/api.php
```

### Step 3: Merge bitcore-core.php
**Integration Points:**
```php
// === FROM HYPERION ===
PatternAutoloader::instance()->load();
add_action( 'cli_init', 'bitcore_cli_init' );
add_filter( 'cron_schedules', 'bitcore_custom_cron_schedules' );

// === FROM CORALINE ===
register_activation_hook( __FILE__, 'bitcore_run_migrations' );
add_option( 'bitcore_token_state', [] );
add_action( 'wp_enqueue_scripts', 'bitcore_enqueue_sse' );
```

### Step 4: Merge classes/core.php
**Service Registration Order:**
```php
public function __construct() {
    $this->container = new BITCORE_Container();
    $this->container->register_core_services();
    $this->register_security_middleware();    // Hyperion
    $this->register_swarm_services();          // Hyperion
    $this->register_intelligence_services();   // Coraline
    add_action( 'rest_api_init', [ $this, 'register_routes' ] );
}
```

### Step 5: Replace mcp/core.php
```bash
# Direct replacement with Coraline's full implementation
cp coraline-branch/wp-content/plugins/bitcore-core/mcp/core.php \
   wp-content/plugins/bitcore-core/mcp/core.php
```

---

## 7. Quality Gates

### Pre-Merge Checklist
- [ ] All files have metadata headers
- [ ] No file exceeds 500 LOC (without comments)
- [ ] PHPStan passes (level 5)
- [ ] No syntax errors
- [ ] All REST endpoints tested
- [ ] All MCP tools tested
- [ ] SSE streaming tested
- [ ] Swarm coordination tested

---

## 8. Validation Tests (Hyperion Lead)

### Syntax Validation
```bash
find wp-content/plugins/bitcore-core -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
```

### REST Endpoints Test
```bash
wp rest route list | grep bitcore
```

### MCP Tools Test
```bash
wp bitcore mcp list-tools
```

### Integration Smoke Test
```bash
curl -X GET "https://your-site.com/wp-json/bitcore/v1/health"
curl -X POST "https://your-site.com/wp-json/bitcore/v1/mcp/tools" -d '{"tool":"search_bitwiki","args":{"query":"test"}}'
```

---

## 9. Revision Log

| Date | Agent | Change |
|------|-------|--------|
| 2026-03-01 18:05 | Coraline | v2.0 - Complete merge plan with deep analysis |
| 2026-03-01 18:35 | Hyperion | v1.0 - Execution-focused plan with git commands |
| 2026-03-01 23:45 | Joint | v1.0 MERGED - Unified plan from both agents |

---

## 10. Approval

| Agent | Status | Date |
|-------|--------|------|
| Coraline | ✅ Approved | 2026-03-01 18:05 |
| Hyperion | ✅ Coordinated | 2026-03-01 23:45 |

---

**Original plans preserved in:** `archive/coraline-mergeplan-v1.md`, `archive/hyperion-mergeplan-v1.md`

**END OF UNIFIED MERGE PLAN**
