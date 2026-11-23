<?php
/**
 * BITCORE Artifacts System - Main Controller
 *
 * @package BITCORE\Artifacts
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Artifacts;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

trait Artifacts_CLI_Trait {

    /**
     * Register WP-CLI commands.
     *
     * @since 1.0.0
     */
    private function register_cli_commands(): void
    {
        \WP_CLI::add_command('bitcore artifact list', [$this, 'cli_list']);
        \WP_CLI::add_command('bitcore artifact get', [$this, 'cli_get']);
        \WP_CLI::add_command('bitcore artifact create', [$this, 'cli_create']);
        \WP_CLI::add_command('bitcore artifact update', [$this, 'cli_update']);
        \WP_CLI::add_command('bitcore artifact delete', [$this, 'cli_delete']);
    }
    /**
     * CLI: List artifacts.
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Named arguments.
     * @since 1.0.0
     */
    public function cli_list(array $args, array $assoc_args): void
    {
        $result = $this->repository->list([
            'type' => $assoc_args['type'] ?? '',
            'search' => $assoc_args['search'] ?? '',
            'page' => (int) ($assoc_args['page'] ?? 1),
            'per_page' => (int) ($assoc_args['per_page'] ?? 20),
        ]);

        if (empty($result['items'])) {
            \WP_CLI::success('No artifacts found.');
            return;
        }

        $table = [];
        foreach ($result['items'] as $artifact) {
            $table[] = [
                'ID' => $artifact['id'],
                'Name' => $artifact['name'],
                'Type' => $artifact['type'],
                'Created' => $artifact['created_at'],
            ];
        }

        \WP_CLI\Utils\format_items('table', $table, ['ID', 'Name', 'Type', 'Created']);
        \WP_CLI::success(sprintf('Showing %d of %d artifacts (page %d)',
            count($result['items']),
            $result['total'],
            $result['page']
        ));
    }
    /**
     * CLI: Get artifact.
     *
     * @param array $args Positional arguments.
     * @since 1.0.0
     */
    public function cli_get(array $args): void
    {
        if (!isset($args[0])) {
            \WP_CLI::error('Artifact ID is required.');
            return;
        }

        $artifact = $this->repository->get((int) $args[0]);

        if (!$artifact) {
            \WP_CLI::error('Artifact not found.');
            return;
        }

        \WP_CLI::success(json_encode($artifact, JSON_PRETTY_PRINT));
    }
    /**
     * CLI: Create artifact.
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Named arguments.
     * @since 1.0.0
     */
    public function cli_create(array $args, array $assoc_args): void
    {
        $required = ['name', 'type', 'content'];
        foreach ($required as $field) {
            if (!isset($assoc_args[$field])) {
                \WP_CLI::error(sprintf('Missing required field: %s', $field));
                return;
            }
        }

        $result = $this->repository->create([
            'name' => $assoc_args['name'],
            'type' => $assoc_args['type'],
            'content' => $assoc_args['content'],
            'metadata' => isset($assoc_args['metadata']) ? json_decode($assoc_args['metadata'], true) : null,
        ]);

        if (is_wp_error($result)) {
            \WP_CLI::error($result->get_error_message());
            return;
        }

        \WP_CLI::success(sprintf('Artifact created with ID: %d', $result));
    }
    /**
     * CLI: Update artifact.
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Named arguments.
     * @since 1.0.0
     */
    public function cli_update(array $args, array $assoc_args): void
    {
        if (!isset($args[0])) {
            \WP_CLI::error('Artifact ID is required.');
            return;
        }

        $data = [];
        foreach (['name', 'type', 'content'] as $field) {
            if (isset($assoc_args[$field])) {
                $data[$field] = $assoc_args[$field];
            }
        }

        $result = $this->repository->update((int) $args[0], $data);

        if (is_wp_error($result)) {
            \WP_CLI::error($result->get_error_message());
            return;
        }

        \WP_CLI::success('Artifact updated successfully.');
    }
    /**
     * CLI: Delete artifact.
     *
     * @param array $args Positional arguments.
     * @since 1.0.0
     */
    public function cli_delete(array $args): void
    {
        if (!isset($args[0])) {
            \WP_CLI::error('Artifact ID is required.');
            return;
        }

        $result = $this->repository->delete((int) $args[0]);

        if (is_wp_error($result)) {
            \WP_CLI::error($result->get_error_message());
            return;
        }

        \WP_CLI::success('Artifact deleted successfully.');
    }
}
