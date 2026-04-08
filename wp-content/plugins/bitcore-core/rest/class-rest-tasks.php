<?php
declare(strict_types=1);
/**
 * REST Tasks Controller
 *
 * @package BITCORE\REST
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/trait-rest-tasks-permissions.php';
require_once __DIR__ . '/trait-rest-tasks-schema.php';

class REST_Tasks {
    use REST_Tasks_Permissions;
    use REST_Tasks_Schema;
    
    const NAMESPACE = 'bitcore/v1';
    const REST_BASE = 'tasks';
    
    private string $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'bitcore_tasks';
    }
    
    public function register_routes(): void {
        register_rest_route(self::NAMESPACE, '/' . self::REST_BASE, [
            'methods' => 'GET',
            'callback' => [$this, 'get_items'],
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route(self::NAMESPACE, '/' . self::REST_BASE . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_item'],
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route(self::NAMESPACE, '/' . self::REST_BASE, [
            'methods' => 'POST',
            'callback' => [$this, 'create_item'],
            'permission_callback' => [$this, 'create_item_permissions_check'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/' . self::REST_BASE . '/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_item'],
            'permission_callback' => [$this, 'update_item_permissions_check'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/' . self::REST_BASE . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_item'],
            'permission_callback' => [$this, 'delete_item_permissions_check'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/' . self::REST_BASE . '/(?P<id>\d+)/dispatch', [
            'methods' => 'POST',
            'callback' => [$this, 'dispatch_task'],
            'permission_callback' => [$this, 'dispatch_item_permissions_check'],
        ]);
    }
    
    public function get_items($request) {
        global $wpdb;
        
        $params = $request->get_params();
        $per_page = $params['per_page'] ?? 10;
        $page = $params['page'] ?? 1;
        $offset = ($page - 1) * $per_page;
        
        $where = '1=1';
        $values = [];
        
        if (!empty($params['status'])) {
            $where .= ' AND status = %s';
            $values[] = sanitize_text_field($params['status']);
        }
        
        if (!empty($params['assigned_to'])) {
            $where .= ' AND assigned_to = %d';
            $values[] = (int) $params['assigned_to'];
        }
        
        $query = "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $values[] = $per_page;
        $values[] = $offset;
        
        $results = $wpdb->get_results($wpdb->prepare($query, ...$values), ARRAY_A);
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}");
        
        return rest_ensure_response([
            'success' => true,
            'data' => array_map([$this, 'prepare_item_for_response'], $results),
            'total' => (int) $total,
            'pages' => ceil($total / $per_page),
        ]);
    }
    
    public function get_item($request) {
        global $wpdb;
        
        $id = (int) $request['id'];
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id), ARRAY_A);
        
        if (!$task) {
            return new WP_Error('not_found', 'Task not found', ['status' => 404]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'data' => $this->prepare_item_for_response($task),
        ]);
    }
    
    public function create_item($request) {
        global $wpdb;
        
        $data = $request->get_json_params();
        
        $insert_data = [
            'title' => sanitize_text_field($data['title']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'status' => 'pending',
            'priority' => sanitize_text_field($data['priority'] ?? 'normal'),
            'assigned_to' => isset($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ];
        
        $result = $wpdb->insert($this->table_name, $insert_data);
        
        if (!$result) {
            return new WP_Error('create_failed', 'Failed to create task', ['status' => 500]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'data' => ['id' => $wpdb->insert_id],
        ]);
    }
    
    public function update_item($request) {
        global $wpdb;
        
        $id = (int) $request['id'];
        $data = $request->get_json_params();
        
        $update_data = [];
        
        if (isset($data['title'])) {
            $update_data['title'] = sanitize_text_field($data['title']);
        }
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }
        if (isset($data['priority'])) {
            $update_data['priority'] = sanitize_text_field($data['priority']);
        }
        if (isset($data['assigned_to'])) {
            $update_data['assigned_to'] = (int) $data['assigned_to'];
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update($this->table_name, $update_data, ['id' => $id]);
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to update task', ['status' => 500]);
        }
        
        return rest_ensure_response(['success' => true]);
    }
    
    public function delete_item($request) {
        global $wpdb;
        
        $id = (int) $request['id'];
        
        $result = $wpdb->delete($this->table_name, ['id' => $id]);
        
        if (!$result) {
            return new WP_Error('delete_failed', 'Failed to delete task', ['status' => 500]);
        }
        
        return rest_ensure_response(['success' => true]);
    }
    
    public function dispatch_task($request) {
        global $wpdb;
        
        $id = (int) $request['id'];
        
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id), ARRAY_A);
        
        if (!$task) {
            return new WP_Error('not_found', 'Task not found', ['status' => 404]);
        }
        
        $wpdb->update(
            $this->table_name,
            ['status' => 'processing', 'dispatched_at' => current_time('mysql')],
            ['id' => $id]
        );
        
        do_action('bitcore_task_dispatch', $task);
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Task dispatched',
        ]);
    }
}
