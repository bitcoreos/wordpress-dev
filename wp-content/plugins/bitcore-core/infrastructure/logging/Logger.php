<?php

declare(strict_types=1);

/**
 * BITCORE Logging Infrastructure - PSR-3 Logger
 *
 * @package BITCORE\Infrastructure\Logging
 * @since 1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Infrastructure\Logging;

use BITCORE\Infrastructure\Auth\TenantContext;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * BITCORE Structured Logger
 *
 * PSR-3 compatible logger with structured JSON output, log rotation,
 * tenant-aware context, and processor chain support.
 *
 * @since 1.0.0
 */
class Logger implements LoggerInterface
{
    /** @var array<string, int> Log level priority mapping */
    private const LEVELS = [
        'debug'     => 100,
        'info'      => 200,
        'notice'    => 250,
        'warning'   => 300,
        'error'     => 400,
        'critical'  => 500,
        'alert'     => 550,
        'emergency' => 600,
    ];

    /** @var string The logging channel */
    private string $channel;

    /** @var TenantContext|null Optional tenant context */
    private ?TenantContext $tenantContext;

    /** @var array<HandlerInterface> Registered handlers */
    private array $handlers = [];

    /** @var array<ProcessorInterface> Registered processors */
    private array $processors = [];

    /** @var array<int, array<string, mixed>> Context stack for nested contexts */
    private array $contextStack = [];

    /** @var array<string, mixed> Static context merged with all logs */
    private array $staticContext = [];

    /**
     * Constructor.
     *
     * @param string              $channel      The logging channel (default: 'bitcore').
     * @param TenantContext|null $tenantContext Optional tenant context for multi-tenant logging.
     * @since 1.0.0
     */
    public function __construct(string $channel = 'bitcore', ?TenantContext $tenantContext = null)
    {
        $this->channel       = $channel;
        $this->tenantContext = $tenantContext;
    }

    /**
     * System is unusable.
     *
     * @param string|Stringable $message The log message.
     * @param array<string, mixed> $context The log context.
     * @return void
     * @since 1.0.0
     */
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * @param string|Stringable $message The log message.
     * @param array<string, mixed> $context The log context.
     * @return void
     * @since 1.0.0
     */
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    /**
     * Critical conditions.
     *
     * @param string|Stringable $message The log message.
     * @param array<string, mixed> $context The log context.
     * @return void
     * @since 1.0.0
     */
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action.
     *
     * @param string|Stringable $message The log message.
     * @param array<string, mixed> $context The log context.
     * @return void
     * @since 1.0.0
     */
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * @param string|Stringable $message The log message.
     * @param array<string, mixed> $context The log context.
     * @return void
     * @since 1.0.0
     */
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string|Stringable $message The log message.
     * @param array<string, mixed> $context The log context.
     * @return void
     * @since 1.0.0
     */
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /**
     * Interesting events.
     *
     * @param string|Stringable $message The log message.
     * @param array<string, mixed> $context The log context.
     * @return void
     * @since 1.0.0
     */
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string|Stringable $message The log message.
     * @param array<string, mixed> $context The log context.
     * @return void
     * @since 1.0.0
     */
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Log a message at the specified level.
     *
     * @param mixed               $level   The log level.
     * @param string|Stringable   $message The log message.
     * @param array<string, mixed> $context The log context.
     * @return void
     * @throws \Psr\Log\InvalidArgumentException If the level is invalid.
     * @since 1.0.0
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $level = (string) $level;
        
        if (!isset(self::LEVELS[$level])) {
            throw new \Psr\Log\InvalidArgumentException("Invalid log level: {$level}");
        }

        $message = (string) $message;
        $context = array_merge($this->staticContext, $this->getMergedContext(), $context);
        $record  = $this->createRecord($level, $message, $context);
        $record  = $this->processRecord($record);
        $this->handleRecord($record);
    }

    /**
     * Log structured data with explicit context.
     *
     * @param string $level   The log level.
     * @param string $message The log message.
     * @param array<string, mixed> $context The structured context.
     * @return void
     * @since 1.0.0
     */
    public function logStructured(string $level, string $message, array $context): void
    {
        $this->log($level, $message, $context);
    }

    /**
     * Push context onto the stack.
     *
     * @param array<string, mixed> $context Context to push.
     * @return void
     * @since 1.0.0
     */
    public function pushContext(array $context): void
    {
        $this->contextStack[] = $context;
    }

    /**
     * Pop context from the stack.
     *
     * @return array<string, mixed> The popped context.
     * @throws \RuntimeException If context stack is empty.
     * @since 1.0.0
     */
    public function popContext(): array
    {
        if (empty($this->contextStack)) {
            throw new \RuntimeException('Cannot pop from empty context stack');
        }

        return array_pop($this->contextStack);
    }

    /**
     * Create a new logger instance with additional context.
     *
     * @param array<string, mixed> $context Context to merge.
     * @return self New logger instance with merged context.
     * @since 1.0.0
     */
    public function withContext(array $context): self
    {
        $newLogger = new self($this->channel, $this->tenantContext);
        
        foreach ($this->handlers as $handler) {
            $newLogger->pushHandler($handler);
        }
        
        foreach ($this->processors as $processor) {
            $newLogger->pushProcessor($processor);
        }

        $newLogger->contextStack = $this->contextStack;
        $newLogger->staticContext = array_merge($this->staticContext, $context);

        return $newLogger;
    }

    /**
     * Register a handler.
     *
     * @param HandlerInterface $handler The handler to register.
     * @return void
     * @since 1.0.0
     */
    public function pushHandler(HandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * Register a processor.
     *
     * @param ProcessorInterface $processor The processor to register.
     * @return void
     * @since 1.0.0
     */
    public function pushProcessor(ProcessorInterface $processor): void
    {
        $this->processors[] = $processor;
    }

    /**
     * Create a log record.
     *
     * @param string $level   The log level.
     * @param string $message The log message.
     * @param array<string, mixed> $context The log context.
     * @return array<string, mixed> The log record.
     * @since 1.0.0
     */
    private function createRecord(string $level, string $message, array $context): array
    {
        $record = [
            'level'    => $level,
            'message'  => $message,
            'context'  => $context,
            'datetime' => new DateTimeImmutable('now', new \DateTimeZone('UTC')),
            'channel'  => $this->channel,
            'extra'    => [],
        ];

        // Add tenant ID if tenant context is available
        if ($this->tenantContext !== null && $this->tenantContext->hasTenant()) {
            $record['tenant_id'] = $this->tenantContext->getCurrentTenant()->getId();
        }

        return $record;
    }

    /**
     * Process a log record through all processors.
     *
     * @param array<string, mixed> $record The log record.
     * @return array<string, mixed> The processed record.
     * @since 1.0.0
     */
    private function processRecord(array $record): array
    {
        foreach ($this->processors as $processor) {
            $record = $processor($record);
        }

        return $record;
    }

    /**
     * Handle a log record through all registered handlers.
     *
     * @param array<string, mixed> $record The log record.
     * @return void
     * @since 1.0.0
     */
    private function handleRecord(array $record): void
    {
        if (empty($this->handlers)) {
            // Fallback to error_log if no handlers
            error_log('BITCORE: ' . $record['message']);
            return;
        }

        foreach ($this->handlers as $handler) {
            if ($handler->handle($record)) {
                // Handler returns true when it handles the record
                break;
            }
        }
    }

    /**
     * Merge all context stack entries into a single array.
     *
     * @return array<string, mixed> The merged context.
     * @since 1.0.0
     */
    private function getMergedContext(): array
    {
        $merged = [];
        foreach ($this->contextStack as $context) {
            $merged = array_merge($merged, $context);
        }
        return $merged;
    }

    // =========================================================================
    // TEST SCAFFOLD - Uncomment for unit testing
    // =========================================================================
    /*
    // Test: Logger basic functionality
    public function testLoggerBasics(): void
    {
        $logger = new Logger('test');
        $logger->pushHandler(new JsonFileHandler('/tmp/logs'));
        
        $logger->info('Test message', ['key' => 'value']);
        assert(file_exists('/tmp/logs/bitcore-' . date('Y-m-d') . '.log'));
    }
    
    // Test: Context stacking
    public function testContextStack(): void
    {
        $logger = new Logger('test');
        $logger->pushContext(['request_id' => 'abc123']);
        $logger->pushContext(['user_id' => 42]);
        
        // Both request_id and user_id should be in context
        $context = $logger->withContext([]);
        
        assert($logger->popContext()['user_id'] === 42);
        assert($logger->popContext()['request_id'] === 'abc123');
    }
    
    // Test: Log levels
    public function testLogLevels(): void
    {
        $levels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
        foreach ($levels as $level) {
            $method = $level;
            // Should not throw
            $this->log->$method('Test ' . $level);
        }
    }
    
    // Test: Invalid log level
    public function testInvalidLevel(): void
    {
        try {
            $this->log('invalid', 'message');
            assert(false, 'Should throw InvalidArgumentException');
        } catch (\Psr\Log\InvalidArgumentException $e) {
            assert(true);
        }
    }
    
    // Test: Handlers chain
    public function testHandlerChain(): void
    {
        $logger = new Logger('test');
        $logger->pushHandler(new ErrorLogHandler('error'));
        $logger->pushHandler(new JsonFileHandler('/tmp/logs'));
        
        // Error should hit ErrorLogHandler first
        $logger->error('Error message');
    }
    
    // Test: Processors
    public function testProcessors(): void
    {
        $logger = new Logger('test');
        $logger->pushProcessor(new WebProcessor());
        
        // WebProcessor should add $_SERVER data to extra
    }
    
    // Test: Tenant integration
    public function testTenantContext(): void
    {
        $tenantContext = new TenantContext();
        // Set up tenant...
        
        $logger = new Logger('test', $tenantContext);
        // Logs should include tenant_id
    }
    */
}
