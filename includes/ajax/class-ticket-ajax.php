<?php
/**
 * Ticket AJAX handler
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Ticket_Ajax
 */
class Arkidevs_Support_Ticket_Ajax {

    /**
     * Instance
     *
     * @var Arkidevs_Support_Ticket_Ajax
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Arkidevs_Support_Ticket_Ajax
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
        // Customer actions
        add_action( 'wp_ajax_arkidevs_create_ticket', array( $this, 'create_ticket' ) );
        add_action( 'wp_ajax_arkidevs_add_reply', array( $this, 'add_reply' ) );
        add_action( 'wp_ajax_arkidevs_search_tickets_customer', array( $this, 'search_tickets_customer' ) );

        // Agent/Admin actions
        add_action( 'wp_ajax_arkidevs_update_ticket', array( $this, 'update_ticket' ) );
        add_action( 'wp_ajax_arkidevs_assign_ticket', array( $this, 'assign_ticket' ) );
        add_action( 'wp_ajax_arkidevs_close_ticket', array( $this, 'close_ticket' ) );
        add_action( 'wp_ajax_arkidevs_update_reply', array( $this, 'update_reply' ) );
        add_action( 'wp_ajax_arkidevs_delete_reply', array( $this, 'delete_reply' ) );
        add_action( 'wp_ajax_arkidevs_search_tickets_admin', array( $this, 'search_tickets_admin' ) );

        // Bulk ticket actions (agents/admins)
        add_action( 'wp_ajax_arkidevs_bulk_update_tickets', array( $this, 'bulk_update_tickets' ) );
        add_action( 'wp_ajax_arkidevs_bulk_export_tickets', array( $this, 'bulk_export_tickets' ) );

        // Tag actions
        add_action( 'wp_ajax_arkidevs_get_tags', array( $this, 'get_tags' ) );
        add_action( 'wp_ajax_arkidevs_add_tag_to_ticket', array( $this, 'add_tag_to_ticket' ) );
        add_action( 'wp_ajax_arkidevs_remove_tag_from_ticket', array( $this, 'remove_tag_from_ticket' ) );
        add_action( 'wp_ajax_arkidevs_set_ticket_tags', array( $this, 'set_ticket_tags' ) );

        // Template actions
        add_action( 'wp_ajax_arkidevs_get_templates', array( $this, 'get_templates' ) );
        add_action( 'wp_ajax_arkidevs_get_template_content', array( $this, 'get_template_content' ) );

        // Ticket merging (agents/admins)
        add_action( 'wp_ajax_arkidevs_merge_tickets', array( $this, 'merge_tickets' ) );

        // Ticket relationships (agents/admins)
        add_action( 'wp_ajax_arkidevs_add_related_ticket', array( $this, 'add_related_ticket' ) );
        add_action( 'wp_ajax_arkidevs_remove_related_ticket', array( $this, 'remove_related_ticket' ) );

        // Ticket rating (customers)
        add_action( 'wp_ajax_arkidevs_submit_rating', array( $this, 'submit_rating' ) );

        // Analytics (agents/admins)
        add_action( 'wp_ajax_arkidevs_get_analytics', array( $this, 'get_analytics' ) );
    }

    /**
     * Merge tickets (agents/admins)
     */
    public function merge_tickets() {
        if ( ! check_ajax_referer( 'arkidevs_support_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'arkidevs-support' ) ) );
        }

        if ( ! Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        $source_ticket_id = intval( $_POST['source_ticket_id'] ?? 0 );
        $target_input     = sanitize_text_field( wp_unslash( $_POST['target_ticket'] ?? '' ) );

        if ( ! $source_ticket_id || empty( $target_input ) ) {
            wp_send_json_error( array( 'message' => __( 'Source and target tickets are required.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-ticket-table.php';

        $target_ticket_id = 0;
        if ( is_numeric( $target_input ) ) {
            $target_ticket_id = (int) $target_input;
        } else {
            $target = Arkidevs_Support_Ticket_Table::get_by_ticket_number( $target_input );
            if ( $target ) {
                $target_ticket_id = (int) $target->id;
            }
        }

        if ( ! $target_ticket_id ) {
            wp_send_json_error( array( 'message' => __( 'Target ticket not found. Use a ticket ID or ticket number.', 'arkidevs-support' ) ) );
        }

        if ( $source_ticket_id === $target_ticket_id ) {
            wp_send_json_error( array( 'message' => __( 'Cannot merge a ticket into itself.', 'arkidevs-support' ) ) );
        }

        // Validate access to both tickets.
        if ( ! Arkidevs_Support_Utils::user_can_access_ticket( $source_ticket_id ) || ! Arkidevs_Support_Utils::user_can_access_ticket( $target_ticket_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have access to one of the selected tickets.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket-merge.php';
        $result = Arkidevs_Support_Ticket_Merge::merge_into( array( $source_ticket_id ), $target_ticket_id, get_current_user_id() );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Tickets merged successfully.', 'arkidevs-support' ),
            'target_ticket_id' => $target_ticket_id,
        ) );
    }

    /**
     * Add related ticket
     */
    public function add_related_ticket() {
        if ( ! check_ajax_referer( 'arkidevs_support_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'arkidevs-support' ) ) );
        }

        if ( ! Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        $ticket_id   = intval( $_POST['ticket_id'] ?? 0 );
        $related_raw = sanitize_text_field( wp_unslash( $_POST['related_ticket'] ?? '' ) );
        $relation_type = sanitize_text_field( wp_unslash( $_POST['relation_type'] ?? 'related' ) );

        if ( ! $ticket_id || empty( $related_raw ) ) {
            wp_send_json_error( array( 'message' => __( 'Ticket and related ticket are required.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket-relations.php';
        $result = Arkidevs_Support_Ticket_Relations::add_relation( $ticket_id, $related_raw, $relation_type, get_current_user_id() );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $html = $this->render_related_tickets_html( $ticket_id );
        wp_send_json_success( array( 'message' => __( 'Related ticket added.', 'arkidevs-support' ), 'html' => $html ) );
    }

    /**
     * Remove related ticket
     */
    public function remove_related_ticket() {
        if ( ! check_ajax_referer( 'arkidevs_support_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'arkidevs-support' ) ) );
        }

        if ( ! Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        $ticket_id  = intval( $_POST['ticket_id'] ?? 0 );
        $related_id = intval( $_POST['related_ticket_id'] ?? 0 );

        if ( ! $ticket_id || ! $related_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid ticket IDs.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket-relations.php';
        $result = Arkidevs_Support_Ticket_Relations::remove_relation( $ticket_id, $related_id );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'Failed to remove relationship.', 'arkidevs-support' ) ) );
        }

        $html = $this->render_related_tickets_html( $ticket_id );
        wp_send_json_success( array( 'message' => __( 'Related ticket removed.', 'arkidevs-support' ), 'html' => $html ) );
    }

    /**
     * Helper: render related tickets HTML fragment for AJAX responses.
     *
     * @param int $ticket_id Ticket ID.
     * @return string
     */
    private function render_related_tickets_html( $ticket_id ) {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket-relations.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-status.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-priority.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';

        $relations = Arkidevs_Support_Ticket_Relations::get_relations( $ticket_id );

        ob_start();
        if ( empty( $relations ) ) {
            echo '<p style="color:#666;">' . esc_html__( 'No related tickets yet.', 'arkidevs-support' ) . '</p>';
        } else {
            echo '<ul class="arkidevs-related-list">';
            foreach ( $relations as $rel ) {
                $t = $rel['ticket'];
                $link = esc_url( add_query_arg( array( 'page' => 'arkidevs-support-agent', 'ticket_id' => $t->id ), admin_url( 'admin.php' ) ) );
                ?>
                <li data-related-id="<?php echo esc_attr( $t->id ); ?>">
                    <a href="<?php echo $link; ?>">
                        #<?php echo esc_html( $t->ticket_number ); ?> — <?php echo esc_html( $t->subject ); ?>
                    </a>
                    <span class="status-badge <?php echo esc_attr( Arkidevs_Support_Status::get_status_class( $t->status ) ); ?>"><?php echo esc_html( Arkidevs_Support_Status::get_status_label( $t->status ) ); ?></span>
                    <span class="priority-badge <?php echo esc_attr( Arkidevs_Support_Priority::get_priority_class( $t->priority ) ); ?>"><?php echo esc_html( Arkidevs_Support_Priority::get_priority_label( $t->priority ) ); ?></span>
                    <button type="button" class="button-link remove-related-ticket" data-ticket-id="<?php echo esc_attr( $ticket_id ); ?>" data-related-id="<?php echo esc_attr( $t->id ); ?>">×</button>
                </li>
                <?php
            }
            echo '</ul>';
        }
        return ob_get_clean();
    }

    /**
     * Submit customer satisfaction rating.
     */
    public function submit_rating() {
        if ( ! check_ajax_referer( 'arkidevs_support_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'arkidevs-support' ) ) );
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'You must be logged in to rate a ticket.', 'arkidevs-support' ) ) );
        }

        $ticket_id = intval( $_POST['ticket_id'] ?? 0 );
        $rating    = intval( $_POST['rating'] ?? 0 );
        $feedback  = isset( $_POST['feedback'] ) ? wp_unslash( $_POST['feedback'] ) : '';

        if ( ! $ticket_id || $rating < 1 || $rating > 5 ) {
            wp_send_json_error( array( 'message' => __( 'Please provide a valid ticket and rating (1-5).', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket-rating.php';

        $ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );
        if ( ! $ticket ) {
            wp_send_json_error( array( 'message' => __( 'Ticket not found.', 'arkidevs-support' ) ) );
        }

        $current_user_id = get_current_user_id();
        if ( intval( $ticket->customer_id ) !== $current_user_id && ! Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot rate this ticket.', 'arkidevs-support' ) ) );
        }

        // Allow rating only when ticket is closed.
        if ( 'closed' !== $ticket->status ) {
            wp_send_json_error( array( 'message' => __( 'You can rate only after the ticket is closed.', 'arkidevs-support' ) ) );
        }

        $result = Arkidevs_Support_Ticket_Rating::save_rating( $ticket_id, $ticket->customer_id, $rating, $feedback );

        if ( is_wp_error( $result ) || ! $result ) {
            wp_send_json_error( array( 'message' => __( 'Unable to save rating. Please try again.', 'arkidevs-support' ) ) );
        }

        wp_send_json_success( array( 'message' => __( 'Thanks for your feedback!', 'arkidevs-support' ) ) );
    }

    /**
     * Get dashboard analytics (agents/admins).
     */
    public function get_analytics() {
        if ( ! check_ajax_referer( 'arkidevs_support_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'arkidevs-support' ) ) );
        }

        if ( ! Arkidevs_Support_Security::user_can( 'manage_tickets' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        $days = isset( $_POST['days'] ) ? intval( $_POST['days'] ) : 30;
        $days = $days > 0 ? $days : 30;

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/analytics/class-analytics.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket-rating.php';

        try {
            $response = array(
                'tickets_by_status'    => Arkidevs_Support_Analytics::tickets_over_time( $days, 'status' ),
                'tickets_by_priority'  => Arkidevs_Support_Analytics::tickets_over_time( $days, 'priority' ),
                'agent_performance'    => Arkidevs_Support_Analytics::agent_performance( $days ),
                'avg_resolution_hours' => Arkidevs_Support_Analytics::average_resolution_time( $days ),
                'csat_trend'           => Arkidevs_Support_Analytics::csat_trend( $days ),
            );

            wp_send_json_success( $response );
        } catch ( Throwable $e ) {
            error_log( 'Arkidevs analytics error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Analytics are temporarily unavailable. Check debug.log for details.', 'arkidevs-support' ) ) );
        }
    }

    /**
     * Create ticket
     */
    public function create_ticket() {
        // Check nonce
        if ( ! check_ajax_referer( 'arkidevs_support_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'arkidevs-support' ) ) );
        }

        // Verify user is logged in
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'You must be logged in to create a ticket. Please log in and try again.', 'arkidevs-support' ) ) );
        }

        // Check user role and capability
        $user = wp_get_current_user();
        $has_permission = Arkidevs_Support_Security::user_can( 'create_ticket' );
        $is_admin = current_user_can( 'manage_options' );
        
        if ( ! $has_permission ) {
            $user_roles = implode( ', ', $user->roles );
            wp_send_json_error( array( 
                'message' => __( 'Permission denied. You do not have permission to create tickets. Your roles: ', 'arkidevs-support' ) . $user_roles 
            ) );
        }

        // For admins: allow specifying customer_id, otherwise use current user
        $customer_id = 0;
        if ( $is_admin && isset( $_POST['customer_id'] ) && ! empty( $_POST['customer_id'] ) ) {
            $customer_id = intval( $_POST['customer_id'] );
            // Verify the customer exists and has customer role
            $customer = get_userdata( $customer_id );
            if ( ! $customer || ! in_array( 'arkidevs_customer', $customer->roles, true ) ) {
                wp_send_json_error( array( 'message' => __( 'Invalid customer selected.', 'arkidevs-support' ) ) );
            }
        } else {
            $customer_id = get_current_user_id();
        }

        if ( ! $customer_id || $customer_id === 0 ) {
            wp_send_json_error( array( 'message' => __( 'Unable to identify user. Please log out and log back in, then try again.', 'arkidevs-support' ) ) );
        }

        $subject = sanitize_text_field( $_POST['subject'] ?? '' );
        $description = wp_kses_post( $_POST['description'] ?? '' );
        $priority = sanitize_text_field( $_POST['priority'] ?? 'medium' );

        if ( empty( $subject ) ) {
            wp_send_json_error( array( 'message' => __( 'Subject is required.', 'arkidevs-support' ) ) );
        }

        if ( empty( $description ) ) {
            wp_send_json_error( array( 'message' => __( 'Description is required.', 'arkidevs-support' ) ) );
        }

        // Get tags if provided
        $tags = array();
        if ( isset( $_POST['tags'] ) && is_array( $_POST['tags'] ) ) {
            $tags = array_map( 'intval', $_POST['tags'] );
            $tags = array_filter( $tags ); // Remove zeros
        }

        $data = array(
            'customer_id' => $customer_id,
            'subject'     => $subject,
            'description' => $description,
            'priority'    => $priority,
            'tags'        => $tags,
        );

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
        $ticket = Arkidevs_Support_Ticket::create( $data );

        if ( is_wp_error( $ticket ) ) {
            wp_send_json_error( array( 'message' => $ticket->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Ticket created successfully.', 'arkidevs-support' ),
            'ticket_id' => $ticket->data->id,
        ) );
    }

    /**
     * Update ticket
     */
    public function update_ticket() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        if ( ! Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        $ticket_id = intval( $_POST['ticket_id'] ?? 0 );
        if ( ! $ticket_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid ticket ID.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
        $ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );

        if ( ! $ticket ) {
            wp_send_json_error( array( 'message' => __( 'Ticket not found.', 'arkidevs-support' ) ) );
        }

        $data = array();

        // Status & priority can be changed by agents and admins
        if ( isset( $_POST['status'] ) ) {
            $data['status'] = sanitize_text_field( wp_unslash( $_POST['status'] ) );
        }
        if ( isset( $_POST['priority'] ) ) {
            $data['priority'] = sanitize_text_field( wp_unslash( $_POST['priority'] ) );
        }

        // Subject & description should only be editable by admins
        $is_admin = current_user_can( 'manage_options' );
        if ( isset( $_POST['subject'] ) && $is_admin ) {
            $data['subject'] = sanitize_text_field( wp_unslash( $_POST['subject'] ) );
        }
        if ( isset( $_POST['description'] ) && $is_admin ) {
            // Allow HTML via wp_kses_post; additional sanitization happens in sanitize_ticket_data
            $data['description'] = wp_kses_post( wp_unslash( $_POST['description'] ) );
        }

        $result = $ticket->update( $data );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // Return updated ticket data for dynamic UI updates
        $updated_data = array( 'message' => __( 'Ticket updated successfully.', 'arkidevs-support' ) );
        
        // Include updated fields if they were changed
        if ( isset( $data['status'] ) ) {
            require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-status.php';
            $updated_data['status'] = $data['status'];
            $updated_data['status_label'] = Arkidevs_Support_Status::get_status_label( $data['status'] );
            $updated_data['status_class'] = Arkidevs_Support_Status::get_status_class( $data['status'] );
        }
        
        if ( isset( $data['priority'] ) ) {
            require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-priority.php';
            $updated_data['priority'] = $data['priority'];
            $updated_data['priority_label'] = Arkidevs_Support_Priority::get_priority_label( $data['priority'] );
            $updated_data['priority_class'] = Arkidevs_Support_Priority::get_priority_class( $data['priority'] );
        }
        
        if ( isset( $data['subject'] ) ) {
            $updated_data['subject'] = $data['subject'];
        }
        
        if ( isset( $data['description'] ) ) {
            $updated_data['description'] = $data['description'];
        }

        wp_send_json_success( $updated_data );
    }

    /**
     * Assign ticket to agent
     */
    public function assign_ticket() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        $ticket_id = intval( $_POST['ticket_id'] ?? 0 );
        $agent_id = intval( $_POST['agent_id'] ?? 0 );

        if ( ! $ticket_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid ticket ID.', 'arkidevs-support' ) ) );
        }

        // Permission rules:
        // - Admins can assign/unassign anyone
        // - Agents can only assign tickets to themselves (cannot unassign or assign others)
        $current_user_id = get_current_user_id();
        $is_admin        = current_user_can( 'manage_options' );
        $is_agent        = Arkidevs_Support_Security::user_can( 'manage_tickets' );

        if ( ! $is_admin ) {
            // Agents: must be assigning to self and not unassigning
            if ( ! $is_agent || $agent_id !== $current_user_id ) {
                wp_send_json_error( array( 'message' => __( 'Permission denied. Agents can only take a ticket for themselves.', 'arkidevs-support' ) ) );
            }
        }

        // Allow unassigning only for admin
        if ( $agent_id === 0 && $is_admin ) {
            $agent_id = null;
        } elseif ( $agent_id === 0 && ! $is_admin ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied. Agents cannot unassign tickets.', 'arkidevs-support' ) ) );
        } else {
            // Verify agent exists and has agent role or is admin
            $agent = get_userdata( $agent_id );
            if ( ! $agent ) {
                wp_send_json_error( array( 'message' => __( 'Agent not found.', 'arkidevs-support' ) ) );
            }

            $is_valid_agent = in_array( 'arkidevs_agent', $agent->roles, true ) || in_array( 'administrator', $agent->roles, true );
            if ( ! $is_valid_agent ) {
                wp_send_json_error( array( 'message' => __( 'Selected user is not an agent.', 'arkidevs-support' ) ) );
            }
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
        $ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );

        if ( ! $ticket ) {
            wp_send_json_error( array( 'message' => __( 'Ticket not found.', 'arkidevs-support' ) ) );
        }

        $result = $ticket->update( array( 'agent_id' => $agent_id ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $message = $agent_id ? __( 'Ticket assigned successfully.', 'arkidevs-support' ) : __( 'Ticket unassigned successfully.', 'arkidevs-support' );
        
        // Return agent info for dynamic UI update
        $agent_name = '';
        if ( $agent_id ) {
            require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';
            $agent_name = Arkidevs_Support_Utils::get_user_display_name( $agent_id );
        }
        
        wp_send_json_success( array( 
            'message' => $message,
            'agent_id' => $agent_id,
            'agent_name' => $agent_name
        ) );
    }

    /**
     * Close ticket
     */
    public function close_ticket() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        if ( ! Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        $ticket_id = intval( $_POST['ticket_id'] ?? 0 );
        if ( ! $ticket_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid ticket ID.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
        $ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );

        if ( ! $ticket ) {
            wp_send_json_error( array( 'message' => __( 'Ticket not found.', 'arkidevs-support' ) ) );
        }

        $result = $ticket->update( array( 'status' => 'closed' ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-status.php';
        wp_send_json_success( array( 
            'message' => __( 'Ticket closed successfully.', 'arkidevs-support' ),
            'status' => 'closed',
            'status_label' => Arkidevs_Support_Status::get_status_label( 'closed' ),
            'status_class' => Arkidevs_Support_Status::get_status_class( 'closed' )
        ) );
    }

    /**
     * Add reply to ticket
     */
    public function add_reply() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        $ticket_id       = intval( $_POST['ticket_id'] ?? 0 );
        $message         = wp_kses_post( $_POST['message'] ?? '' );
        $parent_reply_id = isset( $_POST['parent_reply_id'] ) ? intval( $_POST['parent_reply_id'] ) : null;

        if ( ! $ticket_id || empty( $message ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
        $ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );

        if ( ! $ticket ) {
            wp_send_json_error( array( 'message' => __( 'Ticket not found.', 'arkidevs-support' ) ) );
        }

        // Ensure user is logged in (both customers and agents/admins can comment)
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'You must be logged in to comment on a ticket.', 'arkidevs-support' ) ) );
        }

        // Internal notes are only allowed for agents/admins.
        $is_internal = 0;
        if ( isset( $_POST['is_internal'] ) && Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) {
            $is_internal = intval( $_POST['is_internal'] ) ? 1 : 0;
        }

        $reply_id = $ticket->add_reply( $message, null, $parent_reply_id, $is_internal );

        if ( ! $reply_id ) {
            wp_send_json_error( array( 'message' => __( 'Failed to add reply.', 'arkidevs-support' ) ) );
        }

        // Get the newly created reply
        global $wpdb;
        $replies_table = $wpdb->prefix . 'arkidevs_ticket_replies';
        $new_reply = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$replies_table} WHERE id = %d",
                $reply_id
            )
        );

        if ( ! $new_reply ) {
            wp_send_json_error( array( 'message' => __( 'Failed to retrieve reply.', 'arkidevs-support' ) ) );
        }

        // Calculate depth based on parent
        $depth = 0;
        if ( $parent_reply_id ) {
            // Recursively calculate depth of parent
            $parent_depth = 0;
            $current_parent_id = $parent_reply_id;
            $max_depth = 10; // Safety limit
            $iterations = 0;
            
            while ( $current_parent_id && $iterations < $max_depth ) {
                $parent = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT parent_reply_id FROM {$replies_table} WHERE id = %d",
                        $current_parent_id
                    )
                );
                if ( $parent && $parent->parent_reply_id ) {
                    $parent_depth++;
                    $current_parent_id = $parent->parent_reply_id;
                } else {
                    break;
                }
                $iterations++;
            }
            $depth = $parent_depth + 1;
        }

        // Render the comment HTML
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';
        $is_customer_view = ! Arkidevs_Support_Security::user_can( 'manage_tickets' );
        $comment_html = Arkidevs_Support_Utils::render_comment_html( $new_reply, $ticket, $depth, $is_customer_view );

        wp_send_json_success( array( 
            'message' => __( 'Comment added successfully.', 'arkidevs-support' ),
            'comment_html' => $comment_html,
            'reply_id' => $reply_id,
            'parent_reply_id' => $parent_reply_id ? intval( $parent_reply_id ) : null
        ) );
    }

    /**
     * Update a reply (comment)
     */
    public function update_reply() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        // Only agents and admins can edit comments
        if ( ! Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        $ticket_id = intval( $_POST['ticket_id'] ?? 0 );
        $reply_id  = intval( $_POST['reply_id'] ?? 0 );
        $message   = wp_kses_post( wp_unslash( $_POST['message'] ?? '' ) );

        if ( ! $ticket_id || ! $reply_id || empty( $message ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
        $ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );

        if ( ! $ticket ) {
            wp_send_json_error( array( 'message' => __( 'Ticket not found.', 'arkidevs-support' ) ) );
        }

        // Extra safety: ensure current user can access the ticket
        if ( ! Arkidevs_Support_Utils::user_can_access_ticket( $ticket_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        // Check if user owns the comment (agents can only edit their own, admins can edit any)
        global $wpdb;
        $replies_table = $wpdb->prefix . 'arkidevs_ticket_replies';
        $reply = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT user_id, message FROM {$replies_table} WHERE id = %d AND ticket_id = %d",
                $reply_id,
                $ticket_id
            )
        );

        if ( ! $reply ) {
            wp_send_json_error( array( 'message' => __( 'Comment not found.', 'arkidevs-support' ) ) );
        }

        $current_user_id = get_current_user_id();
        $is_admin = current_user_can( 'manage_options' );
        
        // Agents can only edit their own comments, admins can edit any
        if ( ! $is_admin && $reply->user_id != $current_user_id ) {
            wp_send_json_error( array( 'message' => __( 'You can only edit your own comments.', 'arkidevs-support' ) ) );
        }

        $result = $ticket->update_reply( $reply_id, $message );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'Failed to update comment.', 'arkidevs-support' ) ) );
        }

        // Audit: reply updated.
        if ( class_exists( 'Arkidevs_Support_Audit_Log' ) ) {
            Arkidevs_Support_Audit_Log::log_field_change(
                $ticket_id,
                'reply:' . (int) $reply_id,
                isset( $reply->message ) ? $reply->message : '',
                $message,
                get_current_user_id(),
                'reply_updated'
            );
        }

        // Get the updated reply
        global $wpdb;
        $replies_table = $wpdb->prefix . 'arkidevs_ticket_replies';
        $updated_reply = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$replies_table} WHERE id = %d AND ticket_id = %d",
                $reply_id,
                $ticket_id
            )
        );

        if ( ! $updated_reply ) {
            wp_send_json_error( array( 'message' => __( 'Failed to retrieve updated comment.', 'arkidevs-support' ) ) );
        }

        // Calculate depth based on parent_reply_id
        $depth = 0;
        if ( isset( $updated_reply->parent_reply_id ) && $updated_reply->parent_reply_id ) {
            // Recursively calculate depth
            $parent_depth = 0;
            $current_parent_id = $updated_reply->parent_reply_id;
            $max_depth = 10;
            $iterations = 0;
            
            while ( $current_parent_id && $iterations < $max_depth ) {
                $parent = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT parent_reply_id FROM {$replies_table} WHERE id = %d",
                        $current_parent_id
                    )
                );
                if ( $parent && isset( $parent->parent_reply_id ) && $parent->parent_reply_id ) {
                    $parent_depth++;
                    $current_parent_id = $parent->parent_reply_id;
                } else {
                    break;
                }
                $iterations++;
            }
            $depth = $parent_depth + 1;
        }
        
        $is_customer_view = ! Arkidevs_Support_Security::user_can( 'manage_tickets' );
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';
        $comment_html = Arkidevs_Support_Utils::render_comment_html( $updated_reply, $ticket, $depth, $is_customer_view );

        wp_send_json_success( array( 
            'message' => __( 'Comment updated successfully.', 'arkidevs-support' ),
            'comment_html' => $comment_html,
            'reply_id' => $reply_id
        ) );
    }

    /**
     * Delete a reply (comment)
     */
    public function delete_reply() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        // Only agents and admins can delete comments
        if ( ! Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        $ticket_id = intval( $_POST['ticket_id'] ?? 0 );
        $reply_id  = intval( $_POST['reply_id'] ?? 0 );

        if ( ! $ticket_id || ! $reply_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
        $ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );

        if ( ! $ticket ) {
            wp_send_json_error( array( 'message' => __( 'Ticket not found.', 'arkidevs-support' ) ) );
        }

        // Extra safety: ensure current user can access the ticket
        if ( ! Arkidevs_Support_Utils::user_can_access_ticket( $ticket_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        // Check if user owns the comment (agents can only delete their own, admins can delete any)
        global $wpdb;
        $replies_table = $wpdb->prefix . 'arkidevs_ticket_replies';
        $reply = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT user_id, message FROM {$replies_table} WHERE id = %d AND ticket_id = %d",
                $reply_id,
                $ticket_id
            )
        );

        if ( ! $reply ) {
            wp_send_json_error( array( 'message' => __( 'Comment not found.', 'arkidevs-support' ) ) );
        }

        $current_user_id = get_current_user_id();
        $is_admin = current_user_can( 'manage_options' );
        
        // Agents can only delete their own comments, admins can delete any
        if ( ! $is_admin && $reply->user_id != $current_user_id ) {
            wp_send_json_error( array( 'message' => __( 'You can only delete your own comments.', 'arkidevs-support' ) ) );
        }

        $result = $ticket->delete_reply( $reply_id );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'Failed to delete comment.', 'arkidevs-support' ) ) );
        }

        // Audit: reply deleted.
        if ( class_exists( 'Arkidevs_Support_Audit_Log' ) ) {
            Arkidevs_Support_Audit_Log::log_field_change(
                $ticket_id,
                'reply:' . (int) $reply_id,
                isset( $reply->message ) ? $reply->message : '',
                '',
                get_current_user_id(),
                'reply_deleted'
            );
        }

        wp_send_json_success( array( 'message' => __( 'Comment deleted successfully.', 'arkidevs-support' ) ) );
    }

    /**
     * AJAX: Search and filter tickets for agents/admins.
     */
    public function search_tickets_admin() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        if ( ! Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-ticket-table.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-status.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-priority.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';

        $search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $status   = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
        $priority = isset( $_POST['priority'] ) ? sanitize_text_field( wp_unslash( $_POST['priority'] ) ) : '';
        $agent_id = isset( $_POST['agent_id'] ) ? sanitize_text_field( wp_unslash( $_POST['agent_id'] ) ) : '';
        $customer = isset( $_POST['customer_id'] ) ? (int) $_POST['customer_id'] : 0;
        $tag_id   = isset( $_POST['tag_id'] ) ? (int) $_POST['tag_id'] : 0;
        $date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
        $date_to   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';
        $page_slug = isset( $_POST['page_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['page_slug'] ) ) : 'arkidevs-support-agent';

        // If on "My Tickets" page, always filter by current agent
        if ( 'arkidevs-support-my-tickets' === $page_slug ) {
            $agent_id = get_current_user_id();
        }

        $args = array(
            'search'      => $search,
            'status'      => $status,
            'priority'    => $priority,
            'agent_id'    => $agent_id,
            'customer_id' => $customer,
            'tag_id'      => $tag_id,
            'date_from'   => $date_from,
            'date_to'     => $date_to,
            'limit'       => 200,
            'offset'      => 0,
        );

        $tickets  = Arkidevs_Support_Ticket_Table::search( $args );
        $base_url = admin_url( 'admin.php?page=' . $page_slug );

        ob_start();
        if ( empty( $tickets ) ) {
            echo '<p>' . esc_html__( 'No tickets found.', 'arkidevs-support' ) . '</p>';
        } else {
            require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-time-log-table.php';
            require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/sla/class-sla-tracker.php';
            foreach ( $tickets as $ticket_data ) {
                $ticket = new Arkidevs_Support_Ticket( $ticket_data );
                $active_timer = Arkidevs_Support_Time_Log_Table::has_active_timer( $ticket->id );
                
                // Check SLA status
                $sla_status = Arkidevs_Support_SLA_Tracker::get_ticket_sla_status( $ticket->id );
                $is_overdue = ! is_wp_error( $sla_status ) && ( $sla_status['first_response']['overdue'] || $sla_status['resolution']['overdue'] );
                $overdue_class = $is_overdue ? 'sla-overdue' : '';
                ?>
                <div class="arkidevs-ticket-item <?php echo $active_timer ? 'has-active-timer' : ''; ?> <?php echo esc_attr( $overdue_class ); ?>">
                    <div class="ticket-header">
                        <label class="arkidevs-ticket-select" style="margin-right: 8px;">
                            <input type="checkbox" class="arkidevs-ticket-checkbox" value="<?php echo esc_attr( $ticket->id ); ?>" />
                        </label>
                        <h3>
                            <a href="<?php echo esc_url( add_query_arg( 'ticket_id', $ticket->id, $base_url ) ); ?>">
                                #<?php echo esc_html( $ticket->ticket_number ); ?> - <?php echo esc_html( $ticket->subject ); ?>
                            </a>
                            <?php if ( $active_timer ) : ?>
                                <span class="timer-indicator" title="<?php esc_attr_e( 'Timer is running', 'arkidevs-support' ); ?>">
                                    <span class="timer-icon">⏱</span>
                                    <span class="timer-pulse"></span>
                                </span>
                            <?php endif; ?>
                            <?php if ( $is_overdue && ! is_wp_error( $sla_status ) && isset( $sla_status['first_response'] ) && isset( $sla_status['resolution'] ) ) : ?>
                                <?php
                                $overdue_hours = max( 
                                    isset( $sla_status['first_response']['hours_overdue'] ) ? $sla_status['first_response']['hours_overdue'] : 0,
                                    isset( $sla_status['resolution']['hours_overdue'] ) ? $sla_status['resolution']['hours_overdue'] : 0
                                );
                                $overdue_type = ( isset( $sla_status['first_response']['overdue'] ) && $sla_status['first_response']['overdue'] ) ? __( 'Response', 'arkidevs-support' ) : __( 'Resolution', 'arkidevs-support' );
                                ?>
                                <span class="sla-warning" title="<?php echo esc_attr( sprintf( __( 'SLA %s overdue by %s hours', 'arkidevs-support' ), $overdue_type, $overdue_hours ) ); ?>" style="display: inline-block; margin-left: 8px; padding: 2px 6px; background: #dc3232; color: #fff; border-radius: 3px; font-size: 11px; font-weight: bold;">
                                    ⚠ SLA Overdue
                                </span>
                            <?php endif; ?>
                        </h3>
                        <span class="priority-badge <?php echo esc_attr( Arkidevs_Support_Priority::get_priority_class( $ticket->priority ) ); ?>">
                            <?php echo esc_html( Arkidevs_Support_Priority::get_priority_label( $ticket->priority ) ); ?>
                        </span>
                    </div>
                    <div class="ticket-meta">
                        <span class="status-badge <?php echo esc_attr( Arkidevs_Support_Status::get_status_class( $ticket->status ) ); ?>">
                            <?php echo esc_html( Arkidevs_Support_Status::get_status_label( $ticket->status ) ); ?>
                        </span>
                        <span><?php esc_html_e( 'Customer:', 'arkidevs-support' ); ?> <?php echo esc_html( Arkidevs_Support_Utils::get_user_display_name( $ticket->customer_id ) ); ?></span>
                        <?php if ( $ticket->agent_id ) : ?>
                            <span><?php esc_html_e( 'Assigned:', 'arkidevs-support' ); ?> <strong><?php echo esc_html( Arkidevs_Support_Utils::get_user_display_name( $ticket->agent_id ) ); ?></strong></span>
                        <?php else : ?>
                            <span class="unassigned-text"><?php esc_html_e( 'Unassigned', 'arkidevs-support' ); ?></span>
                        <?php endif; ?>
                        <span><?php echo Arkidevs_Support_Utils::format_datetime_with_relative( $ticket->created_at ); ?></span>
                    </div>
                </div>
                <?php
            }
        }
        $html = ob_get_clean();

        wp_send_json_success(
            array(
                'html' => $html,
            )
        );
    }

    /**
     * AJAX: Search and filter tickets for a customer.
     */
    public function search_tickets_customer() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'You must be logged in to view tickets.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-ticket-table.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-time-log-table.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-status.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-priority.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';

        $customer_id = get_current_user_id();

        $search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $status   = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
        $priority = isset( $_POST['priority'] ) ? sanitize_text_field( wp_unslash( $_POST['priority'] ) ) : '';
        $tag_id   = isset( $_POST['tag_id'] ) ? (int) $_POST['tag_id'] : 0;
        $date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
        $date_to   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';

        $args = array(
            'search'    => $search,
            'status'    => $status,
            'priority'  => $priority,
            'tag_id'    => $tag_id,
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'limit'     => 200,
            'offset'    => 0,
        );

        $tickets = Arkidevs_Support_Ticket_Table::search_by_customer( $customer_id, $args );

        ob_start();
        if ( empty( $tickets ) ) {
            echo '<p>' . esc_html__( 'You have no tickets yet.', 'arkidevs-support' ) . '</p>';
        } else {
            ?>
            <table class="tickets-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Ticket #', 'arkidevs-support' ); ?></th>
                        <th><?php esc_html_e( 'Subject', 'arkidevs-support' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'arkidevs-support' ); ?></th>
                        <th><?php esc_html_e( 'Priority', 'arkidevs-support' ); ?></th>
                        <th><?php esc_html_e( 'Created', 'arkidevs-support' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'arkidevs-support' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $tickets as $ticket_data ) : ?>
                        <?php
                        // Check if ticket has active timer
                        $active_timer = Arkidevs_Support_Time_Log_Table::has_active_timer( $ticket_data->id );
                        ?>
                        <tr class="<?php echo $active_timer ? 'has-active-timer' : ''; ?>">
                            <td>
                                #<?php echo esc_html( $ticket_data->ticket_number ); ?>
                                <?php if ( $active_timer ) : ?>
                                    <span class="timer-indicator" title="<?php esc_attr_e( 'Timer is running', 'arkidevs-support' ); ?>" style="margin-left: 8px;">
                                        <span class="timer-icon">⏱</span>
                                        <span class="timer-pulse"></span>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $ticket_data->subject ); ?></td>
                            <td>
                                <span class="status-badge <?php echo esc_attr( Arkidevs_Support_Status::get_status_class( $ticket_data->status ) ); ?>">
                                    <?php echo esc_html( Arkidevs_Support_Status::get_status_label( $ticket_data->status ) ); ?>
                                </span>
                            </td>
                            <td>
                                <span class="priority-badge <?php echo esc_attr( Arkidevs_Support_Priority::get_priority_class( $ticket_data->priority ) ); ?>">
                                    <?php echo esc_html( Arkidevs_Support_Priority::get_priority_label( $ticket_data->priority ) ); ?>
                                </span>
                            </td>
                            <td><?php echo Arkidevs_Support_Utils::format_datetime_with_relative( $ticket_data->created_at ); ?></td>
                            <td>
                                <?php
                                $view_url = home_url( '/support/ticket/' . $ticket_data->id . '/' );
                                if ( ! get_option( 'permalink_structure' ) ) {
                                    $view_url = home_url( '/support/?ticket_id=' . $ticket_data->id );
                                }
                                ?>
                                <a href="<?php echo esc_url( $view_url ); ?>" class="button">
                                    <?php esc_html_e( 'View', 'arkidevs-support' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        }

        $html = ob_get_clean();

        wp_send_json_success(
            array(
                'html' => $html,
            )
        );
    }

    /**
     * Bulk update tickets (status, priority, assignment, archive, delete).
     */
    public function bulk_update_tickets() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        if ( ! Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        $ticket_ids = isset( $_POST['ticket_ids'] ) ? (array) $_POST['ticket_ids'] : array();
        $ticket_ids = array_filter( array_map( 'intval', $ticket_ids ) );

        if ( empty( $ticket_ids ) ) {
            wp_send_json_error( array( 'message' => __( 'No tickets selected.', 'arkidevs-support' ) ) );
        }

        $bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';

        if ( ! $bulk_action ) {
            wp_send_json_error( array( 'message' => __( 'No bulk action selected.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-status.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-priority.php';

        $updated   = 0;
        $deleted   = 0;
        $archived  = 0;
        $errors    = array();

        switch ( $bulk_action ) {
            case 'status':
                $status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
                if ( ! Arkidevs_Support_Status::is_valid( $status ) ) {
                    wp_send_json_error( array( 'message' => __( 'Invalid status selected.', 'arkidevs-support' ) ) );
                }
                foreach ( $ticket_ids as $ticket_id ) {
                    $ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );
                    if ( ! $ticket ) {
                        $errors[] = $ticket_id;
                        continue;
                    }
                    $result = $ticket->update( array( 'status' => $status ) );
                    if ( ! is_wp_error( $result ) ) {
                        $updated++;
                    } else {
                        $errors[] = $ticket_id;
                    }
                }
                break;

            case 'priority':
                $priority = isset( $_POST['priority'] ) ? sanitize_text_field( wp_unslash( $_POST['priority'] ) ) : '';
                if ( ! Arkidevs_Support_Priority::is_valid( $priority ) ) {
                    wp_send_json_error( array( 'message' => __( 'Invalid priority selected.', 'arkidevs-support' ) ) );
                }
                foreach ( $ticket_ids as $ticket_id ) {
                    $ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );
                    if ( ! $ticket ) {
                        $errors[] = $ticket_id;
                        continue;
                    }
                    $result = $ticket->update( array( 'priority' => $priority ) );
                    if ( ! is_wp_error( $result ) ) {
                        $updated++;
                    } else {
                        $errors[] = $ticket_id;
                    }
                }
                break;

            case 'assign':
                $agent_id = isset( $_POST['agent_id'] ) ? intval( $_POST['agent_id'] ) : 0;

                if ( $agent_id ) {
                    $user = get_user_by( 'id', $agent_id );
                    if ( ! $user ) {
                        wp_send_json_error( array( 'message' => __( 'Selected agent not found.', 'arkidevs-support' ) ) );
                    }
                    $allowed_roles = array( 'arkidevs_agent', 'administrator', 'editor' );
                    if ( ! array_intersect( $allowed_roles, (array) $user->roles ) ) {
                        wp_send_json_error( array( 'message' => __( 'Selected user is not an agent.', 'arkidevs-support' ) ) );
                    }
                }

                foreach ( $ticket_ids as $ticket_id ) {
                    $ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );
                    if ( ! $ticket ) {
                        $errors[] = $ticket_id;
                        continue;
                    }
                    $result = $ticket->update( array( 'agent_id' => $agent_id ) );
                    if ( ! is_wp_error( $result ) ) {
                        $updated++;
                    } else {
                        $errors[] = $ticket_id;
                    }
                }
                break;

            case 'archive':
                foreach ( $ticket_ids as $ticket_id ) {
                    $ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );
                    if ( ! $ticket ) {
                        $errors[] = $ticket_id;
                        continue;
                    }
                    $result = $ticket->update( array( 'status' => 'archived' ) );
                    if ( ! is_wp_error( $result ) ) {
                        $archived++;
                    } else {
                        $errors[] = $ticket_id;
                    }
                }
                break;

            case 'delete':
                global $wpdb;
                $replies_table     = $wpdb->prefix . 'arkidevs_ticket_replies';
                $attachments_table = $wpdb->prefix . 'arkidevs_ticket_attachments';
                $time_logs_table   = $wpdb->prefix . 'arkidevs_time_logs';

                require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-ticket-table.php';

                foreach ( $ticket_ids as $ticket_id ) {
                    // Delete replies
                    $wpdb->delete(
                        $replies_table,
                        array( 'ticket_id' => $ticket_id ),
                        array( '%d' )
                    );

                    // Delete attachments (including files)
                    $attachments = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT file_path FROM {$attachments_table} WHERE ticket_id = %d",
                            $ticket_id
                        )
                    );

                    foreach ( $attachments as $attachment ) {
                        if ( ! empty( $attachment->file_path ) && file_exists( $attachment->file_path ) ) {
                            @unlink( $attachment->file_path );
                        }
                    }

                    $wpdb->delete(
                        $attachments_table,
                        array( 'ticket_id' => $ticket_id ),
                        array( '%d' )
                    );

                    // Delete time logs
                    $wpdb->delete(
                        $time_logs_table,
                        array( 'ticket_id' => $ticket_id ),
                        array( '%d' )
                    );

                    // Finally delete ticket
                    $deleted_ticket = Arkidevs_Support_Ticket_Table::delete( $ticket_id );
                    if ( $deleted_ticket ) {
                        $deleted++;
                    } else {
                        $errors[] = $ticket_id;
                    }
                }
                break;

            default:
                wp_send_json_error( array( 'message' => __( 'Invalid bulk action.', 'arkidevs-support' ) ) );
        }

        $message_parts = array();
        if ( $updated ) {
            $message_parts[] = sprintf(
                /* translators: %d: number of tickets updated */
                _n( '%d ticket updated.', '%d tickets updated.', $updated, 'arkidevs-support' ),
                $updated
            );
        }
        if ( $archived ) {
            $message_parts[] = sprintf(
                /* translators: %d: number of tickets archived */
                _n( '%d ticket archived.', '%d tickets archived.', $archived, 'arkidevs-support' ),
                $archived
            );
        }
        if ( $deleted ) {
            $message_parts[] = sprintf(
                /* translators: %d: number of tickets deleted */
                _n( '%d ticket deleted.', '%d tickets deleted.', $deleted, 'arkidevs-support' ),
                $deleted
            );
        }

        if ( empty( $message_parts ) ) {
            $message_parts[] = __( 'No tickets were changed.', 'arkidevs-support' );
        }

        $response = array(
            'message' => implode( ' ', $message_parts ),
        );

        if ( ! empty( $errors ) ) {
            $response['errors'] = $errors;
        }

        wp_send_json_success( $response );
    }

    /**
     * Bulk export tickets (CSV) for selected IDs.
     */
    public function bulk_export_tickets() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        if ( ! Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        $ticket_ids = isset( $_POST['ticket_ids'] ) ? (array) $_POST['ticket_ids'] : array();
        $ticket_ids = array_filter( array_map( 'intval', $ticket_ids ) );

        if ( empty( $ticket_ids ) ) {
            wp_send_json_error( array( 'message' => __( 'No tickets selected.', 'arkidevs-support' ) ) );
        }

        global $wpdb;

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-ticket-table.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-status.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-priority.php';

        $placeholders = implode( ',', array_fill( 0, count( $ticket_ids ), '%d' ) );
        $table        = Arkidevs_Support_Ticket_Table::get_table_name();

        $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id IN ($placeholders) ORDER BY created_at DESC",
            $ticket_ids
        );

        $rows = $wpdb->get_results( $query );

        if ( empty( $rows ) ) {
            wp_send_json_error( array( 'message' => __( 'No tickets found for export.', 'arkidevs-support' ) ) );
        }

        $csv_rows   = array();
        $csv_header = array(
            'ID',
            'Ticket Number',
            'Subject',
            'Status',
            'Priority',
            'Customer',
            'Agent',
            'Created At',
            'Updated At',
        );

        $csv_rows[] = $csv_header;

        foreach ( $rows as $row ) {
            $customer_name = Arkidevs_Support_Utils::get_user_display_name( $row->customer_id );
            $agent_name    = $row->agent_id ? Arkidevs_Support_Utils::get_user_display_name( $row->agent_id ) : '';

            $csv_rows[] = array(
                $row->id,
                $row->ticket_number,
                $row->subject,
                Arkidevs_Support_Status::get_status_label( $row->status ),
                Arkidevs_Support_Priority::get_priority_label( $row->priority ),
                $customer_name,
                $agent_name,
                $row->created_at,
                isset( $row->updated_at ) ? $row->updated_at : '',
            );
        }

        // Convert to CSV string
        $fh = fopen( 'php://temp', 'r+' );
        foreach ( $csv_rows as $csv_row ) {
            fputcsv( $fh, $csv_row );
        }
        rewind( $fh );
        $csv_data = stream_get_contents( $fh );
        fclose( $fh );

        $filename = 'tickets-export-' . gmdate( 'Ymd-His' ) . '.csv';

        wp_send_json_success(
            array(
                'filename' => $filename,
                'csv'      => $csv_data,
            )
        );
    }

    /**
     * Get all tags
     */
    public function get_tags() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-tag.php';
        $tags = Arkidevs_Support_Tag::get_all();

        wp_send_json_success( array( 'tags' => $tags ) );
    }

    /**
     * Add tag to ticket
     */
    public function add_tag_to_ticket() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        if ( ! Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        $ticket_id = intval( $_POST['ticket_id'] ?? 0 );
        $tag_id = intval( $_POST['tag_id'] ?? 0 );

        if ( ! $ticket_id || ! $tag_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid ticket or tag ID.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
        $ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );

        if ( ! $ticket ) {
            wp_send_json_error( array( 'message' => __( 'Ticket not found.', 'arkidevs-support' ) ) );
        }

        $result = $ticket->add_tag( $tag_id );

        if ( $result ) {
            require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-tag.php';
            $tag = Arkidevs_Support_Tag::get_by_id( $tag_id );
            wp_send_json_success( array( 'message' => __( 'Tag added successfully.', 'arkidevs-support' ), 'tag' => $tag ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to add tag.', 'arkidevs-support' ) ) );
        }
    }

    /**
     * Remove tag from ticket
     */
    public function remove_tag_from_ticket() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        if ( ! Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        $ticket_id = intval( $_POST['ticket_id'] ?? 0 );
        $tag_id = intval( $_POST['tag_id'] ?? 0 );

        if ( ! $ticket_id || ! $tag_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid ticket or tag ID.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
        $ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );

        if ( ! $ticket ) {
            wp_send_json_error( array( 'message' => __( 'Ticket not found.', 'arkidevs-support' ) ) );
        }

        $result = $ticket->remove_tag( $tag_id );

        if ( $result ) {
            wp_send_json_success( array( 'message' => __( 'Tag removed successfully.', 'arkidevs-support' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to remove tag.', 'arkidevs-support' ) ) );
        }
    }

    /**
     * Set tags for ticket
     */
    public function set_ticket_tags() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        if ( ! Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        $ticket_id = intval( $_POST['ticket_id'] ?? 0 );
        $tag_ids = isset( $_POST['tag_ids'] ) && is_array( $_POST['tag_ids'] ) ? array_map( 'intval', $_POST['tag_ids'] ) : array();

        if ( ! $ticket_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid ticket ID.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
        $ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );

        if ( ! $ticket ) {
            wp_send_json_error( array( 'message' => __( 'Ticket not found.', 'arkidevs-support' ) ) );
        }

        $result = $ticket->set_tags( $tag_ids );

        if ( $result ) {
            $tags = $ticket->get_tags();
            wp_send_json_success( array( 'message' => __( 'Tags updated successfully.', 'arkidevs-support' ), 'tags' => $tags ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to update tags.', 'arkidevs-support' ) ) );
        }
    }

    /**
     * AJAX: Get all templates
     */
    public function get_templates() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        if ( ! Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-template.php';
        $templates = Arkidevs_Support_Template::get_all();

        wp_send_json_success( array( 'templates' => $templates ) );
    }

    /**
     * AJAX: Get template content with variables replaced
     */
    public function get_template_content() {
        check_ajax_referer( 'arkidevs_support_nonce', 'nonce' );

        if ( ! Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'arkidevs-support' ) ) );
        }

        $template_id = intval( $_POST['template_id'] ?? 0 );
        $ticket_id = intval( $_POST['ticket_id'] ?? 0 );

        if ( ! $template_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid template ID.', 'arkidevs-support' ) ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-template.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';

        $template = Arkidevs_Support_Template::get_by_id( $template_id );

        if ( ! $template ) {
            wp_send_json_error( array( 'message' => __( 'Template not found.', 'arkidevs-support' ) ) );
        }

        // Build context for variable replacement
        $context = array();

        if ( $ticket_id ) {
            $ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );
            if ( $ticket ) {
                $context['ticket'] = $ticket;
                
                // Get customer
                if ( $ticket->customer_id ) {
                    $customer = get_userdata( $ticket->customer_id );
                    if ( $customer ) {
                        $context['customer'] = $customer;
                    }
                }

                // Get agent
                if ( $ticket->agent_id ) {
                    $agent = get_userdata( $ticket->agent_id );
                    if ( $agent ) {
                        $context['agent'] = $agent;
                    }
                }
            }
        }

        // Replace variables
        $processed_content = Arkidevs_Support_Template::replace_variables( $template->content, $context );

        wp_send_json_success( array(
            'content' => $processed_content,
            'template_name' => $template->name,
        ) );
    }
}

