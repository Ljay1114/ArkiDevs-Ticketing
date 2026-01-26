<?php
/**
 * Dashboard AJAX handler
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Dashboard_Ajax
 */
class Arkidevs_Support_Dashboard_Ajax {

    /**
     * Instance
     *
     * @var Arkidevs_Support_Dashboard_Ajax
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Arkidevs_Support_Dashboard_Ajax
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
        add_action( 'wp_ajax_arkidevs_get_tickets', array( $this, 'get_tickets' ) );
        add_action( 'wp_ajax_arkidevs_get_ticket', array( $this, 'get_ticket' ) );
        add_action( 'wp_ajax_arkidevs_get_customer_hours', array( $this, 'get_customer_hours' ) );
    }

    /**
     * Get tickets
     */
    public function get_tickets() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        $user = wp_get_current_user();
        $tickets = array();

        if ( in_array( 'arkidevs_customer', $user->roles, true ) ) {
            // Customer sees only their tickets
            require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-ticket-table.php';
            $tickets = Arkidevs_Support_Ticket_Table::get_by_customer( $user->ID );
        } elseif ( in_array( 'arkidevs_agent', $user->roles, true ) || in_array( 'administrator', $user->roles, true ) ) {
            // Agent/Admin sees all tickets
            $status = sanitize_text_field( $_GET['status'] ?? '' );
            require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-ticket-table.php';
            
            if ( $status ) {
                $tickets = Arkidevs_Support_Ticket_Table::get_by_status( $status );
            } else {
                $tickets = Arkidevs_Support_Ticket_Table::get_all();
            }
        }

        wp_send_json_success( array( 'tickets' => $tickets ) );
    }

    /**
     * Get single ticket
     */
    public function get_ticket() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        $ticket_id = intval( $_GET['ticket_id'] ?? 0 );
        if ( ! $ticket_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid ticket ID.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';

        if ( ! Arkidevs_Support_Utils::user_can_access_ticket( $ticket_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        $ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );
        if ( ! $ticket ) {
            wp_send_json_error( array( 'message' => __( 'Ticket not found.', 'arkidevs-support' ) ) );
        }

        $replies = $ticket->get_replies();

        wp_send_json_success( array(
            'ticket'  => $ticket->data,
            'replies' => $replies,
        ) );
    }

    /**
     * Get customer hours
     */
    public function get_customer_hours() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        $customer_id = intval( $_GET['customer_id'] ?? get_current_user_id() );
        $user = wp_get_current_user();

        // Only allow customers to see their own hours, or admins/agents to see any
        if ( in_array( 'arkidevs_customer', $user->roles, true ) && $customer_id !== $user->ID ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/time-tracking/class-time-calculator.php';
        $hours = Arkidevs_Support_Time_Calculator::get_customer_hours_summary( $customer_id );

        wp_send_json_success( array( 'hours' => $hours ) );
    }
}


