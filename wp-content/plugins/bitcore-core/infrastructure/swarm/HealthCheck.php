<?php
/**
 * Supporting health check logic for Bitcore
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Swarm;

use BITCORE\Infrastructure\Cache\CacheManager;
use BITCORE\Infrastructure\Swarm\CheckType;
use BITCORE\Infrastructure\Swarm\HealthCheckResult;

if (!defined('ABSPATH')) {
    exit;
}

class DatabaseHealthChecker implements HealthCheckerInterface {

    /**
     * Perform database check.
     *
     * @return HealthCheckResult Result.
     * @since 1.0.0
     */
    public function check(): HealthCheckResult {
        $start = hrtime(true);

        try {
            global $wpdb;
            $result = $wpdb->get_var('SELECT 1');
            $end = hrtime(true);

            if ($result === '1') {
                return HealthCheckResult::success(
                    $this->getType(),
                    (int) round(($end - $start) / 1e6),
                    'Database connected'
                );
            }

            return HealthCheckResult::failure(
                $this->getType(),
                'Unexpected query result',
                (int) round(($end - $start) / 1e6)
            );
        } catch (\Throwable $e) {
            $end = hrtime(true);
            return HealthCheckResult::failure(
                $this->getType(),
                $e->getMessage(),
                (int) round(($end - $start) / 1e6)
            );
        }
    }

    /**
     * Get check type.
     *
     * @return CheckType Type.
     * @since 1.0.0
     */
    public function getType(): CheckType {
        return CheckType::DATABASE;
    }
}

/**
 * Cache connectivity checker.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */
class CacheHealthChecker implements HealthCheckerInterface {

    /**
     * Cache manager.
     *
     * @var CacheManager
     * @since 1.0.0
     */
    private CacheManager $cache;

    /**
     * Constructor.
     *
     * @param CacheManager $cache Cache manager.
     * @since 1.0.0
     */
    public function __construct(CacheManager $cache) {
        $this->cache = $cache;
    }

    /**
     * Perform cache check.
     *
     * @return HealthCheckResult Result.
     * @since 1.0.0
     */
    public function check(): HealthCheckResult {
        $start = hrtime(true);
        $key = 'health_check_' . time();

        try {
            $this->cache->set($key, 'test', 10);
            $value = $this->cache->get($key);
            $this->cache->delete($key);
            $end = hrtime(true);

            if ($value === 'test') {
                return HealthCheckResult::success(
                    $this->getType(),
                    (int) round(($end - $start) / 1e6),
                    'Cache operational'
                );
            }

            return HealthCheckResult::failure(
                $this->getType(),
                'Cache read/write mismatch',
                (int) round(($end - $start) / 1e6)
            );
        } catch (\Throwable $e) {
            $end = hrtime(true);
            return HealthCheckResult::failure(
                $this->getType(),
                $e->getMessage(),
                (int) round(($end - $start) / 1e6)
            );
        }
    }

    /**
     * Get check type.
     *
     * @return CheckType Type.
     * @since 1.0.0
     */
    public function getType(): CheckType {
        return CheckType::CACHE;
    }
}
