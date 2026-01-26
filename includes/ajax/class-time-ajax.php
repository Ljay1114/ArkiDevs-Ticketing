<?php
/**
 * Time tracking AJAX handler
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Time_Ajax
 */
class Arkidevs_Support_Time_Ajax {

    /**
     * Instance
     *
     * @var Arkidevs_Support_Time_Ajax
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Arkidevs_Support_Time_Ajax
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'wp_ajax_arkidevs_start_timer', array( $this, 'start_timer' ) );
        add_action( 'wp_ajax_arkidevs_stop_timer', array( $this, 'stop_timer' ) );
        add_action( 'wp_ajax_arkidevs_allocate_hours', array( $this, 'allocate_hours' ) );
    }

    /**
     * Start timer
     */
    public function start_timer() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        if ( ! Arkidevs_Support_Security::user_can( 'track_time' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        $ticket_id = intval( $_POST['ticket_id'] ?? 0 );
        if ( ! $ticket_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid ticket ID.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/time-tracking/class-timer.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/time-tracking/class-time-calculator.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';
        
        $result = Arkidevs_Support_Timer::start( $ticket_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // Get current time spent (will include active timer)
        $time_spent = Arkidevs_Support_Time_Calculator::calculate_ticket_time( $ticket_id );
        $formatted_time = Arkidevs_Support_Utils::format_duration( $time_spent );

        wp_send_json_success( array(
            'message' => __( 'Timer started.', 'arkidevs-support' ),
            'log_id'  => $result,
            'time_spent' => $formatted_time,
        ) );
    }

    /**
     * Stop timer
     */
    public function stop_timer() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        if ( ! Arkidevs_Support_Security::user_can( 'track_time' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        $ticket_id = intval( $_POST['ticket_id'] ?? 0 );
        if ( ! $ticket_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid ticket ID.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/time-tracking/class-timer.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/time-tracking/class-time-calculator.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';
        
        $result = Arkidevs_Support_Timer::stop( $ticket_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // Calculate updated time spent
        $time_spent = Arkidevs_Support_Time_Calculator::calculate_ticket_time( $ticket_id );
        $formatted_time = Arkidevs_Support_Utils::format_duration( $time_spent );

        wp_send_json_success( array( 
            'message' => __( 'Timer stopped.', 'arkidevs-support' ),
            'time_spent' => $formatted_time,
        ) );
    }

    /**
     * Allocate hours to customer
     */
    public function allocate_hours() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        $customer_id = intval( $_POST['customer_id'] ?? 0 );
        $hours = floatval( $_POST['hours'] ?? 0 );

        if ( ! $customer_id || $hours <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-customer-hours-table.php';
        $result = Arkidevs_Support_Customer_Hours_Table::allocate_hours( $customer_id, $hours );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'Failed to allocate hours.', 'arkidevs-support' ) ) );
        }

        wp_send_json_success( array( 'message' => __( 'Hours allocated successfully.', 'arkidevs-support' ) ) );
    }
}


