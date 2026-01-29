<?php
/**
 * Escalation Engine - Checks tickets and escalates based on rules
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Escalation_Engine
 */
class Arkidevs_Support_Escalation_Engine {

    /**
     * Get escalation history table name
     *
     * @return string
     */
    public static function get_history_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'arkidevs_escalation_history';
    }

    /**
     * Ensure history table exists
     */
    public static function ensure_history_table_exists() {
        global $wpdb;
        $table = self::get_history_table_name();

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                $wpdb->dbname,
                $table
            )
        );

        if ( ! $exists ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE IF NOT EXISTS {$table} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                ticket_id bigint(20) UNSIGNED NOT NULL,
                escalation_rule_id bigint(20) UNSIGNED NOT NULL,
                trigger_type varchar(50) NOT NULL,
                trigger_value int(11) UNSIGNED NOT NULL,
                action_type varchar(50) NOT NULL,
                action_value varchar(255) DEFAULT NULL,
                previous_priority varchar(50) DEFAULT NULL,
                previous_agent_id bigint(20) UNSIGNED DEFAULT NULL,
                escalated_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY ticket_id (ticket_id),
                KEY escalation_rule_id (escalation_rule_id),
                KEY escalated_at (escalated_at)
            ) $charset_collate;";
            dbDelta( $sql );
        }
    }

    /**
     * Check if ticket was already escalated by this rule
     *
     * @param int $ticket_id Ticket ID
     * @param int $rule_id Rule ID
     * @return bool
     */
    public static function was_escalated( $ticket_id, $rule_id ) {
        self::ensure_history_table_exists();

        global $wpdb;
        $table = self::get_history_table_name();

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE ticket_id = %d AND escalation_rule_id = %d",
                $ticket_id,
                $rule_id
            )
        );

        return $count > 0;
    }

    /**
     * Record escalation in history
     *
     * @param int    $ticket_id Ticket ID
     * @param object $rule Escalation rule
     * @param string $previous_priority Previous priority
     * @param int    $previous_agent_id Previous agent ID
     * @return int|false History ID or false on failure
     */
    public static function record_escalation( $ticket_id, $rule, $previous_priority = null, $previous_agent_id = null ) {
        self::ensure_history_table_exists();

        global $wpdb;
        $table = self::get_history_table_name();

        $result = $wpdb->insert(
            $table,
            array(
                'ticket_id'          => $ticket_id,
                'escalation_rule_id' => $rule->id,
                'trigger_type'       => $rule->trigger_type,
                'trigger_value'      => $rule->trigger_value,
                'action_type'        => $rule->action_type,
                'action_value'       => $rule->action_value,
                'previous_priority'  => $previous_priority,
                'previous_agent_id'  => $previous_agent_id,
            ),
            array( '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%d' )
        );

        if ( $result ) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get last activity time for a ticket (last reply)
     *
     * @param int $ticket_id Ticket ID
     * @return string|null Last activity datetime or null
     */
    public static function get_last_activity_time( $ticket_id ) {
        global $wpdb;
        $replies_table = $wpdb->prefix . 'arkidevs_ticket_replies';

        $last_reply = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(created_at) FROM {$replies_table} WHERE ticket_id = %d",
                $ticket_id
            )
        );

        return $last_reply;
    }

    /**
     * Check if ticket matches rule conditions
     *
     * @param object $ticket Ticket object
     * @param object $rule Escalation rule
     * @return bool
     */
    public static function ticket_matches_rule( $ticket, $rule ) {
        // Check priority filter
        if ( ! empty( $rule->priority_filter ) && $ticket->priority !== $rule->priority_filter ) {
            return false;
        }

        // Check status filter
        if ( ! empty( $rule->status_filter ) && $ticket->status !== $rule->status_filter ) {
            return false;
        }

        // Skip closed tickets
        if ( 'closed' === $ticket->status ) {
            return false;
        }

        // Check trigger conditions
        $created_time = strtotime( $ticket->created_at );
        $current_time = current_time( 'timestamp' );
        $hours_since_creation = ( $current_time - $created_time ) / 3600;

        switch ( $rule->trigger_type ) {
            case 'time':
                // Escalate if ticket is open for X hours
                return $hours_since_creation >= $rule->trigger_value;

            case 'priority':
                // Escalate if ticket priority matches trigger_value (priority level)
                // trigger_value can be: 1=low, 2=medium, 3=high, 4=critical
                $priority_levels = array(
                    'low'      => 1,
                    'medium'   => 2,
                    'high'     => 3,
                    'critical' => 4,
                );
                $ticket_priority_level = isset( $priority_levels[ $ticket->priority ] ) ? $priority_levels[ $ticket->priority ] : 2;
                return $ticket_priority_level >= $rule->trigger_value && $hours_since_creation >= 1; // At least 1 hour old

            case 'inactivity':
                // Escalate if no activity for X hours
                $last_activity = self::get_last_activity_time( $ticket->id );
                if ( ! $last_activity ) {
                    // No replies yet, use creation time
                    $last_activity = $ticket->created_at;
                }
                $last_activity_time = strtotime( $last_activity );
                $hours_since_activity = ( $current_time - $last_activity_time ) / 3600;
                return $hours_since_activity >= $rule->trigger_value;

            default:
                return false;
        }
    }

    /**
     * Execute escalation action
     *
     * @param object $ticket Ticket object
     * @param object $rule Escalation rule
     * @return bool|WP_Error
     */
    public static function execute_escalation( $ticket, $rule ) {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';

        $ticket_obj = Arkidevs_Support_Ticket::get_ticket( $ticket->id );
        if ( ! $ticket_obj ) {
            return new WP_Error( 'invalid_ticket', __( 'Invalid ticket.', 'arkidevs-support' ) );
        }

        $previous_priority = $ticket->priority;
        $previous_agent_id = $ticket->agent_id;

        $update_data = array();

        switch ( $rule->action_type ) {
            case 'notify':
                // Just notify supervisors (handled separately)
                break;

            case 'assign':
                // Assign to supervisor
                if ( ! empty( $rule->action_value ) ) {
                    $supervisor_id = intval( $rule->action_value );
                    $supervisor = get_userdata( $supervisor_id );
                    if ( $supervisor && ( in_array( 'administrator', $supervisor->roles, true ) || in_array( 'arkidevs_agent', $supervisor->roles, true ) ) ) {
                        $update_data['agent_id'] = $supervisor_id;
                    }
                }
                break;

            case 'priority_change':
                // Change priority
                if ( ! empty( $rule->action_value ) ) {
                    $new_priority = sanitize_text_field( $rule->action_value );
                    if ( in_array( $new_priority, array( 'low', 'medium', 'high', 'critical' ), true ) ) {
                        $update_data['priority'] = $new_priority;
                    }
                }
                break;
        }

        // Update ticket if needed
        if ( ! empty( $update_data ) ) {
            $ticket_obj->update( $update_data );
        }

        // Record escalation
        self::record_escalation( $ticket->id, $rule, $previous_priority, $previous_agent_id );

        // Add internal note about escalation
        $escalation_note = sprintf(
            __( 'Ticket escalated automatically by rule "%s" (%s trigger: %d hours).', 'arkidevs-support' ),
            $rule->name,
            $rule->trigger_type,
            $rule->trigger_value
        );
        $ticket_obj->add_reply( array(
            'message'     => $escalation_note,
            'is_internal' => true,
        ) );

        return true;
    }

    /**
     * Get all supervisors (administrators)
     *
     * @return array Array of user objects
     */
    public static function get_supervisors() {
        return get_users( array(
            'role' => 'administrator',
        ) );
    }

    /**
     * Send escalation notification to supervisors
     *
     * @param object $ticket Ticket object
     * @param object $rule Escalation rule
     * @return void
     */
    public static function notify_supervisors( $ticket, $rule ) {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/email/class-email-replies.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/email/class-email-preferences.php';

        $supervisors = self::get_supervisors();
        if ( empty( $supervisors ) ) {
            return;
        }

        $subject = sprintf(
            __( 'Ticket #%s Escalated: %s', 'arkidevs-support' ),
            $ticket->ticket_number,
            $ticket->subject
        );

        $message = '<html><body>';
        $message .= '<h2>' . esc_html__( 'Ticket Escalated', 'arkidevs-support' ) . '</h2>';
        $message .= '<p><strong>' . esc_html__( 'Ticket:', 'arkidevs-support' ) . '</strong> #' . esc_html( $ticket->ticket_number ) . '</p>';
        $message .= '<p><strong>' . esc_html__( 'Subject:', 'arkidevs-support' ) . '</strong> ' . esc_html( $ticket->subject ) . '</p>';
        $message .= '<p><strong>' . esc_html__( 'Priority:', 'arkidevs-support' ) . '</strong> ' . esc_html( ucfirst( $ticket->priority ) ) . '</p>';
        $message .= '<p><strong>' . esc_html__( 'Status:', 'arkidevs-support' ) . '</strong> ' . esc_html( ucfirst( $ticket->status ) ) . '</p>';
        $message .= '<p><strong>' . esc_html__( 'Escalation Rule:', 'arkidevs-support' ) . '</strong> ' . esc_html( $rule->name ) . '</p>';
        $message .= '<p><strong>' . esc_html__( 'Trigger:', 'arkidevs-support' ) . '</strong> ' . esc_html( ucfirst( $rule->trigger_type ) ) . ' (' . esc_html( $rule->trigger_value ) . ' hours)</p>';
        $message .= '<hr>';
        $message .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=arkidevs-support-ticket&ticket_id=' . $ticket->id ) ) . '">' . esc_html__( 'View Ticket', 'arkidevs-support' ) . '</a></p>';
        $message .= '</body></html>';

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        foreach ( $supervisors as $supervisor ) {
            // Check if supervisor wants escalation notifications
            if ( Arkidevs_Support_Email_Preferences::is_enabled( $supervisor->ID, 'agent_escalation' ) ) {
                wp_mail( $supervisor->user_email, $subject, $message, $headers );
            }
        }
    }

    /**
     * Process escalation checks for all tickets
     *
     * @return array Results array with escalated tickets count
     */
    public static function process_escalations() {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/escalation/class-escalation-rule.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-ticket-table.php';

        // Get all enabled escalation rules
        $rules = Arkidevs_Support_Escalation_Rule::get_all( array( 'enabled' => 1 ) );
        if ( empty( $rules ) ) {
            return array( 'escalated' => 0, 'checked' => 0 );
        }

        // Get all open tickets (not closed)
        $tickets = Arkidevs_Support_Ticket_Table::get_all();
        $escalated_count = 0;
        $checked_count = 0;

        foreach ( $tickets as $ticket ) {
            // Skip closed tickets
            if ( 'closed' === $ticket->status ) {
                continue;
            }

            $checked_count++;

            foreach ( $rules as $rule ) {
                // Skip if already escalated by this rule
                if ( self::was_escalated( $ticket->id, $rule->id ) ) {
                    continue;
                }

                // Check if ticket matches rule conditions
                if ( self::ticket_matches_rule( $ticket, $rule ) ) {
                    // Execute escalation
                    $result = self::execute_escalation( $ticket, $rule );

                    if ( ! is_wp_error( $result ) ) {
                        $escalated_count++;

                        // Notify supervisors (always notify, even if action is just notify)
                        self::notify_supervisors( $ticket, $rule );
                    }
                }
            }
        }

        return array(
            'escalated' => $escalated_count,
            'checked'   => $checked_count,
        );
    }
}
