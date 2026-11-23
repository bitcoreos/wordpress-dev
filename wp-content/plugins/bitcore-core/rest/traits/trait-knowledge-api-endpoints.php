<?php
/**
 * Knowledge REST API Controller - trait
 *
 * @package BITCORE\Rest
 * @since       1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Rest;

if (!defined('ABSPATH')) exit;

trait Knowledge_Api_Endpoints {
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

    public function get_nodes(\WP_REST_Request $request): \WP_REST_Response {
        $limit = $this->normalize_limit($request->get_param('limit'));
        $nodes = [];

        $bithub = $this->get_bithub_connector();
        if ($bithub) {
            $topics = $bithub->get_latest_topics($limit);
            if (!is_wp_error($topics) && is_array($topics)) {
                foreach ($topics as $topic) {
                    $nodes[] = $this->normalize_knowledge_card($topic, 'bithub', 'topic');
                }
            }

            if (count($nodes) < $limit && method_exists($bithub, 'get_artifacts')) {
                $artifacts = $bithub->get_artifacts();
                if (!is_wp_error($artifacts) && is_array($artifacts)) {
                    foreach ($artifacts as $artifact) {
                        if (count($nodes) >= $limit) {
                            break;
                        }
                        $nodes[] = $this->normalize_knowledge_card($artifact, 'bithub', 'artifact');
                    }
                }
            }
        }

        return rest_ensure_response([
            'nodes' => $nodes,
            'count' => count($nodes),
        ]);
    }

    public function get_edges(\WP_REST_Request $request): \WP_REST_Response {
        $limit = $this->normalize_limit($request->get_param('limit'));
        $edges = [];

        $bitwiki = $this->get_bitwiki_connector();
        if ($bitwiki) {
            $items = method_exists($bitwiki, 'get_index') ? $bitwiki->get_index() : [];
            if (!is_wp_error($items) && is_array($items)) {
                foreach ($items as $item) {
                    if (count($edges) >= $limit) {
                        break;
                    }
                    $edges[] = $this->normalize_knowledge_card($item, 'bitwiki', 'skill');
                }
            }
        }

        return rest_ensure_response([
            'edges' => $edges,
            'count' => count($edges),
        ]);
    }


    public function search_context(\WP_REST_Request $request): \WP_REST_Response {
        $query = trim(sanitize_text_field($request->get_param('query') ?? ''));
        $limit = $this->normalize_limit($request->get_param('limit'));
        $items = [];

        if ($query !== '') {
            $bithub = $this->get_bithub_connector();
            if ($bithub) {
                $bithub_results = method_exists($bithub, 'search')
                    ? $bithub->search($query, $limit)
                    : $bithub->get_latest_topics($limit);
                if (!is_wp_error($bithub_results) && is_array($bithub_results)) {
                    foreach ($bithub_results as $result) {
                        if (count($items) >= $limit) {
                            break;
                        }
                        $items[] = $this->normalize_knowledge_card($result, 'bithub', $result['type'] ?? 'topic');
                    }
                }
            }

            $bitwiki = $this->get_bitwiki_connector();
            if ($bitwiki && method_exists($bitwiki, 'search')) {
                $remaining = max($limit - count($items), 0);
                $bitwiki_results = $remaining > 0 ? $bitwiki->search($query, $remaining) : [];
                if (!is_wp_error($bitwiki_results) && is_array($bitwiki_results)) {
                    foreach ($bitwiki_results as $result) {
                        if (count($items) >= $limit) {
                            break;
                        }
                        $items[] = $this->normalize_knowledge_card($result, 'bitwiki', 'skill');
                    }
                }
            }
        }

        return rest_ensure_response([
            'query' => $query,
            'items' => $items,
            'count' => count($items),
        ]);
    }

    public function create_context(\WP_REST_Request $request): \WP_REST_Response {
        $data = [
            'user_id'      => get_current_user_id(),
            'session_id'   => $request->get_param('session_id'),
            'title'        => sanitize_text_field($request->get_param('title')),
            'content'      => wp_kses_post($request->get_param('content')),
            'content_type' => 'context',
            'tags'         => $this->sanitize_tags($request->get_param('tags')),
            'status'       => 'private',
        ];

        $table  = $this->wpdb->prefix . 'bitcore_knowledge';
        $result = $this->wpdb->insert($table, $data, ['%d', '%s', '%s', '%s', '%s', '%s', '%s']);

        if ($result === false) {
            return new \WP_REST_Response(['error' => 'Failed to create context'], 500);
        }

        return new \WP_REST_Response([
            'id'      => $this->wpdb->insert_id,
            'success' => true,
            'context' => [
                'id'         => $this->wpdb->insert_id,
                'title'      => $data['title'],
                'status'     => 'private',
                'type'       => 'context',
                'session_id' => $data['session_id'],
            ],
        ], 201);
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

        return new \WP_REST_Response([
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
}
