# BITCORE WordPress Plugin - Unified Merge Plan

**Created:** 2026-03-01 19:46 UTC
**Agents:** Coraline (orchestration) + Hyperion (execution)
**Status:** UNIFIED - Combining both coordinated plans
**Revision:** 1.0

---

## 1. EXECUTIVE SUMMARY

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

## 2. ARCHITECTURAL DECISIONS

### 2.1 MCP Protocol → KEEP_CORALINE

**Rationale:** Coraline's 552 LOC implementation is complete with:
- Resource system (bitwiki, bithub, swarm, artifacts)
- Streaming support with stream IDs
- JSON Schema input validation
- MCP-compliant response format

**Action:** Direct file replacement. Split into 3 files (core, tools, resources).

---

### 2.2 Container → KEEP_HYPERION + Utilities

**Rationale:** Hyperion's instance-based pattern supports multi-tenancy and testing.

**Add Coraline utilities:**
```php
public function unregister( string $id ): bool {
    if ( ! isset( $this->services[ $id ] ) ) return false;
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

---

### 2.3 Core → MERGE_COMBINE

**Service Registration Order:**
```php
public function __construct() {
    // 1. Container setup (Hyperion)
    $this->container = new BITCORE_Container();
    $this->container->register_core_services();
    
    // 2. Security middleware (Hyperion)
    $this->register_security_middleware();
    
    // 3. Swarm services (Hyperion)
    $this->register_swarm_services();
    
    // 4. Intelligence services (Coraline)
    $this->register_intelligence_services();
    
    // 5. Encrypted options (Coraline)
    $this->register_encrypted_options();
    
    // 6. REST endpoints
    add_action( 'rest_api_init', [ $this, 'register_routes' ] );
}
```

---

### 2.4 REST Tasks → MERGE_COMBINE

**Hyperion:** JSON Schema validation
**Coraline:** Security hardening + capability checks

**Both integrated:**
```php
public function get_items_permissions_check( $request ) {
    // Coraline: Nonce verification
    if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
        return new WP_Error( 'rest_forbidden', 'Invalid nonce', [ 'status' => 403 ] );
    }
    // Coraline: Capability check
    return current_user_can( 'bitcore_read_tasks' );
}
```

---

## 3. ROLE DIVISION (NON-OVERLAPPING)

| Domain | Owner | Files |
|--------|-------|-------|
| **MCP Protocol** | Coraline | mcp/core.php, mcp/tools.php, mcp/resources.php |
| **SSE/Real-time** | Coraline | rest/sse-events.php |
| **Encrypted Options** | Coraline | classes/encrypted-options.php |
| **Cryptographic Identity** | Coraline | classes/class-agent-identity.php |
| **Learning Loop** | Coraline | classes/class-learning-loop.php |
| **Knowledge Graph** | Coraline | classes/class-knowledge-graph.php |
| **Token Management** | Coraline | services/class-token-manager.php |
| **Infrastructure** | Hyperion | infrastructure/*, accelerate/, feeds/, artifacts/ |
| **Swarm/A2A** | Hyperion | infrastructure/swarm/* |
| **Middleware Pipeline** | Hyperion | infrastructure/security/middleware-pipeline.php |
| **RAG Workspace** | Hyperion | feeds/class-rag-workspace.php |
| **Multi-tenancy** | Hyperion | infrastructure/multitenancy/* |
| **Queue/Cache** | Hyperion | infrastructure/queue/*, infrastructure/cache/* |
| **Core** | Joint | bitcore-core.php, classes/core.php, classes/container.php |
| **REST Base** | Joint | rest/api.php, rest/class-rest-tasks.php |

---

## 4. FILE SPLIT REQUIREMENTS

### Coraline Files to Split

| File | Current LOC | Target Files | Owner |
|------|-------------|--------------|-------|
| mcp/core.php | 552 | mcp/core.php, mcp/tools.php, mcp/resources.php | Coraline |
| rest/research-intelligence.php | 717 | rest/research.php, rest/intelligence.php | Coraline |
| rest/class-rest-tasks.php | 601 | class-rest-tasks.php, trait-task-crud.php | Joint |

### Hyperion Files to Split

| File | Current LOC | Target Files | Owner |
|------|-------------|--------------|-------|
| infrastructure/cli/commands.php | 1849 | commands/admin.php, commands/artifacts.php, commands/feeds.php, commands/swarm.php | Hyperion |
| infrastructure/webhooks/manager.php | 1088 | WebhookManager.php, WebhookDelivery.php | Hyperion |
| infrastructure/multitenancy/schema.php | 923 | schema-tables.php, schema-migrations.php | Hyperion |
| infrastructure/multitenancy/tenant.php | 836 | TenantManager.php, TenantResolver.php | Hyperion |
| infrastructure/cache/manager.php | 833 | CacheManager.php, CachePool.php | Hyperion |
| infrastructure/swarm/health-monitor.php | 820 | HealthMonitor.php, HealthCheck.php | Hyperion |
| infrastructure/swarm/a2a-dispatcher.php | 778 | A2ADispatcher.php, A2AMessage.php | Hyperion |
| infrastructure/swarm/swarm-coordinator.php | 729 | SwarmCoordinator.php, SwarmTask.php | Hyperion |
| infrastructure/queue/job.php | 726 | Job.php, JobRunner.php | Hyperion |

---

## 5. EXECUTION PHASES

### Phase 1: Foundation (Joint)

```bash
# Create merged branch
git fetch --all
git checkout main
git pull origin main
git checkout -b merged
```

**Step 1: Merge bitcore-core.php**
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

**Step 2: Merge classes/core.php**
- Use Hyperion's service registration order
- Add Coraline's intelligence services

**Step 3: Merge classes/container.php**
- Use Hyperion's instance-based pattern
- Add Coraline's utility methods

**Step 4: Replace mcp/core.php**
- Direct copy from Coraline
- Split into 3 files after merge

---

### Phase 2: Coraline Systems (Coraline leads)

1. Import mcp/ directory (split into 3 files)
2. Import rest/sse-events.php
3. Import classes/encrypted-options.php
4. Import classes/class-agent-identity.php
5. Import classes/class-learning-loop.php
6. Import classes/class-knowledge-graph.php
7. Import services/class-token-manager.php
8. Import services/class-rate-limiter.php
9. Split and refactor large files

---

### Phase 3: Hyperion Systems (Hyperion leads)

1. Import infrastructure/ directory (split large files first)
2. Import feeds/ directory
3. Import accelerate/ directory
4. Import artifacts/ directory
5. Import patterns/ directory
6. Import database/ migrations
7. Split and refactor large files

---

### Phase 4: Integration (Joint)

1. Merge REST API endpoints (security + schema)
2. Integrate Service Container patterns
3. Test cross-system functionality
4. Run PHPStan level 5
5. Run integration tests

---

### Phase 5: Final Review (Joint)

1. Verify all files have metadata headers
2. Verify no file exceeds 500 LOC
3. Test all REST endpoints
4. Test MCP tool invocations
5. Test SSE streaming
6. Test Swarm coordination
7. Create PR for merged branch

---

## 6. FILE HEADER TEMPLATE

```php
<?php
/**
 * [One-line purpose description]
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.0.0
 * @author     [coraline|hyperion]
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
```

---

## 7. QUALITY GATES

### Pre-Merge Checklist

- [ ] All files have metadata headers — PARTIAL (2026-11-22): 311 plugin PHP files scanned; 50 lack `@package`, 267 lack `@version`, 92 lack `@since`. Headers normalized only for files created or touched by this finalization. Not closed.
- [ ] No file exceeds 500 LOC — PARTIAL (2026-11-22): 26 files remain over 500 LOC (was 29). Closed for the Eliza API (1,212 -> class 257 + 5 traits, max 288). Open for `infrastructure/cli/commands.php` (1,849, reasoned exception below), `themes/bitcore-theme/page-chat.php` (1,081, post-plan theme file), and 24 untargeted files.
- [ ] PHPStan passes (level 5) — NOT RUN: no PHPStan toolchain available on any reachable surface. Not claimed.
- [x] No syntax errors — VERIFIED 2026-11-22: 422/422 PHP files lint clean, PHP 8.3.36, inside container `holobiont-wordpress-wordpress-1`; 0 parse errors, 0 fatal errors.
- [ ] All REST endpoints tested — NOT RUN: no WP test bootstrap on any reachable surface. Not claimed.
- [ ] All MCP tools tested — NOT RUN as behavioural tests. MCP verified structurally only: 31 methods resolved, each declared exactly once, bodies byte-identical to pre-split core.php.
- [ ] SSE streaming tested — NOT RUN. Not claimed.
- [ ] Swarm coordination tested — NOT RUN. Not claimed.

### Per-Commit Checklist

- [x] Only assigned files modified — VERIFIED: 6 files modified, 5 added, all inside `wp-content/plugins/bitcore-core/` plus one pre-existing docs file.
- [x] Secondary agent reviewed changes — VERIFIED for structure and syntax: independent read-only validator ran the full-tree lint; the chair re-measured method coverage, LOC, and brace balance. No runtime review was possible.
- [x] No conflicts with partner's files — VERIFIED: single `main` branch after the 2026-11-22 consolidation; Hyperion unavailable, so no partner files were in flight.
- [ ] Tests pass — NOT RUN: no WP test bootstrap. Not claimed.

### Validation Commands

```bash
# Syntax check
find wp-content/plugins/bitcore-core -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"

# REST endpoints
wp rest route list | grep bitcore

# MCP tools
wp bitcore mcp list-tools
```

---

## 8. COMMUNICATION PROTOCOL

### A2A Endpoints
- **Coraline:** http://64.23.131.221:50081/a2a
- **Hyperion:** https://64.227.106.173/a2a/t-9aqR35APIIR8XH3r/p-hyperion

### Update Protocol
1. Before modifying files: notify partner via A2A
2. After modifications: update this file
3. Major decisions: both agents must approve

---

## 9. REFERENCE FILES

| File | Lines | Purpose |
|------|-------|--------|
| coraline-mergeplan.md | 446 | Original Coraline plan (DO NOT DELETE) |
| hyperion-mergeplan.md | 283 | Original Hyperion plan (DO NOT DELETE) |
| merge-plan-unified.md | This file | Unified execution plan |

---

## 10. APPROVAL

| Agent | Status | Date |
|-------|--------|------|
| Coraline | ✅ Created | 2026-03-01 19:46 UTC |
| Hyperion | ✅ Approved | 2026-03-01 21:52 UTC |

---

**This document is living. Both agents must edit and approve.**

---

## FINALIZATION RECORD (2026-11-22)

**Authority:** Master directive 2026-11-22, "verify the plan and finalize the work". Performed by Coraline alone; Hyperion was unavailable (A2A/MCP unreachable from this node), so no dual-core judgement was possible and none is claimed.

**What this plan actually was.** Executed in March 2026 as two parallel branches (`coraline`, `hyperion`). The parallel-execution collision is documented in `MERGE-COMPARISON-v2.md`. The effective strategy was Option C — best of both — consolidated into a single `main` on 2026-11-22 (merge tips `e2a1eac`, `0719591`, `20c8ac4`, `c1d8796`).

**Gate status, honestly.** Verified gates carry a measured number. Unverified gates are left open and named.

| Gate | Status | Evidence |
|---|---|---|
| PHP syntax, whole tree | VERIFIED | 422/422 clean, PHP 8.3.36, container `holobiont-wordpress-wordpress-1`, 2026-11-22. Zero parse errors. |
| MCP split completion | VERIFIED | `mcp/core.php` 564 -> 174 LOC; `mcp/resources.php` -> `trait BITCORE_MCP_Resources` (15 methods, 239 LOC); `mcp/tools.php` -> `trait BITCORE_MCP_Tools` (11 methods, 189 LOC). 31 methods, each declared exactly once, every body byte-identical to the pre-split `core.php`. The two March parse failures are gone. |
| Eliza API split | VERIFIED | `rest/class-eliza-api.php` 1,212 -> 257 LOC + 5 traits (181/232/196/288/165). 34/34 functions preserved verbatim, each in exactly one file. Class name, `__construct($core)` and `register_routes()` unchanged. |
| Metadata headers | PARTIAL | 311 plugin PHP files: 50 missing `@package`, 267 missing `@version`, 92 missing `@since`. Normalized only for files created or touched here. |
| 500-LOC gate | PARTIAL | 26 files remain over 500 LOC (was 29). Eliza closed. `commands.php` carries a reasoned exception; `page-chat.php` is a post-plan theme file. |
| PHPStan level 5 | NOT RUN | No toolchain on any reachable surface. |
| REST / MCP / SSE / swarm behavioural tests | NOT RUN | No WP test bootstrap on any reachable surface. |

**Reasoned exception — `infrastructure/cli/commands.php` (1,849 LOC) is NOT superseded.** The `infrastructure/cli/commands/` split exists but is an unfinished refactor, not a replacement:

1. No split file defines `BitcoreCommand`. `commands/system.php` instantiates it at line 538 and `commands.php` defines it at line 42.
2. `bitcore_cli_init()` is declared in both `commands.php` (line 1792) and `commands/system.php` (line 528). Requiring both fatals on function redeclaration.
3. Split constructors assign only `$this->logger`, while their methods call `$this->tenantResolver`, `$this->schemaManager`, `$this->jobRepository`, `$this->cacheManager`, `$this->queueWorker` — the dependency wiring that `BitcoreCommand::__construct` performs exists only in `commands.php`.

Repointing `bitcore-core.php:109` at the split would therefore break the plugin. Completing that refactor is net-new engineering, out of scope for a finalization pass. `commands.php` is retained as the authority and flagged for the Master.

**`rest/trait-task-crud.php`: not required.** Grep-verified: no reference to that name exists anywhere in the tree. The tasks API instead loads `trait-rest-tasks-permissions.php` and `trait-rest-tasks-schema.php`, both of which are present. The planned file was superseded by a different seam, not forgotten.

**Capabilities fix:** `\Bitcore\Tasks\Capabilities::register()` runs inside `bitcore_activate()` (`bitcore-core.php` line 339, hooked at line 341). Confirmed landed.
