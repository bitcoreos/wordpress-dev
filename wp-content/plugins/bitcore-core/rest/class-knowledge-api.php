<?php
/**
 * Knowledge REST API Controller
 *
 * REST endpoints for knowledge pipeline with 6-state permission model.
 *
 * @package BITCORE\Rest
 */

namespace BITCORE\Rest;

if (!defined('ABSPATH')) exit;

/**
 * Class Knowledge_Api
 */
class Knowledge_Api {
    private string $namespace = 'bitcore/v1';
    private $wpdb;

    /** @var array Valid status values */
    private array $valid_statuses = ['private', 'draft', 'review', 'shared', 'published', 'archived'];

    /** @var array Status transition rules */
    private array $transitions = [
        'private'   => ['draft', 'review'],
        'draft'     => ['review', 'private'],
        'review'    => ['shared', 'private', 'draft'],
        'shared'    => ['published', 'private', 'draft'],
        'published' => ['archived'],
        'archived'  => [],
    ];

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->register_routes();
    }

    public function register_routes(): void {
        register_rest_route($this->namespace, '/knowledge', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_items'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args'                => [
                    'status'       => ['type' => 'string', 'enum' => $this->valid_statuses],
                    'content_type' => ['type' => 'string'],
                    'session_id'   => ['type' => 'string'],
                    'per_page'     => ['type' => 'integer', 'default' => 20],
                    'page'         => ['type' => 'integer', 'default' => 1],
                ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'create_item'],
                'permission_callback' => [$this, 'check_create_permission'],
                'args'                => [
                    'title'        => ['type' => 'string', 'required' => true],
                    'content'      => ['type' => 'string', 'required' => true],
                    'content_type' => ['type' => 'string', 'default' => 'markdown'],
                    'tags'         => ['type' => 'array'],
                    'session_id'   => ['type' => 'string'],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/knowledge/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_item'],
                'permission_callback' => [$this, 'check_item_permission'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'update_item'],
                'permission_callback' => [$this, 'check_item_permission'],
                'args'                => [
                    'title'   => ['type' => 'string'],
                    'content' => ['type' => 'string'],
                    'tags'    => ['type' => 'array'],
                    'status'  => ['type' => 'string', 'enum' => $this->valid_statuses],
                ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'delete_item'],
                'permission_callback' => [$this, 'check_item_permission'],
            ],
        ]);

        register_rest_route($this->namespace, '/knowledge/(?P<id>\d+)/publish', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'publish_item'],
                'permission_callback' => [$this, 'check_publish_permission'],
            ],
        ]);

        register_rest_route($this->namespace, '/knowledge/(?P<id>\d+)/transition', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'transition_item'],
                'permission_callback' => [$this, 'check_item_permission'],
                'args'                => [
                    'status' => ['type' => 'string', 'required' => true, 'enum' => $this->valid_statuses],
                ],
            ],
        ]);
    }

    public function get_items(\WP_REST_Request $request): \WP_REST_Response {
        $per_page = max(min((int) ($request->get_param('per_page') ?? 20), 100), 1);
        $page     = max((int) ($request->get_param('page') ?? 1), 1);
        $offset   = ($page - 1) * $per_page;

        $where  = [];
        $params = [];
        $is_admin = current_user_can('manage_options');

        if (!$is_admin) {
            $where[] = 'user_id = %d';
            $params[] = get_current_user_id();
        }

        $status = $request->get_param('status');
        if ($status) {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        $content_type = $request->get_param('content_type');
        if ($content_type) {
            $where[] = 'content_type = %s';
            $params[] = $content_type;
        }

        $session_id = $request->get_param('session_id');
        if ($session_id) {
            $where[] = 'session_id = %s';
            $params[] = $session_id;
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $table     = $this->wpdb->prefix . 'bitcore_knowledge';

        if (!empty($params)) {
            $total = (int) $this->wpdb->get_var(
                $this->wpdb->prepare("SELECT COUNT(*) FROM {$table} {$where_sql}", ...$params)
            );
            $items = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    ...array_merge($params, [$per_page, $offset])
                ),
                ARRAY_A
            );
        } else {
            $total = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where_sql}");
            $items = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    $per_page, $offset
                ),
                ARRAY_A
            );
        }

        return rest_ensure_response([
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($total / $per_page),
        ]);
    }

    public function create_item(\WP_REST_Request $request): \WP_REST_Response {
        $data = [
            'user_id'      => get_current_user_id(),
            'session_id'   => $request->get_param('session_id'),
            'title'        => sanitize_text_field($request->get_param('title')),
            'content'      => wp_kses_post($request->get_param('content')),
            'content_type' => sanitize_text_field($request->get_param('content_type') ?? 'markdown'),
            'tags'         => $this->sanitize_tags($request->get_param('tags')),
            'status'       => 'private',
        ];

        $table  = $this->wpdb->prefix . 'bitcore_knowledge';
        $result = $this->wpdb->insert($table, $data, ['%d', '%s', '%s', '%s', '%s', '%s', '%s']);

        if ($result === false) {
            return new \WP_REST_Response(['error' => 'Failed to create knowledge item'], 500);
        }

        return rest_ensure_response([
            'id'      => $this->wpdb->insert_id,
            'status'  => 'private',
            'success' => true,
        ], 201);
    }

    public function get_item(\WP_REST_Request $request): \WP_REST_Response {
        $item = $this->get_item_by_id((int) $request->get_param('id'));
        if (!$item) {
            return new \WP_REST_Response(['error' => 'Not found'], 404);
        }
        if (!$this->can_read_item($item)) {
            return new \WP_REST_Response(['error' => 'Forbidden'], 403);
        }
        return rest_ensure_response($item);
    }

    public function update_item(\WP_REST_Request $request): \WP_REST_Response {
        $id   = (int) $request->get_param('id');
        $item = $this->get_item_by_id($id);
        if (!$item) {
            return new \WP_REST_Response(['error' => 'Not found'], 404);
        }
        if (!$this->can_modify_item($item)) {
            return new \WP_REST_Response(['error' => 'Forbidden'], 403);
        }

        $update = [];
        $format = [];

        $fields = ['title' => '%s', 'content' => '%s', 'tags' => '%s'];
        foreach ($fields as $field => $f) {
            if ($request->get_param($field) !== null) {
                $update[$field] = $field === 'title'
                    ? sanitize_text_field($request->get_param($field))
                    : ($field === 'tags'
                        ? $this->sanitize_tags($request->get_param($field))
                        : wp_kses_post($request->get_param($field)));
                $format[] = $f;
            }
        }

        $new_status = $request->get_param('status');
        if ($new_status !== null) {
            $validation = $this->validate_transition($item['status'], $new_status, $item);
            if (is_wp_error($validation)) {
                return new \WP_REST_Response(['error' => $validation->get_error_message()], 400);
            }
            $update['status'] = $new_status;
            $format[] = '%s';
        }

        if (empty($update)) {
            return rest_ensure_response(['success' => true, 'message' => 'No changes']);
        }

        $table  = $this->wpdb->prefix . 'bitcore_knowledge';
        $result = $this->wpdb->update($table, $update, ['id' => $id], $format, ['%d']);

        return rest_ensure_response(['success' => $result !== false]);
    }

    public function delete_item(\WP_REST_Request $request): \WP_REST_Response {
        $id   = (int) $request->get_param('id');
        $item = $this->get_item_by_id($id);
        if (!$item) {
            return new \WP_REST_Response(['error' => 'Not found'], 404);
        }
        if (!$this->can_modify_item($item)) {
            return new \WP_REST_Response(['error' => 'Forbidden'], 403);
        }

        $table  = $this->wpdb->prefix . 'bitcore_knowledge';
        $result = $this->wpdb->delete($table, ['id' => $id], ['%d']);

        return rest_ensure_response(['success' => $result !== false]);
    }

    public function publish_item(\WP_REST_Request $request): \WP_REST_Response {
        $id   = (int) $request->get_param('id');
        $item = $this->get_item_by_id($id);
        if (!$item) {
            return new \WP_REST_Response(['error' => 'Not found'], 404);
        }

        $is_admin = current_user_can('manage_options');
        if (!$is_admin && !in_array($item['status'], ['review', 'shared'], true)) {
            return new \WP_REST_Response(['error' => 'Item must be in review or shared status to publish'], 403);
        }

        $bitwiki = new \BITCORE_Services_Connector_BITwiki(null);
        $result  = $bitwiki->create_page($item['title'], $item['content'], 'Published from BITCORE Knowledge Pipeline');

        if (isset($result['error'])) {
            return new \WP_REST_Response(['error' => $result['error']], 500);
        }

        $table = $this->wpdb->prefix . 'bitcore_knowledge';
        $this->wpdb->update($table, [
            'status'             => 'published',
            'bitwiki_page_title' => $result['title'],
            'bitwiki_page_id'    => $result['page_id'],
            'published_at'       => current_time('mysql'),
        ], ['id' => $id], ['%s', '%s', '%d', '%s'], ['%d']);

        return rest_ensure_response([
            'success'          => true,
            'bitwiki_page_id'  => $result['page_id'],
            'bitwiki_page_title' => $result['title'],
        ]);
    }

    public function transition_item(\WP_REST_Request $request): \WP_REST_Response {
        $id         = (int) $request->get_param('id');
        $new_status = $request->get_param('status');
        $item       = $this->get_item_by_id($id);
        if (!$item) {
            return new \WP_REST_Response(['error' => 'Not found'], 404);
        }

        $validation = $this->validate_transition($item['status'], $new_status, $item);
        if (is_wp_error($validation)) {
            return new \WP_REST_Response(['error' => $validation->get_error_message()], 400);
        }

        $table = $this->wpdb->prefix . 'bitcore_knowledge';
        $this->wpdb->update($table, ['status' => $new_status], ['id' => $id], ['%s'], ['%d']);

        return rest_ensure_response(['success' => true, 'status' => $new_status]);
    }

    /* ---------- helpers ---------- */

    private function get_item_by_id(int $id): ?array {
        $table = $this->wpdb->prefix . 'bitcore_knowledge';
        $row   = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    private function can_read_item(array $item): bool {
        $user_id  = get_current_user_id();
        $is_admin = current_user_can('manage_options');

        if ($is_admin || (int) $item['user_id'] === $user_id) {
            return true;
        }
        if (in_array($item['status'], ['shared', 'published'], true)) {
            return is_user_logged_in();
        }
        return false;
    }

    private function can_modify_item(array $item): bool {
        $is_admin = current_user_can('manage_options');
        if ($is_admin) {
            return true;
        }
        if ((int) $item['user_id'] !== get_current_user_id()) {
            return false;
        }
        return in_array($item['status'], ['private', 'draft', 'review'], true);
    }

    private function validate_transition(string $from, string $to, array $item): \WP_Error|true {
        $is_admin = current_user_can('manage_options');

        if (!isset($this->transitions[$from]) || !in_array($to, $this->transitions[$from], true)) {
            return new \WP_Error('invalid_transition', "Cannot transition from {$from} to {$to}");
        }

        if ($is_admin) {
            return true;
        }

        if ((int) $item['user_id'] !== get_current_user_id()) {
            return new \WP_Error('not_owner', 'You do not own this item');
        }

        if ($from === 'review' && in_array($to, ['shared', 'published'], true)) {
            return new \WP_Error('admin_required', 'Only admin can transition to shared or published');
        }
        if ($from === 'shared' && $to === 'published') {
            return new \WP_Error('admin_required', 'Only admin can publish');
        }

        return true;
    }

    private function sanitize_tags($tags): string {
        if (is_array($tags)) {
            $tags = array_map('sanitize_text_field', $tags);
            return implode(',', $tags);
        }
        return sanitize_text_field($tags ?? '');
    }

    public function check_read_permission(): bool {
        return is_user_logged_in() || current_user_can('manage_options');
    }

    public function check_create_permission(): bool {
        return is_user_logged_in() || current_user_can('manage_options');
    }

    public function check_item_permission(\WP_REST_Request $request): bool {
        $id   = (int) $request->get_param('id');
        $item = $this->get_item_by_id($id);
        if (!$item) {
            return true;
        }
        return $this->can_read_item($item);
    }

    public function check_publish_permission(\WP_REST_Request $request): bool {
        $id   = (int) $request->get_param('id');
        $item = $this->get_item_by_id($id);
        if (!$item) {
            return true;
        }
        if (current_user_can('manage_options')) {
            return true;
        }
        if ((int) $item['user_id'] !== get_current_user_id()) {
            return false;
        }
        return in_array($item['status'], ['review', 'shared'], true);
    }
}
