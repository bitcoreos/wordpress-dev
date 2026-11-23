<?php
/**
 * Factory Pattern Implementation
 *
 * Use for creating objects without specifying exact class
 * Pattern extracted from mcp-adapter and userswp repositories
 *
 * @package BITCORE\Patterns
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Patterns;

/**
 * Factory Interface
 *
 * Defines contract for factory implementations.
 *
 * @since 1.0.0
 */
interface FactoryInterface {
    /**
     * Create instance.
     *
     * @param string $type Object type to create.
     * @param array<string,mixed> $args Constructor arguments.
     * @return object Created instance.
     */
    public function create(string $type, array $args = []): object;

    /**
     * Check if factory can create type.
     *
     * @param string $type Object type.
     * @return bool True if can create.
     */
    public function canCreate(string $type): bool;
}

/**
 * Abstract Factory Base
 *
 * Provides common factory functionality.
 *
 * @since 1.0.0
 */
abstract class AbstractFactory implements FactoryInterface {
    /**
     * Registered types.
     *
     * @var array<string,class-string<object>>
     */
    protected array $types = [];

    /**
     * {@inheritDoc}
     */
    public function canCreate(string $type): bool {
        return isset($this->types[$type]);
    }

    /**
     * {@inheritDoc}
     */
    public function create(string $type, array $args = []): object {
        if (!$this->canCreate($type)) {
            throw new \InvalidArgumentException(
                sprintf(__('Unknown type: %s', 'bitcore-core'), $type)
            );
        }

        $class = $this->types[$type];
        return new $class(...$args);
    }

    /**
     * Register a type.
     *
     * @param string $type Type identifier.
     * @param class-string<object> $class Class name.
     * @return self
     */
    public function register(string $type, string $class): self {
        $this->types[$type] = $class;
        return $this;
    }
}

/**
 * Connector Factory
 *
 * Factory for creating connector instances.
 *
 * @since 1.0.0
 */
class ConnectorFactory extends AbstractFactory {
    /**
     * Constructor.
     */
    public function __construct() {
        $this->register('n8n', N8nConnector::class);
        $this->register('bithub', BithubConnector::class);
        $this->register('bitwiki', BitwikiConnector::class);
    }
}

/**
 * Task Factory
 *
 * Factory for creating task instances.
 *
 * @since 1.0.0
 */
class TaskFactory extends AbstractFactory {
    /**
     * Constructor.
     */
    public function __construct() {
        $this->register('research', ResearchTask::class);
        $this->register('code', CodeTask::class);
        $this->register('review', ReviewTask::class);
        $this->register('test', TestTask::class);
    }
}

/**
 * N8n Connector
 *
 * N8n workflow connector.
 *
 * @since 1.0.0
 */
class N8nConnector {
    /**
     * Endpoint URL.
     *
     * @var string
     */
    private string $endpoint;

    /**
     * API key.
     *
     * @var string
     */
    private string $apiKey;

    /**
     * Constructor.
     *
     * @param string $endpoint N8n endpoint.
     * @param string $apiKey API key.
     */
    public function __construct(string $endpoint, string $apiKey) {
        $this->endpoint = $endpoint;
        $this->apiKey = $apiKey;
    }

    /**
     * Trigger workflow.
     *
     * @param string $workflowId Workflow ID.
     * @param array<string,mixed> $data Workflow data.
     * @return array|\WP_Error Response or error.
     */
    public function trigger(string $workflowId, array $data = []) {
        return wp_remote_post(
            $this->endpoint . '/webhook/' . $workflowId,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($data),
                'timeout' => 30,
            ]
        );
    }
}

/**
 * Bithub Connector
 *
 * Bithub platform connector.
 *
 * @since 1.0.0
 */
class BithubConnector {
    /**
     * Endpoint URL.
     *
     * @var string
     */
    private string $endpoint;

    /**
     * API token.
     *
     * @var string
     */
    private string $token;

    /**
     * Constructor.
     *
     * @param string $endpoint Bithub endpoint.
     * @param string $token API token.
     */
    public function __construct(string $endpoint, string $token) {
        $this->endpoint = $endpoint;
        $this->token = $token;
    }
}

/**
 * Bitwiki Connector
 *
 * Bitwiki wiki connector.
 *
 * @since 1.0.0
 */
class BitwikiConnector {
    /**
     * API endpoint.
     *
     * @var string
     */
    private string $endpoint;

    /**
     * Constructor.
     *
     * @param string $endpoint API endpoint.
     */
    public function __construct(string $endpoint) {
        $this->endpoint = $endpoint;
    }
}

/**
 * Abstract Task
 *
 * Base task class.
 *
 * @since 1.0.0
 */
abstract class AbstractTask {
    /**
     * Task ID.
     *
     * @var string
     */
    protected string $id;

    /**
     * Task name.
     *
     * @var string
     */
    protected string $name;

    /**
     * Constructor.
     *
     * @param string $id Task ID.
     * @param string $name Task name.
     */
    public function __construct(string $id, string $name) {
        $this->id = $id;
        $this->name = $name;
    }

    /**
     * Execute task.
     *
     * @param array<string,mixed> $context Execution context.
     * @return mixed Task result.
     */
    abstract public function execute(array $context = []);
}

/**
 * Research Task
 *
 * Research task implementation.
 *
 * @since 1.0.0
 */
class ResearchTask extends AbstractTask {
    /**
     * {@inheritDoc}
     */
    public function execute(array $context = []) {
        // Research logic
        return ['status' => 'completed', 'type' => 'research'];
    }
}

/**
 * Code Task
 *
 * Code task implementation.
 *
 * @since 1.0.0
 */
class CodeTask extends AbstractTask {
    /**
     * {@inheritDoc}
     */
    public function execute(array $context = []) {
        // Code execution logic
        return ['status' => 'completed', 'type' => 'code'];
    }
}

/**
 * Review Task
 *
 * Review task implementation.
 *
 * @since 1.0.0
 */
class ReviewTask extends AbstractTask {
    /**
     * {@inheritDoc}
     */
    public function execute(array $context = []) {
        // Review logic
        return ['status' => 'completed', 'type' => 'review'];
    }
}

/**
 * Test Task
 *
 * Test task implementation.
 *
 * @since 1.0.0
 */
class TestTask extends AbstractTask {
    /**
     * {@inheritDoc}
     */
    public function execute(array $context = []) {
        // Test logic
        return ['status' => 'completed', 'type' => 'test'];
    }
}
