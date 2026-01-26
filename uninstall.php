<?php
/**
 * Uninstall script for Arkidevs Support plugin
 *
 * @package Arkidevs_Support
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom database tables
$tables = array(
    $wpdb->prefix . 'arkidevs_tickets',
    $wpdb->prefix . 'arkidevs_time_logs',
    $wpdb->prefix . 'arkidevs_customer_hours',
    $wpdb->prefix . 'arkidevs_ticket_attachments',
    $wpdb->prefix . 'arkidevs_ticket_replies',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Delete plugin options
delete_option( 'arkidevs_support_version' );
delete_option( 'arkidevs_support_db_version' );

// Clear any cached data
wp_cache_flush();


