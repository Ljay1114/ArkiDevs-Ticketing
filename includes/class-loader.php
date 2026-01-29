<?php
/**
 * Plugin loader class
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Loader
 */
class Arkidevs_Support_Loader {

    /**
     * Instance
     *
     * @var Arkidevs_Support_Loader
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Arkidevs_Support_Loader
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
        $this->check_migrations();
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Check and run database migrations if needed
     */
    private function check_migrations() {
        $current_version = get_option( 'arkidevs_support_version', '0.0.0' );
        
        // Run migrations if version is older than current
        if ( version_compare( $current_version, ARKIDEVS_SUPPORT_VERSION, '<' ) ) {
            // Run activation to ensure database is up to date
            require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/class-activator.php';
            Arkidevs_Support_Activator::activate();
        }
    }

    /**
     * Load all dependencies
     */
    private function load_dependencies() {
        // Helper classes
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-security.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-logger.php';

        // Audit log
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/audit/class-audit-log.php';

        // Database classes
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-db.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-ticket-table.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-time-log-table.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-customer-hours-table.php';

        // Auth classes
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/auth/class-roles.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/auth/class-auth-redirect.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/auth/class-user-cleanup.php';

        // Ticket classes
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-status.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-priority.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-attachments.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket-merge.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket-relations.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket-rating.php';

        // Time tracking classes
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/time-tracking/class-timer.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/time-tracking/class-time-calculator.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/time-tracking/class-time-reports.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/time-tracking/class-customer-hours-admin.php';

        // Email classes
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/email/class-email-parser.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/email/class-email-replies.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/email/class-email-listener.php';

        // Dashboard classes
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/dashboards/class-admin-dashboard.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/dashboards/class-agent-dashboard.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/dashboards/class-customer-dashboard.php';

        // Tag management
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-tag.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-tag-admin.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-auto-assign.php';

        // Template management
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-template.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-template-admin.php';

        // SLA management
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/sla/class-sla-rule.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/sla/class-sla-tracker.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/sla/class-sla-admin.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/sla/class-sla-reports.php';

        // Escalation management
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/escalation/class-escalation-rule.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/escalation/class-escalation-engine.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/escalation/class-escalation-admin.php';

        // Analytics
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/analytics/class-analytics.php';

        // AJAX classes
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/ajax/class-ticket-ajax.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/ajax/class-time-ajax.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/ajax/class-dashboard-ajax.php';

        // API classes
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/api/class-rest-api.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Initialize roles
        Arkidevs_Support_Roles::get_instance();

        // Initialize auth redirects
        Arkidevs_Support_Auth_Redirect::get_instance();

        // Initialize user cleanup
        Arkidevs_Support_User_Cleanup::get_instance();

        // Register login widget
        add_action( 'widgets_init', array( $this, 'register_login_widget' ) );

        // Initialize dashboards
        Arkidevs_Support_Admin_Dashboard::get_instance();
        Arkidevs_Support_Agent_Dashboard::get_instance();
        Arkidevs_Support_Customer_Dashboard::get_instance();

        // Initialize tag management
        Arkidevs_Support_Tag_Admin::get_instance();

        // Initialize template management
        Arkidevs_Support_Template_Admin::get_instance();

        // Initialize SLA management
        Arkidevs_Support_SLA_Admin::get_instance();

        // Initialize escalation management
        Arkidevs_Support_Escalation_Admin::get_instance();

        // Initialize customer hours management
        Arkidevs_Support_Customer_Hours_Admin::get_instance();

        // Set up escalation cron job
        $this->setup_escalation_cron();

        // Initialize AJAX handlers
        Arkidevs_Support_Ticket_Ajax::get_instance();
        Arkidevs_Support_Time_Ajax::get_instance();
        Arkidevs_Support_Dashboard_Ajax::get_instance();

        // Initialize REST API
        Arkidevs_Support_REST_API::get_instance();

        // Initialize email listener
        Arkidevs_Support_Email_Listener::get_instance();

        // Set up email notification hooks
        $this->setup_email_notifications();

        // Enqueue scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
    }

    /**
     * Set up email notification hooks
     */
    private function setup_email_notifications() {
        add_action( 'arkidevs_ticket_created', array( $this, 'send_ticket_created_email' ), 10, 1 );
        add_action( 'arkidevs_ticket_updated', array( $this, 'send_ticket_updated_email' ), 10, 1 );
        add_action( 'arkidevs_ticket_status_changed', array( $this, 'send_ticket_status_email' ), 10, 2 );
        add_action( 'arkidevs_ticket_replied', array( $this, 'send_ticket_replied_email' ), 10, 2 );
        add_action( 'arkidevs_ticket_assigned', array( $this, 'send_ticket_assigned_email' ), 10, 3 );
    }

    /**
     * Send ticket created email
     *
     * @param Arkidevs_Support_Ticket $ticket Ticket object
     */
    public function send_ticket_created_email( $ticket ) {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/email/class-email-replies.php';
        // Notify customer.
        Arkidevs_Support_Email_Replies::send_ticket_email( $ticket, 'created' );
        // Notify agents (new ticket).
        Arkidevs_Support_Email_Replies::send_agent_new_ticket_notifications( $ticket );
    }

    /**
     * Send ticket updated email
     *
     * @param Arkidevs_Support_Ticket $ticket Ticket object
     */
    public function send_ticket_updated_email( $ticket ) {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/email/class-email-replies.php';
        Arkidevs_Support_Email_Replies::send_ticket_email( $ticket, 'updated' );
    }

    /**
     * Send ticket status changed email
     *
     * @param Arkidevs_Support_Ticket $ticket Ticket object
     * @param string                  $new_status New status
     */
    public function send_ticket_status_email( $ticket, $new_status ) {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/email/class-email-replies.php';
        
        // Ticket object has been reloaded, so use its current status
        if ( 'closed' === $ticket->status ) {
            Arkidevs_Support_Email_Replies::send_ticket_email( $ticket, 'closed' );
        } else {
            Arkidevs_Support_Email_Replies::send_ticket_email( $ticket, 'updated' );
        }
    }

    /**
     * Send ticket replied email
     *
     * @param Arkidevs_Support_Ticket $ticket Ticket object
     * @param int                     $reply_id Reply ID
     */
    public function send_ticket_replied_email( $ticket, $reply_id ) {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/email/class-email-replies.php';

        global $wpdb;
        $replies_table = $wpdb->prefix . 'arkidevs_ticket_replies';
        $reply         = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$replies_table} WHERE id = %d AND ticket_id = %d",
                $reply_id,
                $ticket->id
            )
        );

        // Do not send any external notifications for internal notes.
        if ( $reply && ! empty( $reply->is_internal ) ) {
            return;
        }

        // Notify customer (for any reply).
        Arkidevs_Support_Email_Replies::send_ticket_email( $ticket, 'replied' );

        // Notify agents only when the reply is from a customer.
        if ( $reply ) {
            Arkidevs_Support_Email_Replies::send_agent_customer_reply_notifications( $ticket, $reply );
        }
    }

    /**
     * Send ticket assignment email to the assigned agent.
     *
     * @param Arkidevs_Support_Ticket $ticket       Ticket object.
     * @param int                     $new_agent_id New agent ID.
     * @param int                     $old_agent_id Old agent ID.
     */
    public function send_ticket_assigned_email( $ticket, $new_agent_id, $old_agent_id ) {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/email/class-email-replies.php';
        Arkidevs_Support_Email_Replies::send_agent_assignment_notification( $ticket, $new_agent_id, $old_agent_id );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets() {
        $screen = get_current_screen();
        
        if ( ! $screen || strpos( $screen->id, 'arkidevs-support' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'arkidevs-support-admin',
            ARKIDEVS_SUPPORT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ARKIDEVS_SUPPORT_VERSION
        );

        wp_enqueue_style(
            'arkidevs-support-common',
            ARKIDEVS_SUPPORT_PLUGIN_URL . 'assets/css/common.css',
            array(),
            ARKIDEVS_SUPPORT_VERSION
        );

        wp_enqueue_script(
            'arkidevs-support-common',
            ARKIDEVS_SUPPORT_PLUGIN_URL . 'assets/js/common.js',
            array( 'jquery' ),
            ARKIDEVS_SUPPORT_VERSION,
            true
        );

        wp_enqueue_script(
            'arkidevs-support-admin',
            ARKIDEVS_SUPPORT_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'arkidevs-support-common' ),
            ARKIDEVS_SUPPORT_VERSION,
            true
        );

        wp_localize_script(
            'arkidevs-support-common',
            'arkidevsSupport',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'arkidevs_support_nonce' ),
            )
        );
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        // Check if we're on the support page
        $is_support_page = false;
        
        // Method 1: Check URL path
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        if ( preg_match( '#^/support(/|$)#', $request_uri ) ) {
            $is_support_page = true;
        }
        
        // Method 2: Check query var (available after rewrite rules are processed)
        if ( ! $is_support_page ) {
            $is_support_page = get_query_var( 'arkidevs_support_page' );
        }
        
        // Method 3: Check if page has the shortcode
        global $post;
        $has_shortcode = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'arkidevs_customer_dashboard' );

        // Only load assets on support pages
        if ( ! $is_support_page && ! $has_shortcode ) {
            return;
        }

        wp_enqueue_style(
            'arkidevs-support-customer',
            ARKIDEVS_SUPPORT_PLUGIN_URL . 'assets/css/customer.css',
            array(),
            ARKIDEVS_SUPPORT_VERSION
        );

        wp_enqueue_style(
            'arkidevs-support-common',
            ARKIDEVS_SUPPORT_PLUGIN_URL . 'assets/css/common.css',
            array(),
            ARKIDEVS_SUPPORT_VERSION
        );

        // Enqueue shared frontend JS helpers (defines window.arkidevsSupport helpers)
        wp_enqueue_script(
            'arkidevs-support-common',
            ARKIDEVS_SUPPORT_PLUGIN_URL . 'assets/js/common.js',
            array( 'jquery' ),
            ARKIDEVS_SUPPORT_VERSION,
            true
        );

        // Localize script for common.js (must be enqueued before customer.js)
        wp_localize_script(
            'arkidevs-support-common',
            'arkidevsSupport',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'arkidevs_support_nonce' ),
            )
        );

        // Enqueue customer-specific JS (depends on common.js)
        wp_enqueue_script(
            'arkidevs-support-customer',
            ARKIDEVS_SUPPORT_PLUGIN_URL . 'assets/js/customer.js',
            array( 'jquery', 'arkidevs-support-common' ),
            ARKIDEVS_SUPPORT_VERSION,
            true
        );
    }

    /**
     * Set up escalation cron job
     */
    private function setup_escalation_cron() {
        // Schedule escalation check if not already scheduled
        if ( ! wp_next_scheduled( 'arkidevs_check_escalations' ) ) {
            wp_schedule_event( time(), 'hourly', 'arkidevs_check_escalations' );
        }

        // Hook the escalation check
        add_action( 'arkidevs_check_escalations', array( $this, 'run_escalation_check' ) );
    }

    /**
     * Run escalation check (called by cron)
     */
    public function run_escalation_check() {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/escalation/class-escalation-engine.php';
        Arkidevs_Support_Escalation_Engine::process_escalations();
    }

    /**
     * Register login widget
     */
    public function register_login_widget() {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/auth/class-login-widget.php';
        register_widget( 'Arkidevs_Support_Login_Widget' );
    }
}

