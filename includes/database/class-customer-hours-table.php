<?php
/**
 * Customer hours table handler
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Customer_Hours_Table
 */
class Arkidevs_Support_Customer_Hours_Table {

    /**
     * Get table name
     *
     * @return string
     */
    public static function get_table_name() {
        return Arkidevs_Support_DB::get_table_name( 'customer_hours' );
    }

    /**
     * Insert or update customer hours
     *
     * @param int   $customer_id Customer ID
     * @param float $hours_allocated Hours to allocate
     * @return bool
     */
    public static function allocate_hours( $customer_id, $hours_allocated ) {
        global $wpdb;
        $table = self::get_table_name();

        $existing = self::get_by_customer( $customer_id );

        // Recompute spent from logs so remaining is consistent even if hours are allocated after work was logged.
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-time-log-table.php';
        $spent_from_logs = Arkidevs_Support_Time_Log_Table::get_total_hours_by_customer( $customer_id );

        if ( $existing ) {
            // Update existing record
            $new_allocated = $existing->hours_allocated + $hours_allocated;
            $new_remaining = $new_allocated - $spent_from_logs;

            return (bool) $wpdb->update(
                $table,
                array(
                    'hours_allocated' => $new_allocated,
                    'hours_spent'     => $spent_from_logs,
                    'hours_remaining' => $new_remaining,
                ),
                array( 'customer_id' => $customer_id ),
                array( '%f', '%f', '%f' ),
                array( '%d' )
            );
        } else {
            // Insert new record
            return (bool) $wpdb->insert(
                $table,
                array(
                    'customer_id'    => $customer_id,
                    'hours_allocated' => $hours_allocated,
                    'hours_spent'    => $spent_from_logs,
                    'hours_remaining' => ( $hours_allocated - $spent_from_logs ),
                ),
                array( '%d', '%f', '%f', '%f' )
            );
        }
    }

    /**
     * Get customer hours by customer ID
     *
     * @param int $customer_id Customer ID
     * @return object|null
     */
    public static function get_by_customer( $customer_id ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE customer_id = %d",
                $customer_id
            )
        );
    }

    /**
     * Update hours spent
     *
     * @param int   $customer_id Customer ID
     * @param float $hours Hours to add to spent
     * @return bool
     */
    public static function add_hours_spent( $customer_id, $hours ) {
        global $wpdb;
        $table = self::get_table_name();

        $existing = self::get_by_customer( $customer_id );

        if ( ! $existing ) {
            return false;
        }

        $new_spent = $existing->hours_spent + $hours;
        $new_remaining = $existing->hours_allocated - $new_spent;

        return (bool) $wpdb->update(
            $table,
            array(
                'hours_spent'    => $new_spent,
                'hours_remaining' => $new_remaining,
            ),
            array( 'customer_id' => $customer_id ),
            array( '%f', '%f' ),
            array( '%d' )
        );
    }

    /**
     * Update customer hours
     *
     * @param int   $customer_id Customer ID
     * @param array $data Data to update
     * @return bool
     */
    public static function update( $customer_id, $data ) {
        global $wpdb;
        $table = self::get_table_name();

        $result = $wpdb->update(
            $table,
            $data,
            array( 'customer_id' => $customer_id ),
            null,
            array( '%d' )
        );

        return false !== $result;
    }
}


