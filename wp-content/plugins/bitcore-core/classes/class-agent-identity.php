<?php
/**
 * BITCORE_Agent_Identity class for Bitcore
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
class BITCORE_Agent_Identity {
    
    /**
     * Register or update an agent identity
     *
     * @param string $agent_id Unique agent identifier
     * @param string $public_key PEM-formatted public key
     * @param string $algorithm Signature algorithm (default: RSASSA-PKCS1-v1_5)
     * @return bool|int Number of rows affected or false on error
     */
    public static function register_identity($agent_id, $public_key, $algorithm = 'RSASSA-PKCS1-v1_5') {
        global $wpdb;
        $table = $wpdb->prefix . 'bitcore_agent_identities';
        
        // Sanitize inputs
        $agent_id = sanitize_text_field($agent_id);
        $algorithm = sanitize_text_field($algorithm);
        
        return $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (agent_id, public_key, algorithm, created_at, updated_at)
            VALUES (%s, %s, %s, NOW(), NOW())
            ON DUPLICATE KEY UPDATE public_key = VALUES(public_key), algorithm = VALUES(algorithm), updated_at = NOW()",
            $agent_id, $public_key, $algorithm
        ));
    }
    
    /**
     * Get identity for an agent
     *
     * @param string $agent_id Agent identifier
     * @return object|null Identity row or null
     */
    public static function get_identity($agent_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'bitcore_agent_identities';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE agent_id = %s",
            sanitize_text_field($agent_id)
        ));
    }
    
    /**
     * Verify a signature from an agent
     *
     * @param string $agent_id Agent identifier
     * @param string $message Original message
     * @param string $signature Base64-encoded signature
     * @return bool True if signature is valid
     */
    public static function verify_signature($agent_id, $message, $signature) {
        $identity = self::get_identity($agent_id);
        if (!$identity || empty($identity->public_key)) {
            return false;
        }
        
        // Get public key resource
        $pub_key = openssl_pkey_get_public($identity->public_key);
        if (!$pub_key) {
            return false;
        }
        
        // Decode signature from base64
        $signature_decoded = base64_decode($signature);
        if ($signature_decoded === false) {
            return false;
        }
        
        // Verify signature
        $result = openssl_verify($message, $signature_decoded, $pub_key, OPENSSL_ALGO_SHA256);
        
        // Free key resource
        openssl_free_key($pub_key);
        
        return $result === 1;
    }
    
    /**
     * Revoke an agent identity
     *
     * @param string $agent_id Agent identifier
     * @return bool True on success
     */
    public static function revoke_identity($agent_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'bitcore_agent_identities';
        
        return $wpdb->delete(
            $table,
            ['agent_id' => sanitize_text_field($agent_id)],
            ['%s']
        ) !== false;
    }
    
    /**
     * List all registered identities
     *
     * @param int $limit Maximum results
     * @param int $offset Offset for pagination
     * @return array Array of identity objects
     */
    public static function list_identities($limit = 100, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'bitcore_agent_identities';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT agent_id, algorithm, created_at, updated_at FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit, $offset
        ));
    }
    
    /**
     * Generate a new key pair for an agent
     *
     * @return array|false Array with 'private_key' and 'public_key' or false on failure
     */
    public static function generate_key_pair() {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        
        $keypair = openssl_pkey_new($config);
        if (!$keypair) {
            return false;
        }
        
        // Export private key
        openssl_pkey_export($keypair, $private_key);
        
        // Get public key
        $details = openssl_pkey_get_details($keypair);
        $public_key = $details['key'];
        
        return [
            'private_key' => $private_key,
            'public_key' => $public_key,
        ];
    }
}
