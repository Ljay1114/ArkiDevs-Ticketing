<?php
/**
 * Database handler class
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_DB
 */
class Arkidevs_Support_DB {

    /**
     * Get database instance
     *
     * @global wpdb $wpdb
     * @return wpdb
     */
    public static function get_db() {
        global $wpdb;
        return $wpdb;
    }

    /**
     * Get table name with prefix
     *
     * @param string $table Table name without prefix
     * @return string
     */
    public static function get_table_name( $table ) {
        global $wpdb;
        return $wpdb->prefix . 'arkidevs_' . $table;
    }

    /**
     * Prepare query with table name
     *
     * @param string $query SQL query with %s placeholder for table name
     * @param string $table Table name without prefix
     * @return string
     */
    public static function prepare_table_query( $query, $table ) {
        return sprintf( $query, self::get_table_name( $table ) );
    }
}


