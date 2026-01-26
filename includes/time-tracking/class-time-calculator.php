<?php
/**
 * Time calculation utilities
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Time_Calculator
 */
class Arkidevs_Support_Time_Calculator {

    /**
     * Calculate total time spent on ticket
     *
     * @param int $ticket_id Ticket ID
     * @return float Total hours
     */
    public static function calculate_ticket_time( $ticket_id ) {
        $logs = Arkidevs_Support_Time_Log_Table::get_by_ticket( $ticket_id );
        
        $total_hours = 0.00;
        foreach ( $logs as $log ) {
            if ( $log->duration ) {
                $total_hours += floatval( $log->duration );
            } elseif ( $log->end_time === null ) {
                // Active timer - calculate current duration
                $start_time = strtotime( $log->start_time );
                $current_time = current_time( 'timestamp' );
                $total_hours += ( $current_time - $start_time ) / 3600;
            }
        }

        return round( $total_hours, 2 );
    }

    /**
     * Calculate total time spent by customer
     *
     * @param int $customer_id Customer ID
     * @return float Total hours
     */
    public static function calculate_customer_time( $customer_id ) {
        // Use time logs as the source of truth so historical logs reflect immediately.
        return floatval( Arkidevs_Support_Time_Log_Table::get_total_hours_by_customer( $customer_id ) );
    }

    /**
     * Get customer hours summary
     *
     * @param int $customer_id Customer ID
     * @return array
     */
    public static function get_customer_hours_summary( $customer_id ) {
        $customer_hours = Arkidevs_Support_Customer_Hours_Table::get_by_customer( $customer_id );

        if ( ! $customer_hours ) {
            return array(
                'allocated' => 0.00,
                'spent'     => 0.00,
                'remaining' => 0.00,
            );
        }

        $allocated = floatval( $customer_hours->hours_allocated );
        $spent     = self::calculate_customer_time( $customer_id );
        $remaining = $allocated - $spent;

        return array(
            'allocated' => $allocated,
            'spent'     => round( $spent, 2 ),
            'remaining' => round( $remaining, 2 ),
        );
    }
}


