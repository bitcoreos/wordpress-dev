<?php
/**
 * Cache Manager Implementation
 *
 * PSR-16 SimpleCache implementation with multi-tier caching support
 * for WordPress SaaS infrastructure.
 *
 * @package BITCORE\Infrastructure\Cache
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Cache;

use Psr\SimpleCache\CacheInterface;
use DateInterval;
use DateTime;
use BITCORE\Infrastructure\Multitenancy\TenantContext;

/**

 * Object Cache Tier - WordPress Object Cache wrapper
 *
 * @package BITCORE\Infrastructure\Cache
 * @since 1.0.0
 */
class ObjectCacheTier implements CacheInterface
{
    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = wp_cache_get($key);
        return $value !== false ? $value : $default;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $expire = $this->convertTtl($ttl);
        return wp_cache_set($key, $value, '', $expire);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        return wp_cache_delete($key);
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        return wp_cache_flush();
    }

    /**
     * @inheritDoc
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    /**
     * @inheritDoc
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return wp_cache_get($key) !== false;
    }

    /**
     * Convert TTL to seconds
     *
     * @param null|int|DateInterval $ttl
     * @return int
     */
    private function convertTtl(null|int|DateInterval $ttl): int
    {
        if ($ttl === null) {
            return 0;
        }
        if ($ttl instanceof DateInterval) {
            $now = new DateTime();
            $then = clone $now;
            $then->add($ttl);
            return $then->getTimestamp() - $now->getTimestamp();
        }
        return $ttl;
    }
}

/**
 * Transient Cache Tier - WordPress Transients API wrapper
 *
 * @package BITCORE\Infrastructure\Cache
 * @since 1.0.0
 */
class TransientCacheTier implements CacheInterface, ClearableInterface
{
    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = get_transient($key);
        return $value !== false ? $value : $default;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $expire = $this->convertTtl($ttl) ?: 0;
        return set_transient($key, $value, $expire);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        return delete_transient($key);
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \'_transient_%\'");
        wp_cache_flush();
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    /**
     * @inheritDoc
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return get_transient($key) !== false;
    }

    /**
     * @inheritDoc
     */
    public function clearByPattern(string $pattern): int
    {
        global $wpdb;
        $count = 0;
        $transients = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE \'_transient_%\'");
        foreach ($transients as $transient) {
            $key = str_replace('_transient_', '', $transient);
            if (preg_match($pattern, $key)) {
                delete_transient($key);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Convert TTL to seconds
     *
     * @param null|int|DateInterval $ttl
     * @return int
     */
    private function convertTtl(null|int|DateInterval $ttl): int
    {
        if ($ttl === null) {
            return 0;
        }
        if ($ttl instanceof DateInterval) {
            $now = new DateTime();
            $then = clone $now;
            $then->add($ttl);
            return $then->getTimestamp() - $now->getTimestamp();
        }
        return $ttl;
    }
}

/**
 * Database Cache Tier - WordPress Options table fallback
 *
 * @package BITCORE\Infrastructure\Cache
 * @since 1.0.0
 */
class DatabaseCacheTier implements CacheInterface, ClearableInterface
{
    private const PREFIX = 'bitcore_cache_';

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = get_option(self::PREFIX . $key);
        return $value !== false ? maybe_unserialize($value) : $default;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $serialized = maybe_serialize($value);
        $result = update_option(self::PREFIX . $key, $serialized, false);

        // Store expiration if TTL provided
        if ($ttl !== null) {
            $expire = time() + $this->convertTtl($ttl);
            update_option(self::PREFIX . $key . '_expire', $expire, false);
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        delete_option(self::PREFIX . $key . '_expire');
        return delete_option(self::PREFIX . $key);
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like(self::PREFIX) . '%'
        ));
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    /**
     * @inheritDoc
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        $value = get_option(self::PREFIX . $key);
        if ($value === false) {
            return false;
        }

        // Check expiration
        $expire = get_option(self::PREFIX . $key . '_expire');
        if ($expire !== false && $expire < time()) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function clearByPattern(string $pattern): int
    {
        global $wpdb;
        $count = 0;
        $options = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like(self::PREFIX) . '%'
        ));

        foreach ($options as $option) {
            $key = str_replace(self::PREFIX, '', $option);
            if (preg_match($pattern, $key)) {
                $this->delete($key);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Convert TTL to seconds
     *
     * @param null|int|DateInterval $ttl
     * @return int
     */
    private function convertTtl(null|int|DateInterval $ttl): int
    {
        if ($ttl === null) {
            return 0;
        }
        if ($ttl instanceof DateInterval) {
            $now = new DateTime();
            $then = clone $now;
            $then->add($ttl);
            return $then->getTimestamp() - $now->getTimestamp();
        }
        return $ttl;
    }
}

/**
 * Interface for cache tiers that support pattern-based clearing
 *
 * @package BITCORE\Infrastructure\Cache
 * @since 1.0.0
 */
interface ClearableInterface
{
    /**
     * Clear cache entries matching a pattern
     *
     * @param string $pattern Regex pattern
     * @return int Number of cleared entries
     */
    public function clearByPattern(string $pattern): int;
}
