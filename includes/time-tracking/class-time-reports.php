<?php
/**
 * Time reports class
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Time_Reports
 */
class Arkidevs_Support_Time_Reports {

    /**
     * Get time report for agent
     *
     * @param int    $agent_id Agent ID
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     * @return array
     */
    public static function get_agent_report( $agent_id, $start_date = null, $end_date = null ) {
        global $wpdb;
        $table = Arkidevs_Support_Time_Log_Table::get_table_name();

        $where = array( 'agent_id = %d' );
        $values = array( $agent_id );

        if ( $start_date ) {
            $where[] = 'start_time >= %s';
            $values[] = $start_date . ' 00:00:00';
        }

        if ( $end_date ) {
            $where[] = 'start_time <= %s';
            $values[] = $end_date . ' 23:59:59';
        }

        $where_clause = implode( ' AND ', $where );

        $query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY start_time DESC";

        if ( ! empty( $values ) ) {
            $logs = $wpdb->get_results( $wpdb->prepare( $query, $values ) );
        } else {
            $logs = $wpdb->get_results( $query );
        }

        $total_hours = 0.00;
        foreach ( $logs as $log ) {
            $total_hours += floatval( $log->duration );
        }

        return array(
            'logs'        => $logs,
            'total_hours' => round( $total_hours, 2 ),
            'total_logs'  => count( $logs ),
        );
    }

    /**
     * Get time report for customer
     *
     * @param int    $customer_id Customer ID
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     * @return array
     */
    public static function get_customer_report( $customer_id, $start_date = null, $end_date = null ) {
        global $wpdb;
        $table = Arkidevs_Support_Time_Log_Table::get_table_name();

        $where = array( 'customer_id = %d' );
        $values = array( $customer_id );

        if ( $start_date ) {
            $where[] = 'start_time >= %s';
            $values[] = $start_date . ' 00:00:00';
        }

        if ( $end_date ) {
            $where[] = 'start_time <= %s';
            $values[] = $end_date . ' 23:59:59';
        }

        $where_clause = implode( ' AND ', $where );

        $query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY start_time DESC";

        if ( ! empty( $values ) ) {
            $logs = $wpdb->get_results( $wpdb->prepare( $query, $values ) );
        } else {
            $logs = $wpdb->get_results( $query );
        }

        $total_hours = 0.00;
        foreach ( $logs as $log ) {
            $total_hours += floatval( $log->duration );
        }

        return array(
            'logs'        => $logs,
            'total_hours' => round( $total_hours, 2 ),
            'total_logs'  => count( $logs ),
        );
    }
}


