<?php
/**
 * BITCORE_Encrypted_Options implementation
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
 * BITCORE Encrypted Options Handler
 * Pattern: Encrypted Options Storage
 * Source: Site Secrets, AI Services templates
 * 
 * Provides transparent encryption/decryption for sensitive WordPress options
 * using WordPress salts for key derivation. Supports key rotation gracefully.
 *
 * @package BITCORE
 * @since 1.0.0
 */

class BITCORE_Encrypted_Options {
    /**
     * Option prefix for encrypted values
     */
    const OPTION_PREFIX = 'bitcore_enc_';

    /**
     * Meta key for storing key rotation version
     */
    const KEY_VERSION_META = 'bitcore_key_version';

    /**
     * Current encryption version
     */
    private $current_key_version = 1;

    /**
     * Constructor
     */
    public function __construct() {
        // Check for key rotation on init
        add_action( 'init', [ $this, 'check_key_rotation' ], 1 );
    }

    /**
     * Get decrypted option value
     * 
     * @param string $key       Option key (without prefix)
     * @param mixed  $default   Default value if not found
     * @return mixed Decrypted value or default
     */
    public function get_encrypted_option( $key, $default = false ) {
        $encrypted = get_option( self::OPTION_PREFIX . $key, false );
        
        if ( false === $encrypted ) {
            return $default;
        }

        $decrypted = $this->decrypt( $encrypted );
        
        if ( false === $decrypted ) {
            // Decryption failed - possible key rotation
            // Try with legacy key if available
            $decrypted = $this->decrypt_legacy( $encrypted );
            
            if ( false === $decrypted ) {
                return $default;
            }
            
            // Re-encrypt with current key
            $this->update_encrypted_option( $key, $decrypted );
        }

        return maybe_unserialize( $decrypted );
    }

    /**
     * Update option with encryption
     * 
     * @param string $key    Option key (without prefix)
     * @param mixed  $value  Value to encrypt and store
     * @return bool Success
     */
    public function update_encrypted_option( $key, $value ) {
        $serialized = maybe_serialize( $value );
        $encrypted = $this->encrypt( $serialized );
        
        if ( false === $encrypted ) {
            return false;
        }

        return update_option( self::OPTION_PREFIX . $key, $encrypted );
    }

    /**
     * Delete encrypted option
     * 
     * @param string $key Option key (without prefix)
     * @return bool Success
     */
    public function delete_encrypted_option( $key ) {
        return delete_option( self::OPTION_PREFIX . $key );
    }

    /**
     * Check if encrypted option exists
     * 
     * @param string $key Option key (without prefix)
     * @return bool
     */
    public function has_encrypted_option( $key ) {
        return false !== get_option( self::OPTION_PREFIX . $key, false );
    }

    /**
     * Encrypt data using AES-256-GCM
     * 
     * @param string $data Data to encrypt
     * @return string|false Base64 encoded encrypted data or false on failure
     */
    private function encrypt( $data ) {
        $key = $this->get_encryption_key();
        
        if ( empty( $key ) ) {
            return false;
        }

        $iv = random_bytes( 16 );
        $tag = '';
        
        // Use AES-256-GCM if available (PHP 7.1+)
        if ( function_exists( 'openssl_encrypt' ) && defined( 'OPENSSL_RAW_DATA' ) ) {
            $encrypted = openssl_encrypt(
                $data,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                '',
                16
            );
            
            if ( false === $encrypted ) {
                return false;
            }
            
            // Format: version:base64(iv+tag+encrypted)
            return $this->current_key_version . ':' . base64_encode( $iv . $tag . $encrypted );
        }
        
        // Fallback to simple XOR with hash (less secure, for compatibility)
        $encrypted = $data ^ hash_hmac( 'sha256', $iv, $key, true );
        return $this->current_key_version . ':xor:' . base64_encode( $iv . $encrypted );
    }

    /**
     * Decrypt data
     * 
     * @param string $data Encrypted data
     * @return string|false Decrypted data or false on failure
     */
    private function decrypt( $data ) {
        // Extract version
        $parts = explode( ':', $data, 2 );
        if ( count( $parts ) !== 2 ) {
            return false;
        }
        
        $version = (int) $parts[0];
        $payload = $parts[1];
        
        // Get key for this version
        $key = $this->get_encryption_key( $version );
        
        if ( empty( $key ) ) {
            return false;
        }
        
        // Check for XOR fallback
        if ( strpos( $payload, 'xor:' ) === 0 ) {
            $payload = substr( $payload, 4 );
            $decoded = base64_decode( $payload, true );
            
            if ( false === $decoded || strlen( $decoded ) < 16 ) {
                return false;
            }
            
            $iv = substr( $decoded, 0, 16 );
            $encrypted = substr( $decoded, 16 );
            
            return $encrypted ^ hash_hmac( 'sha256', $iv, $key, true );
        }
        
        // AES-256-GCM decryption
        $decoded = base64_decode( $payload, true );
        
        if ( false === $decoded || strlen( $decoded ) < 32 ) {
            return false;
        }
        
        $iv = substr( $decoded, 0, 16 );
        $tag = substr( $decoded, 16, 16 );
        $encrypted = substr( $decoded, 32 );
        
        $decrypted = openssl_decrypt(
            $encrypted,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        return $decrypted;
    }

    /**
     * Legacy decryption for key rotation
     * 
     * @param string $data Encrypted data
     * @return string|false Decrypted data or false
     */
    private function decrypt_legacy( $data ) {
        // Try previous key versions
        for ( $v = $this->current_key_version - 1; $v >= 1; $v-- ) {
            $key = $this->get_encryption_key( $v );
            if ( ! empty( $key ) ) {
                // Attempt decryption
                $parts = explode( ':', $data, 2 );
                if ( count( $parts ) === 2 && (int) $parts[0] === $v ) {
                    // Temporarily override version for decryption
                    $original_version = $this->current_key_version;
                    $this->current_key_version = $v;
                    $result = $this->decrypt( $data );
                    $this->current_key_version = $original_version;
                    
                    if ( false !== $result ) {
                        return $result;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Get encryption key from WordPress salts
     * 
     * @param int $version Key version
     * @return string Encryption key
     */
    private function get_encryption_key( $version = null ) {
        $version = $version ?: $this->current_key_version;
        
        // Combine AUTH_KEY and SECURE_AUTH_KEY for 64-byte key
        $key_material = wp_salt( 'auth' ) . wp_salt( 'secure_auth' );
        
        // Derive version-specific key using HKDF-like approach
        return hash_hmac( 'sha256', $key_material, 'bitcore-v' . $version, true );
    }

    /**
     * Check for key rotation and update stored version
     */
    public function check_key_rotation() {
        $stored_version = (int) get_site_option( self::KEY_VERSION_META, 1 );
        
        if ( $stored_version !== $this->current_key_version ) {
            // Key rotation detected - could trigger re-encryption here
            // For now, just update the stored version
            update_site_option( self::KEY_VERSION_META, $this->current_key_version );
            
            /**
             * Fired when key rotation is detected
             * 
             * @param int $old_version Previous key version
             * @param int $new_version Current key version
             */
            do_action( 'bitcore_key_rotation_detected', $stored_version, $this->current_key_version );
        }
    }

    /**
     * Re-encrypt all options with current key (manual rotation)
     * 
     * @return array Results with counts
     */
    public function rotate_keys() {
        global $wpdb;
        
        $results = [
            'rotated' => 0,
            'failed'  => 0,
        ];
        
        // Get all encrypted options
        $options = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( self::OPTION_PREFIX ) . '%'
            )
        );
        
        foreach ( $options as $option ) {
            $key = str_replace( self::OPTION_PREFIX, '', $option->option_name );
            $decrypted = $this->decrypt( $option->option_value );
            
            if ( false !== $decrypted ) {
                // Re-encrypt with current key
                $re_encrypted = $this->encrypt( $decrypted );
                
                if ( false !== $re_encrypted ) {
                    update_option( $option->option_name, $re_encrypted );
                    $results['rotated']++;
                } else {
                    $results['failed']++;
                }
            } else {
                $results['failed']++;
            }
        }
        
        return $results;
    }
}
