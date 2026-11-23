<?php

declare(strict_types=1);

/**
 * BITCORE Artifacts System - Main Controller
 *
 * @package BITCORE\Artifacts
 * @since 1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Artifacts;

require_once __DIR__ . '/trait-artifacts-cli-trait.php';

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

    use Artifacts_CLI_Trait;
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
        return is_user_logged_in();
    }

    /**
     * Check if user can delete artifacts.
     *
     * @return bool
     * @since 1.0.0
     */
    public function can_delete(): bool
    {
        return is_user_logged_in();
    }






}

// Initialize when loaded
new Artifacts_Controller();
