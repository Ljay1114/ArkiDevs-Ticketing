<?php
/**
 * Ticket audit log (history)
 *
 * Tracks field changes and important events for accountability/debugging.
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Audit_Log
 */
class Arkidevs_Support_Audit_Log {

    /**
     * Get audit log table name.
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'arkidevs_ticket_audit_log';
    }

    /**
     * Ensure audit table exists (for older installs).
     *
     * @return void
     */
    public static function ensure_table_exists() {
        global $wpdb;

        $table = self::get_table_name();

        // Fast path: if table exists, do nothing.
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists === $table ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) UNSIGNED NOT NULL,
            event_type varchar(50) NOT NULL,
            field_name varchar(100) DEFAULT NULL,
            old_value longtext DEFAULT NULL,
            new_value longtext DEFAULT NULL,
            actor_user_id bigint(20) UNSIGNED DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY ticket_id (ticket_id),
            KEY event_type (event_type),
            KEY actor_user_id (actor_user_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Log a field change for a ticket.
     *
     * @param int    $ticket_id Ticket ID.
     * @param string $field_name Field name (status/priority/agent_id/subject/description/etc).
     * @param mixed  $old_value Old value.
     * @param mixed  $new_value New value.
     * @param int    $actor_user_id Actor WP user ID.
     * @param string $event_type Event type, default: field_change.
     * @return bool
     */
    public static function log_field_change( $ticket_id, $field_name, $old_value, $new_value, $actor_user_id = 0, $event_type = 'field_change' ) {
        self::ensure_table_exists();

        $ticket_id = (int) $ticket_id;
        if ( ! $ticket_id ) {
            return false;
        }

        // Avoid noise: only log real changes.
        if ( (string) $old_value === (string) $new_value ) {
            return false;
        }

        global $wpdb;
        $table = self::get_table_name();

        $actor_user_id = (int) $actor_user_id;
        if ( ! $actor_user_id && is_user_logged_in() ) {
            $actor_user_id = get_current_user_id();
        }

        $ip_address = '';
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        $user_agent = '';
        if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
            $user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
        }

        $data = array(
            'ticket_id'      => $ticket_id,
            'event_type'     => sanitize_text_field( $event_type ),
            'field_name'     => sanitize_text_field( $field_name ),
            'old_value'      => is_scalar( $old_value ) ? (string) $old_value : wp_json_encode( $old_value ),
            'new_value'      => is_scalar( $new_value ) ? (string) $new_value : wp_json_encode( $new_value ),
            'actor_user_id'  => $actor_user_id ? $actor_user_id : null,
            'ip_address'     => $ip_address ? $ip_address : null,
            'user_agent'     => $user_agent ? mb_substr( $user_agent, 0, 255 ) : null,
            'created_at'     => current_time( 'mysql' ),
        );

        $formats = array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' );

        $result = $wpdb->insert( $table, $data, $formats );
        return (bool) $result;
    }

    /**
     * Log a generic event.
     *
     * @param int    $ticket_id Ticket ID.
     * @param string $event_type Event type.
     * @param string $message Message stored in new_value.
     * @param int    $actor_user_id Actor WP user ID.
     * @return bool
     */
    public static function log_event( $ticket_id, $event_type, $message, $actor_user_id = 0 ) {
        return self::log_field_change( $ticket_id, '', '', $message, $actor_user_id, $event_type );
    }

    /**
     * Get audit entries for a ticket.
     *
     * @param int $ticket_id Ticket ID.
     * @param int $limit Max rows.
     * @return array
     */
    public static function get_by_ticket( $ticket_id, $limit = 200 ) {
        self::ensure_table_exists();

        global $wpdb;
        $table = self::get_table_name();

        $ticket_id = (int) $ticket_id;
        $limit     = (int) $limit;
        if ( $limit < 1 ) {
            $limit = 200;
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE ticket_id = %d ORDER BY created_at DESC, id DESC LIMIT %d",
                $ticket_id,
                $limit
            )
        );
    }
}

