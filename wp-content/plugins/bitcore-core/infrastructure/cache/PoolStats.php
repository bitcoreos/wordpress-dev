<?php
/**
 * Cache Pool Implementation
 *
 * Pooled caching with tenant-aware key scoping and statistics tracking
 * for WordPress SaaS infrastructure.
 *
 * @package BITCORE\Infrastructure\Cache
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Cache;

use Psr\SimpleCache\CacheInterface;
use DateTime;

/**
 * Pool Statistics DTO
 *
 * Immutable data transfer object for cache pool metrics.
 *
 * @package BITCORE\Infrastructure\Cache
 * @since 1.0.0
 */
class PoolStats
{
    /**
     * Constructor
     *
     * @param int $hits Number of cache hits
     * @param int $misses Number of cache misses
     * @param float $hitRate Cache hit rate percentage
     * @param int $size Total number of cached entries
     * @param int|null $oldestEntry Timestamp of oldest entry
     * @since 1.0.0
     */
    public function __construct(
        private readonly int $hits,
        private readonly int $misses,
        private readonly float $hitRate,
        private readonly int $size,
        private readonly ?int $oldestEntry = null
    ) {}

    /**
     * Get number of cache hits
     *
     * @return int
     * @since 1.0.0
     */
    public function getHits(): int
    {
        return $this->hits;
    }

    /**
     * Get number of cache misses
     *
     * @return int
     * @since 1.0.0
     */
    public function getMisses(): int
    {
        return $this->misses;
    }

    /**
     * Get cache hit rate percentage
     *
     * @return float
     * @since 1.0.0
     */
    public function getHitRate(): float
    {
        return $this->hitRate;
    }

    /**
     * Get total number of cached entries
     *
     * @return int
     * @since 1.0.0
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get timestamp of oldest entry
     *
     * @return int|null
     * @since 1.0.0
     */
    public function getOldestEntry(): ?int
    {
        return $this->oldestEntry;
    }

    /**
     * Get total requests
     *
     * @return int
     * @since 1.0.0
     */
    public function getTotal(): int
    {
        return $this->hits + $this->misses;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     * @since 1.0.0
     */
    public function toArray(): array
    {
        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'hitRate' => $this->hitRate,
            'size' => $this->size,
            'oldestEntry' => $this->oldestEntry,
            'total' => $this->getTotal()
        ];
    }
}
