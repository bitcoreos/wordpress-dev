<?php
/**
 * BITCORE_Artifact_CLI implementation
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

/**
 * Artifact WP-CLI Commands
 *
 * @package BITCORE_Core\Artifacts
 * @since 1.4.0
 */

class BITCORE_Artifact_CLI {

    private BITCORE_Artifact_Repository $repository;

    public function __construct() {
        $this->repository = new BITCORE_Artifact_Repository();
    }

    public function list(array $args, array $assoc_args): void {
        $type = $assoc_args['type'] ?? '';
        $format = $assoc_args['format'] ?? 'table';
        
        $artifacts = $type 
            ? $this->repository->find_by_type($type, 100, 0)
            : $this->repository->find_all(100, 0);
        
        if (empty($artifacts)) {
            WP_CLI::success('No artifacts found.');
            return;
        }
        
        $items = array_map(function($a) {
            return [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->type,
                'created' => $a->created_at,
            ];
        }, $artifacts);
        
        WP_CLI\Utils\format_items($format, $items, ['id', 'name', 'type', 'created']);
    }

    public function get(array $args, array $assoc_args): void {
        if (empty($args[0])) {
            WP_CLI::error('Please provide an artifact ID or name.');
        }
        
        $identifier = $args[0];
        $artifact = is_numeric($identifier)
            ? $this->repository->find((int) $identifier)
            : $this->repository->find_by_name($identifier);
        
        if (!$artifact) {
            WP_CLI::error('Artifact not found.');
        }
        
        $format = $assoc_args['format'] ?? 'yaml';
        WP_CLI\Utils\format_items($format, [$artifact], ['id', 'name', 'type', 'content', 'metadata', 'created_at', 'updated_at']);
    }

    public function create(array $args, array $assoc_args): void {
        $required = ['name', 'type'];
        foreach ($required as $field) {
            if (empty($assoc_args[$field])) {
                WP_CLI::error("Missing required field: {$field}");
            }
        }
        
        $data = [
            'name' => $assoc_args['name'],
            'type' => $assoc_args['type'],
            'content' => $assoc_args['content'] ?? '{}',
            'metadata' => $assoc_args['metadata'] ?? '{}',
            'author' => get_current_user_id() ?: 1,
        ];
        
        $id = $this->repository->create($data);
        WP_CLI::success("Created artifact with ID: {$id}");
    }

    public function update(array $args, array $assoc_args): void {
        if (empty($args[0])) {
            WP_CLI::error('Please provide an artifact ID.');
        }
        
        $id = (int) $args[0];
        $artifact = $this->repository->find($id);
        
        if (!$artifact) {
            WP_CLI::error('Artifact not found.');
        }
        
        $data = [];
        foreach (['name', 'type', 'content', 'metadata'] as $field) {
            if (isset($assoc_args[$field])) {
                $data[$field] = $assoc_args[$field];
            }
        }
        
        if (empty($data)) {
            WP_CLI::error('No fields to update.');
        }
        
        $this->repository->update($id, $data);
        WP_CLI::success("Updated artifact {$id}.");
    }

    public function delete(array $args, array $assoc_args): void {
        if (empty($args[0])) {
            WP_CLI::error('Please provide an artifact ID.');
        }
        
        $id = (int) $args[0];
        $artifact = $this->repository->find($id);
        
        if (!$artifact) {
            WP_CLI::error('Artifact not found.');
        }
        
        $this->repository->delete($id);
        WP_CLI::success("Deleted artifact {$id}.");
    }

    public function migrate(array $args, array $assoc_args): void {
        $this->repository->create_table();
        WP_CLI::success('Artifacts table migrated.');
    }
}
