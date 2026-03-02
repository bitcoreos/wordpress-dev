<?php
/**
 * Schema table creation and database structure for Bitcore multitenancy
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

<?php
/**
 * Multi-Tenancy Schema Management
 *
 * Provides schema isolation, migration system, and database abstraction
 * for multi-tenant SaaS operations in WordPress environment.
 *
 * @package BITCORE
 * @subpackage Infrastructure\MultiTenancy
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\MultiTenancy;

use BITCORE\Infrastructure\Auth\TenantContext;
use InvalidArgumentException;
use RuntimeException;
use DateTimeImmutable;

/**
 * Schema Version DTO
 *
 * Immutable value object representing a migration schema version entry.
 * Tracks application metadata including timing and errors.
 *
 * @since 1.0.0
 */
final class SchemaVersion {

    /**
     * Migration version identifier
     *
     * @var string
     * @since 1.0.0
     */
    public readonly string $version;

    /**
     * Timestamp when migration was applied
     *
     * @var DateTimeImmutable|null
     * @since 1.0.0
     */
    public readonly ?DateTimeImmutable $appliedAt;

    /**
     * Duration of migration execution in milliseconds
     *
     * @var int
     * @since 1.0.0
     */
    public readonly int $duration;

    /**
     * Error message if migration failed
     *
     * @var string|null
     * @since 1.0.0
     */
    public readonly ?string $error;

    /**
     * Constructor
     *
     * @param string $version Migration version identifier
     * @param DateTimeImmutable|null $appliedAt Timestamp when applied
     * @param int $duration Execution duration in milliseconds
     * @param string|null $error Error message if failed
     * @since 1.0.0
     */
    public function __construct(
        string $version,
        ?DateTimeImmutable $appliedAt = null,
        int $duration = 0,
        ?string $error = null
    ) {
        if (empty($version)) {
            throw new InvalidArgumentException('Schema version cannot be empty');
        }

        $this->version = $version;
        $this->appliedAt = $appliedAt;
        $this->duration = $duration;
        $this->error = $error;
    }

    /**
     * Check if migration was successful
     *
     * @return bool True if no error occurred
     * @since 1.0.0
     */
    public function isSuccessful(): bool {
        return $this->error === null;
    }

    /**
     * Check if migration has been applied
     *
     * @return bool True if appliedAt is set
     * @since 1.0.0
     */
    public function isApplied(): bool {
        return $this->appliedAt !== null;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed> Array representation
     * @since 1.0.0
     */
    public function toArray(): array {
        return [
            'version' => $this->version,
            'appliedAt' => $this->appliedAt?->format('c'),
            'duration' => $this->duration,
            'error' => $this->error,
        ];
    }
}

/**
 * Migration Interface
 *
 * Contract for database migrations in multi-tenant environment.
 * Implementations define upgrade and rollback operations.
 *
 * @since 1.0.0
 */
interface MigrationInterface {

    /**
     * Apply migration
     *
     * Executes forward migration to upgrade schema.
     *
     * @return void
     * @throws RuntimeException If migration fails
     * @since 1.0.0
     */
    public function up(): void;

    /**
     * Revert migration
     *
     * Executes rollback to downgrade schema.
     *
     * @return void
     * @throws RuntimeException If rollback fails
     * @since 1.0.0
     */
    public function down(): void;

    /**
     * Get migration version
     *
     * @return string Semantic version string (e.g., "1.0.0")
     * @since 1.0.0
     */
    public function getVersion(): string;

    /**
     * Get migration description
     *
     * @return string Human-readable description of changes
     * @since 1.0.0
     */
    public function getDescription(): string;
}

/**
 * Abstract Migration
 *
 * Base class for database migrations providing common utilities
 * and database access for tenant schema operations.
 *
 * @since 1.0.0
 */
abstract class AbstractMigration implements MigrationInterface {

    /**
     * WordPress database instance
     *
     * @var \wpdb
     * @since 1.0.0
     */
    protected \wpdb $db;

    /**
     * Tenant ID for this migration
     *
     * @var int
     * @since 1.0.0
     */
    protected int $tenantId;

    /**
     * Schema manager for table name resolution
     *
     * @var SchemaManager|null
     * @since 1.0.0
     */
    protected ?SchemaManager $schemaManager = null;

    /**
     * Constructor
     *
     * @param \wpdb $db WordPress database instance
     * @param int $tenantId Tenant ID for this migration
     * @since 1.0.0
     */
    public function __construct(\wpdb $db, int $tenantId) {
        $this->db = $db;
        $this->tenantId = $tenantId;
    }

    /**
     * Set schema manager for table resolution
     *
     * @param SchemaManager $schemaManager Schema manager instance
     * @return void
     * @since 1.0.0
     */
    public function setSchemaManager(SchemaManager $schemaManager): void {
        $this->schemaManager = $schemaManager;
    }

    /**
     * Get table name with tenant prefix
     *
     * Uses SchemaManager if available, otherwise constructs manually.
     *
     * @param string $table Base table name
     * @return string Full table name with tenant prefix
     * @since 1.0.0
     */
    protected function getTableName(string $table): string {
        if ($this->schemaManager !== null) {
            return $this->schemaManager->getTableName($table, $this->tenantId);
        }

        return $this->db->prefix . "tenant_{$this->tenantId}_{$table}";
    }

    /**
     * Execute SQL with error handling
     *
     * @param string $sql SQL statement to execute
     * @return bool True on success
     * @throws RuntimeException On query failure
     * @since 1.0.0
     */
    protected function execute(string $sql): bool {
        $result = $this->db->query($sql);

        if ($result === false) {
            throw new RuntimeException(
                "Migration failed for tenant {$this->tenantId}: " . $this->db->last_error
            );
        }

        return true;
    }

    /**
     * Execute dbDelta for schema changes
     *
     * @param string $sql DDL statement for dbDelta
     * @return array<string, string> dbDelta results
     * @since 1.0.0
     */
    protected function delta(string $sql): array {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        return dbDelta($sql);
    }
}

/**
 * Schema Manager
 *
 * Manages tenant-specific schema isolation, migrations, and table resolution.
 * Provides both table-prefix and row-level isolation strategies.
 *
 * @since 1.0.0
 */
class SchemaManager {

    /**
     * WordPress database instance
     *
     * @var \wpdb
     * @since 1.0.0
     */
    private \wpdb $db;

    /**
     * Tenant context for current tenant
     *
     * @var TenantContext
     * @since 1.0.0
     */
    private TenantContext $tenantContext;

    /**
     * Registered migrations
     *
     * @var array<string, array{up: callable, down: callable}>
     * @since 1.0.0
     */
    private array $migrations = [];

    /**
     * Schema versions table name
     *
     * @var string
     * @since 1.0.0
     */
    private string $versionsTable;

    /**
     * Shared tables (not prefixed per tenant)
     *
     * @var array<string>
     * @since 1.0.0
     */
    private array $sharedTables = [];

    /**
     * Row-level isolation tables
     *
     * @var array<string>
     * @since 1.0.0
     */
    private array $rowLevelTables = [];

    /**
     * Isolation strategy: 'prefix' or 'row'
     *
     * @var string
     * @since 1.0.0
     */
    private string $isolationStrategy;

    /**
     * Currently active tenant for table resolution
     *
     * @var int|null
     * @since 1.0.0
     */
    private ?int $activeTenant = null;

    /**
     * Constructor
     *
     * @param \wpdb $db WordPress database instance
     * @param TenantContext $tenantContext Tenant context manager
     * @param string $isolationStrategy Schema isolation strategy ('prefix' or 'row')
     * @since 1.0.0
     */
    public function __construct(
        \wpdb $db,
        TenantContext $tenantContext,
        string $isolationStrategy = 'prefix'
    ) {
        $this->db = $db;
        $this->tenantContext = $tenantContext;
        $this->isolationStrategy = $isolationStrategy;
        $this->versionsTable = $this->db->prefix . 'bitcore_schema_versions';
    }

    /**
     * Create tenant schema
     *
     * Initializes schema for a new tenant including all required tables.
     *
     * @param int $tenantId Tenant ID
     * @return void
     * @throws RuntimeException If schema creation fails
     * @since 1.0.0
     */
    public function createTenantSchema(int $tenantId): void {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $this->switchToTenant($tenantId);

        try {
            // Core tenant tables
            $tables = [
                'posts',
                'postmeta',
                'options',
                'users',
                'usermeta',
            ];

            foreach ($tables as $table) {
                $tableName = $this->getTableName($table, $tenantId);

                $charset = $this->db->charset_collate;

                switch ($table) {
                    case 'posts':
                        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
                            ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                            post_author bigint(20) unsigned NOT NULL DEFAULT 0,
                            post_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                            post_date_gmt datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                            post_content longtext NOT NULL,
                            post_title text NOT NULL,
                            post_excerpt text NOT NULL,
                            post_status varchar(20) NOT NULL DEFAULT 'publish',
                            comment_status varchar(20) NOT NULL DEFAULT 'open',
                            ping_status varchar(20) NOT NULL DEFAULT 'open',
                            post_password varchar(255) NOT NULL DEFAULT '',
                            post_name varchar(200) NOT NULL DEFAULT '',
                            to_ping text NOT NULL,
                            pinged text NOT NULL,
                            post_modified datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                            post_modified_gmt datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                            post_content_filtered longtext NOT NULL,
                            post_parent bigint(20) unsigned NOT NULL DEFAULT 0,
                            guid varchar(255) NOT NULL DEFAULT '',
                            menu_order int(11) NOT NULL DEFAULT 0,
                            post_type varchar(20) NOT NULL DEFAULT 'post',
                            post_mime_type varchar(100) NOT NULL DEFAULT '',
                            comment_count bigint(20) NOT NULL DEFAULT 0,
                            PRIMARY KEY (ID),
                            KEY post_name (post_name),
                            KEY type_status_date (post_type, post_status, post_date, ID),
                            KEY post_parent (post_parent),
                            KEY post_author (post_author)
                        ) {$charset};";
                        break;

                    case 'postmeta':
                        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
                            meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                            post_id bigint(20) unsigned NOT NULL DEFAULT 0,
                            meta_key varchar(255) DEFAULT NULL,
                            meta_value longtext,
                            PRIMARY KEY (meta_id),
                            KEY post_id (post_id),
                            KEY meta_key (meta_key)
                        ) {$charset};";
                        break;

                    case 'options':
                        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
                            option_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                            option_name varchar(191) NOT NULL DEFAULT '',
                            option_value longtext NOT NULL,
                            autoload varchar(20) NOT NULL DEFAULT 'yes',
                            PRIMARY KEY (option_id),
                            UNIQUE KEY option_name (option_name),
                            KEY autoload (autoload)
                        ) {$charset};";
                        break;

                    case 'users':
                        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
                            ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                            user_login varchar(60) NOT NULL DEFAULT '',
                            user_pass varchar(255) NOT NULL DEFAULT '',
                            user_nicename varchar(50) NOT NULL DEFAULT '',
                            user_email varchar(100) NOT NULL DEFAULT '',
                            user_url varchar(100) NOT NULL DEFAULT '',
                            user_registered datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                            user_activation_key varchar(255) NOT NULL DEFAULT '',
                            user_status int(11) NOT NULL DEFAULT 0,
                            display_name varchar(250) NOT NULL DEFAULT '',
                            PRIMARY KEY (ID),
                            KEY user_login_key (user_login),
                            KEY user_nicename (user_nicename),
                            KEY user_email (user_email)
                        ) {$charset};";
                        break;

                    case 'usermeta':
                        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
                            umeta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                            meta_key varchar(255) DEFAULT NULL,
                            meta_value longtext,
                            PRIMARY KEY (umeta_id),
                            KEY user_id (user_id),
                            KEY meta_key (meta_key)
                        ) {$charset};";
                        break;

                    default:
                        continue 2;
                }

                dbDelta($sql);
            }

            // Initialize schema version