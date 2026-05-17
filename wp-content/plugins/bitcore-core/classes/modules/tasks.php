<?php
/**
 * BITCORE Tasks Module
 * Custom post type for agent task management
 *
 * @package BITCORE_Core
 * @since 1.2.0
 */

declare(strict_types=1);

/**
 * Class BITCORE_Modules_Tasks
 * Handles agent_task CPT, meta fields, and task lifecycle
 */
class BITCORE_Modules_Tasks {

    /**
     * Post type name
     */
    const POST_TYPE = 'agent_task';

    /**
     * Status taxonomy
     */
    const TAX_STATUS = 'task_status';

    /**
     * Channel taxonomy
     */
    const TAX_CHANNEL = 'bitcore_channel';

    /**
     * Task type taxonomy
     */
    const TAX_TYPE = 'task_type';

    /**
     * Core instance
     */
    private $core;

    /**
     * Constructor
     */
    public function __construct($core) {
        $this->core = $core;
        
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomy']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta'], 10, 2);
        add_action('transition_post_status', [$this, 'on_status_change'], 10, 3);
        
        // Register default statuses on init
        add_action('init', [$this, 'register_default_statuses'], 20);
    }

    /**
     * Register agent_task CPT
     */
    public function register_post_type(): void {
        $labels = [
            'name'                  => _x('Agent Tasks', 'Post type general name', 'bitcore'),
            'singular_name'         => _x('Agent Task', 'Post type singular name', 'bitcore'),
            'menu_name'             => _x('Agent Tasks', 'Admin Menu text', 'bitcore'),
            'add_new'               => __('Add New', 'bitcore'),
            'add_new_item'          => __('Add New Agent Task', 'bitcore'),
            'edit_item'             => __('Edit Agent Task', 'bitcore'),
            'new_item'              => __('New Agent Task', 'bitcore'),
            'view_item'             => __('View Agent Task', 'bitcore'),
            'search_items'          => __('Search Agent Tasks', 'bitcore'),
            'not_found'             => __('No agent tasks found', 'bitcore'),
            'not_found_in_trash'    => __('No agent tasks found in Trash', 'bitcore'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'bitcore',
            'query_var'          => true,
            'rewrite'            => ['slug' => 'agent-task'],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-marker',
            'supports'           => ['title', 'editor', 'author', 'revisions'],
            'show_in_rest'       => true,
            'rest_base'          => 'agent-tasks',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register task_status taxonomy
     */
    public function register_taxonomy(): void {
        $labels = [
            'name'          => _x('Task Statuses', 'taxonomy general name', 'bitcore'),
            'singular_name' => _x('Task Status', 'taxonomy singular name', 'bitcore'),
        ];

        $args = [
            'labels'       => $labels,
            'public'       => false,
            'hierarchical' => false,
            'show_ui'      => true,
            'show_in_rest' => true,
        ];

        register_taxonomy(self::TAX_STATUS, self::POST_TYPE, $args);

        // Channel taxonomy
        $channel_labels = [
            'name'          => _x('Channels', 'taxonomy general name', 'bitcore'),
            'singular_name' => _x('Channel', 'taxonomy singular name', 'bitcore'),
        ];
        register_taxonomy(self::TAX_CHANNEL, [self::POST_TYPE, 'post'], [
            'labels'       => $channel_labels,
            'public'       => false,
            'hierarchical' => true,
            'show_ui'      => true,
            'show_in_rest' => true,
        ]);

        // Task type taxonomy
        $type_labels = [
            'name'          => _x('Task Types', 'taxonomy general name', 'bitcore'),
            'singular_name' => _x('Task Type', 'taxonomy singular name', 'bitcore'),
        ];
        register_taxonomy(self::TAX_TYPE, self::POST_TYPE, [
            'labels'       => $type_labels,
            'public'       => false,
            'hierarchical' => true,
            'show_ui'      => true,
            'show_in_rest' => true,
        ]);
    }

    /**
     * Register default statuses
     */
    public function register_default_statuses(): void {
        $statuses = [
            // Legacy statuses
            'pending'   => 'Pending',
            'active'    => 'Active',
            'completed' => 'Completed',
            'failed'    => 'Failed',
            // Kanban statuses
            'inbox'     => 'Inbox',
            'backlog'   => 'Backlog',
            'doing'     => 'Doing',
            'review'    => 'Review',
            'done'      => 'Done',
            'archived'  => 'Archived',
        ];

        foreach ($statuses as $slug => $name) {
            if (!term_exists($slug, self::TAX_STATUS)) {
                wp_insert_term($name, self::TAX_STATUS, ['slug' => $slug]);
            }
        }
    }

    /**
     * Add meta boxes
     */
    public function add_meta_boxes(): void {
        add_meta_box(
            'bitcore_task_meta',
            __('Task Details', 'bitcore'),
            [$this, 'render_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Render meta box
     */
    public function render_meta_box($post): void {
        wp_nonce_field('bitcore_task_meta', 'bitcore_task_meta_nonce');
        
        $assigned_to = get_post_meta($post->ID, '_bitcore_assigned_to', true);
        $priority = get_post_meta($post->ID, '_bitcore_priority', true) ?: 'normal';
        $deadline = get_post_meta($post->ID, '_bitcore_deadline', true);
        $params = get_post_meta($post->ID, '_bitcore_params', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="bitcore_assigned_to"><?php _e('Assigned To', 'bitcore'); ?></label></th>
                <td>
                    <input type="text" id="bitcore_assigned_to" name="bitcore_assigned_to"
                           value="<?php echo esc_attr($assigned_to); ?>" class="regular-text"
                           placeholder="agent-name or user-login">
                    <p class="description">Agent or user to handle this task</p>
                </td>
            </tr>
            <tr>
                <th><label for="bitcore_priority"><?php _e('Priority', 'bitcore'); ?></label></th>
                <td>
                    <select id="bitcore_priority" name="bitcore_priority">
                        <option value="low" <?php selected($priority, 'low'); ?>>Low</option>
                        <option value="normal" <?php selected($priority, 'normal'); ?>>Normal</option>
                        <option value="high" <?php selected($priority, 'high'); ?>>High</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="bitcore_deadline"><?php _e('Deadline', 'bitcore'); ?></label></th>
                <td>
                    <input type="datetime-local" id="bitcore_deadline" name="bitcore_deadline"
                           value="<?php echo esc_attr($deadline); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="bitcore_params"><?php _e('Parameters (JSON)', 'bitcore'); ?></label></th>
                <td>
                    <textarea id="bitcore_params" name="bitcore_params" rows="5" class="large-text code"><?php echo esc_textarea($params); ?></textarea>
                    <p class="description">JSON parameters for the task</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save meta data
     */
    public function save_meta($post_id, $post): void {
        if (!isset($_POST['bitcore_task_meta_nonce']) ||
            !wp_verify_nonce($_POST['bitcore_task_meta_nonce'], 'bitcore_task_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['bitcore_assigned_to'])) {
            update_post_meta($post_id, '_bitcore_assigned_to', sanitize_text_field($_POST['bitcore_assigned_to']));
        }
        if (isset($_POST['bitcore_priority'])) {
            update_post_meta($post_id, '_bitcore_priority', sanitize_text_field($_POST['bitcore_priority']));
        }
        if (isset($_POST['bitcore_deadline'])) {
            update_post_meta($post_id, '_bitcore_deadline', sanitize_text_field($_POST['bitcore_deadline']));
        }
        if (isset($_POST['bitcore_params'])) {
            $params = wp_unslash($_POST['bitcore_params']);
            if ($this->is_valid_json($params)) {
                update_post_meta($post_id, '_bitcore_params', $params);
            }
        }
    }

    /**
     * On status change - trigger webhook
     */
    public function on_status_change($new_status, $old_status, $post): void {
        if ($post->post_type !== self::POST_TYPE) {
            return;
        }

        if ($new_status === 'publish' && $old_status !== 'publish') {
            $this->trigger_webhook($post, 'created');
        }
    }

    /**
     * Trigger webhook to n8n
     */
    private function trigger_webhook($post, $event): void {
        $webhook_url = get_option('bitcore_n8n_webhook_url');
        if (empty($webhook_url)) {
            return;
        }

        $payload = [
            'event'     => $event,
            'task_id'   => $post->ID,
            'title'     => $post->post_title,
            'content'   => $post->post_content,
            'status'    => $post->post_status,
            'meta'      => [
                'assigned_to' => get_post_meta($post->ID, '_bitcore_assigned_to', true),
                'priority'    => get_post_meta($post->ID, '_bitcore_priority', true),
                'deadline'    => get_post_meta($post->ID, '_bitcore_deadline', true),
                'params'      => json_decode(get_post_meta($post->ID, '_bitcore_params', true), true),
            ],
            'timestamp' => current_time('mysql'),
        ];

        wp_remote_post($webhook_url, [
            'body'    => wp_json_encode($payload),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Bitcore-Event' => $event,
            ],
            'timeout' => 5,
        ]);
    }

    /**
     * Validate JSON
     */
    private function is_valid_json($string): bool {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
