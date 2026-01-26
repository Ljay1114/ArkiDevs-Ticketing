<?php
/**
 * Agent dashboard class
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Agent_Dashboard
 */
class Arkidevs_Support_Agent_Dashboard {

    /**
     * Instance
     *
     * @var Arkidevs_Support_Agent_Dashboard
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Arkidevs_Support_Agent_Dashboard
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
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        $capability = Arkidevs_Support_Security::user_can( 'manage_tickets' ) ? 'read' : 'manage_options';

        add_menu_page(
            __( 'Agent Dashboard', 'arkidevs-support' ),
            __( 'Tickets', 'arkidevs-support' ),
            $capability,
            'arkidevs-support-agent',
            array( $this, 'render_dashboard' ),
            'dashicons-tickets-alt',
            31
        );

        // Add "My Tickets" submenu
        add_submenu_page(
            'arkidevs-support-agent',
            __( 'My Tickets', 'arkidevs-support' ),
            __( 'My Tickets', 'arkidevs-support' ),
            $capability,
            'arkidevs-support-my-tickets',
            array( $this, 'render_my_tickets' )
        );
    }

    /**
     * Render agent dashboard
     */
    public function render_dashboard() {
        if ( ! Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'arkidevs-support' ) );
        }

        // Get ticket ID from query string if viewing a specific ticket
        $ticket_id = isset( $_GET['ticket_id'] ) ? intval( $_GET['ticket_id'] ) : 0;

        if ( $ticket_id ) {
            include ARKIDEVS_SUPPORT_PLUGIN_DIR . 'templates/agent/ticket-view.php';
        } else {
            include ARKIDEVS_SUPPORT_PLUGIN_DIR . 'templates/agent/dashboard.php';
        }
    }

    /**
     * Render My Tickets page
     */
    public function render_my_tickets() {
        if ( ! Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'arkidevs-support' ) );
        }

        // Get ticket ID from query string if viewing a specific ticket
        $ticket_id = isset( $_GET['ticket_id'] ) ? intval( $_GET['ticket_id'] ) : 0;

        if ( $ticket_id ) {
            include ARKIDEVS_SUPPORT_PLUGIN_DIR . 'templates/agent/ticket-view.php';
        } else {
            include ARKIDEVS_SUPPORT_PLUGIN_DIR . 'templates/agent/my-tickets.php';
        }
    }
}


