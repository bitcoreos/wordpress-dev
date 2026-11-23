<?php
/**
 * Core ElizaOS user lifecycle
 *
 * Extracted verbatim from wp-content/plugins/bitcore-core/classes/core.php (BITCORE 500-LOC refactor); logic unchanged.
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     coraline
 */

declare(strict_types=1);


if (!defined('ABSPATH')) {
    exit;
}


trait BITCORE_Core_Eliza_Trait {

    /**
     * Provision new user with default ElizaOS session on registration.
     *
     * @param int $user_id WordPress user ID of newly registered user
     */
    public function eliza_user_registered(int $user_id): void {
        // Skip if user has no read capability
        if (!user_can($user_id, 'read')) {
            return;
        }

        // Provision once only. Existing active-agent choices must survive re-fired hooks.
        $active_agent = get_user_meta($user_id, 'bitcore_eliza_active_agent', true);
        if (!empty($active_agent)) {
            return;
        }

        [$default_agent, $agent_id] = $this->get_default_eliza_agent_assignment();

        // Set active agent for the new user
        update_user_meta($user_id, 'bitcore_eliza_active_agent', $agent_id);
        update_user_meta($user_id, 'bitcore_eliza_active_agent_name', $default_agent);

        // Credits usage is lazily initialized by Credits_Manager.
    }

    /**
     * Resume user's active ElizaOS agent session on login.
     *
     * @param string $user_login Username
     * @param WP_User $user User object
     */
    public function eliza_user_login(string $user_login, WP_User $user): void {
        $user_id = $user->ID;

        // Check if user has an active agent
        $active_agent = get_user_meta($user_id, 'bitcore_eliza_active_agent', true);

        if (empty($active_agent)) {
            [$default_agent, $agent_id] = $this->get_default_eliza_agent_assignment();

            update_user_meta($user_id, 'bitcore_eliza_active_agent', $agent_id);
            update_user_meta($user_id, 'bitcore_eliza_active_agent_name', $default_agent);
        }

        // Credits usage is lazily initialized by Credits_Manager.
    }

    /**
     * Provision subscription-derived agent quota after verified purchase.
     *
     * This method is deliberately provider-agnostic. Checkout, WooCommerce,
     * PMPro, EDD, or x402 adapters should call `do_action(
     * 'bitcore_subscription_purchase', $user_id, $tier, $metadata )` only
     * after their own payment verification succeeds.
     *
     * @param array<string,mixed> $metadata Purchase metadata sanitized by Credits_Manager.
     */
    public function provision_subscription_purchase(int $user_id, string $tier, array $metadata = []): bool {
        require_once BITCORE_PATH . 'infrastructure/class-credits.php';

        if (!class_exists('\BITCORE\Infrastructure\Credits_Manager')) {
            return false;
        }

        $credits = new \BITCORE\Infrastructure\Credits_Manager();
        return $credits->provision_subscription_purchase($user_id, $tier, $metadata);
    }

    /**
     * Return a normalized default ElizaOS agent assignment.
     *
     * @return array{0:string,1:string} Tuple of agent slug and agent UUID.
     */
    private function get_default_eliza_agent_assignment(): array {
        $default_agent = (string) get_option('bitcore_eliza_default_agent', 'aria');
        $agent_map = [
            'aria'  => (string) get_option('bitcore_eliza_agent_aria', 'eac677dd-94c4-02ab-bfea-bc554e52b2a3'),
            'forge' => (string) get_option('bitcore_eliza_agent_forge', '3ab11b75-ca36-0cee-a3d7-7bfcab3c27cd'),
            'muse'  => (string) get_option('bitcore_eliza_agent_muse', 'cf0dee2b-9fc6-041b-83a0-90a644d26362'),
        ];

        if (!array_key_exists($default_agent, $agent_map)) {
            $default_agent = 'aria';
        }

        return [$default_agent, $agent_map[$default_agent]];
    }


}
