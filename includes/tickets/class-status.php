<?php
/**
 * Ticket status handler
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Status
 */
class Arkidevs_Support_Status {

    /**
     * Get all available statuses
     *
     * @return array
     */
    public static function get_statuses() {
        return array(
            'open'        => __( 'Open', 'arkidevs-support' ),
            'in_progress' => __( 'In Progress', 'arkidevs-support' ),
            'pending'     => __( 'Pending', 'arkidevs-support' ),
            'closed'      => __( 'Closed', 'arkidevs-support' ),
            'resolved'    => __( 'Resolved', 'arkidevs-support' ),
            // Archived tickets are kept for history but normally hidden from main queues.
            'archived'    => __( 'Archived', 'arkidevs-support' ),
            // Merged tickets were consolidated into another ticket and should be hidden from main queues.
            'merged'      => __( 'Merged', 'arkidevs-support' ),
        );
    }

    /**
     * Get status label
     *
     * @param string $status Status key
     * @return string
     */
    public static function get_status_label( $status ) {
        $statuses = self::get_statuses();
        return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
    }

    /**
     * Validate status
     *
     * @param string $status Status to validate
     * @return bool
     */
    public static function is_valid( $status ) {
        $statuses = self::get_statuses();
        return isset( $statuses[ $status ] );
    }

    /**
     * Get status badge class
     *
     * @param string $status Status key
     * @return string
     */
    public static function get_status_class( $status ) {
        $classes = array(
            'open'        => 'status-open',
            'in_progress' => 'status-in-progress',
            'pending'     => 'status-pending',
            'closed'      => 'status-closed',
            'resolved'    => 'status-resolved',
            'archived'    => 'status-archived',
            'merged'      => 'status-merged',
        );

        return isset( $classes[ $status ] ) ? $classes[ $status ] : 'status-default';
    }
}


