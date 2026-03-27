# MERGE COMPARISON v2 - Coraline vs Hyperion

**Generated:** 2026-03-02 05:10 UTC
**Status:** Parallel merge conflict - both agents executed independently

---

## Executive Summary

| Metric | Coraline (Local) | Hyperion (Remote) |
|--------|------------------|-------------------|
| **PHP Files** | 149 | 132 |
| **Approach** | Strict 500 LOC splits | Consolidated files |
| **Tests** | ✅ Included | ❌ Removed |
| **Patterns** | ❌ Missing | ✅ Included |

---

## Files Only in Coraline (Local) - 35 files

### Intelligence Classes (Coraline-specific)
```
classes/class-capabilities.php
classes/class-context-store.php
classes/modules/tasks.php
classes/services/abstract-connector.php
```

### Infrastructure (Split per 500 LOC plan)
```
infrastructure/cache/CacheManager.php
infrastructure/cache/CachePool.php
infrastructure/cli/commands/admin.php
infrastructure/cli/commands/cache.php
infrastructure/cli/commands/migration.php
infrastructure/cli/commands/queue.php
infrastructure/cli/commands/system.php
infrastructure/multitenancy/schema-migrations.php
infrastructure/multitenancy/schema-tables.php
infrastructure/multitenancy/TenantManager.php
infrastructure/multitenancy/TenantResolver.php
infrastructure/queue/Job.php
infrastructure/queue/JobRunner.php
infrastructure/swarm/A2ADispatcher.php
infrastructure/swarm/A2ADispatcherSupport.php
infrastructure/swarm/HealthCheck.php
infrastructure/swarm/HealthMonitorClass.php
infrastructure/swarm/HealthMonitorInterfaces.php
infrastructure/swarm/SwarmCoordinator.php
infrastructure/swarm/SwarmCoordinatorSupport.php
infrastructure/webhooks/WebhookDelivery.php
infrastructure/webhooks/WebhookManager.php
```

### MCP (Split per plan)
```
mcp/resources.php
mcp/tools.php
```

### REST (Split per plan)
```
rest/class-rest-tasks-permissions.php
rest/research-intelligence-contexts.php
```

### Tests (Preserved)
```
tests/sanitization.php
tests/test-rest-api.php
tests/test-sanitization.php
```

### Templates
```
templates/loader.php
```

---

## Files Only in Hyperion (Remote) - 17 files

### Patterns (Missing from Coraline)
```
patterns/builder.php
patterns/decorator.php
patterns/factory.php
patterns/repository.php
patterns/strategy.php
```

### Infrastructure (Consolidated versions)
```
infrastructure/cache/manager.php
infrastructure/cache/pool.php
infrastructure/cli/commands.php (consolidated, 1849 LOC)
infrastructure/multitenancy/schema.php
infrastructure/multitenancy/tenant.php
infrastructure/queue/job.php
infrastructure/queue/worker.php
infrastructure/swarm/a2a-dispatcher.php
infrastructure/swarm/health-monitor.php
infrastructure/swarm/swarm-coordinator.php
infrastructure/webhooks/manager.php
```

### Modules
```
classes/modules/capabilities.php
```

---

## Architectural Differences

| Component | Coraline | Hyperion |
|-----------|----------|----------|
| **File Splits** | Enforced 500 LOC | Consolidated (up to 1849 LOC) |
| **Tests** | Preserved from source | Removed |
| **Patterns** | Not imported | Included (5 pattern files) |
| **CLI Commands** | Split into 5 files | Single 1849 LOC file |
| **Swarm** | Split into 8 files | Split into 3 files |
| **Cache** | Split into 2 files | Split into 2 files (different names) |

---

## Recommendations

### Option A: Use Coraline + Add Hyperion Patterns
- Take Coraline's properly split files
- Add patterns/ directory from Hyperion
- Result: ~154 files, all under 500 LOC

### Option B: Use Hyperion + Add Coraline Tests
- Take Hyperion's consolidated approach
- Add tests from Coraline
- Result: ~135 files, some over 500 LOC

### Option C: Create Unified v3
- Merge best of both:
  - Coraline's file splits (500 LOC compliance)
  - Hyperion's patterns directory
  - All tests preserved
- Result: Complete, compliant codebase

---

## Next Steps

1. **Review this comparison**
2. **Choose merge strategy** (A, B, or C)
3. **Execute final merge**
4. **Push to origin/merge**

---

**Version:** 2.0
**Authors:** Coraline (local) + Hyperion (remote)
