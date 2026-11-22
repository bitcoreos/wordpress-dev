# Merge Plan: coraline + hyperion → merged

**Created:** 2026-03-01 18:05 UTC
**Agents:** Coraline (orchestration) + Hyperion (execution)
**Status:** COORDINATED - Both agents aligned
**Revision:** 2.0

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

## 2. Deep Analysis Results

### 2.1 Coraline Branch - Systems

| System | Implementation | Files | Status |
|--------|----------------|-------|--------|
| **MCP Protocol** | Tool registration with JSON schema, resource handlers | `mcp/core.php` | ✅ Complete |
| **SSE Streaming** | Transient-based event broadcasting | `rest/sse-events.php` | ✅ Complete |
| **Knowledge Graph** | Node/edge storage with traversal | `classes/class-knowledge-graph.php` | ✅ Complete |
| **Context Store** | RAG vector storage with embeddings | `classes/class-context-store.php` | ✅ Complete |
| **Encrypted Options** | AES-256-GCM with key rotation | `classes/encrypted-options.php` | ✅ Complete |
| **Cryptographic Identity** | RSA signature verification for agents | `classes/class-agent-identity.php` | ✅ Complete |
| **Learning Loop** | Episode scoring, recommendations | `classes/class-learning-loop.php` | ✅ Complete |
| **Token Rotation** | Zero-downtime with grace period | `services/class-token-manager.php` | ✅ Complete |
| **Rate Limiting** | Transient-based, configurable | `services/class-rate-limiter.php` | ✅ Complete |
| **Service Container** | Singleton DI pattern | `classes/container.php` | ✅ Complete |

### 2.2 Hyperion Branch - Systems

| System | Implementation | Files | Status |
|--------|----------------|-------|--------|
| **A2A Swarm** | Task distribution, load balancing, health monitoring | `infrastructure/swarm/*` | ✅ Complete |
| **Middleware Pipeline** | PSR-15 style request processing | `infrastructure/security/middleware-pipeline.php` | ✅ Complete |
| **RAG Workspace** | Document storage, embeddings, vector search | `feeds/class-rag-workspace.php` | ✅ Complete |
| **Timeline Feeds** | Content aggregation, trend detection, clustering | `feeds/feeds.php`, `feeds/*.php` | ✅ Complete |
| **Accelerate Engine** | Content distillation strategies | `accelerate/accelerate.php` | ✅ Complete |
| **Multi-tenancy** | Tenant context, schema isolation | `infrastructure/multitenancy/*` | ✅ Complete |
| **Repository Pattern** | Generic RepositoryInterface<T> | `patterns/repository.php` | ✅ Complete |
| **Structured Logging** | PSR-3 style with handlers | `infrastructure/logging/Logger.php` | ✅ Complete |
| **Queue System** | Background job processing | `infrastructure/queue/*` | ✅ Complete |
| **Cache Manager** | PSR-6 cache pool | `infrastructure/cache/*` | ✅ Complete |

### 2.3 Overlapping Systems

| System | Coraline | Hyperion | Resolution |
|--------|----------|----------|------------|
| Service Container | Singleton + factories | Instance-based | Use Hyperion's instance-based + Coraline utilities |
| REST Tasks | Security-focused | Schema-focused | Merge Hyperion schema + Coraline security |
| MCP Core | 552 LOC, complete | Not present | Use Coraline implementation |
| REST API Base | Identical | Identical | No conflict - merge directly |
| Autoloader | Simple PSR-4 | Pattern autoloader | Use Hyperion's pattern autoloader |

---

## 3. Agent Role Assignments

### 3.1 Primary Responsibilities (Non-Overlapping)

| Component | Primary Agent | Secondary Agent | Files Owned |
|-----------|---------------|-----------------|-------------|
| **MCP Protocol** | Coraline | Hyperion (review) | `mcp/core.php`, `rest/mcp-tools-api.php` |
| **SSE/Real-time** | Coraline | Hyperion (review) | `rest/sse-events.php` |
| **Encrypted Options** | Coraline | Hyperion (review) | `classes/encrypted-options.php` |
| **Cryptographic Identity** | Coraline | Hyperion (review) | `classes/class-agent-identity.php` |
| **Learning Loop** | Coraline | Hyperion (review) | `classes/class-learning-loop.php` |
| **Knowledge Graph** | Coraline | Hyperion (review) | `classes/class-knowledge-graph.php` |
| **Token Management** | Coraline | Hyperion (review) | `services/class-token-manager.php` |
| **Swarm/A2A** | Hyperion | Coraline (review) | `infrastructure/swarm/*` |
| **RAG Workspace** | Hyperion | Coraline (review) | `feeds/class-rag-workspace.php` |
| **Timeline Feeds** | Hyperion | Coraline (review) | `feeds/*.php` |
| **Accelerate** | Hyperion | Coraline (review) | `accelerate/*.php` |
| **Middleware Pipeline** | Hyperion | Coraline (review) | `infrastructure/security/middleware-pipeline.php` |
| **Multi-tenancy** | Hyperion | Coraline (review) | `infrastructure/multitenancy/*` |
| **Queue/Cache** | Hyperion | Coraline (review) | `infrastructure/queue/*`, `infrastructure/cache/*` |
| **Repository Pattern** | Hyperion | Coraline (review) | `patterns/repository.php` |
| **Structured Logging** | Hyperion | Coraline (review) | `infrastructure/logging/*` |

### 3.2 Joint Responsibilities

| Component | Owner | Reviewer | Notes |
|-----------|-------|----------|-------|
| `bitcore-core.php` | Joint | Joint | Main plugin loader - both modify |
| `classes/core.php` | Joint | Joint | Core initialization - both modify |
| `classes/container.php` | Joint | Joint | DI container - merge both patterns |
| REST API endpoints | Joint | Joint | Merge security + schema patterns |

---

## 4. File Split Requirements

### 4.1 Files Requiring Split (500+ LOC)

#### Coraline Files to Split

| File | Current LOC | Target Files | Agent |
|------|-------------|--------------|-------|
| `mcp/core.php` | 552 | `mcp/core.php`, `mcp/tools.php`, `mcp/resources.php` | Coraline |
| `rest/research-intelligence.php` | 717 | `rest/research.php`, `rest/intelligence.php`, `rest/workspace.php` | Coraline |
| `rest/class-rest-tasks.php` | 601 | `rest/class-rest-tasks.php`, `rest/trait-task-crud.php`, `rest/trait-task-utils.php` | Coraline |

#### Hyperion Files to Split

| File | Current LOC | Target Files | Agent |
|------|-------------|--------------|-------|
| `infrastructure/cli/commands.php` | 1849 | `commands/admin.php`, `commands/artifacts.php`, `commands/feeds.php`, `commands/swarm.php` | Hyperion |
| `infrastructure/webhooks/manager.php` | 1088 | `WebhookManager.php`, `WebhookDelivery.php`, `SignatureVerifier.php` | Hyperion |
| `infrastructure/multitenancy/schema.php` | 923 | `schema-tables.php`, `schema-migrations.php` | Hyperion |
| `infrastructure/multitenancy/tenant.php` | 836 | `TenantManager.php`, `TenantResolver.php` | Hyperion |
| `infrastructure/cache/manager.php` | 833 | `CacheManager.php`, `CachePool.php` | Hyperion |
| `infrastructure/swarm/health-monitor.php` | 820 | `HealthMonitor.php`, `HealthCheck.php` | Hyperion |
| `infrastructure/swarm/a2a-dispatcher.php` | 778 | `A2ADispatcher.php`, `A2AMessage.php` | Hyperion |
| `infrastructure/swarm/swarm-coordinator.php` | 729 | `SwarmCoordinator.php`, `SwarmTask.php` | Hyperion |
| `infrastructure/queue/job.php` | 726 | `Job.php`, `JobRunner.php` | Hyperion |

---

## 5. Metadata Header Standard

### 5.1 Required Format

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

### 5.2 Rules
- One-line purpose only (no fluff)
- No `@subpackage`, `@license`, `@copyright` (bloat)
- `@author` indicates which agent owns the file
- `@since` is merge date
- `@version` follows semver

---

## 6. Code Examples for Merge

### 6.1 Service Container Merge

**From Coraline (utilities to integrate):**
```php
// Coraline's factory pattern
$this->container
    ->register('encrypted_options', function() {
        return new BITCORE_Encrypted_Options();
    })
    ->set('core', $this);
```

**From Hyperion (instance-based to use):**
```php
// Hyperion's instance registration
$this->container->register('logger', '\\BITCORE\\Infrastructure\\Logging\\Logger');
$this->container->register('cache.manager', '\\BITCORE\\Infrastructure\\Cache\\CacheManager');
```

**Merged approach:**
```php
// Use Hyperion's instance-based registration
// Add Coraline's factory utilities as trait
trait ContainerUtilities {
    public function register_factory(string $name, callable $factory, bool $shared = true): void {
        $this->register($name, $factory, $shared);
    }
}
```

### 6.2 REST Tasks Merge

**From Coraline (security pattern):**
```php
// Permission callback with capability check
'permission_callback' => function($request) {
    return current_user_can('bitcore_read_tasks');
},

// Encrypted option storage
$task_data['encrypted_meta'] = $this->core->encrypt($meta);
```

**From Hyperion (schema pattern):**
```php
// TaskEntity with readonly properties
readonly class TaskEntity {
    public function __construct(
        public string $id,
        public string $name,
        public string $status,
        public int $userId,
        public \DateTimeImmutable $createdAt
    ) {}
}

// Repository pattern
$task = $this->taskRepository->find($id);
```

**Merged approach:**
```php
// Use Hyperion's entity + repository
// Add Coraline's security layer

readonly class TaskEntity {
    // ... Hyperion properties
    public ?string $encryptedMeta = null; // Coraline addition
}

class TaskRepository extends AbstractRepository {
    public function findSecure(string $id, int $userId): ?TaskEntity {
        $task = $this->find($id);
        if ($task && $task->userId !== $userId) {
            return null; // Security: user isolation
        }
        return $task;
    }
}
```

### 6.3 MCP Tool Registration (Coraline - Preserve)

```php
/**
 * MCP tool registration with JSON schema
 */
public function register_tool(string $name, array $config): void {
    $this->tools[$name] = [
        'description' => $config['description'],
        'parameters' => $config['parameters'],
        'callback' => $config['callback'],
        'streaming' => $config['streaming'] ?? false,
    ];
}

// Usage example
$mcp->register_tool('search_bitwiki', [
    'description' => 'Search BITwiki knowledge base',
    'parameters' => [
        'query' => ['type' => 'string', 'required' => true],
        'limit' => ['type' => 'integer', 'default' => 10],
    ],
    'callback' => [$connector, 'search'],
]);
```

### 6.4 SSE Broadcasting (Coraline - Preserve)

```php
/**
 * SSE event broadcasting with transient storage
 */
public static function broadcast(string $type, array $payload): void {
    $event = [
        'type' => $type,
        'payload' => $payload,
        'timestamp' => time(),
    ];
    
    foreach (self::$clients as $client_id => $connected_time) {
        $events = get_transient("bitcore_sse_events_{$client_id}") ?: [];
        $events[] = $event;
        set_transient("bitcore_sse_events_{$client_id}", $events, 60);
    }
}

// Event types
// task_created, task_updated, agent_spawned, activity_logged
```

### 6.5 Swarm Coordinator (Hyperion - Preserve)

```php
/**
 * Task distribution with load balancing
 */
public function selectAgentForTask(SwarmTask $task): ?AgentDescriptor {
    $candidates = $this->getAgentsByCapabilities($task->requiredCapabilities);
    
    usort($candidates, function($a, $b) {
        $scoreA = $a->currentLoad * 10 + $a->priority;
        $scoreB = $b->currentLoad * 10 + $b->priority;
        return $scoreA <=> $scoreB;
    });
    
    return $candidates[0] ?? null;
}
```

### 6.6 RAG Workspace (Hyperion - Preserve)

```php
/**
 * Document storage with embeddings
 */
public function store_document(string $content, array $metadata = []): int {
    $document_id = $this->insert_document($content, $metadata);
    $this->build_index($document_id, $content);
    return $document_id;
}

public function query(string $query, array $options = []): array {
    $embedding = $this->generate_embedding($query);
    $chunks = $this->find_similar_chunks($embedding, $options['limit'] ?? 5);
    return $this->build_context($chunks);
}
```

---

## 7. Merge Execution Phases

### Phase 1: Foundation (Joint)
1. Create `merged` branch from `main`
2. Merge `bitcore-core.php` with both initializations
3. Merge `classes/core.php` with combined services
4. Merge `classes/container.php` with utilities trait
5. Add metadata headers to all foundation files

### Phase 2: Coraline Systems (Coraline leads)
1. Import `mcp/` directory (split into 3 files)
2. Import `rest/sse-events.php`
3. Import `classes/encrypted-options.php`
4. Import `classes/class-agent-identity.php`
5. Import `classes/class-learning-loop.php`
6. Import `classes/class-knowledge-graph.php`
7. Import `classes/class-context-store.php`
8. Import `services/class-token-manager.php`
9. Import `services/class-rate-limiter.php`
10. Split and refactor large files

### Phase 3: Hyperion Systems (Hyperion leads)
1. Import `infrastructure/` directory (split large files first)
2. Import `feeds/` directory
3. Import `accelerate/` directory
4. Import `artifacts/` directory
5. Import `command-center/` directory
6. Import `patterns/` directory
7. Import `database/` migrations
8. Split and refactor large files

### Phase 4: Integration (Joint)
1. Merge REST API endpoints (security + schema)
2. Integrate Service Container patterns
3. Test cross-system functionality
4. Run PHPStan level 5
5. Run integration tests

### Phase 5: Final Review (Joint)
1. Verify all files have metadata headers
2. Verify no file exceeds 500 LOC
3. Test all REST endpoints
4. Test MCP tool invocations
5. Test SSE streaming
6. Test Swarm coordination
7. Create PR for `merged` branch

---

## 8. Quality Gates

### Pre-Merge Checklist

- [ ] All files have metadata headers
- [ ] No file exceeds 500 LOC (without comments)
- [ ] PHPStan passes (level 5)
- [ ] No syntax errors
- [ ] All REST endpoints tested
- [ ] All MCP tools tested
- [ ] SSE streaming tested
- [ ] Swarm coordination tested

### Per-Commit Checklist

- [ ] Only assigned files modified
- [ ] Secondary agent reviewed changes
- [ ] No conflicts with partner's files
- [ ] Tests pass

---

## 9. Communication Protocol

### Sync File Location
```
/a0/usr/projects/coraline/wordpress/merge-plan.md
```

### A2A Endpoints
- **Coraline:** `http://64.23.131.221:50081/a2a`
- **Hyperion:** `https://64.227.106.173/a2a/t-9aqR35APIIR8XH3r/p-hyperion`

### Update Protocol
1. Before modifying files: notify partner via A2A
2. After modifications: update this file
3. Major decisions: both agents must approve

---

## 10. Revision Log

| Date | Agent | Change |
|------|-------|--------|
| 2026-03-01 18:05 | Coraline | v2.0 - Complete rewrite with deep analysis |
| 2026-03-01 18:05 | Coraline | Added code examples, confirmed roles from Hyperion |
| 2026-03-01 18:33 | Coraline | Alignment confirmed via user relay - A2A endpoint unreachable |
| 2026-03-01 18:33 | Both | COORDINATED - Ready for Phase 1 execution |

---

## 11. Approval

| Agent | Status | Date |
|-------|--------|------|
| Coraline | ✅ Approved | 2026-03-01 18:05 |
| Hyperion | ✅ Coordinated | 2026-03-01 18:33 UTC |

---

**This document is living. Both agents must edit and approve.**
