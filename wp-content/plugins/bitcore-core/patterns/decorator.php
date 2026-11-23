<?php
/**
 * Decorator Pattern Implementation
 *
 * Use for extending functionality without subclassing
 * Pattern extracted from mcp-adapter and ai-services
 *
 * @package BITCORE\Patterns
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Patterns;

/**
 * Component Interface
 *
 * Base interface for components that can be decorated.
 *
 * @since 1.0.0
 */
interface ComponentInterface {
    /**
     * Execute the component operation.
     *
     * @param mixed $data Input data.
     * @return mixed Operation result.
     */
    public function operation($data);
}

/**
 * Abstract Decorator Base
 *
 * Base class for decorators implementing the component interface.
 *
 * @since 1.0.0
 */
abstract class Decorator implements ComponentInterface {
    /**
     * Wrapped component.
     *
     * @var ComponentInterface|null
     */
    protected ?ComponentInterface $component = null;

    /**
     * Constructor.
     *
     * @param ComponentInterface|null $component Component to wrap.
     */
    public function __construct(?ComponentInterface $component = null) {
        $this->component = $component;
    }

    /**
     * Set the wrapped component.
     *
     * @param ComponentInterface $component Component to wrap.
     * @return self
     */
    public function setComponent(ComponentInterface $component): self {
        $this->component = $component;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    abstract public function operation($data);
}

/**
 * Base Component
 *
 * Concrete component that can be decorated.
 *
 * @since 1.0.0
 */
class BaseComponent implements ComponentInterface {
    /**
     * {@inheritDoc}
     */
    public function operation($data) {
        return $data;
    }
}

/**
 * Logging Decorator
 *
 * Adds logging functionality to any component.
     *
     * @since 1.0.0
     */
class LoggingDecorator extends Decorator {
    /**
     * Log level.
     *
     * @var string
     */
    protected string $logLevel = 'info';

    /**
     * Set log level.
     *
     * @param string $level Log level.
     * @return self
     */
    public function setLogLevel(string $level): self {
        $this->logLevel = $level;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function operation($data) {
        if ($this->component) {
            $startTime = microtime(true);
            $result = $this->component->operation($data);
            $duration = (microtime(true) - $startTime) * 1000;

            // Log the operation
            if (function_exists('bitcore_log')) {
                bitcore_log(
                    sprintf(
                        '[%s] Operation executed in %.2fms',
                        $this->logLevel,
                        $duration
                    ),
                    ['component' => get_class($this->component)]
                );
            }

            return $result;
        }
        return $data;
    }
}

/**
 * Validation Decorator
 *
 * Adds validation functionality to any component.
     *
     * @since 1.0.0
     */
class ValidationDecorator extends Decorator {
    /**
     * Validation rules.
     *
     * @var array<string,mixed>
     */
    protected array $rules = [];

    /**
     * Set validation rules.
     *
     * @param array<string,mixed> $rules Validation rules.
     * @return self
     */
    public function setRules(array $rules): self {
        $this->rules = $rules;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function operation($data) {
        // Validate before passing to component
        $validated = $this->validate($data);
        if (is_wp_error($validated)) {
            return $validated;
        }

        if ($this->component) {
            return $this->component->operation($validated);
        }
        return $validated;
    }

    /**
     * Validate data against rules.
     *
     * @param mixed $data Data to validate.
     * @return mixed|\WP_Error Validated data or error.
     */
    protected function validate($data) {
        foreach ($this->rules as $field => $rule) {
            $value = $data[$field] ?? null;

            if ($rule === 'required' && empty($value)) {
                return new \WP_Error(
                    'validation_failed',
                    sprintf(__('Field %s is required.', 'bitcore-core'), $field)
                );
            }
        }
        return $data;
    }
}

/**
 * Caching Decorator
 *
 * Adds caching functionality to any component.
     *
     * @since 1.0.0
     */
class CachingDecorator extends Decorator {
    /**
     * Cache key prefix.
     *
     * @var string
     */
    protected string $cachePrefix = 'decorator_';

    /**
     * Cache expiration.
     *
     * @var int
     */
    protected int $expiration = HOUR_IN_SECONDS;

    /**
     * Set cache configuration.
     *
     * @param string $prefix Cache key prefix.
     * @param int $expiration Cache expiration in seconds.
     * @return self
     */
    public function setCacheConfig(string $prefix, int $expiration): self {
        $this->cachePrefix = $prefix;
        $this->expiration = $expiration;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function operation($data) {
        $cacheKey = $this->getCacheKey($data);
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        if ($this->component) {
            $result = $this->component->operation($data);
            set_transient($cacheKey, $result, $this->expiration);
            return $result;
        }

        return $data;
    }

    /**
     * Generate cache key from data.
     *
     * @param mixed $data Input data.
     * @return string Cache key.
     */
    protected function getCacheKey($data): string {
        return $this->cachePrefix . md5(serialize($data));
    }
}
