<?php
/**
 * Time tracking timer class
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Timer
 */
class Arkidevs_Support_Timer {

    /**
     * Start time tracking for a ticket
     *
     * @param int $ticket_id Ticket ID
     * @param int $agent_id Agent ID (optional)
     * @return int|WP_Error Time log ID on success, WP_Error on failure
     */
    public static function start( $ticket_id, $agent_id = null ) {
        if ( null === $agent_id ) {
            $agent_id = get_current_user_id();
        }

        // Check if user can track time
        if ( ! Arkidevs_Support_Security::user_can( 'track_time', $agent_id ) ) {
            return new WP_Error( 'permission_denied', __( 'You do not have permission to track time.', 'arkidevs-support' ) );
        }

        // Get ticket
        $ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );
        if ( ! $ticket ) {
            return new WP_Error( 'invalid_ticket', __( 'Invalid ticket.', 'arkidevs-support' ) );
        }

        // Check if there's already an active timer for this ticket and agent
        $active_log = Arkidevs_Support_Time_Log_Table::get_active_log( $ticket_id, $agent_id );
        if ( $active_log ) {
            return new WP_Error( 'timer_already_running', __( 'Timer is already running for this ticket.', 'arkidevs-support' ) );
        }

        // Create time log entry
        $log_id = Arkidevs_Support_Time_Log_Table::insert( array(
            'ticket_id'   => $ticket_id,
            'agent_id'    => $agent_id,
            'customer_id' => $ticket->customer_id,
            'start_time'  => current_time( 'mysql' ),
            'end_time'    => null,
        ) );

        if ( ! $log_id ) {
            return new WP_Error( 'start_failed', __( 'Failed to start timer.', 'arkidevs-support' ) );
        }

        return $log_id;
    }

    /**
     * Stop time tracking for a ticket
     *
     * @param int $ticket_id Ticket ID
     * @param int $agent_id Agent ID (optional)
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function stop( $ticket_id, $agent_id = null ) {
        if ( null === $agent_id ) {
            $agent_id = get_current_user_id();
        }

        // Get active log
        $active_log = Arkidevs_Support_Time_Log_Table::get_active_log( $ticket_id, $agent_id );
        if ( ! $active_log ) {
            return new WP_Error( 'no_active_timer', __( 'No active timer found for this ticket.', 'arkidevs-support' ) );
        }

        // Calculate duration
        $start_time = strtotime( $active_log->start_time );
        $end_time = current_time( 'timestamp' );
        $duration_hours = ( $end_time - $start_time ) / 3600; // Convert to hours

        // Update time log
        $result = Arkidevs_Support_Time_Log_Table::update( $active_log->id, array(
            'end_time' => current_time( 'mysql' ),
            'duration' => round( $duration_hours, 2 ),
        ) );

        if ( $result ) {
            // Update customer hours spent
            $customer_hours = Arkidevs_Support_Customer_Hours_Table::get_by_customer( $active_log->customer_id );
            if ( $customer_hours ) {
                Arkidevs_Support_Customer_Hours_Table::add_hours_spent( $active_log->customer_id, $duration_hours );
            }

            return true;
        }

        return new WP_Error( 'stop_failed', __( 'Failed to stop timer.', 'arkidevs-support' ) );
    }

    /**
     * Get active timer for ticket and agent
     *
     * @param int $ticket_id Ticket ID
     * @param int $agent_id Agent ID (optional)
     * @return object|null
     */
    public static function get_active_timer( $ticket_id, $agent_id = null ) {
        if ( null === $agent_id ) {
            $agent_id = get_current_user_id();
        }

        return Arkidevs_Support_Time_Log_Table::get_active_log( $ticket_id, $agent_id );
    }
}


