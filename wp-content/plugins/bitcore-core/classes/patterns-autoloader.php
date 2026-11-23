<?php
/**
 * Pattern Autoloader
 *
 * Loads and registers all pattern classes
 *
 * @package BITCORE\Patterns
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Patterns;

/**
 * Pattern Autoloader
 *
 * @since 1.0.0
 */
class PatternAutoloader {
    /**
     * Instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Loaded patterns.
     *
     * @var array<string,bool>
     */
    private array $loaded = [];

    /**
     * Constructor.
     */
    private function __construct() {}

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load all patterns.
     *
     * @return self
     */
    public function load(): self {
        $this->loadPatterns();
        $this->loadDatabase();
        $this->loadTemplates();
        $this->loadWidgets();
        $this->loadDomain();
        $this->loadValidation();
        $this->loadRealtime();
        
        return $this;
    }

    /**
     * Load design patterns.
     *
     * @return void
     */
    private function loadPatterns(): void {
        $patternFiles = [
            'builder' => BITCORE_PLUGIN_DIR . 'patterns/builder.php',
            'strategy' => BITCORE_PLUGIN_DIR . 'patterns/strategy.php',
            'decorator' => BITCORE_PLUGIN_DIR . 'patterns/decorator.php',
            'repository' => BITCORE_PLUGIN_DIR . 'patterns/repository.php',
            'factory' => BITCORE_PLUGIN_DIR . 'patterns/factory.php',
        ];

        foreach ($patternFiles as $name => $file) {
            if (file_exists($file) && !isset($this->loaded[$name])) {
                require_once $file;
                $this->loaded[$name] = true;
            }
        }
    }

    /**
     * Load database patterns.
     *
     * @return void
     */
    private function loadDatabase(): void {
        $file = BITCORE_PLUGIN_DIR . 'database/schema.php';
        if (file_exists($file) && !isset($this->loaded['database'])) {
            require_once $file;
            $this->loaded['database'] = true;
        }
    }

    /**
     * Load template patterns.
     *
     * @return void
     */
    private function loadTemplates(): void {
        $file = BITCORE_PLUGIN_DIR . 'templates/loader.php';
        if (file_exists($file) && !isset($this->loaded['templates'])) {
            require_once $file;
            $this->loaded['templates'] = true;
        }
    }

    /**
     * Load widget patterns.
     *
     * @return void
     */
    private function loadWidgets(): void {
        $file = BITCORE_PLUGIN_DIR . 'widgets/base.php';
        if (file_exists($file) && !isset($this->loaded['widgets'])) {
            require_once $file;
            $this->loaded['widgets'] = true;
        }
    }

    /**
     * Load domain patterns.
     *
     * @return void
     */
    private function loadDomain(): void {
        $domainFiles = [
            'value-objects' => BITCORE_PLUGIN_DIR . 'domain/value-objects.php',
            'events' => BITCORE_PLUGIN_DIR . 'domain/events.php',
        ];

        foreach ($domainFiles as $name => $file) {
            if (file_exists($file) && !isset($this->loaded["domain.{$name}"])) {
                require_once $file;
                $this->loaded["domain.{$name}"] = true;
            }
        }
    }

    /**
     * Load validation patterns.
     *
     * @return void
     */
    private function loadValidation(): void {
        $file = BITCORE_PLUGIN_DIR . 'validation/schema.php';
        if (file_exists($file) && !isset($this->loaded['validation'])) {
            require_once $file;
            $this->loaded['validation'] = true;
        }
    }

    /**
     * Load realtime patterns.
     *
     * @return void
     */
    private function loadRealtime(): void {
        $file = BITCORE_PLUGIN_DIR . 'realtime/sse.php';
        if (file_exists($file) && !isset($this->loaded['realtime'])) {
            require_once $file;
            $this->loaded['realtime'] = true;
        }
    }

    /**
     * Check if pattern is loaded.
     *
     * @param string $name Pattern name.
     * @return bool True if loaded.
     */
    public function isLoaded(string $name): bool {
        return isset($this->loaded[$name]);
    }

    /**
     * Get loaded patterns.
     *
     * @return array<string> Loaded pattern names.
     */
    public function getLoaded(): array {
        return array_keys($this->loaded);
    }

    /**
     * Initialize database on plugin activation.
     *
     * @return void
     */
    public static function activate(): void {
        if (class_exists(Database\SchemaManager::class)) {
            $schema = new Database\SchemaManager();
            $schema->install();
        }
    }

    /**
     * Register widgets.
     *
     * @return void
     */
    public function registerWidgets(): void {
        if (class_exists(Widgets\TaskStatusWidget::class)) {
            Widgets\WidgetRegistration::register(Widgets\TaskStatusWidget::class);
        }
    }
}
