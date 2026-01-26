<?php
/**
 * Ticket priority handler
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Priority
 */
class Arkidevs_Support_Priority {

    /**
     * Get all available priorities
     *
     * @return array
     */
    public static function get_priorities() {
        return array(
            'low'      => __( 'Low', 'arkidevs-support' ),
            'medium'   => __( 'Medium', 'arkidevs-support' ),
            'high'     => __( 'High', 'arkidevs-support' ),
            'critical' => __( 'Critical', 'arkidevs-support' ),
        );
    }

    /**
     * Get priority label
     *
     * @param string $priority Priority key
     * @return string
     */
    public static function get_priority_label( $priority ) {
        $priorities = self::get_priorities();
        return isset( $priorities[ $priority ] ) ? $priorities[ $priority ] : $priority;
    }

    /**
     * Validate priority
     *
     * @param string $priority Priority to validate
     * @return bool
     */
    public static function is_valid( $priority ) {
        $priorities = self::get_priorities();
        return isset( $priorities[ $priority ] );
    }

    /**
     * Get priority badge class
     *
     * @param string $priority Priority key
     * @return string
     */
    public static function get_priority_class( $priority ) {
        $classes = array(
            'low'      => 'priority-low',
            'medium'   => 'priority-medium',
            'high'     => 'priority-high',
            'critical' => 'priority-critical',
        );

        return isset( $classes[ $priority ] ) ? $classes[ $priority ] : 'priority-default';
    }

    /**
     * Get priority weight (for sorting)
     *
     * @param string $priority Priority key
     * @return int
     */
    public static function get_priority_weight( $priority ) {
        $weights = array(
            'low'      => 1,
            'medium'   => 2,
            'high'     => 3,
            'critical' => 4,
        );

        return isset( $weights[ $priority ] ) ? $weights[ $priority ] : 0;
    }
}


