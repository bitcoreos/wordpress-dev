<?php
declare(strict_types=1);
/**
 * BITCORE MCP (Model Context Protocol) Core
 * Pattern: Enhanced MCP Protocol Implementation
 * Source: MCP Adapter template
 * 
 * Provides AI tool calling, resource access, and streaming responses
 * following the Model Context Protocol specification.
 *
 * @package BITCORE
 * @since 1.0.0
 * @version     1.0.0
 */

require_once __DIR__ . '/resources.php';
require_once __DIR__ . '/tools.php';

class BITCORE_MCP_Core {
    use BITCORE_MCP_Resources;
    use BITCORE_MCP_Tools;

    private $core;
    private $tools = [];
    private $resources = [];
    private $streams = [];

    public function __construct( $core ) {
        $this->core = $core;

        $this->register_core_tools();
        $this->register_core_resources();
    }

    /**
     * Register core MCP tools
     */
    private function register_core_tools() {
        // Search tool
        $this->register_tool( 'search_bitwiki', [
            'description' => 'Search BITwiki knowledge base',
            'parameters' => [
                'query' => [ 'type' => 'string', 'required' => true, 'description' => 'Search query' ],
                'limit' => [ 'type' => 'integer', 'default' => 5, 'description' => 'Max results to return' ],
            ],
            'callback' => [ $this, 'tool_search_bitwiki' ],
            'streaming' => false,
        ] );

        // Get artifacts tool
        $this->register_tool( 'get_artifacts', [
            'description' => 'Get available artifacts from BIThub',
            'parameters' => [],
            'callback' => [ $this, 'tool_get_artifacts' ],
            'streaming' => false,
        ] );

        // Submit intent tool
        $this->register_tool( 'submit_research_intent', [
            'description' => 'Submit a research intent to the librarian',
            'parameters' => [
                'intent' => [ 'type' => 'string', 'required' => true, 'description' => 'Research intent' ],
                'scope' => [ 'type' => 'string', 'enum' => [ 'quick', 'deep' ], 'default' => 'quick', 'description' => 'Research scope' ],
            ],
            'callback' => [ $this, 'tool_submit_intent' ],
            'streaming' => false,
        ] );

        // Get queue status
        $this->register_tool( 'get_queue_status', [
            'description' => 'Get current research queue status',
            'parameters' => [],
            'callback' => [ $this, 'tool_get_queue' ],
            'streaming' => false,
        ] );

        // Dispatch to swarm
        $this->register_tool( 'dispatch_to_swarm', [
            'description' => 'Dispatch a task to the agent swarm',
            'parameters' => [
                'task' => [ 'type' => 'string', 'required' => true, 'description' => 'Task description' ],
                'agents' => [ 'type' => 'array', 'default' => [], 'description' => 'Specific agents to dispatch' ],
            ],
            'callback' => [ $this, 'tool_dispatch_swarm' ],
            'streaming' => true,
        ] );

        // Streaming chat tool
        $this->register_tool( 'stream_chat', [
            'description' => 'Chat with streaming response',
            'parameters' => [
                'message' => [ 'type' => 'string', 'required' => true, 'description' => 'Message to send' ],
                'context' => [ 'type' => 'string', 'default' => '', 'description' => 'Conversation context' ],
            ],
            'callback' => [ $this, 'tool_stream_chat' ],
            'streaming' => true,
        ] );
    }

    /**
     * Register core MCP resources
     */
    private function register_core_resources() {
        // BITwiki knowledge base
        $this->register_resource( 'bitwiki', [
            'name' => 'BITwiki Knowledge Base',
            'description' => 'Access to BITwiki documentation and articles',
            'mimeType' => 'application/json',
            'uri' => 'bitcore://bitwiki/{path}',
            'callback' => [ $this, 'resource_bitwiki' ],
        ] );

        // BIThub discussions
        $this->register_resource( 'bithub', [
            'name' => 'BIThub Discussions',
            'description' => 'Access to BIThub forum discussions',
            'mimeType' => 'application/json',
            'uri' => 'bitcore://bithub/{topic_id}',
            'callback' => [ $this, 'resource_bithub' ],
        ] );

        // Agent swarm status
        $this->register_resource( 'swarm', [
            'name' => 'Agent Swarm Status',
            'description' => 'Current status of agent swarm nodes',
            'mimeType' => 'application/json',
            'uri' => 'bitcore://swarm/{agent_id}',
            'callback' => [ $this, 'resource_swarm' ],
        ] );

        // Research artifacts
        $this->register_resource( 'artifacts', [
            'name' => 'Research Artifacts',
            'description' => 'Access to research artifacts and outputs',
            'mimeType' => 'application/json',
            'uri' => 'bitcore://artifacts/{artifact_id}',
            'callback' => [ $this, 'resource_artifacts' ],
        ] );
    }

    /**
     * Register a tool
     * 
     * @param string $name   Tool identifier
     * @param array  $config Tool configuration
     * @return $this
     */
    public function register_tool( $name, $config ) {
        $this->tools[ $name ] = wp_parse_args( $config, [
            'description' => '',
            'parameters' => [],
            'callback' => null,
            'streaming' => false,
        ] );
        return $this;
    }

    /**
     * Register a resource
     * 
     * @param string $name   Resource identifier
     * @param array  $config Resource configuration
     * @return $this
     */
    public function register_resource( $name, $config ) {
        $this->resources[ $name ] = wp_parse_args( $config, [
            'name' => $name,
            'description' => '',
            'mimeType' => 'application/json',
            'uri' => '',
            'callback' => null,
        ] );
        return $this;
    }
}
