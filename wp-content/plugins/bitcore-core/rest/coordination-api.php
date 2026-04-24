<?php
/**
 * BITCORE Coordination REST API
 * Endpoints for voting, bidding, and consensus
 *
 * @package BITCORE_Core
 * @since 1.3.0
 */

declare(strict_types=1);

// Load coordination classes
require_once dirname(__FILE__) . '/../infrastructure/coordination/class-task-bid.php';
require_once dirname(__FILE__) . '/../infrastructure/coordination/class-vote-engine.php';
require_once dirname(__FILE__) . '/../infrastructure/coordination/class-consensus-protocol.php';

/**
 * Class BITCORE_Coordination_API
 */
class BITCORE_Coordination_API {

    private string $namespace = 'bitcore/v1';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        // Bid endpoints
        register_rest_route($this->namespace, '/bids', [
            'methods' => 'POST',
            'callback' => [$this, 'submit_bid'],
            'permission_callback' => [$this, 'permission_check'],
        ]);
        register_rest_route($this->namespace, '/bids/(?P<entity_type>[\w]+)/(?P<entity_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_bids'],
            'permission_callback' => '__return_true',
        ]);

        // Vote endpoints
        register_rest_route($this->namespace, '/votes', [
            'methods' => 'POST',
            'callback' => [$this, 'create_vote'],
            'permission_callback' => [$this, 'permission_check'],
        ]);
        register_rest_route($this->namespace, '/votes/(?P<vote_id>[\w-]+)/cast', [
            'methods' => 'POST',
            'callback' => [$this, 'cast_vote'],
            'permission_callback' => [$this, 'permission_check'],
        ]);
        register_rest_route($this->namespace, '/votes/(?P<vote_id>[\w-]+)/result', [
            'methods' => 'GET',
            'callback' => [$this, 'get_vote_result'],
            'permission_callback' => '__return_true',
        ]);

        // Consensus endpoint
        register_rest_route($this->namespace, '/consensus', [
            'methods' => 'POST',
            'callback' => [$this, 'evaluate_consensus'],
            'permission_callback' => [$this, 'permission_check'],
        ]);
    }

    public function permission_check(): bool {
        return is_user_logged_in() || $this->verify_agent_token();
    }

    private function verify_agent_token(): bool {
        $token = '';
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            $token = $headers['X-Agent-Token'] ?? $headers['x-agent-token'] ?? '';
        }
        // Fallback to $_SERVER
        if (empty($token)) {
            $token = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? '';
        }
        return !empty($token) && $this->validate_token($token);
    }

    private function validate_token(string $token): bool {
        return apply_filters('bitcore_validate_agent_token', false, $token);
    }

    /**
     * Submit a bid on an entity
     */
    public function submit_bid(WP_REST_Request $request): WP_REST_Response {
        $bid = new BITCORE_Coordination_TaskBid(
            $request->get_param('entity_type'),
            (int) $request->get_param('entity_id'),
            $request->get_param('agent_id'),
            (float) ($request->get_param('confidence') ?? 0.5),
            (int) ($request->get_param('estimated_tokens') ?? 0),
            (int) ($request->get_param('estimated_seconds') ?? 0),
            (int) ($request->get_param('priority') ?? 5),
            $request->get_param('rationale') ?? '',
            $request->get_param('capabilities') ?? []
        );

        $stored = add_post_meta($bid->entity_id, "bitcore_bid_{$bid->agent_id}", $bid->to_array());

        return new WP_REST_Response([
            'success' => (bool) $stored,
            'bid' => $bid->to_array(),
        ], 201);
    }

    /**
     * Get all bids for an entity
     */
    public function get_bids(WP_REST_Request $request): WP_REST_Response {
        $entity_id = (int) $request->get_param('entity_id');
        $meta = get_post_meta($entity_id);
        $bids = [];
        
        foreach ($meta as $key => $values) {
            if (str_starts_with($key, 'bitcore_bid_')) {
                $bids[] = maybe_unserialize($values[0]);
            }
        }
        
        return new WP_REST_Response(['bids' => $bids]);
    }

    /**
     * Create a new vote
     */
    public function create_vote(WP_REST_Request $request): WP_REST_Response {
        $engine = new BITCORE_Coordination_VoteEngine(
            $request->get_param('entity_type'),
            (int) $request->get_param('entity_id'),
            $request->get_param('options') ?? ['approve', 'reject'],
            $request->get_param('voters') ?? [],
            (int) ($request->get_param('deadline_seconds') ?? 300)
        );
        
        $vote_id = 'vote_' . bin2hex(random_bytes(8));
        set_transient($vote_id, $engine->to_array(), 3600);
        
        return new WP_REST_Response([
            'vote_id' => $vote_id,
            'engine' => $engine->to_array(),
        ], 201);
    }

    /**
     * Cast a vote
     */
    public function cast_vote(WP_REST_Request $request): WP_REST_Response {
        $vote_id = $request->get_param('vote_id');
        $data = get_transient($vote_id);
        
        if (!$data) {
            return new WP_REST_Response(['error' => 'Vote not found or expired'], 404);
        }
        
        $engine = BITCORE_Coordination_VoteEngine::from_array($data);
        $success = $engine->cast_vote(
            $request->get_param('voter_id'),
            $request->get_param('option'),
            (float) ($request->get_param('weight') ?? 1.0),
            $request->get_param('rationale') ?? ''
        );
        
        set_transient($vote_id, $engine->to_array(), 3600);
        
        return new WP_REST_Response([
            'success' => $success,
            'engine' => $engine->to_array(),
        ]);
    }

    /**
     * Get vote result
     */
    public function get_vote_result(WP_REST_Request $request): WP_REST_Response {
        $vote_id = $request->get_param('vote_id');
        $data = get_transient($vote_id);
        
        if (!$data) {
            return new WP_REST_Response(['error' => 'Vote not found or expired'], 404);
        }
        
        return new WP_REST_Response(['result' => $data]);
    }

    /**
     * Evaluate consensus
     */
    public function evaluate_consensus(WP_REST_Request $request): WP_REST_Response {
        $protocol = new BITCORE_Coordination_ConsensusProtocol(
            $request->get_param('type') ?? 'majority',
            $request->get_param('weights') ?? ['hyperion' => 2, 'coraline' => 1, 'hermes' => 1],
            (float) ($request->get_param('threshold') ?? 0.5)
        );
        
        $vote_id = $request->get_param('vote_id');
        $data = get_transient($vote_id);
        
        if (!$data) {
            return new WP_REST_Response(['error' => 'Vote not found'], 404);
        }
        
        $engine = BITCORE_Coordination_VoteEngine::from_array($data);
        $result = $protocol->evaluate($engine);
        
        return new WP_REST_Response(['consensus' => $result]);
    }
}

new BITCORE_Coordination_API();
