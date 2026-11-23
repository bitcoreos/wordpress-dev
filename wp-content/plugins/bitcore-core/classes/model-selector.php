<?php
declare(strict_types=1);
/**
 * BITCORE Model Selector
 * Pattern: Capability-Based Model Selection
 * Source: AI Services template
 * 
 * Maps user capabilities to available AI models with fallback chains
 * for graceful degradation based on user permissions.
 *
 * @package BITCORE
 * @since 1.0.0
 * @version     1.0.0
 */

class BITCORE_Model_Selector {
    /**
     * Option name for model configuration
     */
    const MODELS_OPTION = 'bitcore_models';

    /**
     * Default models configuration
     */
    private $default_models = [
        'coraline-v1' => [
            'name'        => 'Coraline v1',
            'provider'    => 'venice',
            'model_id'    => 'kimi-k2.5',
            'capability'  => 'bitcore_use_advanced_ai',
            'cost_tier'   => 'premium',
            'max_tokens'  => 32768,
            'features'    => [ 'reasoning', 'coding', 'analysis' ],
            'fallback_to' => 'hyperion-v1',
        ],
        'hyperion-v1' => [
            'name'        => 'Hyperion v1',
            'provider'    => 'venice',
            'model_id'    => 'deepseek-r1',
            'capability'  => 'bitcore_use_ai',
            'cost_tier'   => 'standard',
            'max_tokens'  => 8192,
            'features'    => [ 'reasoning', 'coding' ],
            'fallback_to' => 'claude-haiku',
        ],
        'claude-haiku' => [
            'name'        => 'Claude Haiku',
            'provider'    => 'anthropic',
            'model_id'    => 'claude-3-haiku-20240307',
            'capability'  => 'bitcore_use_basic_ai',
            'cost_tier'   => 'economy',
            'max_tokens'  => 4096,
            'features'    => [ 'chat' ],
            'fallback_to' => null,
        ],
    ];

    /**
     * Task type to model preference mapping
     */
    private $task_preferences = [
        'coding'     => [ 'coraline-v1', 'hyperion-v1' ],
        'analysis'   => [ 'coraline-v1', 'hyperion-v1', 'claude-haiku' ],
        'chat'       => [ 'hyperion-v1', 'claude-haiku' ],
        'research'   => [ 'coraline-v1', 'hyperion-v1' ],
        'quick'      => [ 'hyperion-v1', 'claude-haiku' ],
        'deep'       => [ 'coraline-v1' ],
    ];

    /**
     * Cached models configuration
     */
    private $models_cache = null;

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize default models if not set
        add_action( 'init', [ $this, 'maybe_initialize_defaults' ], 0 );
        
        // Clear cache on model update
        add_action( 'update_option_' . self::MODELS_OPTION, [ $this, 'clear_cache' ] );
    }

    /**
     * Select model for user based on capabilities and task type
     * 
     * @param int    $user_id   User ID (0 for current user)
     * @param string $task_type Task type (coding, analysis, chat, etc.)
     * @param array  $options   Additional options
     * @return array|false Model configuration or false if no suitable model
     */
    public function select_model_for_user( $user_id = 0, $task_type = 'chat', $options = [] ) {
        $user_id = $user_id ?: get_current_user_id();
        $models = $this->get_models();
        
        // Get preferred models for task type
        $preferred = $this->task_preferences[ $task_type ] ?? array_keys( $models );
        
        // Try each preferred model
        foreach ( $preferred as $model_slug ) {
            if ( ! isset( $models[ $model_slug ] ) ) {
                continue;
            }
            
            $model = $models[ $model_slug ];
            
            // Check if user has required capability
            if ( $this->user_can_use_model( $user_id, $model_slug, $models ) ) {
                // Check model availability
                if ( $this->is_model_available( $model_slug, $options ) ) {
                    return $this->prepare_model_config( $model_slug, $model, $task_type );
                }
            }
        }
        
        // Fallback to any available model
        foreach ( $models as $model_slug => $model ) {
            if ( $this->user_can_use_model( $user_id, $model_slug, $models ) ) {
                if ( $this->is_model_available( $model_slug, $options ) ) {
                    return $this->prepare_model_config( $model_slug, $model, $task_type );
                }
            }
        }
        
        return false;
    }

    /**
     * Check if user can use a specific model
     * 
     * @param int    $user_id    User ID
     * @param string $model_slug Model identifier
     * @param array  $models     Available models
     * @return bool
     */
    public function user_can_use_model( $user_id, $model_slug, $models = null ) {
        $models = $models ?: $this->get_models();
        
        if ( ! isset( $models[ $model_slug ] ) ) {
            return false;
        }
        
        $model = $models[ $model_slug ];
        $required_cap = $model['capability'] ?? 'bitcore_use_ai';
        
        // Map to fallback capability if specific one doesn't exist
        if ( ! $this->capability_exists( $required_cap ) ) {
            $required_cap = $this->map_capability_fallback( $required_cap );
        }
        
        // Check user capability
        return user_can( $user_id, $required_cap );
    }

    /**
     * Get model by slug
     * 
     * @param string $model_slug Model identifier
     * @return array|false Model configuration or false
     */
    public function get_model( $model_slug ) {
        $models = $this->get_models();
        
        if ( ! isset( $models[ $model_slug ] ) ) {
            return false;
        }
        
        return $models[ $model_slug ];
    }

    /**
     * Get all available models for user
     * 
     * @param int $user_id User ID (0 for current user)
     * @return array List of models user can access
     */
    public function get_available_models( $user_id = 0 ) {
        $user_id = $user_id ?: get_current_user_id();
        $models = $this->get_models();
        $available = [];
        
        foreach ( $models as $slug => $config ) {
            if ( $this->user_can_use_model( $user_id, $slug, $models ) ) {
                $available[ $slug ] = $config;
            }
        }
        
        return $available;
    }

    /**
     * Register custom model
     * 
     * @param string $slug   Model identifier
     * @param array  $config Model configuration
     * @return bool Success
     */
    public function register_model( $slug, $config ) {
        $models = $this->get_models();
        
        // Validate required fields
        $required = [ 'name', 'provider', 'model_id', 'capability' ];
        foreach ( $required as $field ) {
            if ( empty( $config[ $field ] ) ) {
                return false;
            }
        }
        
        // Set defaults
        $config = wp_parse_args( $config, [
            'cost_tier'   => 'standard',
            'max_tokens'  => 4096,
            'features'    => [],
            'fallback_to' => null,
        ] );
        
        $models[ $slug ] = $config;
        
        return update_option( self::MODELS_OPTION, $models );
    }

    /**
     * Unregister model
     * 
     * @param string $slug Model identifier
     * @return bool Success
     */
    public function unregister_model( $slug ) {
        $models = $this->get_models();
        
        if ( ! isset( $models[ $slug ] ) ) {
            return false;
        }
        
        unset( $models[ $slug ] );
        
        return update_option( self::MODELS_OPTION, $models );
    }

    /**
     * Update model configuration
     * 
     * @param string $slug   Model identifier
     * @param array  $config Updated configuration
     * @return bool Success
     */
    public function update_model( $slug, $config ) {
        $models = $this->get_models();
        
        if ( ! isset( $models[ $slug ] ) ) {
            return false;
        }
        
        $models[ $slug ] = array_merge( $models[ $slug ], $config );
        
        return update_option( self::MODELS_OPTION, $models );
    }

    /**
     * Get fallback chain for model
     * 
     * @param string $model_slug Starting model
     * @param int    $max_depth  Maximum chain length
     * @return array Fallback chain
     */
    public function get_fallback_chain( $model_slug, $max_depth = 5 ) {
        $chain = [];
        $models = $this->get_models();
        $current = $model_slug;
        $depth = 0;
        
        while ( $current && $depth < $max_depth ) {
            if ( ! isset( $models[ $current ] ) ) {
                break;
            }
            
            // Prevent infinite loops
            if ( in_array( $current, $chain, true ) ) {
                break;
            }
            
            $chain[] = $current;
            $current = $models[ $current ]['fallback_to'] ?? null;
            $depth++;
        }
        
        return $chain;
    }

    /**
     * Check if model is available
     * 
     * @param string $model_slug Model identifier
     * @param array  $options    Additional checks
     * @return bool
     */
    private function is_model_available( $model_slug, $options = [] ) {
        $model = $this->get_model( $model_slug );
        
        if ( ! $model ) {
            return false;
        }
        
        // Check feature requirements
        if ( ! empty( $options['requires_features'] ) ) {
            $missing = array_diff( $options['requires_features'], $model['features'] ?? [] );
            if ( ! empty( $missing ) ) {
                return false;
            }
        }
        
        // Check cost tier preference
        if ( ! empty( $options['max_cost_tier'] ) ) {
            $tiers = [ 'economy' => 1, 'standard' => 2, 'premium' => 3 ];
            $model_tier = $tiers[ $model['cost_tier'] ] ?? 2;
            $max_tier = $tiers[ $options['max_cost_tier'] ] ?? 3;
            
            if ( $model_tier > $max_tier ) {
                return false;
            }
        }
        
        /**
         * Filter model availability
         * 
         * @param bool   $available  Whether model is available
         * @param string $model_slug Model identifier
         * @param array  $model      Model configuration
         * @param array  $options    Options
         */
        return apply_filters( 'bitcore_model_available', true, $model_slug, $model, $options );
    }

    /**
     * Prepare model config for use
     * 
     * @param string $slug      Model identifier
     * @param array  $model     Model configuration
     * @param string $task_type Task type
     * @return array Prepared config
     */
    private function prepare_model_config( $slug, $model, $task_type ) {
        $config = array_merge( $model, [
            'slug'      => $slug,
            'task_type' => $task_type,
            'selected_at' => time(),
        ] );
        
        // Apply task-specific overrides
        if ( $task_type === 'quick' ) {
            $config['max_tokens'] = min( $config['max_tokens'], 2048 );
        }
        
        /**
         * Filter prepared model config
         * 
         * @param array  $config    Prepared config
         * @param string $slug      Model identifier
         * @param string $task_type Task type
         */
        return apply_filters( 'bitcore_model_config', $config, $slug, $task_type );
    }

    /**
     * Get all models configuration
     * 
     * @return array Models configuration
     */
    public function get_models() {
        if ( null === $this->models_cache ) {
            $this->models_cache = get_option( self::MODELS_OPTION, [] );
            
            // Merge with defaults
            $this->models_cache = wp_parse_args( $this->models_cache, $this->default_models );
            
            /**
             * Filter available models
             * 
             * @param array $models Models configuration
             */
            $this->models_cache = apply_filters( 'bitcore_models', $this->models_cache );
        }
        
        return $this->models_cache;
    }

    /**
     * Initialize default models if not set
     */
    public function maybe_initialize_defaults() {
        $models = get_option( self::MODELS_OPTION, null );
        
        if ( null === $models ) {
            update_option( self::MODELS_OPTION, $this->default_models );
        }
    }

    /**
     * Clear models cache
     */
    public function clear_cache() {
        $this->models_cache = null;
    }

    /**
     * Check if capability exists
     * 
     * @param string $cap Capability name
     * @return bool
     */
    private function capability_exists( $cap ) {
        global $wp_roles;
        
        if ( ! isset( $wp_roles ) ) {
            $wp_roles = new WP_Roles();
        }
        
        foreach ( $wp_roles->roles as $role ) {
            if ( isset( $role['capabilities'][ $cap ] ) ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Map capability to fallback
     * 
     * @param string $cap Capability name
     * @return string Fallback capability
     */
    private function map_capability_fallback( $cap ) {
        $fallbacks = [
            'bitcore_use_advanced_ai' => 'bitcore_use_ai',
            'bitcore_use_ai' => 'bitcore_use_basic_ai',
            'bitcore_use_basic_ai' => 'read',
        ];
        
        return $fallbacks[ $cap ] ?? 'read';
    }

    /**
     * Get task preferences
     * 
     * @return array Task preferences
     */
    public function get_task_preferences() {
        /**
         * Filter task preferences
         * 
         * @param array $preferences Task type to model mapping
         */
        return apply_filters( 'bitcore_model_task_preferences', $this->task_preferences );
    }

    /**
     * Set task preferences
     * 
     * @param array $preferences New preferences
     * @return bool Success
     */
    public function set_task_preferences( $preferences ) {
        $this->task_preferences = $preferences;
        
        /**
         * Fired when task preferences are updated
         * 
         * @param array $preferences Updated preferences
         */
        do_action( 'bitcore_task_preferences_updated', $preferences );
        
        return true;
    }
}
