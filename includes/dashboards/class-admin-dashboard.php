<?php
/**
 * Admin dashboard class
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Admin_Dashboard
 */
class Arkidevs_Support_Admin_Dashboard {

    /**
     * Instance
     *
     * @var Arkidevs_Support_Admin_Dashboard
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Arkidevs_Support_Admin_Dashboard
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
        add_menu_page(
            __( 'Arkidevs Support', 'arkidevs-support' ),
            __( 'Support', 'arkidevs-support' ),
            'manage_options',
            'arkidevs-support-admin',
            array( $this, 'render_dashboard' ),
            'dashicons-tickets-alt',
            30
        );
    }

    /**
     * Render admin dashboard
     */
    public function render_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'arkidevs-support' ) );
        }

        // Get ticket ID from query string if viewing a specific ticket
        $ticket_id = isset( $_GET['ticket_id'] ) ? intval( $_GET['ticket_id'] ) : 0;

        if ( $ticket_id ) {
            // Use the same ticket view template as agents
            include ARKIDEVS_SUPPORT_PLUGIN_DIR . 'templates/agent/ticket-view.php';
        } else {
            include ARKIDEVS_SUPPORT_PLUGIN_DIR . 'templates/admin/dashboard.php';
        }
    }
}


