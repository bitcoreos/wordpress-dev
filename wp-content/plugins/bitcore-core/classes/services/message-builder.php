<?php
declare(strict_types=1);
/**
 * BITCORE_Services_Message_Builder class for Bitcore
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */


if (!defined('ABSPATH')) {
    exit;
}
class BITCORE_Services_Message_Builder {
    private $core;

    public function __construct( $core ) {
        $this->core = $core;
    }

    /**
     * Build research intent message
     */
    public function build_intent( $user_intent, $context = [] ) {
        $message = [
            'role' => 'user',
            'content' => $user_intent,
            'timestamp' => time(),
            'context' => $context,
        ];

        return $message;
    }

    /**
     * Build system message for agent
     */
    public function build_system_message( $agent = 'coraline' ) {
        $system_prompts = [
            'coraline' => 'You are Coraline, the orchestration layer of BITCORE. You coordinate research tasks and dispatch to appropriate agents.',
            'hyperion' => 'You are Hyperion, the validation layer of BITCORE. You verify outputs and ensure quality.',
            'swarm' => 'You are a specialized subordinate agent in the BITCORE swarm. Execute your assigned task precisely.',
        ];

        return [
            'role' => 'system',
            'content' => $system_prompts[$agent] ?? $system_prompts['coraline'],
        ];
    }

    /**
     * Build artifact context
     */
    public function build_artifact_context( $artifact_name ) {
        $artifacts = [
            'binary' => 'Use binary logic: true/false decisions only.',
            'ternary' => 'Use ternary logic: yes/no/maybe framework.',
            'quaternary' => 'Use quaternary analysis: four-quadrant evaluation.',
            'cognitive_biases' => 'Check for cognitive biases in the reasoning.',
        ];

        return $artifacts[$artifact_name] ?? '';
    }
}
