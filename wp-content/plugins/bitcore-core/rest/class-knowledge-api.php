<?php
/**
 * Knowledge REST API Controller
 *
 * REST endpoints for knowledge pipeline with 6-state permission model.
 *
 * @package BITCORE\Rest
 * @since       1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Rest;

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/traits/trait-knowledge-api-endpoints.php';
require_once __DIR__ . '/traits/trait-knowledge-api-helpers.php';
/**
 * Class Knowledge_Api
 */
class Knowledge_Api {
    use Knowledge_Api_Endpoints;
    use Knowledge_Api_Helpers;
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

        register_rest_route($this->namespace, '/knowledge/nodes', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_nodes'],
            'permission_callback' => [$this, 'check_read_permission'],
            'args'                => [
                'limit' => ['type' => 'integer', 'default' => 20],
            ],
        ]);

        register_rest_route($this->namespace, '/knowledge/edges', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_edges'],
            'permission_callback' => [$this, 'check_read_permission'],
            'args'                => [
                'limit' => ['type' => 'integer', 'default' => 20],
            ],
        ]);

        register_rest_route($this->namespace, '/context/search', [
            'methods'             => 'POST',
            'callback'            => [$this, 'search_context'],
            'permission_callback' => [$this, 'check_read_permission'],
            'args'                => [
                'query' => ['type' => 'string', 'required' => true],
                'limit' => ['type' => 'integer', 'default' => 20],
            ],
        ]);

        register_rest_route($this->namespace, '/context', [
            'methods'             => 'POST',
            'callback'            => [$this, 'create_context'],
            'permission_callback' => [$this, 'check_create_permission'],
            'args'                => [
                'title'      => ['type' => 'string', 'required' => true],
                'content'    => ['type' => 'string', 'required' => true],
                'tags'       => ['type' => 'array'],
                'session_id' => ['type' => 'string'],
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
