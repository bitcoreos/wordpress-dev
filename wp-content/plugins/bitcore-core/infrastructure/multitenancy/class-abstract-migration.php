<?php
/**
 * Abstract Migration
 *
 * Base class for database migrations providing common utilities.
 *
 * @package BITCORE\Infrastructure\MultiTenancy
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\MultiTenancy;

use RuntimeException;

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
