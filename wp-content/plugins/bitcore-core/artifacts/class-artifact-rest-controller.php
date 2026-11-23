<?php
/**
 * Artifact REST Controller
 *
 * @package BITCORE_Core\Artifacts
 * @since 1.4.0
 * @version     1.0.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class BITCORE_Artifact_REST_Controller extends WP_REST_Controller {

    protected string $namespace = 'bitcore/v1';
    protected string $rest_base = 'artifacts';
    private BITCORE_Artifact_Repository $repository;
    private BITCORE_Artifact_Schema $schema;

    public function __construct() {
        $this->repository = new BITCORE_Artifact_Repository();
        $this->schema = new BITCORE_Artifact_Schema();
    }

    public function register_routes(): void {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'get_items_permissions_check'],
                'args' => $this->get_collection_params(),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'create_item_permissions_check'],
                'args' => $this->schema->get_endpoint_args('POST'),
            ],
            'schema' => [$this, 'get_public_item_schema'],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'get_item_permissions_check'],
                'args' => ['id' => ['required' => true, 'type' => 'integer']],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'update_item_permissions_check'],
                'args' => $this->schema->get_endpoint_args('PUT'),
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_item'],
                'permission_callback' => [$this, 'delete_item_permissions_check'],
                'args' => ['id' => ['required' => true, 'type' => 'integer']],
            ],
            'schema' => [$this, 'get_public_item_schema'],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/schema', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_schema_response'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function get_items(WP_REST_Request $request): WP_REST_Response {
        $type = $request->get_param('type');
        $page = (int) $request->get_param('page');
        $per_page = (int) $request->get_param('per_page');
        $offset = ($page - 1) * $per_page;

        if ($type) {
            $items = $this->repository->find_by_type($type, $per_page, $offset);
            $total = $this->repository->count($type);
        } else {
            $items = $this->repository->find_all($per_page, $offset);
            $total = $this->repository->count();
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => ceil($total / $per_page),
            ],
        ], 200);
    }

    public function create_item(WP_REST_Request $request): WP_REST_Response {
        $data = [
            'name' => $request->get_param('name'),
            'type' => $request->get_param('type') ?: 'generic',
            'content' => json_encode($request->get_param('content')),
            'schema_def' => json_encode($request->get_param('schema_def') ?: []),
            'metadata' => json_encode($request->get_param('metadata') ?: []),
            'author' => get_current_user_id(),
        ];

        $errors = $this->schema->validate($data);
        if (!empty($errors)) {
            return new WP_REST_Response(['success' => false, 'errors' => $errors], 400);
        }

        $id = $this->repository->create($data);
        $item = $this->repository->find($id);

        return new WP_REST_Response(['success' => true, 'data' => $item], 201);
    }

    public function get_item(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $item = $this->repository->find($id);

        if (!$item) {
            return new WP_REST_Response(['success' => false, 'message' => 'Artifact not found'], 404);
        }

        return new WP_REST_Response(['success' => true, 'data' => $item], 200);
    }

    public function update_item(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $item = $this->repository->find($id);

        if (!$item) {
            return new WP_REST_Response(['success' => false, 'message' => 'Artifact not found'], 404);
        }

        $data = [];
        if ($request->get_param('name')) {
            $data['name'] = $request->get_param('name');
        }
        if ($request->get_param('type')) {
            $data['type'] = $request->get_param('type');
        }
        if ($request->get_param('content')) {
            $data['content'] = json_encode($request->get_param('content'));
        }
        if ($request->get_param('metadata')) {
            $data['metadata'] = json_encode($request->get_param('metadata'));
        }

        $this->repository->update($id, $data);
        $item = $this->repository->find($id);

        return new WP_REST_Response(['success' => true, 'data' => $item], 200);
    }

    public function delete_item(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $item = $this->repository->find($id);

        if (!$item) {
            return new WP_REST_Response(['success' => false, 'message' => 'Artifact not found'], 404);
        }

        $this->repository->delete($id);
        return new WP_REST_Response(['success' => true, 'message' => 'Artifact deleted'], 200);
    }

    public function get_schema_response(): WP_REST_Response {
        return new WP_REST_Response($this->schema->get_schema(), 200);
    }

    public function get_public_item_schema(): array {
        return $this->schema->get_schema();
    }

    public function get_collection_params(): array {
        return [
            'page' => ['type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint'],
            'per_page' => ['type' => 'integer', 'default' => 20, 'sanitize_callback' => 'absint'],
            'type' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        ];
    }

    public function get_items_permissions_check(WP_REST_Request $request): bool {
        return is_user_logged_in();
    }

    public function get_item_permissions_check(WP_REST_Request $request): bool {
        return is_user_logged_in();
    }

    public function create_item_permissions_check(WP_REST_Request $request): bool {
        return is_user_logged_in();
    }

    public function update_item_permissions_check(WP_REST_Request $request): bool {
        return is_user_logged_in();
    }

    public function delete_item_permissions_check(WP_REST_Request $request): bool {
        return is_user_logged_in();
    }
}
