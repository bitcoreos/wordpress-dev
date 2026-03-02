<?php
/**
 * Artifacts_Controller implementation
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
 * BITCORE Artifacts System - Main Controller
 *
 * @package BITCORE\Artifacts
 * @since 1.0.0
 */

namespace BITCORE\Artifacts;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Artifacts Module Controller
 *
 * Initializes the artifacts system with REST API and CLI support.
 *
 * @since 1.0.0
 */
class Artifacts_Controller
{
    /** @var string REST API namespace */
    private string $namespace = 'bitcore/v1';

    /** @var Artifact_Repository Repository instance */
    private Artifact_Repository $repository;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->repository = new Artifact_Repository();
        $this->init();
    }

    /**
     * Initialize the module.
     *
     * @since 1.0.0
     */
    public function init(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
        
        if (defined('WP_CLI') && WP_CLI) {
            $this->register_cli_commands();
        }
    }

    /**
     * Register REST API routes.
     *
     * @since 1.0.0
     */
    public function register_routes(): void
    {
        // List artifacts
        register_rest_route($this->namespace, '/artifacts', [
            'methods' => 'GET',
            'callback' => [$this, 'list_artifacts'],
            'permission_callback' => '__return_true',
            'args' => [
                'type' => [
                    'type' => 'string',
                    'enum' => Artifact_Schema::VALID_TYPES,
                    'required' => false,
                ],
                'search' => [
                    'type' => 'string',
                    'required' => false,
                ],
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 20,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
            ],
        ]);

        // Get single artifact
        register_rest_route($this->namespace, '/artifacts/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_artifact'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                ],
            ],
        ]);

        // Create artifact
        register_rest_route($this->namespace, '/artifacts', [
            'methods' => 'POST',
            'callback' => [$this, 'create_artifact'],
            'permission_callback' => [$this, 'can_edit'],
            'args' => [
                'name' => [
                    'type' => 'string',
                    'required' => true,
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => Artifact_Schema::VALID_TYPES,
                    'required' => true,
                ],
                'content' => [
                    'type' => 'string',
                    'required' => true,
                ],
                'schema' => [
                    'type' => 'object',
                    'required' => false,
                ],
                'metadata' => [
                    'type' => 'object',
                    'required' => false,
                ],
            ],
        ]);

        // Update artifact
        register_rest_route($this->namespace, '/artifacts/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_artifact'],
            'permission_callback' => [$this, 'can_edit'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                ],
                'name' => [
                    'type' => 'string',
                    'required' => false,
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => Artifact_Schema::VALID_TYPES,
                    'required' => false,
                ],
                'content' => [
                    'type' => 'string',
                    'required' => false,
                ],
                'schema' => [
                    'type' => 'object',
                    'required' => false,
                ],
                'metadata' => [
                    'type' => 'object',
                    'required' => false,
                ],
            ],
        ]);

        // Delete artifact
        register_rest_route($this->namespace, '/artifacts/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_artifact'],
            'permission_callback' => [$this, 'can_delete'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                ],
            ],
        ]);

        // Get schema definition
        register_rest_route($this->namespace, '/artifacts/schema', [
            'methods' => 'GET',
            'callback' => [$this, 'get_schema'],
            'permission_callback' => '__return_true',
        ]);

        // Get counts by type
        register_rest_route($this->namespace, '/artifacts/counts', [
            'methods' => 'GET',
            'callback' => [$this, 'get_counts'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * List artifacts.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     * @since 1.0.0
     */
    public function list_artifacts(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $args = [
            'type' => $request->get_param('type') ?? '',
            'search' => $request->get_param('search') ?? '',
            'page' => (int) ($request->get_param('page') ?? 1),
            'per_page' => (int) ($request->get_param('per_page') ?? 20),
            'orderby' => $request->get_param('orderby') ?? 'created_at',
            'order' => $request->get_param('order') ?? 'DESC',
        ];

        $result = $this->repository->list($args);

        return rest_ensure_response([
            'success' => true,
            'data' => $result['items'],
            'meta' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total_pages' => $result['total_pages'],
            ],
        ]);
    }

    /**
     * Get single artifact.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     * @since 1.0.0
     */
    public function get_artifact(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');
        $artifact = $this->repository->get($id);

        if (!$artifact) {
            return new WP_Error(
                'artifact_not_found',
                'Artifact not found.',
                ['status' => 404]
            );
        }

        return rest_ensure_response([
            'success' => true,
            'data' => $artifact,
        ]);
    }

    /**
     * Create artifact.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     * @since 1.0.0
     */
    public function create_artifact(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $data = [
            'name' => $request->get_param('name'),
            'type' => $request->get_param('type'),
            'content' => $request->get_param('content'),
            'schema' => $request->get_param('schema'),
            'metadata' => $request->get_param('metadata'),
        ];

        $result = $this->repository->create($data);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'success' => true,
            'data' => $this->repository->get($result),
            'message' => 'Artifact created successfully.',
        ]);
    }

    /**
     * Update artifact.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     * @since 1.0.0
     */
    public function update_artifact(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');
        $data = [];

        foreach (['name', 'type', 'content', 'schema', 'metadata'] as $field) {
            if ($request->has_param($field)) {
                $data[$field] = $request->get_param($field);
            }
        }

        $result = $this->repository->update($id, $data);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'success' => true,
            'data' => $this->repository->get($id),
            'message' => 'Artifact updated successfully.',
        ]);
    }

    /**
     * Delete artifact.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     * @since 1.0.0
     */
    public function delete_artifact(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');
        $result = $this->repository->delete($id);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'success' => true,
            'message' => 'Artifact deleted successfully.',
        ]);
    }

    /**
     * Get schema definition.
     *
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function get_schema(): WP_REST_Response
    {
        return rest_ensure_response([
            'success' => true,
            'data' => Artifact_Schema::get_full_schema(),
        ]);
    }

    /**
     * Get counts by type.
     *
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function get_counts(): WP_REST_Response
    {
        return rest_ensure_response([
            'success' => true,
            'data' => $this->repository->count_by_type(),
        ]);
    }

    /**
     * Check if user can edit artifacts.
     *
     * @return bool
     * @since 1.0.0
     */
    public function can_edit(): bool
    {
        return current_user_can('edit_posts');
    }

    /**
     * Check if user can delete artifacts.
     *
     * @return bool
     * @since 1.0.0
     */
    public function can_delete(): bool
    {
        return current_user_can('delete_posts');
    }

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

// Initialize when loaded
new Artifacts_Controller();
