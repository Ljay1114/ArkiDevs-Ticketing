<?php
/**
 * Utility functions
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Utils
 */
class Arkidevs_Support_Utils {

    /**
     * Generate unique ticket number
     *
     * @return string
     */
    public static function generate_ticket_number() {
        return 'TKT-' . strtoupper( wp_generate_password( 8, false ) ) . '-' . date( 'Ymd' );
    }

    /**
     * Format time duration
     *
     * @param float $hours Hours to format
     * @return string
     */
    public static function format_duration( $hours ) {
        if ( $hours < 1 ) {
            return round( $hours * 60 ) . ' min';
        }
        return round( $hours, 2 ) . ' hrs';
    }

    /**
     * Format date/time for display
     *
     * @param string $date_time Date/time string
     * @param bool   $show_time Whether to show time
     * @return string
     */
    public static function format_datetime( $date_time, $show_time = true ) {
        if ( empty( $date_time ) ) {
            return '';
        }

        $timestamp = strtotime( $date_time );
        if ( ! $timestamp ) {
            return $date_time;
        }

        // Get WordPress date/time format
        $date_format = get_option( 'date_format' );
        $time_format = get_option( 'time_format' );

        if ( $show_time ) {
            return date_i18n( $date_format . ' ' . $time_format, $timestamp );
        }

        return date_i18n( $date_format, $timestamp );
    }

    /**
     * Format date/time with relative time fallback
     * Shows actual date/time, with relative time as tooltip
     *
     * @param string $date_time Date/time string
     * @return string
     */
    public static function format_datetime_with_relative( $date_time ) {
        if ( empty( $date_time ) ) {
            return '';
        }

        $timestamp = strtotime( $date_time );
        if ( ! $timestamp ) {
            return $date_time;
        }

        // Get WordPress date/time format
        $date_format = get_option( 'date_format' );
        $time_format = get_option( 'time_format' );
        $full_datetime = date_i18n( $date_format . ' ' . $time_format, $timestamp );
        $relative_time = human_time_diff( $timestamp, current_time( 'timestamp' ) ) . ' ago';

        // Return formatted date/time with relative time as tooltip
        return sprintf(
            '<span title="%s">%s</span>',
            esc_attr( $relative_time ),
            esc_html( $full_datetime )
        );
    }

    /**
     * Render a single comment/reply as HTML
     * Used for AJAX responses to dynamically add comments
     *
     * @param object $reply Reply object
     * @param object $ticket Ticket object
     * @param int    $depth Current nesting depth
     * @param bool   $is_customer_view Whether this is for customer view (read-only)
     * @return string HTML for the comment
     */
    public static function render_comment_html( $reply, $ticket, $depth = 0, $is_customer_view = false ) {
        $is_nested    = $depth > 0;
        $author_name  = self::get_user_display_name( $reply->user_id );
        $reply_id     = intval( $reply->id );
        $is_internal  = ! empty( $reply->is_internal );
        
        // Ensure we have valid data
        if ( empty( $reply ) || empty( $ticket ) ) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="reply-item <?php echo $is_nested ? 'reply-nested' : ''; ?><?php echo $is_internal && ! $is_customer_view ? ' reply-internal' : ''; ?>" data-reply-id="<?php echo esc_attr( $reply_id ); ?>" data-author-name="<?php echo esc_attr( $author_name ); ?>" data-depth="<?php echo esc_attr( $depth ); ?>">
            <?php if ( $is_nested ) : ?>
                <div class="reply-thread-line"></div>
            <?php endif; ?>
            <div class="reply-author">
                <strong class="reply-author-name <?php echo $is_internal && ! $is_customer_view ? 'reply-author-internal' : ''; ?>"><?php echo esc_html( $author_name ); ?></strong>
                <span class="reply-date"><?php echo self::format_datetime_with_relative( $reply->created_at ); ?></span>
                <?php if ( $is_internal && ! $is_customer_view ) : ?>
                    <span class="reply-internal-badge"><?php esc_html_e( 'Internal note', 'arkidevs-support' ); ?></span>
                <?php endif; ?>
            </div>
            <div class="reply-content">
                <div class="reply-text">
                    <?php echo wp_kses_post( wpautop( $reply->message ) ); ?>
                </div>
                <?php if ( ! $is_customer_view ) : ?>
                    <?php 
                    $current_user_id = get_current_user_id();
                    $is_comment_author = ( $reply->user_id == $current_user_id );
                    $is_admin = current_user_can( 'manage_options' );
                    $can_edit_delete = $is_comment_author || $is_admin;
                    ?>
                    <?php if ( Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) : ?>
                        <div class="reply-actions">
                            <button type="button" class="button-link reply-to-comment" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>" data-reply-id="<?php echo esc_attr( $reply_id ); ?>" data-author-name="<?php echo esc_attr( $author_name ); ?>">
                                <?php esc_html_e( 'Reply', 'arkidevs-support' ); ?>
                            </button>
                            <?php if ( $can_edit_delete ) : ?>
                                <span>|</span>
                                <button type="button" class="button-link edit-reply" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>" data-reply-id="<?php echo esc_attr( $reply_id ); ?>">
                                    <?php esc_html_e( 'Edit', 'arkidevs-support' ); ?>
                                </button>
                                <span>|</span>
                                <button type="button" class="button-link delete-reply" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>" data-reply-id="<?php echo esc_attr( $reply_id ); ?>">
                                    <?php esc_html_e( 'Delete', 'arkidevs-support' ); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="reply-children" data-parent-reply-id="<?php echo esc_attr( $reply_id ); ?>"></div>
            </div>
        </div>
        <?php
        $html = ob_get_clean();
        
        // Clean up any potential issues
        $html = trim( $html );
        
        return $html;
    }

    /**
     * Get user display name
     *
     * @param int $user_id User ID
     * @return string
     */
    public static function get_user_display_name( $user_id ) {
        $user = get_userdata( $user_id );
        return $user ? $user->display_name : __( 'Unknown', 'arkidevs-support' );
    }

    /**
     * Get all agents (users with agent role or administrators)
     *
     * @return array Array of user objects
     */
    public static function get_all_agents() {
        $agents = array();

        // Get users with agent role
        $agent_users = get_users( array(
            'role' => 'arkidevs_agent',
        ) );

        // Get administrators
        $admin_users = get_users( array(
            'role' => 'administrator',
        ) );

        // Merge and remove duplicates
        $all_users = array_merge( $agent_users, $admin_users );
        $unique_users = array();

        foreach ( $all_users as $user ) {
            if ( ! isset( $unique_users[ $user->ID ] ) ) {
                $unique_users[ $user->ID ] = $user;
            }
        }

        return array_values( $unique_users );
    }

    /**
     * Sanitize ticket data
     *
     * @param array $data Data to sanitize
     * @return array
     */
    public static function sanitize_ticket_data( $data ) {
        $sanitized = array();

        // Preserve customer_id (it's already validated/sanitized as integer)
        if ( isset( $data['customer_id'] ) ) {
            $sanitized['customer_id'] = intval( $data['customer_id'] );
        }

        // Preserve agent_id if present (can be null for unassignment)
        if ( isset( $data['agent_id'] ) ) {
            if ( $data['agent_id'] === null || $data['agent_id'] === '' ) {
                $sanitized['agent_id'] = null;
            } else {
                $sanitized['agent_id'] = intval( $data['agent_id'] );
            }
        }

        if ( isset( $data['subject'] ) ) {
            $sanitized['subject'] = sanitize_text_field( $data['subject'] );
        }

        if ( isset( $data['description'] ) ) {
            $sanitized['description'] = wp_kses_post( $data['description'] );
        }

        if ( isset( $data['priority'] ) ) {
            $sanitized['priority'] = sanitize_text_field( $data['priority'] );
        }

        if ( isset( $data['status'] ) ) {
            $sanitized['status'] = sanitize_text_field( $data['status'] );
        }

        return $sanitized;
    }

    /**
     * Check if user can access ticket
     *
     * @param int $ticket_id Ticket ID
     * @param int $user_id User ID
     * @return bool
     */
    public static function user_can_access_ticket( $ticket_id, $user_id = null ) {
        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            return false;
        }

        $user = get_userdata( $user_id );

        // Admin and agents can access all tickets
        if ( in_array( 'administrator', $user->roles, true ) || in_array( 'arkidevs_agent', $user->roles, true ) ) {
            return true;
        }

        // Customers can only access their own tickets
        $ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );
        if ( $ticket && $ticket->customer_id === $user_id ) {
            return true;
        }

        return false;
    }
}

