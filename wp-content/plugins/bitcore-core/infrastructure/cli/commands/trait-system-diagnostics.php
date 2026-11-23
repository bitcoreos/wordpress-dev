<?php
/**
use BITCORE\Infrastructure\CLI\BitcoreCommand;
use BITCORE\Infrastructure\Cache\CacheManager;
use BITCORE\Infrastructure\Logging\Logger;
use BITCORE\Infrastructure\MultiTenancy\Tenant;
use BITCORE\Infrastructure\MultiTenancy\TenantRepository;
use BITCORE\Infrastructure\Queue\QueueWorker;
use BITCORE\Infrastructure\Queue\WorkerConfig;
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


trait Bitcore_System_Diagnostics_Trait {

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
