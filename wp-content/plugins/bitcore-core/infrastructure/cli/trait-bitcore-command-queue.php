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



trait Bitcore_Command_Queue_Trait {

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
}
