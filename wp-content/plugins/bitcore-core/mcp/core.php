<?php
/**
 * BITCORE MCP (Model Context Protocol) Core
 * AI tool calling and function execution
 */

class BITCORE_MCP_Core {
    private $core;
    private $tools = [];

    public function __construct( $core ) {
        $this->core = $core;

        $this->register_core_tools();
    }

    /**
     * Register core MCP tools
     */
    private function register_core_tools() {
        // Search tool
        $this->register_tool( 'search_bitwiki', [
            'description' => 'Search BITwiki knowledge base',
            'parameters' => [
                'query' => [ 'type' => 'string', 'required' => true ],
                'limit' => [ 'type' => 'integer', 'default' => 5 ],
            ],
            'callback' => [ $this, 'tool_search_bitwiki' ],
        ] );

        // Get artifacts tool
        $this->register_tool( 'get_artifacts', [
            'description' => 'Get available artifacts from BIThub',
            'parameters' => [],
            'callback' => [ $this, 'tool_get_artifacts' ],
        ] );

        // Submit intent tool
        $this->register_tool( 'submit_research_intent', [
            'description' => 'Submit a research intent to the librarian',
            'parameters' => [
                'intent' => [ 'type' => 'string', 'required' => true ],
                'scope' => [ 'type' => 'string', 'enum' => [ 'quick', 'deep' ], 'default' => 'quick' ],
            ],
            'callback' => [ $this, 'tool_submit_intent' ],
        ] );

        // Get queue status
        $this->register_tool( 'get_queue_status', [
            'description' => 'Get current research queue status',
            'parameters' => [],
            'callback' => [ $this, 'tool_get_queue' ],
        ] );

        // Dispatch to swarm
        $this->register_tool( 'dispatch_to_swarm', [
            'description' => 'Dispatch a task to the agent swarm',
            'parameters' => [
                'task' => [ 'type' => 'string', 'required' => true ],
                'agents' => [ 'type' => 'array', 'default' => [] ],
            ],
            'callback' => [ $this, 'tool_dispatch_swarm' ],
        ] );
    }

    /**
     * Register a tool
     */
    public function register_tool( $name, $config ) {
        $this->tools[ $name ] = $config;
    }

    /**
     * Get all tools
     */
    public function get_tools() {
        $tools = [];
        foreach ( $this->tools as $name => $config ) {
            $tools[] = [
                'name' => $name,
                'description' => $config['description'],
                'parameters' => $config['parameters'],
            ];
        }
        return $tools;
    }

    /**
     * Call a tool
     */
    public function call_tool( $name, $args = [] ) {
        if ( ! isset( $this->tools[ $name ] ) ) {
            return new WP_Error( 'tool_not_found', 'Tool not found: ' . $name );
        }

        $tool = $this->tools[ $name ];

        // Validate required parameters
        foreach ( $tool['parameters'] as $param => $config ) {
            if ( isset( $config['required'] ) && $config['required'] && ! isset( $args[ $param ] ) ) {
                return new WP_Error( 'missing_param', 'Missing required parameter: ' . $param );
            }
        }

        // Call the callback
        return call_user_func( $tool['callback'], $args );
    }

    // Tool implementations
    private function tool_search_bitwiki( $args ) {
        return $this->core->connector_bitwiki->search( $args['query'], $args['limit'] ?? 5 );
    }

    private function tool_get_artifacts() {
        return $this->core->connector_bithub->get_artifacts();
    }

    private function tool_submit_intent( $args ) {
        return $this->core->connector_n8n->send_intent( $args['intent'], $args['scope'], get_current_user_id() );
    }

    private function tool_get_queue() {
        return $this->core->connector_n8n->get_queue_status();
    }

    private function tool_dispatch_swarm( $args ) {
        return $this->core->connector_n8n->dispatch_to_swarm( $args['task'], $args['agents'] ?? [] );
    }
}
