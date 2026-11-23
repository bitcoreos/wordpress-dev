<?php
declare(strict_types=1);
/**
 * Learning Loop - trait
 *
 * @package BITCORE
 * @since 1.0.2
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Learning_Loop_Schema {
    /**
     * Create database tables on plugin activation
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Episodes table
        $episodes_table = $wpdb->prefix . 'bitcore_learning_episodes';
        $sql_episodes = "CREATE TABLE $episodes_table (
            id varchar(32) NOT NULL,
            org_id varchar(64) NOT NULL DEFAULT 'org_default',
            action_id varchar(64) NOT NULL,
            agent_id varchar(64) NOT NULL,
            action_type varchar(50) NOT NULL,
            status varchar(20),
            outcome_label varchar(50),
            risk_score float DEFAULT 0,
            reversible tinyint(1) DEFAULT 1,
            confidence float DEFAULT 50,
            duration_ms int DEFAULT NULL,
            cost_estimate float DEFAULT 0,
            invalidated_assumptions int DEFAULT 0,
            open_loops int DEFAULT 0,
            recommendation_id varchar(32),
            recommendation_applied tinyint(1) DEFAULT 0,
            score float DEFAULT 0,
            score_breakdown longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_org_action (org_id, action_id),
            KEY idx_org_agent (org_id, agent_id),
            KEY idx_action_type (action_type),
            KEY idx_score (score)
        ) $charset_collate;";

        // Recommendations table
        $recommendations_table = $wpdb->prefix . 'bitcore_learning_recommendations';
        $sql_recommendations = "CREATE TABLE $recommendations_table (
            id varchar(32) NOT NULL,
            org_id varchar(64) NOT NULL DEFAULT 'org_default',
            agent_id varchar(64) NOT NULL,
            action_type varchar(50) NOT NULL,
            confidence float DEFAULT 0,
            sample_size int DEFAULT 0,
            top_sample_size int DEFAULT 0,
            success_rate float DEFAULT 0,
            avg_score float DEFAULT 0,
            hints longtext,
            guidance longtext,
            active tinyint(1) DEFAULT 1,
            computed_at datetime NOT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_org_agent_type (org_id, agent_id, action_type),
            KEY idx_active (active)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_episodes );
        dbDelta( $sql_recommendations );
    }

    /**
     * Generate unique episode ID
     *
     * @return string
     */
    private function generate_id( $prefix = 'lep' ) {
        return $prefix . '_' . substr( wp_generate_uuid4(), 0, 24 );
    }
}
