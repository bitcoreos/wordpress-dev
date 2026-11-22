<?php
declare(strict_types=1);
/**
 * BITCORE_Services_Usage_Stats class for Bitcore
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
class BITCORE_Services_Usage_Stats {
    private $core;

    public function __construct( $core ) {
        $this->core = $core;
    }

    /**
     * Log API call
     */
    public function log_call( $endpoint, $tokens = 0, $cost = 0 ) {
        $stats = get_option( 'bitcore_usage_stats', [] );

        $today = date( 'Y-m-d' );

        if ( ! isset( $stats[$today] ) ) {
            $stats[$today] = [
                'calls' => 0,
                'tokens' => 0,
                'cost' => 0,
            ];
        }

        $stats[$today]['calls']++;
        $stats[$today]['tokens'] += $tokens;
        $stats[$today]['cost'] += $cost;

        update_option( 'bitcore_usage_stats', $stats );
    }

    /**
     * Get usage for date range
     */
    public function get_usage( $days = 30 ) {
        $stats = get_option( 'bitcore_usage_stats', [] );
        $result = [];

        for ( $i = 0; $i < $days; $i++ ) {
            $date = date( 'Y-m-d', strtotime( "-$i days" ) );
            $result[$date] = $stats[$date] ?? [
                'calls' => 0,
                'tokens' => 0,
                'cost' => 0,
            ];
        }

        return $result;
    }

    /**
     * Get total credits used
     */
    public function get_total_credits() {
        $stats = get_option( 'bitcore_usage_stats', [] );
        $total = 0;

        foreach ( $stats as $day ) {
            $total += $day['cost'] ?? 0;
        }

        return $total;
    }
}
