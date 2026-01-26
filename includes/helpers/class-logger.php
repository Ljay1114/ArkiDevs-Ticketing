<?php
/**
 * Logger class
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Logger
 */
class Arkidevs_Support_Logger {

    /**
     * Log message
     *
     * @param string $message Log message
     * @param string $level Log level (info, warning, error)
     */
    public static function log( $message, $level = 'info' ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $log_entry = sprintf(
                '[%s] [%s] %s',
                current_time( 'mysql' ),
                strtoupper( $level ),
                $message
            );

            error_log( $log_entry );
        }
    }

    /**
     * Log error
     *
     * @param string $message Error message
     */
    public static function error( $message ) {
        self::log( $message, 'error' );
    }

    /**
     * Log warning
     *
     * @param string $message Warning message
     */
    public static function warning( $message ) {
        self::log( $message, 'warning' );
    }

    /**
     * Log info
     *
     * @param string $message Info message
     */
    public static function info( $message ) {
        self::log( $message, 'info' );
    }
}


