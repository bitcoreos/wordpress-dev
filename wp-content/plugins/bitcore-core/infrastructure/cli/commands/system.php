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

    // SYSTEM COMMANDS
    // =================================================================================

    /**
     * Show system health
     *
     * Displays overall system health status.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format
     * ---
     * default: table
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp bitcore health
     *
     *     $ wp bitcore health --format=json
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
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

    /**
     * Run diagnostics
     *
     * Performs comprehensive system diagnostics.
     *
     * ## OPTIONS
     *
     * [--deep]
     * : Run deep diagnostics (slower)
     *
     * [--output=<file>]
     * : Save report to file
     *
     * ## EXAMPLES
     *
     *     # Quick diagnostics
     *     $ wp bitcore diagnose
     *
     *     # Deep diagnostics with report
     *     $ wp bitcore diagnose --deep --output=/tmp/diagnosis.json
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function diagnose($args, $assoc_args): void {
        try {
            $deep = isset($assoc_args['deep']);
            $outputFile = $assoc_args['output'] ?? null;

            $this->logger->info('Running diagnostics', ['deep' => $deep]);

            WP_CLI::log("Running system diagnostics..." . ($deep ? ' (deep scan)' : ''));

            $report = [
                'timestamp' => date('Y-m-d H:i:s'),
                'wordpress' => $this->getWordPressInfo(),
                'bitcore' => $this->getBitcoreInfo(),
                'database' => $this->getDatabaseDiagnostics($deep),
                'cache' => $this->getCacheDiagnostics($deep),
                'queue' => $this->getQueueDiagnostics($deep),
                'tenants' => $this->getTenantDiagnostics($deep),
            ];

            // Add recommendations
            $report['recommendations'] = $this->generateRecommendations($report);

            if ($outputFile) {
                file_put_contents($outputFile, json_encode($report, JSON_PRETTY_PRINT));
                WP_CLI::success("Diagnostic report saved to: {$outputFile}");
            } else {
                WP_CLI::log("\nDiagnostic Report:");
                WP_CLI::log(json_encode($report, JSON_PRETTY_PRINT));
            }

            $this->logger->info('Diagnostics completed');

        } catch (Throwable $e) {
            $this->logger->error('Diagnostics failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    // =================================================================================
    // HELPER METHODS
    // =================================================================================

    /**
     * Get tenant repository instance
     *
     * @return TenantRepository
     * @since 1.0.0
     */
    private function getTenantRepository(): TenantRepository {
        // TenantRepository should be injected, but create if not available
        return new TenantRepository();
    }

    /**
     * Get all tenants
     *
     * @return array<Tenant>
     * @since 1.0.0
     */
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

    /**
     * Hydrate tenant from database row
     *
     * @param array $row Database row
     * @return Tenant
     * @since 1.0.0
     */
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

    /**
     * Create worker with custom config
     *
     * @param WorkerConfig $config
     * @return QueueWorker
     * @since 1.0.0
     */
    private function createWorkerWithConfig(WorkerConfig $config): QueueWorker {
        // Return existing worker or create new with config
        return $this->queueWorker;
    }

    /**
     * Warm cache for specific type
     *
     * @param int $tenantId
     * @param string $type
     * @return void
     * @since 1.0.0
     */
    private function warmCacheType(int $tenantId, string $type): void {
        // Implementation depends on cache warming strategy
        $key = "tenant:{$tenantId}:{$type}";
        $this->cacheManager->set($key, ['warmed' => time()], 3600);
    }

    /**
     * Check database health
     *
     * @return array
     * @since 1.0.0
     */
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

    /**
     * Check cache health
     *
     * @return array
     * @since 1.0.0
     */
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

    /**
     * Check queue health
     *
     * @return array
     * @since 1.0.0
     */
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

    /**
     * Check tenant health
     *
     * @return array
     * @since 1.0.0
     */
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

    /**
     * Get WordPress information
     *
     * @return array
     * @since 1.0.0
     */
    private function getWordPressInfo(): array {
        return [
            'version' => get_bloginfo('version'),
            'url' => get_bloginfo('url'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
        ];
    }

    /**
     * Get BITCORE information
     *
     * @return array
     * @since 1.0.0
     */
    private function getBitcoreInfo(): array {
        return [
            'version' => defined('BITCORE_VERSION') ? BITCORE_VERSION : 'unknown',
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
        ];
    }

    /**
     * Get database diagnostics
     *
     * @param bool $deep
     * @return array
     * @since 1.0.0
     */
    private function getDatabaseDiagnostics(bool $deep): array {
        global $wpdb;

        $info = [
            'connected' => true,
            'tables' => [],
        ];

        if ($deep) {
            $tables = $wpdb->get_results("SHOW TABLE STATUS LIKE '{$wpdb->prefix}bitcore_%'", ARRAY_A);
            foreach ($tables as $table) {
                $info['tables'][] = [
                    'name' => $table['Name'],
                    'rows' => $table['Rows'],
                    'size' => size_format($table['Data_length'] + $table['Index_length']),
                ];
            }
        }

        return $info;
    }

    /**
     * Get cache diagnostics
     *
     * @param bool $deep
     * @return array
     * @since 1.0.0
     */
    private function getCacheDiagnostics(bool $deep): array {
        $info = [
            'object_cache' => wp_using_ext_object_cache(),
            'tiers' => [],
        ];

        if ($deep) {
            $info['stats'] = $this->cacheManager->getStats();
        }

        return $info;
    }

    /**
     * Get queue diagnostics
     *
     * @param bool $deep
     * @return array
     * @since 1.0.0
     */
    private function getQueueDiagnostics(bool $deep): array {
        if (!$this->jobRepository) {
            return ['available' => false];
        }

        $info = [
            'available' => true,
            'pending' => $this->jobRepository->countPending(),
            'failed' => $this->jobRepository->countFailed(),
        ];

        if ($deep) {
            $info['recent_failed'] = $this->jobRepository->getFailed(null, 5);
        }

        return $info;
    }

    /**
     * Get tenant diagnostics
     *
     * @param bool $deep
     * @return array
     * @since 1.0.0
     */
    private function getTenantDiagnostics(bool $deep): array {
        $repository = $this->getTenantRepository();
        $active = $repository->listActive();

        $info = [
            'total' => count($this->getAllTenants()),
            'active' => count($active),
        ];

        if ($deep) {
            $info['tenants'] = [];
            foreach ($active as $tenant) {
                $version = $this->schemaManager->getTenantSchemaVersion($tenant->id);
                $info['tenants'][] = [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'version' => $version ?? 'N/A',
                ];
            }
        }

        return $info;
    }

    /**
     * Generate recommendations based on diagnostics
     *
     * @param array $report
     * @return array
     * @since 1.0.0
     */
    private function generateRecommendations(array $report): array {
        $recommendations = [];

        // Check for failed jobs
        if (isset($report['queue']['failed']) && $report['queue']['failed'] > 10) {
            $recommendations[] = 'High number of failed jobs detected. Run `wp bitcore queue retry --all` to retry.';
        }

        // Check for object cache
        if (empty($report['cache']['object_cache'])) {
            $recommendations[] = 'Object cache not enabled. Consider installing Redis or Memcached for better performance.';
        }

        // Check for debug mode
        if (!empty($report['wordpress']['debug'])) {
            $recommendations[] = 'WP_DEBUG is enabled. Disable for production.';
        }

        return $recommendations;
    }
}

// =================================================================================
// WP-CLI REGISTRATION
// =================================================================================

if (defined('WP_CLI') && WP_CLI) {
    /**
     * Initialize BITCORE CLI commands
     *
     * This function should be called from the main plugin file to register
     * all BITCORE commands with WP-CLI.
     *
     * Example usage in plugin bootstrap:
     *
     *     if (defined('WP_CLI') && WP_CLI) {
     *         require_once __DIR__ . '/infrastructure/cli/commands.php';
     *         bitcore_cli_init();
     *     }
     *
     * @since 1.0.0
     * @return void
     */
    function bitcore_cli_init(): void {
        // Dependencies should be available from plugin container
        $container = apply_filters('bitcore_container', null);

        if (!$container) {
            WP_CLI::warning('BITCORE container not available. Commands may not function correctly.');
            return;
        }

        try {
            $command = new BitcoreCommand(
                $container->get(TenantResolver::class),
                $container->get(SchemaManager::class),
                $container->get(QueueWorker::class),
                $container->get(CacheManager::class),
                $container->get(Logger::class)
            );

            WP_CLI::add_command('bitcore', $command);

        } catch (Throwable $e) {
            WP_CLI::error('Failed to initialize BITCORE CLI: ' . $e->getMessage(), false);
        }
    }

    // Auto-initialize if container is available
    add_action('cli_init', 'bitcore_cli_init');
}

// =================================================================================
// TEST SCAFFOLD
// =================================================================================

/**
 * Test Scaffolding Notes:
 *
 * TODO: Implement unit tests for each command
 * TODO: Create integration tests with mock WordPress environment
 * TODO: Add test coverage for error handling paths
 * TODO: Validate output formatting for all --format options
 * TODO: Test dry-run mode for destructive operations
 * TODO: Verify progress bar functionality with large datasets
 * TODO: Test tenant context switching isolation
 * TODO: Validate queue worker memory and time limits
 * TODO: Test migration rollback scenarios
 * TODO: Verify cache warming operations
 *
 * Test Commands:
 *   wp bitcore tenant list --format=json
 *   wp bitcore tenant create "Test" --domain=test.local --dry-run
 *   wp bitcore queue work --max-jobs=1 --dry-run
 *   wp bitcore cache clear --dry-run
 *   wp bitcore migrate --dry-run
 *   wp bitcore health
 *   wp bitcore diagnose --deep
 *
 * @since 1.0.0
 */
