<?php
/**
 * Artifact Repository - CRUD operations
 *
 * @package BITCORE_Core\Artifacts
 * @since 1.4.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class BITCORE_Artifact_Repository {

    private string $table_name;
    private \wpdb $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'bitcore_artifacts';
    }

    public function create_table(): void {
        $charset_collate = $this->wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(100) NOT NULL DEFAULT 'generic',
            content longtext NOT NULL,
            schema_def longtext DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            tenant_id bigint(20) unsigned DEFAULT NULL,
            author bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_type (type),
            KEY idx_tenant (tenant_id),
            KEY idx_author (author)
        ) {$charset_collate};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function find(int $id): ?object {
        $sql = $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id);
        $result = $this->wpdb->get_row($sql);
        return $result ? $this->hydrate($result) : null;
    }

    public function find_by_name(string $name): ?object {
        $sql = $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE name = %s", $name);
        $result = $this->wpdb->get_row($sql);
        return $result ? $this->hydrate($result) : null;
    }

    public function find_by_type(string $type, int $limit = 50, int $offset = 0): array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE type = %s LIMIT %d OFFSET %d",
            $type, $limit, $offset
        );
        $results = $this->wpdb->get_results($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    public function find_all(int $limit = 50, int $offset = 0): array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit, $offset
        );
        $results = $this->wpdb->get_results($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    public function create(array $data): int {
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');
        $this->wpdb->insert($this->table_name, $data);
        return (int) $this->wpdb->insert_id;
    }

    public function update(int $id, array $data): bool {
        $data['updated_at'] = current_time('mysql');
        $result = $this->wpdb->update($this->table_name, $data, ['id' => $id]);
        return $result !== false;
    }

    public function delete(int $id): bool {
        $result = $this->wpdb->delete($this->table_name, ['id' => $id]);
        return $result !== false;
    }

    public function count(string $type = ''): int {
        if ($type) {
            $sql = $this->wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE type = %s", $type);
        } else {
            $sql = "SELECT COUNT(*) FROM {$this->table_name}";
        }
        return (int) $this->wpdb->get_var($sql);
    }

    private function hydrate(object $row): object {
        $row->content = json_decode($row->content, true) ?? $row->content;
        $row->schema_def = json_decode($row->schema_def ?? '{}', true);
        $row->metadata = json_decode($row->metadata ?? '{}', true);
        return $row;
    }
}
