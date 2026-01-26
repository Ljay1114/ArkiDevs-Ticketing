<?php
/**
 * Customer dashboard class
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Customer_Dashboard
 */
class Arkidevs_Support_Customer_Dashboard {

    /**
     * Instance
     *
     * @var Arkidevs_Support_Customer_Dashboard
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Arkidevs_Support_Customer_Dashboard
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
        add_action( 'init', array( $this, 'register_shortcode' ) );
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ) );
        add_action( 'template_redirect', array( $this, 'handle_public_page' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'admin_notices', array( $this, 'rewrite_rules_notice' ) );
    }

    /**
     * Register shortcode for customer dashboard
     */
    public function register_shortcode() {
        add_shortcode( 'arkidevs_customer_dashboard', array( $this, 'render_dashboard_shortcode' ) );
    }

    /**
     * Add rewrite rules for public support page
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^support/?$',
            'index.php?arkidevs_support_page=1',
            'top'
        );
        
        add_rewrite_rule(
            '^support/ticket/([0-9]+)/?$',
            'index.php?arkidevs_support_page=1&arkidevs_ticket_id=$matches[1]',
            'top'
        );
    }

    /**
     * Flush rewrite rules if needed
     */
    public function maybe_flush_rewrite_rules() {
        $rewrite_rules_flushed = get_option( 'arkidevs_support_rewrite_rules_flushed', false );
        
        if ( ! $rewrite_rules_flushed ) {
            flush_rewrite_rules( false );
            update_option( 'arkidevs_support_rewrite_rules_flushed', true );
        }
    }

    /**
     * Show admin notice if rewrite rules need flushing
     */
    public function rewrite_rules_notice() {
        // Only show to admins
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Check if we're on the support admin page
        $screen = get_current_screen();
        if ( $screen && strpos( $screen->id, 'arkidevs' ) !== false ) {
            $rewrite_rules_flushed = get_option( 'arkidevs_support_rewrite_rules_flushed', false );
            
            if ( ! $rewrite_rules_flushed ) {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>' . esc_html__( 'Arkidevs Support:', 'arkidevs-support' ) . '</strong> ';
                echo esc_html__( 'Please go to Settings → Permalinks and click "Save Changes" to activate the /support/ URL.', 'arkidevs-support' );
                echo '</p>';
                echo '</div>';
            }
        }
    }

    /**
     * Add query vars
     *
     * @param array $vars Query vars
     * @return array
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'arkidevs_support_page';
        $vars[] = 'arkidevs_ticket_id';
        return $vars;
    }

    /**
     * Handle public support page
     */
    public function handle_public_page() {
        $support_page = get_query_var( 'arkidevs_support_page' );
        
        if ( $support_page ) {
            // Set up the page
            global $wp_query;
            $wp_query->is_page = true;
            $wp_query->is_singular = true;
            $wp_query->is_home = false;
            $wp_query->is_archive = false;
            $wp_query->is_category = false;

            // Get ticket ID if viewing a specific ticket
            $ticket_id = intval( get_query_var( 'arkidevs_ticket_id' ) );
            if ( ! $ticket_id && isset( $_GET['ticket_id'] ) ) {
                $ticket_id = intval( $_GET['ticket_id'] );
            }

            // Render the dashboard
            $this->render_public_dashboard( $ticket_id );
            exit;
        }
    }

    /**
     * Render public dashboard
     *
     * @param int $ticket_id Ticket ID (optional)
     */
    private function render_public_dashboard( $ticket_id = 0 ) {
        // Check if user is logged in
        if ( ! is_user_logged_in() ) {
            // Load WordPress header
            get_header();

            echo '<div class="arkidevs-support-public-wrapper">';
            echo '<div class="arkidevs-support-container">';
            echo '<div class="arkidevs-support-login-required">';
            echo '<h2>' . esc_html__( 'Login Required', 'arkidevs-support' ) . '</h2>';
            echo '<p>' . esc_html__( 'Please log in to access your support tickets.', 'arkidevs-support' ) . '</p>';
            echo '<p><a href="' . esc_url( wp_login_url( home_url( '/support/' ) ) ) . '" class="button button-primary">' . esc_html__( 'Log In', 'arkidevs-support' ) . '</a></p>';
            echo '</div>';
            echo '</div>';
            echo '</div>';

            // Load WordPress footer
            get_footer();
            return;
        }

        // Verify user has customer role (or admin/agent for preview)
        $user = wp_get_current_user();
        if ( ! in_array( 'arkidevs_customer', $user->roles, true ) && 
             ! in_array( 'administrator', $user->roles, true ) && 
             ! in_array( 'arkidevs_agent', $user->roles, true ) ) {
            // Load WordPress header
            get_header();

            echo '<div class="arkidevs-support-public-wrapper">';
            echo '<div class="arkidevs-support-container">';
            echo '<div class="arkidevs-support-login-required">';
            echo '<h2>' . esc_html__( 'Access Denied', 'arkidevs-support' ) . '</h2>';
            echo '<p>' . esc_html__( 'You do not have permission to access this page. Please contact an administrator to assign you the appropriate role.', 'arkidevs-support' ) . '</p>';
            echo '</div>';
            echo '</div>';
            echo '</div>';

            // Load WordPress footer
            get_footer();
            return;
        }

        // Load WordPress header
        get_header();

        echo '<div class="arkidevs-support-public-wrapper">';
        echo '<div class="arkidevs-support-container">';

        if ( $ticket_id ) {
            $this->render_ticket_view( $ticket_id );
        } else {
            $this->render_dashboard();
        }

        echo '</div>';
        echo '</div>';

        // Load WordPress footer
        get_footer();
    }

    /**
     * Render customer dashboard shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_dashboard_shortcode( $atts = array() ) {
        if ( ! is_user_logged_in() ) {
            return '<div class="arkidevs-support-login-required">
                <p>' . __( 'Please log in to view your tickets.', 'arkidevs-support' ) . '</p>
                <p><a href="' . wp_login_url( get_permalink() ) . '" class="button">' . __( 'Log In', 'arkidevs-support' ) . '</a></p>
            </div>';
        }

        $user = wp_get_current_user();
        if ( ! in_array( 'arkidevs_customer', $user->roles, true ) ) {
            return '<p>' . __( 'You do not have permission to access this page.', 'arkidevs-support' ) . '</p>';
        }

        // Get ticket ID from query string if viewing a specific ticket
        $ticket_id = isset( $_GET['ticket_id'] ) ? intval( $_GET['ticket_id'] ) : 0;

        ob_start();

        if ( $ticket_id ) {
            $this->render_ticket_view( $ticket_id );
        } else {
            $this->render_dashboard();
        }

        return ob_get_clean();
    }

    /**
     * Render customer dashboard
     */
    private function render_dashboard() {
        include ARKIDEVS_SUPPORT_PLUGIN_DIR . 'templates/customer/dashboard.php';
    }

    /**
     * Render ticket view
     *
     * @param int $ticket_id Ticket ID
     */
    private function render_ticket_view( $ticket_id ) {
        include ARKIDEVS_SUPPORT_PLUGIN_DIR . 'templates/customer/ticket-view.php';
    }
}
