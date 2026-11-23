<?php
/**
 * WP-CLI Commands Integration
 *
 * Provides comprehensive CLI interface for BITCORE infrastructure management
 * including tenant operations, queue management, cache control, migrations,
 * and system diagnostics.
 *
 * @package BITCORE
 * @subpackage Infrastructure\CLI
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\CLI;

use TenantResolver;
use BITCORE\Infrastructure\MultiTenancy\Tenant;
use BITCORE\Infrastructure\MultiTenancy\TenantRepository;
use BITCORE\Infrastructure\MultiTenancy\SchemaManager;
use BITCORE\Infrastructure\Queue\QueueWorker;
use BITCORE\Infrastructure\Queue\WorkerConfig;
use BITCORE\Infrastructure\Queue\JobRepository;
use BITCORE\Infrastructure\Cache\CacheManager;
use BITCORE\Infrastructure\Logging\Logger;
use WP_CLI;
use WP_CLI\Utils;
use InvalidArgumentException;
use RuntimeException;
use Throwable;



require_once __DIR__ . '/trait-bitcore-command-tenant.php';
require_once __DIR__ . '/trait-bitcore-command-queue.php';
require_once __DIR__ . '/trait-bitcore-command-cache.php';
require_once __DIR__ . '/trait-bitcore-command-migration.php';
require_once __DIR__ . '/trait-bitcore-command-health.php';
require_once __DIR__ . '/trait-bitcore-command-diagnostics.php';

class BitcoreCommand {
    use Bitcore_Command_Tenant_Trait;
    use Bitcore_Command_Queue_Trait;
    use Bitcore_Command_Cache_Trait;
    use Bitcore_Command_Migration_Trait;
    use Bitcore_Command_Health_Trait;
    use Bitcore_Command_Diagnostics_Trait;



    /** @var TenantResolver */
    private TenantResolver $tenantResolver;

    /** @var SchemaManager */
    private SchemaManager $schemaManager;

    /** @var QueueWorker */
    private QueueWorker $queueWorker;

    /** @var CacheManager */
    private CacheManager $cacheManager;

    /** @var Logger */
    private Logger $logger;

    /** @var JobRepository|null */
    private ?JobRepository $jobRepository = null;

    /**
     * Constructor
     *
     * @param TenantResolver $tenantResolver Tenant resolution service
     * @param SchemaManager $schemaManager Schema migration manager
     * @param QueueWorker $queueWorker Queue worker processor
     * @param CacheManager $cacheManager Cache management service
     * @param Logger $logger Logging service
     * @since 1.0.0
     */

    public function __construct(
        TenantResolver $tenantResolver,
        SchemaManager $schemaManager,
        QueueWorker $queueWorker,
        CacheManager $cacheManager,
        Logger $logger
    ) {
        $this->tenantResolver = $tenantResolver;
        $this->schemaManager = $schemaManager;
        $this->queueWorker = $queueWorker;
        $this->cacheManager = $cacheManager;
        $this->logger = $logger;

        // Initialize job repository if available
        if (class_exists(JobRepository::class)) {
            $this->jobRepository = new JobRepository();
        }

        $this->logger->info('BitcoreCommand initialized', [
            'component' => 'cli',
            'action' => 'constructor'
        ]);
    }

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
