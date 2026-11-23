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



trait Bitcore_Command_Cache_Trait {

    public function cache_clear($args, $assoc_args): void {
        try {
            $tenantId = isset($assoc_args['tenant']) ? (int) $assoc_args['tenant'] : null;
            $tag = $assoc_args['tag'] ?? null;
            $dry_run = isset($assoc_args['dry-run']);

            $this->logger->info('Clearing cache', [
                'tenant_id' => $tenantId,
                'tag' => $tag,
                'dry_run' => $dry_run
            ]);

            if ($dry_run) {
                WP_CLI::log('Would clear cache' . ($tenantId ? " for tenant {$tenantId}" : ' globally') . ($tag ? " with tag '{$tag}'" : '') . '.');
                return;
            }

            if ($tenantId) {
                $this->cacheManager->clearByTenant($tenantId);
                WP_CLI::success("Cache cleared for tenant {$tenantId}.");
            } elseif ($tag) {
                $this->cacheManager->deleteByTag($tag);
                WP_CLI::success("Cache cleared for tag '{$tag}'.");
            } else {
                $this->cacheManager->clear();
                WP_CLI::success('All cache cleared successfully.');
            }

            $this->logger->info('Cache cleared');

        } catch (Throwable $e) {
            $this->logger->error('Cache clear command failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    public function cache_warm($args, $assoc_args): void {
        try {
            $tenantId = (int) ($args[0] ?? 0);
            $types = explode(',', $assoc_args['types'] ?? 'settings,posts,users');

            if ($tenantId <= 0) {
                WP_CLI::error('Valid tenant ID is required.', false);
                return;
            }

            // Verify tenant exists
            $repository = $this->getTenantRepository();
            $tenant = $repository->findById($tenantId);

            if (!$tenant) {
                WP_CLI::error("Tenant {$tenantId} not found.", false);
                return;
            }

            $this->logger->info('Warming cache', [
                'tenant_id' => $tenantId,
                'types' => $types
            ]);

            WP_CLI::log("Warming cache for tenant {$tenantId}...");

            // Set tenant context
            $this->tenantResolver->setCurrentTenant($tenant);

            $warmed = 0;
            $progress = Utils::make_progress_bar('Warming cache', count($types));

            foreach ($types as $type) {
                try {
                    $this->warmCacheType($tenantId, trim($type));
                    $warmed++;
                    $progress->tick();
                } catch (Throwable $e) {
                    WP_CLI::warning("Failed to warm {$type}: {$e->getMessage()}");
                }
            }

            $progress->finish();

            WP_CLI::success("Cache warmed for {$warmed} types.");
            $this->logger->info('Cache warming completed', [
                'tenant_id' => $tenantId,
                'warmed' => $warmed
            ]);

        } catch (Throwable $e) {
            $this->logger->error('Cache warm command failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    public function cache_stats($args, $assoc_args): void {
        try {
            $format = $assoc_args['format'] ?? 'table';

            $this->logger->info('Retrieving cache statistics');

            // Get statistics from cache manager
            $stats = $this->cacheManager->getStats();

            $items = [
                [
                    'metric' => 'Total Keys',
                    'value' => $stats['keys'] ?? 'N/A',
                ],
                [
                    'metric' => 'Hits',
                    'value' => $stats['hits'] ?? 'N/A',
                ],
                [
                    'metric' => 'Misses',
                    'value' => $stats['misses'] ?? 'N/A',
                ],
                [
                    'metric' => 'Hit Rate',
                    'value' => isset($stats['hit_rate']) ? round($stats['hit_rate'] * 100, 2) . '%' : 'N/A',
                ],
                [
                    'metric' => 'Memory Usage',
                    'value' => isset($stats['memory']) ? size_format($stats['memory']) : 'N/A',
                ],
            ];

            if ($format === 'json') {
                WP_CLI::log(json_encode($stats, JSON_PRETTY_PRINT));
            } else {
                WP_CLI::log("Cache Statistics:");
                Utils::format_items('table', $items, ['metric', 'value']);
            }

            $this->logger->info('Cache stats retrieved');

        } catch (Throwable $e) {
            $this->logger->error('Cache stats command failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }
}
