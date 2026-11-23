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



trait Bitcore_Command_Health_Trait {

    public function health($args, $assoc_args): void {
        try {
            $format = $assoc_args['format'] ?? 'table';

            $this->logger->info('Checking system health');

            $checks = [
                'database' => $this->checkDatabaseHealth(),
                'cache' => $this->checkCacheHealth(),
                'queue' => $this->checkQueueHealth(),
                'tenants' => $this->checkTenantHealth(),
            ];

            $items = [];
            $allHealthy = true;

            foreach ($checks as $name => $check) {
                $items[] = [
                    'component' => $name,
                    'status' => $check['healthy'] ? '✓ healthy' : '✗ unhealthy',
                    'message' => $check['message'],
                ];
                if (!$check['healthy']) {
                    $allHealthy = false;
                }
            }

            if ($format === 'json') {
                WP_CLI::log(json_encode(['checks' => $items, 'overall' => $allHealthy ? 'healthy' : 'unhealthy'], JSON_PRETTY_PRINT));
            } else {
                WP_CLI::log("System Health Check:");
                Utils::format_items('table', $items, ['component', 'status', 'message']);

                if ($allHealthy) {
                    WP_CLI::success('All systems operational.');
                } else {
                    WP_CLI::warning('Some systems require attention.');
                }
            }

            $this->logger->info('Health check completed', ['healthy' => $allHealthy]);

        } catch (Throwable $e) {
            $this->logger->error('Health check failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    private function checkDatabaseHealth(): array {
        try {
            global $wpdb;
            $result = $wpdb->get_var("SELECT 1");
            return [
                'healthy' => ($result === '1'),
                'message' => 'Database connection OK'
            ];
        } catch (Throwable $e) {
            return [
                'healthy' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function checkCacheHealth(): array {
        try {
            $testKey = 'health_check_' . time();
            $this->cacheManager->set($testKey, 'test', 60);
            $value = $this->cacheManager->get($testKey);
            $this->cacheManager->delete($testKey);

            return [
                'healthy' => ($value === 'test'),
                'message' => 'Cache read/write OK'
            ];
        } catch (Throwable $e) {
            return [
                'healthy' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function checkQueueHealth(): array {
        if (!$this->jobRepository) {
            return [
                'healthy' => false,
                'message' => 'Job repository not available'
            ];
        }

        try {
            $pending = $this->jobRepository->countPending();
            $failed = $this->jobRepository->countFailed();

            $healthy = ($failed < 100); // Arbitrary threshold

            return [
                'healthy' => $healthy,
                'message' => "{$pending} pending, {$failed} failed"
            ];
        } catch (Throwable $e) {
            return [
                'healthy' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function checkTenantHealth(): array {
        try {
            $repository = $this->getTenantRepository();
            $active = count($repository->listActive());

            return [
                'healthy' => ($active > 0),
                'message' => "{$active} active tenant(s)"
            ];
        } catch (Throwable $e) {
            return [
                'healthy' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function getWordPressInfo(): array {
        return [
            'version' => get_bloginfo('version'),
            'url' => get_bloginfo('url'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
        ];
    }

    private function getBitcoreInfo(): array {
        return [
            'version' => defined('BITCORE_VERSION') ? BITCORE_VERSION : 'unknown',
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
        ];
    }
}
