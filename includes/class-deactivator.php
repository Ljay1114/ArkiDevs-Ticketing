<?php
/**
 * Plugin deactivation handler
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Deactivator
 */
class Arkidevs_Support_Deactivator {

    /**
     * Deactivate plugin
     */
    public static function deactivate() {
        // Clear escalation cron job
        $timestamp = wp_next_scheduled( 'arkidevs_check_escalations' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'arkidevs_check_escalations' );
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}


