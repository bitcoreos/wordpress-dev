<?php
/**
 * Builder Pattern Implementation
 *
 * Use for complex object construction (TaskBuilder, ConnectorBuilder)
 * Pattern extracted from ai-services and mcp-adapter repositories
 *
 * @package BITCORE\Patterns
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Patterns;

/**
 * Builder Interface
 *
 * Defines contract for builder implementations.
 *
 * @since 1.0.0
 */
interface BuilderInterface {
    /**
     * Set part A of the object.
     *
     * @param mixed $value The value to set.
     * @return self
     */
    public function setPartA($value): self;

    /**
     * Set part B of the object.
     *
     * @param mixed $value The value to set.
     * @return self
     */
    public function setPartB($value): self;

    /**
     * Get the constructed result.
     *
     * @return mixed The constructed object.
     */
    public function getResult();
}

/**
 * Abstract Builder Base Class
 *
 * Provides common functionality for builders.
 *
 * @since 1.0.0
 */
abstract class AbstractBuilder implements BuilderInterface {
    /**
     * Build data.
     *
     * @var array<string,mixed>
     */
    protected array $data = [];

    /**
     * Reset the builder state.
     *
     * @return void
     */
    protected function reset(): void {
        $this->data = [];
    }

    /**
     * {@inheritDoc}
     */
    abstract public function setPartA($value): self;

    /**
     * {@inheritDoc}
     */
    abstract public function setPartB($value): self;

    /**
     * {@inheritDoc}
     */
    abstract public function getResult();
}

/**
 * Task Builder
 *
 * Builder for constructing task objects with complex configuration.
 *
 * @since 1.0.0
 */
class TaskBuilder extends AbstractBuilder {
    /**
     * Set task name.
     *
     * @param string $name Task name.
     * @return self
     */
    public function setPartA($value): self {
        $this->data['name'] = sanitize_text_field($value);
        return $this;
    }

    /**
     * Set task description.
     *
     * @param string $description Task description.
     * @return self
     */
    public function setPartB($value): self {
        $this->data['description'] = sanitize_textarea_field($value);
        return $this;
    }

    /**
     * Set task priority.
     *
     * @param string $priority Priority level (low, normal, high, urgent).
     * @return self
     */
    public function setPriority(string $priority): self {
        $valid_priorities = ['low', 'normal', 'high', 'urgent'];
        $this->data['priority'] = in_array($priority, $valid_priorities, true) ? $priority : 'normal';
        return $this;
    }

    /**
     * Set task metadata.
     *
     * @param array<string,mixed> $meta Metadata array.
     * @return self
     */
    public function setMeta(array $meta): self {
        $this->data['meta'] = array_map('sanitize_text_field', $meta);
        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string,mixed> The constructed task data.
     */
    public function getResult(): array {
        $result = $this->data;
        $this->reset();
        return $result;
    }
}

/**
 * Connector Builder
 *
 * Builder for constructing connector configuration objects.
 *
 * @since 1.0.0
 */
class ConnectorBuilder extends AbstractBuilder {
    /**
     * Set connector type.
     *
     * @param string $type Connector type (n8n, bithub, bitwiki).
     * @return self
     */
    public function setPartA($value): self {
        $this->data['type'] = sanitize_key($value);
        return $this;
    }

    /**
     * Set connector endpoint URL.
     *
     * @param string $url Endpoint URL.
     * @return self
     */
    public function setPartB($value): self {
        $this->data['endpoint'] = esc_url_raw($value);
        return $this;
    }

    /**
     * Set authentication credentials.
     *
     * @param array<string,string> $credentials Auth credentials.
     * @return self
     */
    public function setCredentials(array $credentials): self {
        $this->data['credentials'] = $credentials;
        return $this;
    }

    /**
     * Set connection timeout.
     *
     * @param int $timeout Timeout in seconds.
     * @return self
     */
    public function setTimeout(int $timeout): self {
        $this->data['timeout'] = max(5, min(300, $timeout));
        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string,mixed> The constructed connector config.
     */
    public function getResult(): array {
        $result = $this->data;
        $this->reset();
        return $result;
    }
}
