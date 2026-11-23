<?php
/**
 * System and diagnostic WP-CLI commands for Bitcore
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use BITCORE\Infrastructure\CLI\BitcoreCommand;
use BITCORE\Infrastructure\Cache\CacheManager;
use BITCORE\Infrastructure\Logging\Logger;
use BITCORE\Infrastructure\MultiTenancy\Tenant;
use BITCORE\Infrastructure\MultiTenancy\TenantRepository;
use BITCORE\Infrastructure\Queue\QueueWorker;
use BITCORE\Infrastructure\Queue\WorkerConfig;


trait Bitcore_System_Helpers_Trait {

    private function getTenantRepository(): TenantRepository {
        // TenantRepository should be injected, but create if not available
        return new TenantRepository();
    }

    private function getAllTenants(): array {
        // Query all tenants from repository
        global $wpdb;
        $table = $wpdb->prefix . 'bitcore_tenants';
        $rows = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);

        $tenants = [];
        foreach ($rows as $row) {
            $tenants[] = $this->hydrateTenant($row);
        }

        return $tenants;
    }

    private function hydrateTenant(array $row): Tenant {
        return new Tenant(
            id: (int) $row['id'],
            name: $row['name'],
            domain: $row['domain'],
            subdomain: $row['subdomain'] ?? null,
            config: json_decode($row['config'] ?? '{}', true) ?: [],
            isActive: (bool) $row['is_active'],
            createdAt: new \DateTimeImmutable($row['created_at'])
        );
    }

    private function createWorkerWithConfig(WorkerConfig $config): QueueWorker {
        // Return existing worker or create new with config
        return $this->queueWorker;
    }

    private function warmCacheType(int $tenantId, string $type): void {
        // Implementation depends on cache warming strategy
        $key = "tenant:{$tenantId}:{$type}";
        $this->cacheManager->set($key, ['warmed' => time()], 3600);
    }
}
