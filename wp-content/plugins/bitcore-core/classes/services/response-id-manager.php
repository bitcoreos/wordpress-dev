<?php
declare(strict_types=1);
/**
 * BITCORE_Services_Response_ID_Manager class for Bitcore
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
class BITCORE_Services_Response_ID_Manager {
    private $core;

    public function __construct( $core ) {
        $this->core = $core;
    }

    /**
     * Generate unique response ID
     */
    public function generate() {
        return 'bitcore_' . uniqid() . '_' . wp_rand( 1000, 9999 );
    }

    /**
     * Store response ID mapping
     */
    public function store( $response_id, $data ) {
        update_option( 'bitcore_response_' . $response_id, $data, false );
    }

    /**
     * Retrieve response data
     */
    public function retrieve( $response_id ) {
        return get_option( 'bitcore_response_' . $response_id, null );
    }
}
