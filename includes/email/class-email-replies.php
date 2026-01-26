<?php
/**
 * Email replies handler
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Email_Replies
 */
class Arkidevs_Support_Email_Replies {

    /**
     * Send ticket notification email to the customer.
     *
     * @param Arkidevs_Support_Ticket $ticket Ticket object.
     * @param string                  $type   Email type (created, updated, closed, replied).
     * @return bool
     */
    public static function send_ticket_email( $ticket, $type = 'created' ) {
        if ( ! $ticket || ! $ticket->data ) {
            return false;
        }

        $customer = get_userdata( $ticket->customer_id );
        if ( ! $customer || ! $customer->user_email ) {
            return false;
        }

        // Check customer email preferences.
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/email/class-email-preferences.php';

        $pref_key = null;
        switch ( $type ) {
            case 'created':
                $pref_key = 'customer_ticket_created';
                break;
            case 'replied':
                $pref_key = 'customer_ticket_replied';
                break;
            case 'updated':
            case 'closed':
                $pref_key = 'customer_ticket_status';
                break;
        }

        if ( $pref_key && ! Arkidevs_Support_Email_Preferences::is_enabled( $customer->ID, $pref_key ) ) {
            return false;
        }

        $subject  = '';
        $template = '';

        switch ( $type ) {
            case 'created':
                $subject = sprintf( __( 'Ticket #%s Created', 'arkidevs-support' ), $ticket->ticket_number );
                $template = 'ticket-created.php';
                break;
            
            case 'updated':
                $subject = sprintf( __( 'Ticket #%s Updated', 'arkidevs-support' ), $ticket->ticket_number );
                $template = 'ticket-updated.php';
                break;
            
            case 'closed':
                $subject = sprintf( __( 'Ticket #%s Closed', 'arkidevs-support' ), $ticket->ticket_number );
                $template = 'ticket-closed.php';
                break;
            
            case 'replied':
                $subject = sprintf( __( 'Reply to Ticket #%s', 'arkidevs-support' ), $ticket->ticket_number );
                $template = 'ticket-replied.php';
                break;
        }

        if ( empty( $template ) ) {
            return false;
        }

        $message = self::get_email_template( $template, $ticket );

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        return wp_mail( $customer->user_email, $subject, $message, $headers );
    }

    /**
     * Get email template
     *
     * @param string                  $template_name Template name
     * @param Arkidevs_Support_Ticket $ticket Ticket object
     * @return string
     */
    private static function get_email_template( $template_name, $ticket ) {
        $template_path = ARKIDEVS_SUPPORT_PLUGIN_DIR . 'templates/emails/' . $template_name;

        if ( ! file_exists( $template_path ) ) {
            return self::get_default_email_template( $ticket );
        }

        ob_start();
        include $template_path;
        return ob_get_clean();
    }

    /**
     * Get default email template
     *
     * @param Arkidevs_Support_Ticket $ticket Ticket object
     * @return string
     */
    protected static function get_default_email_template( $ticket ) {
        $html = '<html><body>';
        $html .= '<h2>' . sprintf( __( 'Ticket #%s', 'arkidevs-support' ), $ticket->ticket_number ) . '</h2>';
        $html .= '<p><strong>' . __( 'Subject:', 'arkidevs-support' ) . '</strong> ' . esc_html( $ticket->subject ) . '</p>';
        $html .= '<p><strong>' . __( 'Status:', 'arkidevs-support' ) . '</strong> ' . Arkidevs_Support_Status::get_status_label( $ticket->status ) . '</p>';
        $html .= '<p><strong>' . __( 'Priority:', 'arkidevs-support' ) . '</strong> ' . Arkidevs_Support_Priority::get_priority_label( $ticket->priority ) . '</p>';
        $html .= '<hr>';
        $html .= '<div>' . wpautop( $ticket->description ) . '</div>';
        $html .= '<hr>';
        $html .= '<p><small>' . __( 'View your ticket:', 'arkidevs-support' ) . ' <a href="' . home_url( '/support/' ) . '">' . home_url( '/support/' ) . '</a></small></p>';
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Send agent notification for a new ticket.
     *
     * @param Arkidevs_Support_Ticket $ticket Ticket object.
     * @return void
     */
    public static function send_agent_new_ticket_notifications( $ticket ) {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/email/class-email-preferences.php';

        // If ticket already assigned, notify only that agent, otherwise all agents.
        $recipients = array();
        if ( ! empty( $ticket->agent_id ) ) {
            $agent = get_userdata( $ticket->agent_id );
            if ( $agent && $agent->user_email && Arkidevs_Support_Email_Preferences::is_enabled( $agent->ID, 'agent_new_ticket' ) ) {
                $recipients[] = $agent;
            }
        } else {
            $agents = Arkidevs_Support_Utils::get_all_agents();
            foreach ( $agents as $agent ) {
                if ( $agent->user_email && Arkidevs_Support_Email_Preferences::is_enabled( $agent->ID, 'agent_new_ticket' ) ) {
                    $recipients[] = $agent;
                }
            }
        }

        if ( empty( $recipients ) ) {
            return;
        }

        $subject = sprintf( __( 'New Ticket #%s Created', 'arkidevs-support' ), $ticket->ticket_number );
        $message = self::get_default_email_template( $ticket );
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        foreach ( $recipients as $user ) {
            wp_mail( $user->user_email, $subject, $message, $headers );
        }
    }

    /**
     * Send agent notification when a customer replies.
     *
     * @param Arkidevs_Support_Ticket $ticket   Ticket object.
     * @param object                  $reply    Reply row.
     * @return void
     */
    public static function send_agent_customer_reply_notifications( $ticket, $reply ) {
        if ( ! $reply || empty( $reply->user_id ) ) {
            return;
        }

        $reply_user = get_userdata( $reply->user_id );
        if ( ! $reply_user ) {
            return;
        }

        // Only notify agents when the reply is from a customer (not from agents/admins).
        $roles = (array) $reply_user->roles;
        if ( in_array( 'arkidevs_agent', $roles, true ) || in_array( 'administrator', $roles, true ) ) {
            return;
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/email/class-email-preferences.php';

        $recipients = array();

        if ( ! empty( $ticket->agent_id ) ) {
            $agent = get_userdata( $ticket->agent_id );
            if ( $agent && $agent->user_email && Arkidevs_Support_Email_Preferences::is_enabled( $agent->ID, 'agent_customer_reply' ) ) {
                $recipients[] = $agent;
            }
        } else {
            $agents = Arkidevs_Support_Utils::get_all_agents();
            foreach ( $agents as $agent ) {
                if ( $agent->user_email && Arkidevs_Support_Email_Preferences::is_enabled( $agent->ID, 'agent_customer_reply' ) ) {
                    $recipients[] = $agent;
                }
            }
        }

        if ( empty( $recipients ) ) {
            return;
        }

        $subject = sprintf( __( 'Customer Reply on Ticket #%s', 'arkidevs-support' ), $ticket->ticket_number );

        // Simple HTML that includes the latest reply message.
        $html  = '<html><body>';
        $html .= '<h2>' . sprintf( __( 'Ticket #%s', 'arkidevs-support' ), $ticket->ticket_number ) . '</h2>';
        $html .= '<p><strong>' . __( 'Subject:', 'arkidevs-support' ) . '</strong> ' . esc_html( $ticket->subject ) . '</p>';
        $html .= '<hr>';
        $html .= '<p><strong>' . __( 'New reply from customer:', 'arkidevs-support' ) . '</strong></p>';
        $html .= '<div>' . wpautop( $reply->message ) . '</div>';
        $html .= '<hr>';
        $html .= '<p><small>' . __( 'View this ticket in the dashboard.', 'arkidevs-support' ) . '</small></p>';
        $html .= '</body></html>';

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        foreach ( $recipients as $user ) {
            wp_mail( $user->user_email, $subject, $html, $headers );
        }
    }

    /**
     * Send notification to an agent when a ticket is assigned to them.
     *
     * @param Arkidevs_Support_Ticket $ticket       Ticket object.
     * @param int                     $new_agent_id New agent user ID.
     * @param int                     $old_agent_id Previous agent user ID (optional).
     * @return void
     */
    public static function send_agent_assignment_notification( $ticket, $new_agent_id, $old_agent_id = 0 ) {
        $new_agent_id = intval( $new_agent_id );
        if ( ! $new_agent_id ) {
            return;
        }

        $agent = get_userdata( $new_agent_id );
        if ( ! $agent || ! $agent->user_email ) {
            return;
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/email/class-email-preferences.php';

        if ( ! Arkidevs_Support_Email_Preferences::is_enabled( $agent->ID, 'agent_assignment' ) ) {
            return;
        }

        $subject = sprintf( __( 'Ticket #%s Assigned to You', 'arkidevs-support' ), $ticket->ticket_number );
        $message = self::get_default_email_template( $ticket );
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        wp_mail( $agent->user_email, $subject, $message, $headers );
    }
}


