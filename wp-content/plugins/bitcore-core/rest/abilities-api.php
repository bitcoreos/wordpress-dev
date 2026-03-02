<?php
/**
 * BITCORE_Rest_Abilities_API class for Bitcore
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
class BITCORE_Rest_Abilities_API {
    private $namespace = 'bitcore/v1';
    private $abilities = array();
    private $categories = array();

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_action( 'bitcore_abilities_init', array( $this, 'register_core_abilities' ) );
    }

    /**
     * Register REST routes for abilities
     */
    public function register_routes() {
        // List all abilities
        register_rest_route( $this->namespace, '/abilities', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_abilities' ),
            'permission_callback' => '__return_true',
        ) );

        // Get ability details
        register_rest_route( $this->namespace, '/abilities/(?P<name>[a-z0-9_-]+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_ability' ),
            'permission_callback' => '__return_true',
        ) );

        // Execute ability
        register_rest_route( $this->namespace, '/abilities/(?P<name>[a-z0-9_-]+)', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'execute_ability' ),
            'permission_callback' => array( $this, 'check_execute_permission' ),
        ) );

        // List categories
        register_rest_route( $this->namespace, '/abilities/categories', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_categories' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Register core BITCORE abilities
     */
    public function register_core_abilities() {
        // Content category
        $this->register_category( 'content', array(
            'label'       => __( 'Content', 'bitcore' ),
            'description' => __( 'Content management abilities', 'bitcore' ),
        ) );

        // Create post ability
        $this->register_ability( 'bitcore_create_post', array(
            'label'             => __( 'Create Post', 'bitcore' ),
            'description'       => __( 'Create a new WordPress post', 'bitcore' ),
            'category'          => 'content',
            'input_schema'      => array(
                'type'       => 'object',
                'properties' => array(
                    'title'   => array(
                        'type'        => 'string',
                        'description' => __( 'Post title', 'bitcore' ),
                    ),
                    'content' => array(
                        'type'        => 'string',
                        'description' => __( 'Post content', 'bitcore' ),
                    ),
                    'status'  => array(
                        'type'        => 'string',
                        'enum'        => array( 'draft', 'publish', 'private' ),
                        'default'     => 'draft',
                        'description' => __( 'Post status', 'bitcore' ),
                    ),
                ),
                'required' => array( 'title' ),
            ),
            'output_schema'     => array(
                'type'       => 'object',
                'properties' => array(
                    'id'      => array( 'type' => 'integer' ),
                    'title'   => array( 'type' => 'string' ),
                    'status'  => array( 'type' => 'string' ),
                    'url'     => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback'  => array( $this, 'create_post' ),
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
        ) );

        // Get posts ability
        $this->register_ability( 'bitcore_get_posts', array(
            'label'             => __( 'Get Posts', 'bitcore' ),
            'description'       => __( 'Retrieve WordPress posts', 'bitcore' ),
            'category'          => 'content',
            'input_schema'      => array(
                'type'       => 'object',
                'properties' => array(
                    'per_page' => array(
                        'type'    => 'integer',
                        'default' => 10,
                        'maximum' => 100,
                    ),
                    'page'     => array(
                        'type'    => 'integer',
                        'default' => 1,
                    ),
                    'status'   => array(
                        'type'    => 'string',
                        'enum'    => array( 'publish', 'draft', 'private', 'any' ),
                        'default' => 'publish',
                    ),
                ),
            ),
            'output_schema'     => array(
                'type'  => 'array',
                'items' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'id'     => array( 'type' => 'integer' ),
                        'title'  => array( 'type' => 'string' ),
                        'status' => array( 'type' => 'string' ),
                        'date'   => array( 'type' => 'string' ),
                    ),
                ),
            ),
            'execute_callback'  => array( $this, 'get_posts' ),
            'permission_callback' => '__return_true',
        ) );

        // AI category
        $this->register_category( 'ai', array(
            'label'       => __( 'AI', 'bitcore' ),
            'description' => __( 'AI-powered abilities', 'bitcore' ),
        ) );

        // Chat ability
        $this->register_ability( 'bitcore_chat', array(
            'label'             => __( 'Chat', 'bitcore' ),
            'description'       => __( 'Send message to BITCORE AI', 'bitcore' ),
            'category'          => 'ai',
            'input_schema'      => array(
                'type'       => 'object',
                'properties' => array(
                    'message' => array(
                        'type'        => 'string',
                        'description' => __( 'Message to send', 'bitcore' ),
                    ),
                    'model'   => array(
                        'type'    => 'string',
                        'enum'    => array( 'coraline', 'hyperion' ),
                        'default' => 'coraline',
                    ),
                ),
                'required' => array( 'message' ),
            ),
            'output_schema'     => array(
                'type'       => 'object',
                'properties' => array(
                    'response' => array( 'type' => 'string' ),
                    'model'    => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback'  => array( $this, 'chat' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Register an ability
     */
    public function register_ability( $name, $args ) {
        $this->abilities[ $name ] = wp_parse_args( $args, array(
            'label'               => '',
            'description'         => '',
            'category'            => '',
            'input_schema'        => array(),
            'output_schema'       => array(),
            'execute_callback'    => null,
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Register a category
     */
    public function register_category( $name, $args ) {
        $this->categories[ $name ] = wp_parse_args( $args, array(
            'label'       => '',
            'description' => '',
        ) );
    }

    /**
     * Get all abilities
     */
    public function get_abilities( $request ) {
        $category = $request->get_param( 'category' );
        $abilities = array();

        foreach ( $this->abilities as $name => $ability ) {
            if ( $category && $ability['category'] !== $category ) {
                continue;
            }
            $abilities[] = array(
                'name'        => $name,
                'label'       => $ability['label'],
                'description' => $ability['description'],
                'category'    => $ability['category'],
            );
        }

        return rest_ensure_response( $abilities );
    }

    /**
     * Get single ability
     */
    public function get_ability( $request ) {
        $name = $request->get_param( 'name' );

        if ( ! isset( $this->abilities[ $name ] ) ) {
            return new WP_Error(
                'ability_not_found',
                __( 'Ability not found.', 'bitcore' ),
                array( 'status' => 404 )
            );
        }

        $ability = $this->abilities[ $name ];

        return rest_ensure_response( array(
            'name'          => $name,
            'label'         => $ability['label'],
            'description'   => $ability['description'],
            'category'      => $ability['category'],
            'input_schema'  => $ability['input_schema'],
            'output_schema' => $ability['output_schema'],
        ) );
    }

    /**
     * Execute ability
     */
    public function execute_ability( $request ) {
        $name = $request->get_param( 'name' );
        $input = $request->get_json_params();

        if ( ! isset( $this->abilities[ $name ] ) ) {
            return new WP_Error(
                'ability_not_found',
                __( 'Ability not found.', 'bitcore' ),
                array( 'status' => 404 )
            );
        }

        $ability = $this->abilities[ $name ];

        // Validate input against schema
        if ( ! empty( $ability['input_schema'] ) ) {
            $valid = $this->validate_input( $input, $ability['input_schema'] );
            if ( is_wp_error( $valid ) ) {
                return $valid;
            }
        }

        // Execute
        if ( is_callable( $ability['execute_callback'] ) ) {
            $result = call_user_func( $ability['execute_callback'], $input );
            return rest_ensure_response( $result );
        }

        return new WP_Error(
            'execution_failed',
            __( 'Ability execution failed.', 'bitcore' ),
            array( 'status' => 500 )
        );
    }

    /**
     * Get categories
     */
    public function get_categories() {
        return rest_ensure_response( $this->categories );
    }

    /**
     * Check execute permission
     */
    public function check_execute_permission( $request ) {
        $name = $request->get_param( 'name' );

        if ( ! isset( $this->abilities[ $name ] ) ) {
            return true; // Let execute_ability handle 404
        }

        $ability = $this->abilities[ $name ];

        if ( is_callable( $ability['permission_callback'] ) ) {
            return call_user_func( $ability['permission_callback'] );
        }

        return true;
    }

    /**
     * Validate input against schema
     */
    private function validate_input( $input, $schema ) {
        if ( isset( $schema['required'] ) ) {
            foreach ( $schema['required'] as $field ) {
                if ( ! isset( $input[ $field ] ) ) {
                    return new WP_Error(
                        'missing_required_field',
                        sprintf( __( 'Missing required field: %s', 'bitcore' ), $field ),
                        array( 'status' => 400 )
                    );
                }
            }
        }

        return true;
    }

    // Execute callbacks
    private function create_post( $input ) {
        $post_data = array(
            'post_title'   => $input['title'],
            'post_content' => isset( $input['content'] ) ? $input['content'] : '',
            'post_status'  => isset( $input['status'] ) ? $input['status'] : 'draft',
            'post_type'    => 'post',
        );

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        return array(
            'id'     => $post_id,
            'title'  => $input['title'],
            'status' => $post_data['post_status'],
            'url'    => get_permalink( $post_id ),
        );
    }

    private function get_posts( $input ) {
        $args = array(
            'posts_per_page' => isset( $input['per_page'] ) ? $input['per_page'] : 10,
            'paged'          => isset( $input['page'] ) ? $input['page'] : 1,
            'post_status'    => isset( $input['status'] ) ? $input['status'] : 'publish',
        );

        $query = new WP_Query( $args );
        $posts = array();

        foreach ( $query->posts as $post ) {
            $posts[] = array(
                'id'     => $post->ID,
                'title'  => $post->post_title,
                'status' => $post->post_status,
                'date'   => $post->post_date,
            );
        }

        return $posts;
    }

    private function chat( $input ) {
        // Placeholder for AI chat - integrate with existing bitcore-core chat
        return array(
            'response' => 'BITCORE AI: Received message: ' . $input['message'],
            'model'    => isset( $input['model'] ) ? $input['model'] : 'coraline',
        );
    }
}

// Initialize
new BITCORE_Rest_Abilities_API();
