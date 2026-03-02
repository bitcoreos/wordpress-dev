<?php
/**
 * for implementation
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
 * BITCORE Accelerate Engine - Main Controller
 *
 * @package BITCORE\Accelerate
 * @since 1.0.0
 */

namespace BITCORE\Accelerate;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Accelerate Controller
 *
 * REST API and CLI interface for the Accelerate Engine.
 *
 * @since 1.0.0
 */
class Accelerate_Controller
{
    /** @var string REST API namespace */
    private string $namespace = 'bitcore/v1';

    /** @var Distillation_Engine Distillation engine */
    private Distillation_Engine $distiller;

    /** @var Artifact_Compiler Artifact compiler */
    private Artifact_Compiler $compiler;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->distiller = new Distillation_Engine();
        $this->compiler = new Artifact_Compiler(null, $this->distiller);
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
        // Distillation endpoints
        register_rest_route($this->namespace, '/accelerate/distill/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'distill_artifact'],
            'permission_callback' => [$this, 'can_process'],
            'args' => [
                'id' => ['type' => 'integer', 'required' => true],
                'strategy' => [
                    'type' => 'string',
                    'default' => 'summarize',
                    'enum' => ['extract_key_points', 'summarize', 'compress', 'extract_patterns', 'generate_outline'],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/accelerate/distill-batch', [
            'methods' => 'POST',
            'callback' => [$this, 'distill_batch'],
            'permission_callback' => [$this, 'can_process'],
            'args' => [
                'artifact_ids' => ['type' => 'array', 'required' => true],
                'strategy' => ['type' => 'string', 'default' => 'summarize'],
            ],
        ]);

        // Compilation endpoints
        register_rest_route($this->namespace, '/accelerate/compile', [
            'methods' => 'POST',
            'callback' => [$this, 'compile_artifacts'],
            'permission_callback' => [$this, 'can_process'],
            'args' => [
                'artifact_ids' => ['type' => 'array', 'required' => true],
                'method' => [
                    'type' => 'string',
                    'default' => 'merge',
                    'enum' => ['merge', 'extract', 'categorize', 'distill'],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/accelerate/compile-preview', [
            'methods' => 'POST',
            'callback' => [$this, 'preview_compilation'],
            'permission_callback' => [$this, 'can_process'],
        ]);

        register_rest_route($this->namespace, '/accelerate/create-compiled', [
            'methods' => 'POST',
            'callback' => [$this, 'create_compiled_artifact'],
            'permission_callback' => [$this, 'can_edit'],
            'args' => [
                'artifact_ids' => ['type' => 'array', 'required' => true],
                'name' => ['type' => 'string', 'required' => true],
            ],
        ]);

        // Info endpoints
        register_rest_route($this->namespace, '/accelerate/strategies', [
            'methods' => 'GET',
            'callback' => [$this, 'get_strategies'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/accelerate/methods', [
            'methods' => 'GET',
            'callback' => [$this, 'get_methods'],
            'permission_callback' => '__return_true',
        ]);
    }

    // === REST Callbacks ===

    /**
     * Distill single artifact.
     */
    public function distill_artifact(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');
        $strategy = $request->get_param('strategy');
        $options = $request->get_json_params() ?? [];

        $result = $this->distiller->distill($id, $strategy, $options);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Distill multiple artifacts.
     */
    public function distill_batch(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        $ids = $params['artifact_ids'] ?? [];
        $strategy = $params['strategy'] ?? 'summarize';
        $options = $params['options'] ?? [];

        $results = $this->distiller->distill_batch($ids, $strategy, $options);

        return rest_ensure_response([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * Compile artifacts.
     */
    public function compile_artifacts(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $params = $request->get_json_params();
        $ids = $params['artifact_ids'] ?? [];
        $options = [
            'method' => $params['method'] ?? 'merge',
            'separator' => $params['separator'] ?? "\n\n---\n\n",
            'strategy' => $params['strategy'] ?? 'summarize',
        ];

        $result = $this->compiler->compile($ids, $options);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Preview compilation.
     */
    public function preview_compilation(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $params = $request->get_json_params();
        $ids = $params['artifact_ids'] ?? [];
        $options = $params['options'] ?? [];

        $result = $this->compiler->preview($ids, $options);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Create compiled artifact.
     */
    public function create_compiled_artifact(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $params = $request->get_json_params();
        $ids = $params['artifact_ids'] ?? [];
        $name = sanitize_text_field($params['name'] ?? 'Compiled Artifact');
        $options = $params['options'] ?? [];

        $result = $this->compiler->create_compiled_artifact($ids, $name, $options);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'success' => true,
            'artifact_id' => $result,
            'message' => 'Compiled artifact created successfully.',
        ]);
    }

    /**
     * Get available strategies.
     */
    public function get_strategies(): WP_REST_Response
    {
        return rest_ensure_response([
            'success' => true,
            'data' => $this->distiller->get_strategies(),
        ]);
    }

    /**
     * Get available compilation methods.
     */
    public function get_methods(): WP_REST_Response
    {
        return rest_ensure_response([
            'success' => true,
            'data' => $this->compiler->get_methods(),
        ]);
    }

    // === Permission Callbacks ===

    public function can_process(): bool
    {
        return current_user_can('read');
    }

    public function can_edit(): bool
    {
        return current_user_can('edit_posts');
    }

    // === CLI Commands ===

    private function register_cli_commands(): void
    {
        \WP_CLI::add_command('bitcore accelerate distill', [$this, 'cli_distill']);
        \WP_CLI::add_command('bitcore accelerate compile', [$this, 'cli_compile']);
        \WP_CLI::add_command('bitcore accelerate strategies', [$this, 'cli_strategies']);
    }

    public function cli_distill(array $args, array $assoc_args): void
    {
        if (!isset($args[0])) {
            \WP_CLI::error('Artifact ID required.');
            return;
        }

        $strategy = $assoc_args['strategy'] ?? 'summarize';
        $result = $this->distiller->distill((int) $args[0], $strategy, $assoc_args);

        if (is_wp_error($result)) {
            \WP_CLI::error($result->get_error_message());
            return;
        }

        \WP_CLI::success('Distillation complete.');
        \WP_CLI::line($result['result']['content'] ?? '');
    }

    public function cli_compile(array $args, array $assoc_args): void
    {
        $ids_str = $assoc_args['ids'] ?? '';

        if (empty($ids_str)) {
            \WP_CLI::error('--ids parameter required (comma-separated).');
            return;
        }

        $ids = array_map('intval', explode(',', $ids_str));
        $name = $assoc_args['name'] ?? 'Compiled Artifact';
        $method = $assoc_args['method'] ?? 'merge';

        $result = $this->compiler->create_compiled_artifact($ids, $name, ['method' => $method]);

        if (is_wp_error($result)) {
            \WP_CLI::error($result->get_error_message());
            return;
        }

        \WP_CLI::success(sprintf('Compiled artifact created with ID: %d', $result));
    }

    public function cli_strategies(): void
    {
        $strategies = $this->distiller->get_strategies();
        \WP_CLI::success('Available strategies: ' . implode(', ', $strategies));
    }
}

// Initialize
new Accelerate_Controller();
