<?php
/**
 * Time log table handler
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Time_Log_Table
 */
class Arkidevs_Support_Time_Log_Table {

    /**
     * Get table name
     *
     * @return string
     */
    public static function get_table_name() {
        return Arkidevs_Support_DB::get_table_name( 'time_logs' );
    }

    /**
     * Check if table exists.
     *
     * @return bool
     */
    public static function table_exists() {
        global $wpdb;
        $table = self::get_table_name();

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            )
        );

        return ! empty( $exists );
    }

    /**
     * Insert time log
     *
     * @param array $data Time log data
     * @return int|false Time log ID on success, false on failure
     */
    public static function insert( $data ) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'ticket_id'  => 0,
            'agent_id'   => 0,
            'customer_id' => 0,
            'start_time' => current_time( 'mysql' ),
            'end_time'   => null,
            'duration'   => 0.00,
            'description' => '',
        );

        $data = wp_parse_args( $data, $defaults );

        $result = $wpdb->insert(
            $table,
            $data,
            array( '%d', '%d', '%d', '%s', '%s', '%f', '%s' )
        );

        if ( $result ) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get time log by ID
     *
     * @param int $log_id Time log ID
     * @return object|null
     */
    public static function get_by_id( $log_id ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $log_id
            )
        );
    }

    /**
     * Update time log
     *
     * @param int   $log_id Time log ID
     * @param array $data Data to update
     * @return bool
     */
    public static function update( $log_id, $data ) {
        global $wpdb;
        $table = self::get_table_name();

        $result = $wpdb->update(
            $table,
            $data,
            array( 'id' => $log_id ),
            null,
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Get time logs by ticket
     *
     * @param int $ticket_id Ticket ID
     * @return array
     */
    public static function get_by_ticket( $ticket_id ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE ticket_id = %d ORDER BY created_at DESC",
                $ticket_id
            )
        );
    }

    /**
     * Get time logs by customer
     *
     * @param int $customer_id Customer ID
     * @return array
     */
    public static function get_by_customer( $customer_id ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE customer_id = %d ORDER BY created_at DESC",
                $customer_id
            )
        );
    }

    /**
     * Get active time log for ticket and agent
     *
     * @param int $ticket_id Ticket ID
     * @param int $agent_id Agent ID
     * @return object|null
     */
    public static function get_active_log( $ticket_id, $agent_id ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE ticket_id = %d AND agent_id = %d AND end_time IS NULL ORDER BY start_time DESC LIMIT 1",
                $ticket_id,
                $agent_id
            )
        );
    }

    /**
     * Check if ticket has any active timer
     *
     * @param int $ticket_id Ticket ID
     * @return object|null Active timer log or null
     */
    public static function has_active_timer( $ticket_id ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE ticket_id = %d AND end_time IS NULL ORDER BY start_time DESC LIMIT 1",
                $ticket_id
            )
        );
    }

    /**
     * Get total hours logged for a customer (includes active timers)
     *
     * @param int $customer_id Customer ID
     * @return float
     */
    public static function get_total_hours_by_customer( $customer_id ) {
        global $wpdb;
        $table = self::get_table_name();

        // Sum completed logs.
        $completed = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(duration), 0) FROM {$table} WHERE customer_id = %d AND end_time IS NOT NULL",
                $customer_id
            )
        );

        $completed = floatval( $completed );

        // Add active logs durations.
        $active_logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT start_time FROM {$table} WHERE customer_id = %d AND end_time IS NULL",
                $customer_id
            )
        );

        $active_hours = 0.0;
        if ( ! empty( $active_logs ) ) {
            $now = current_time( 'timestamp' );
            foreach ( $active_logs as $log ) {
                $start = strtotime( $log->start_time );
                if ( $start ) {
                    $active_hours += ( $now - $start ) / 3600;
                }
            }
        }

        return round( $completed + $active_hours, 2 );
    }
}


