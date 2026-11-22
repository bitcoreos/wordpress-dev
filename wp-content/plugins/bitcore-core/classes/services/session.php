<?php
declare(strict_types=1);
/**
 * BITCORE_Services_Session class for Bitcore
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
class BITCORE_Services_Session {
    private $core;

    public function __construct( $core ) {
        $this->core = $core;

        if ( ! session_id() && ! headers_sent() ) {
            session_start();
        }
    }

    /**
     * Get session value
     */
    public function get( $key, $default = null ) {
        return isset( $_SESSION['bitcore_' . $key] ) ? $_SESSION['bitcore_' . $key] : $default;
    }

    /**
     * Set session value
     */
    public function set( $key, $value ) {
        $_SESSION['bitcore_' . $key] = $value;
    }

    /**
     * Get or create conversation ID
     */
    public function get_conversation_id() {
        $id = $this->get( 'conversation_id' );

        if ( ! $id ) {
            $id = 'conv_' . time() . '_' . wp_rand( 1000, 9999 );
            $this->set( 'conversation_id', $id );
        }

        return $id;
    }

    /**
     * Can start session
     */
    public function can_start_session() {
        return ! headers_sent();
    }
}
