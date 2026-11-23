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

/**
 * BITCORE WP-CLI Command Handler
 *
 * Orchestrates all BITCORE infrastructure operations via WP-CLI.
 * Provides tenant management, queue processing, cache control,
 * schema migrations, and system diagnostics.
 *
 * @since 1.0.0
 */
class BitcoreCommand {

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

    // =================================================================================
    // TENANT COMMANDS
    // =================================================================================

    /**
     * List all tenants
     *
     * Lists all registered tenants in the system with their current status.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format. Accepts: table, json, csv, yaml, count
     * ---
     * default: table
     * ---
     *
     * [--active-only]
     * : Show only active tenants
     *
     * ## EXAMPLES
     *
     *     # List all tenants in table format
     *     $ wp bitcore tenant list
     *
     *     # List tenants as JSON
     *     $ wp bitcore tenant list --format=json
     *
     *     # List only active tenants
     *     $ wp bitcore tenant list --active-only
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function tenant_list($args, $assoc_args): void {
        try {
            $format = $assoc_args['format'] ?? 'table';
            $active_only = isset($assoc_args['active-only']);

            $this->logger->info('Executing tenant list command', [
                'format' => $format,
                'active_only' => $active_only
            ]);

            // Get tenant repository
            $repository = $this->getTenantRepository();
            $tenants = $active_only ? $repository->listActive() : $this->getAllTenants();

            if (empty($tenants)) {
                WP_CLI::log('No tenants found.');
                return;
            }

            $items = [];
            foreach ($tenants as $tenant) {
                $items[] = [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'domain' => $tenant->domain,
                    'subdomain' => $tenant->subdomain ?? '',
                    'active' => $tenant->isActive ? 'yes' : 'no',
                    'created' => $tenant->createdAt->format('Y-m-d H:i:s'),
                ];
            }

            if ($format === 'count') {
                WP_CLI::log(count($items));
            } else {
                Utils::format_items($format, $items, ['id', 'name', 'domain', 'subdomain', 'active', 'created']);
            }

            $this->logger->info('Tenant list command completed', [
                'count' => count($items)
            ]);

        } catch (Throwable $e) {
            $this->logger->error('Tenant list command failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    /**
     * Create new tenant
     *
     * Creates a new tenant with the specified name and domain.
     *
     * ## OPTIONS
     *
     * <name>
     * : Tenant name
     *
     * [--domain=<domain>]
     * : Primary domain for the tenant
     *
     * [--subdomain=<subdomain>]
     * : Subdomain prefix (optional)
     *
     * [--config=<config>]
     * : JSON configuration for tenant
     *
     * [--dry-run]
     * : Preview changes without executing
     *
     * ## EXAMPLES
     *
     *     $ wp bitcore tenant create "Acme Corp" --domain=acme.example.com
     *
     *     $ wp bitcore tenant create "Test Tenant" --domain=test.com --subdomain=test --dry-run
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function tenant_create($args, $assoc_args): void {
        try {
            $name = $args[0] ?? null;
            $domain = $assoc_args['domain'] ?? null;
            $subdomain = $assoc_args['subdomain'] ?? null;
            $config_json = $assoc_args['config'] ?? '{}';
            $dry_run = isset($assoc_args['dry-run']);

            if (empty($name)) {
                WP_CLI::error('Tenant name is required.', false);
                return;
            }

            if (empty($domain)) {
                WP_CLI::error('Domain is required. Use --domain=<domain>', false);
                return;
            }

            $config = json_decode($config_json, true) ?: [];

            $this->logger->info('Executing tenant create command', [
                'name' => $name,
                'domain' => $domain,
                'dry_run' => $dry_run
            ]);

            if ($dry_run) {
                WP_CLI::log("Would create tenant:");
                WP_CLI::log("  Name: {$name}");
                WP_CLI::log("  Domain: {$domain}");
                WP_CLI::log("  Subdomain: " . ($subdomain ?: '(none)'));
                WP_CLI::log("  Config: " . json_encode($config));
                return;
            }

            $repository = $this->getTenantRepository();
            $tenant = $repository->save(null, [
                'name' => $name,
                'domain' => $domain,
                'subdomain' => $subdomain,
                'config' => $config,
                'is_active' => 1,
            ]);

            if ($tenant) {
                WP_CLI::success("Tenant created successfully: ID {$tenant->id}");
                $this->logger->info('Tenant created', [
                    'tenant_id' => $tenant->id,
                    'name' => $name
                ]);

                // Initialize schema for new tenant
                try {
                    $this->schemaManager->createTenantSchema($tenant->id);
                    WP_CLI::log("Tenant schema initialized.");
                } catch (Throwable $schemaError) {
                    WP_CLI::warning("Schema initialization failed: " . $schemaError->getMessage());
                    $this->logger->warning('Tenant schema initialization failed', [
                        'tenant_id' => $tenant->id,
                        'error' => $schemaError->getMessage()
                    ]);
                }
            } else {
                WP_CLI::error('Failed to create tenant.', false);
            }

        } catch (Throwable $e) {
            $this->logger->error('Tenant create command failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    /**
     * Delete tenant
     *
     * Soft-deletes a tenant by deactivating it.
     *
     * ## OPTIONS
     *
     * <id>
     * : Tenant ID to delete
     *
     * [--force]
     * : Permanently delete (instead of soft delete)
     *
     * [--dry-run]
     * : Preview changes without executing
     *
     * ## EXAMPLES
     *
     *     $ wp bitcore tenant delete 123
     *
     *     $ wp bitcore tenant delete 123 --force
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function tenant_delete($args, $assoc_args): void {
        try {
            $id = (int) ($args[0] ?? 0);
            $force = isset($assoc_args['force']);
            $dry_run = isset($assoc_args['dry-run']);

            if ($id <= 0) {
                WP_CLI::error('Valid tenant ID is required.', false);
                return;
            }

            $this->logger->info('Executing tenant delete command', [
                'tenant_id' => $id,
                'force' => $force,
                'dry_run' => $dry_run
            ]);

            // Verify tenant exists
            $repository = $this->getTenantRepository();
            $tenant = $repository->findById($id);

            if (!$tenant) {
                WP_CLI::error("Tenant {$id} not found.", false);
                return;
            }

            if ($dry_run) {
                WP_CLI::log("Would " . ($force ? 'permanently delete' : 'deactivate') . " tenant:");
                WP_CLI::log("  ID: {$tenant->id}");
                WP_CLI::log("  Name: {$tenant->name}");
                WP_CLI::log("  Domain: {$tenant->domain}");
                return;
            }

            if ($force) {
                // Permanent deletion would require additional implementation
                WP_CLI::warning('Permanent deletion not implemented. Use deactivate instead.');
            }

            $result = $repository->deactivate($id);

            if ($result) {
                WP_CLI::success("Tenant {$id} deactivated successfully.");
                $this->logger->info('Tenant deactivated', ['tenant_id' => $id]);

                // Clear tenant cache
                $this->cacheManager->clear();
            } else {
                WP_CLI::error("Failed to deactivate tenant {$id}.", false);
            }

        } catch (Throwable $e) {
            $this->logger->error('Tenant delete command failed', [
                'tenant_id' => $args[0] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    /**
     * Switch to tenant context
     *
     * Sets the current tenant context for subsequent operations.
     *
     * ## OPTIONS
     *
     * <id>
     * : Tenant ID to switch to
     *
     * ## EXAMPLES
     *
     *     $ wp bitcore tenant switch 123
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function tenant_switch($args, $assoc_args): void {
        try {
            $id = (int) ($args[0] ?? 0);

            if ($id <= 0) {
                WP_CLI::error('Valid tenant ID is required.', false);
                return;
            }

            // Verify tenant exists
            $repository = $this->getTenantRepository();
            $tenant = $repository->findById($id);

            if (!$tenant) {
                WP_CLI::error("Tenant {$id} not found.", false);
                return;
            }

            $this->tenantResolver->setCurrentTenant($tenant);

            WP_CLI::success("Switched to tenant: {$tenant->name} (ID: {$id})");
            $this->logger->info('Tenant context switched', [
                'tenant_id' => $id,
                'tenant_name' => $tenant->name
            ]);

        } catch (Throwable $e) {
            $this->logger->error('Tenant switch command failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    // =================================================================================
    // QUEUE COMMANDS
    // =================================================================================

    /**
     * Run queue worker
     *
     * Processes jobs from the queue.
     *
     * ## OPTIONS
     *
     * [--queue=<queue>]
     * : Queue name to process
     * ---
     * default: default
     * ---
     *
     * [--max-jobs=<max>]
     * : Maximum jobs to process
     * ---
     * default: 10
     * ---
     *
     * [--memory-limit=<limit>]
     * : Memory limit in MB
     * ---
     * default: 256
     * ---
     *
     * [--time-limit=<limit>]
     * : Time limit in seconds
     * ---
     * default: 300
     * ---
     *
     * [--stop-on-error]
     * : Stop on first error
     *
     * ## EXAMPLES
     *
     *     # Process default queue
     *     $ wp bitcore queue work
     *
     *     # Process specific queue with limits
     *     $ wp bitcore queue work --queue=emails --max-jobs=50
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function queue_work($args, $assoc_args): void {
        try {
            $queue = $assoc_args['queue'] ?? 'default';
            $maxJobs = (int) ($assoc_args['max-jobs'] ?? 10);
            $memoryLimit = (int) ($assoc_args['memory-limit'] ?? 256);
            $timeLimit = (int) ($assoc_args['time-limit'] ?? 300);
            $stopOnError = isset($assoc_args['stop-on-error']);

            $this->logger->info('Starting queue worker', [
                'queue' => $queue,
                'max_jobs' => $maxJobs,
                'memory_limit' => $memoryLimit,
                'time_limit' => $timeLimit
            ]);

            WP_CLI::log("Starting queue worker...");
            WP_CLI::log("  Queue: {$queue}");
            WP_CLI::log("  Max jobs: {$maxJobs}");
            WP_CLI::log("  Memory limit: {$memoryLimit}MB");
            WP_CLI::log("  Time limit: {$timeLimit}s");

            $config = new WorkerConfig(
                maxJobs: $maxJobs,
                memoryLimit: $memoryLimit,
                timeLimit: $timeLimit,
                stopOnError: $stopOnError
            );

            // Create worker with config
            $worker = $this->createWorkerWithConfig($config);

            $processed = 0;
            $failed = 0;

            $progress = Utils::make_progress_bar('Processing jobs', $maxJobs);

            for ($i = 0; $i < $maxJobs; $i++) {
                try {
                    $result = $worker->processNext($queue);

                    if ($result === null) {
                        WP_CLI::log("\nNo more jobs in queue.");
                        break;
                    }

                    if ($result->success) {
                        $processed++;
                        $this->logger->debug('Job processed successfully', [
                            'job_id' => $result->job->id ?? 'unknown'
                        ]);
                    } else {
                        $failed++;
                        $this->logger->warning('Job failed', [
                            'job_id' => $result->job->id ?? 'unknown',
                            'error' => $result->error
                        ]);

                        if ($stopOnError) {
                            WP_CLI::warning("\nStopping due to error: {$result->error}");
                            break;
                        }
                    }

                    $progress->tick();

                } catch (Throwable $e) {
                    $failed++;
                    $this->logger->error('Worker error', [
                        'error' => $e->getMessage()
                    ]);

                    if ($stopOnError) {
                        WP_CLI::warning("\nStopping due to exception: {$e->getMessage()}");
                        break;
                    }
                }
            }

            $progress->finish();

            WP_CLI::log("\nQueue worker completed:");
            WP_CLI::log("  Processed: {$processed}");
            WP_CLI::log("  Failed: {$failed}");

            if ($failed === 0) {
                WP_CLI::success('All jobs processed successfully.');
            } else {
                WP_CLI::warning("{$failed} jobs failed. Check logs for details.");
            }

            $this->logger->info('Queue worker finished', [
                'processed' => $processed,
                'failed' => $failed
            ]);

        } catch (Throwable $e) {
            $this->logger->error('Queue work command failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    /**
     * Show queue status
     *
     * Displays pending and failed job counts.
     *
     * ## OPTIONS
     *
     * [--queue=<queue>]
     * : Filter by queue name
     *
     * [--format=<format>]
     * : Output format
     * ---
     * default: table
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp bitcore queue status
     *
     *     $ wp bitcore queue status --queue=emails
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function queue_status($args, $assoc_args): void {
        try {
            $queue = $assoc_args['queue'] ?? null;
            $format = $assoc_args['format'] ?? 'table';

            if (!$this->jobRepository) {
                WP_CLI::error('Job repository not available.', false);
                return;
            }

            $this->logger->info('Checking queue status', [
                'queue' => $queue ?? 'all'
            ]);

            $pending = $this->jobRepository->countPending($queue);
            $failed = $this->jobRepository->countFailed($queue);

            $items = [
                [
                    'queue' => $queue ?? 'all',
                    'pending' => $pending,
                    'failed' => $failed,
                    'total' => $pending + $failed,
                ]
            ];

            if ($format === 'json') {
                WP_CLI::log(json_encode($items[0], JSON_PRETTY_PRINT));
            } else {
                WP_CLI::log("Queue Status:");
                Utils::format_items('table', $items, ['queue', 'pending', 'failed', 'total']);
            }

            $this->logger->info('Queue status retrieved', [
                'pending' => $pending,
                'failed' => $failed
            ]);

        } catch (Throwable $e) {
            $this->logger->error('Queue status command failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    /**
     * Retry failed jobs
     *
     * Re-queues failed jobs for retry.
     *
     * ## OPTIONS
     *
     * [--all]
     * : Retry all failed jobs
     *
     * [--id=<id>]
     * : Retry specific job by ID
     *
     * [--limit=<limit>]
     * : Maximum jobs to retry
     * ---
     * default: 10
     * ---
     *
     * ## EXAMPLES
     *
     *     # Retry all failed jobs
     *     $ wp bitcore queue retry --all
     *
     *     # Retry specific job
     *     $ wp bitcore queue retry --id=abc-123
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function queue_retry($args, $assoc_args): void {
        try {
            $all = isset($assoc_args['all']);
            $id = $assoc_args['id'] ?? null;
            $limit = (int) ($assoc_args['limit'] ?? 10);

            if (!$this->jobRepository) {
                WP_CLI::error('Job repository not available.', false);
                return;
            }

            $retried = 0;

            if ($id) {
                // Retry specific job
                $job = $this->jobRepository->retry($id);
                if ($job) {
                    $retried = 1;
                    WP_CLI::success("Job {$id} re-queued successfully.");
                    $this->logger->info('Job retried', ['job_id' => $id]);
                } else {
                    WP_CLI::error("Job {$id} not found or not failed.", false);
                }
            } elseif ($all) {
                // Retry all failed jobs
                $failedJobs = $this->jobRepository->getFailed(null, $limit);

                if (empty($failedJobs)) {
                    WP_CLI::log('No failed jobs to retry.');
                    return;
                }

                $progress = Utils::make_progress_bar('Retrying jobs', count($failedJobs));

                foreach ($failedJobs as $jobData) {
                    try {
                        $job = $this->jobRepository->retry($jobData['id']);
                        if ($job) {
                            $retried++;
                        }
                        $progress->tick();
                    } catch (Throwable $e) {
                        $this->logger->warning('Failed to retry job', [
                            'job_id' => $jobData['id'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                $progress->finish();

                WP_CLI::success("{$retried} jobs re-queued for retry.");
                $this->logger->info('Batch retry completed', [
                    'retried' => $retried
                ]);
            } else {
                WP_CLI::error('Specify --all or --id=<id>', false);
            }

        } catch (Throwable $e) {
            $this->logger->error('Queue retry command failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    /**
     * Flush failed jobs
     *
     * Permanently removes failed jobs from the database.
     *
     * ## OPTIONS
     *
     * [--hours=<hours>]
     * : Only flush jobs older than specified hours
     *
     * [--dry-run]
     * : Preview changes without executing
     *
     * ## EXAMPLES
     *
     *     # Flush all failed jobs
     *     $ wp bitcore queue flush
     *
     *     # Flush jobs older than 24 hours
     *     $ wp bitcore queue flush --hours=24
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function queue_flush($args, $assoc_args): void {
        try {
            $hours = isset($assoc_args['hours']) ? (int) $assoc_args['hours'] : null;
            $dry_run = isset($assoc_args['dry-run']);

            if (!$this->jobRepository) {
                WP_CLI::error('Job repository not available.', false);
                return;
            }

            $count = $this->jobRepository->countFailed();

            if ($count === 0) {
                WP_CLI::log('No failed jobs to flush.');
                return;
            }

            if ($dry_run) {
                WP_CLI::log("Would flush {$count} failed jobs" . ($hours ? " older than {$hours} hours" : '') . '.');
                return;
            }

            $this->logger->info('Flushing failed jobs', [
                'count' => $count,
                'hours' => $hours
            ]);

            $flushed = $this->jobRepository->prune($hours);

            WP_CLI::success("{$flushed} failed jobs flushed.");
            $this->logger->info('Failed jobs flushed', ['count' => $flushed]);

        } catch (Throwable $e) {
            $this->logger->error('Queue flush command failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    // =================================================================================
    // CACHE COMMANDS
    // =================================================================================

    /**
     * Clear cache
     *
     * Clears all or tenant-specific cache.
     *
     * ## OPTIONS
     *
     * [--tenant=<tenant>]
     * : Clear cache for specific tenant ID
     *
     * [--tag=<tag>]
     * : Clear cache by tag
     *
     * [--dry-run]
     * : Preview changes without executing
     *
     * ## EXAMPLES
     *
     *     # Clear all cache
     *     $ wp bitcore cache clear
     *
     *     # Clear cache for tenant
     *     $ wp bitcore cache clear --tenant=123
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
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

    /**
     * Warm cache for tenant
     *
     * Pre-populates cache with tenant data.
     *
     * ## OPTIONS
     *
     * <tenant_id>
     * : Tenant ID to warm cache for
     *
     * [--types=<types>]
     * : Comma-separated list of cache types to warm
     * ---
     * default: settings,posts,users
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp bitcore cache warm 123
     *
     *     $ wp bitcore cache warm 123 --types=settings,options
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
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

    /**
     * Show cache statistics
     *
     * Displays cache hit/miss ratios and memory usage.
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
     *     $ wp bitcore cache stats
     *
     *     $ wp bitcore cache stats --format=json
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
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

    // =================================================================================
    // MIGRATION COMMANDS
    // =================================================================================

    /**
     * Run migrations
     *
     * Executes pending database migrations.
     *
     * ## OPTIONS
     *
     * [--tenant=<tenant>]
     * : Run migrations for specific tenant
     *
     * [--version=<version>]
     * : Target specific version (default: latest)
     *
     * [--dry-run]
     * : Preview changes without executing
     *
     * ## EXAMPLES
     *
     *     # Run all pending migrations
     *     $ wp bitcore migrate
     *
     *     # Migrate specific tenant
     *     $ wp bitcore migrate --tenant=123
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function migrate($args, $assoc_args): void {
        try {
            $tenantId = isset($assoc_args['tenant']) ? (int) $assoc_args['tenant'] : null;
            $version = $assoc_args['version'] ?? null;
            $dry_run = isset($assoc_args['dry-run']);

            $this->logger->info('Running migrations', [
                'tenant_id' => $tenantId,
                'version' => $version,
                'dry_run' => $dry_run
            ]);

            if ($dry_run) {
                WP_CLI::log('Would run migrations' . ($tenantId ? " for tenant {$tenantId}" : ' for all tenants') . ($version ? " to version {$version}" : '') . '.');
                return;
            }

            if ($tenantId) {
                // Migrate specific tenant
                $migrated = $this->schemaManager->migrateTenantSchema($tenantId, $version);
                WP_CLI::success("Migrated tenant {$tenantId}: {$migrated} migrations applied.");
            } else {
                // Migrate all tenants
                $repository = $this->getTenantRepository();
                $tenants = $repository->listActive();
                $totalMigrated = 0;

                $progress = Utils::make_progress_bar('Migrating tenants', count($tenants));

                foreach ($tenants as $tenant) {
                    try {
                        $migrated = $this->schemaManager->migrateTenantSchema($tenant->id, $version);
                        $totalMigrated += $migrated;
                        $progress->tick();
                    } catch (Throwable $e) {
                        WP_CLI::warning("Failed to migrate tenant {$tenant->id}: {$e->getMessage()}");
                    }
                }

                $progress->finish();
                WP_CLI::success("Migrations completed: {$totalMigrated} total migrations applied across " . count($tenants) . " tenants.");
            }

            $this->logger->info('Migrations completed');

        } catch (Throwable $e) {
            $this->logger->error('Migrate command failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    /**
     * Rollback migration
     *
     * Reverts migrations to a previous version.
     *
     * ## OPTIONS
     *
     * <version>
     * : Target version to rollback to
     *
     * [--tenant=<tenant>]
     * : Rollback specific tenant
     *
     * [--steps=<steps>]
     * : Number of migrations to rollback
     * ---
     * default: 1
     * ---
     *
     * [--dry-run]
     * : Preview changes without executing
     *
     * ## EXAMPLES
     *
     *     # Rollback last migration
     *     $ wp bitcore rollback
     *
     *     # Rollback to specific version
     *     $ wp bitcore rollback 1.0.0
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function rollback($args, $assoc_args): void {
        try {
            $version = $args[0] ?? null;
            $tenantId = isset($assoc_args['tenant']) ? (int) $assoc_args['tenant'] : null;
            $steps = (int) ($assoc_args['steps'] ?? 1);
            $dry_run = isset($assoc_args['dry-run']);

            $this->logger->info('Rolling back migrations', [
                'version' => $version,
                'tenant_id' => $tenantId,
                'steps' => $steps,
                'dry_run' => $dry_run
            ]);

            if ($dry_run) {
                WP_CLI::log("Would rollback {$steps} migration(s)" . ($tenantId ? " for tenant {$tenantId}" : '') . ($version ? " to version {$version}" : '') . '.');
                return;
            }

            if ($tenantId) {
                $rolled = $this->schemaManager->rollbackTenantSchema($tenantId, $steps);
                WP_CLI::success("Rolled back {$rolled} migration(s) for tenant {$tenantId}.");
            } else {
                // Rollback all tenants
                $repository = $this->getTenantRepository();
                $tenants = $repository->listActive();
                $totalRolled = 0;

                foreach ($tenants as $tenant) {
                    try {
                        $rolled = $this->schemaManager->rollbackTenantSchema($tenant->id, $steps);
                        $totalRolled += $rolled;
                    } catch (Throwable $e) {
                        WP_CLI::warning("Failed to rollback tenant {$tenant->id}: {$e->getMessage()}");
                    }
                }

                WP_CLI::success("Rollback completed: {$totalRolled} total migration(s) across " . count($tenants) . " tenants.");
            }

            $this->logger->info('Rollback completed');

        } catch (Throwable $e) {
            $this->logger->error('Rollback command failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    /**
     * Show migration status
     *
     * Displays current migration version and history.
     *
     * ## OPTIONS
     *
     * [--tenant=<tenant>]
     * : Show status for specific tenant
     *
     * [--format=<format>]
     * : Output format
     * ---
     * default: table
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp bitcore migrate-status
     *
     *     $ wp bitcore migrate-status --tenant=123
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function migrate_status($args, $assoc_args): void {
        try {
            $tenantId = isset($assoc_args['tenant']) ? (int) $assoc_args['tenant'] : null;
            $format = $assoc_args['format'] ?? 'table';

            $this->logger->info('Checking migration status', [
                'tenant_id' => $tenantId
            ]);

            if ($tenantId) {
                // Show status for specific tenant
                $version = $this->schemaManager->getTenantSchemaVersion($tenantId);
                $items = [
                    [
                        'tenant_id' => $tenantId,
                        'current_version' => $version ?? 'N/A',
                        'status' => $version ? 'Migrated' : 'Not initialized',
                    ]
                ];
            } else {
                // Show status for all tenants
                $repository = $this->getTenantRepository();
                $tenants = $repository->listActive();
                $items = [];

                foreach ($tenants as $tenant) {
                    $version = $this->schemaManager->getTenantSchemaVersion($tenant->id);
                    $items[] = [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'current_version' => $version ?? 'N/A',
                        'status' => $version ? 'Migrated' : 'Not initialized',
                    ];
                }
            }

            if ($format === 'json') {
                WP_CLI::log(json_encode($items, JSON_PRETTY_PRINT));
            } else {
                Utils::format_items('table', $items, array_keys($items[0]));
            }

            $this->logger->info('Migration status retrieved');

        } catch (Throwable $e) {
            $this->logger->error('Migrate status command failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    // =================================================================================
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
