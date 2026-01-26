<?php
/**
 * Ticket relationships / dependencies
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Ticket_Relations
 */
class Arkidevs_Support_Ticket_Relations {

    /**
     * Get table name.
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'arkidevs_ticket_relations';
    }

    /**
     * Ensure the relations table exists (for runtime safety on older installs).
     *
     * @return void
     */
    public static function ensure_table() {
        global $wpdb;
        $table = self::get_table_name();
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists === $table ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) UNSIGNED NOT NULL,
            related_ticket_id bigint(20) UNSIGNED NOT NULL,
            relation_type varchar(50) DEFAULT 'related',
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY ticket_pair (ticket_id, related_ticket_id),
            KEY ticket_id (ticket_id),
            KEY related_ticket_id (related_ticket_id),
            KEY relation_type (relation_type)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Resolve a ticket input (ID or ticket number) to ticket row.
     *
     * @param int|string $input Ticket ID or ticket number.
     * @return object|null
     */
    private static function resolve_ticket( $input ) {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-ticket-table.php';
        if ( is_numeric( $input ) ) {
            return Arkidevs_Support_Ticket_Table::get_by_id( (int) $input );
        }
        return Arkidevs_Support_Ticket_Table::get_by_ticket_number( sanitize_text_field( $input ) );
    }

    /**
     * Add relation between two tickets (undirected).
     *
     * @param int|string $ticket_id Ticket ID.
     * @param int|string $related_input Related ticket ID or number.
     * @param string     $relation_type Relation type (default: related).
     * @param int        $created_by WP user ID.
     * @return true|WP_Error
     */
    public static function add_relation( $ticket_id, $related_input, $relation_type = 'related', $created_by = 0 ) {
        self::ensure_table();

        $ticket_id = (int) $ticket_id;
        if ( ! $ticket_id ) {
            return new WP_Error( 'invalid_ticket', __( 'Invalid ticket.', 'arkidevs-support' ) );
        }

        $related = self::resolve_ticket( $related_input );
        if ( ! $related ) {
            return new WP_Error( 'related_not_found', __( 'Related ticket not found.', 'arkidevs-support' ) );
        }
        $related_id = (int) $related->id;

        if ( $related_id === $ticket_id ) {
            return new WP_Error( 'same_ticket', __( 'You cannot relate a ticket to itself.', 'arkidevs-support' ) );
        }

        // Normalize ordering to keep uniqueness simple (undirected relation).
        $a = min( $ticket_id, $related_id );
        $b = max( $ticket_id, $related_id );

        global $wpdb;
        $table = self::get_table_name();

        $result = $wpdb->replace(
            $table,
            array(
                'ticket_id'          => $a,
                'related_ticket_id'  => $b,
                'relation_type'      => sanitize_text_field( $relation_type ),
                'created_by'         => $created_by ? (int) $created_by : null,
                'created_at'         => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%d', '%s' )
        );

        if ( false === $result ) {
            return new WP_Error( 'relation_failed', __( 'Unable to save relationship.', 'arkidevs-support' ) );
        }

        return true;
    }

    /**
     * Remove relation between two tickets.
     *
     * @param int $ticket_id Ticket ID.
     * @param int $related_id Related ticket ID.
     * @return bool
     */
    public static function remove_relation( $ticket_id, $related_id ) {
        self::ensure_table();
        $a = min( (int) $ticket_id, (int) $related_id );
        $b = max( (int) $ticket_id, (int) $related_id );

        global $wpdb;
        $table = self::get_table_name();

        return (bool) $wpdb->delete(
            $table,
            array(
                'ticket_id'         => $a,
                'related_ticket_id' => $b,
            ),
            array( '%d', '%d' )
        );
    }

    /**
     * Get relations for a ticket.
     *
     * @param int $ticket_id Ticket ID.
     * @return array
     */
    public static function get_relations( $ticket_id ) {
        self::ensure_table();
        global $wpdb;
        $table = self::get_table_name();
        $ticket_id = (int) $ticket_id;

        // Fetch both directions because we store normalized pairs.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE ticket_id = %d OR related_ticket_id = %d ORDER BY created_at DESC",
                $ticket_id,
                $ticket_id
            )
        );

        if ( empty( $rows ) ) {
            return array();
        }

        // Collect counterpart IDs
        $related_ids = array();
        foreach ( $rows as $row ) {
            $related_ids[] = ( $row->ticket_id == $ticket_id ) ? (int) $row->related_ticket_id : (int) $row->ticket_id;
        }
        $related_ids = array_values( array_unique( $related_ids ) );

        if ( empty( $related_ids ) ) {
            return array();
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-ticket-table.php';
        $placeholders = implode( ',', array_fill( 0, count( $related_ids ), '%d' ) );
        $tickets = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . Arkidevs_Support_Ticket_Table::get_table_name() . " WHERE id IN ({$placeholders})",
                $related_ids
            ),
            OBJECT_K
        );

        $result = array();
        foreach ( $rows as $row ) {
            $other_id = ( $row->ticket_id == $ticket_id ) ? (int) $row->related_ticket_id : (int) $row->ticket_id;
            if ( isset( $tickets[ $other_id ] ) ) {
                $result[] = array(
                    'relation_type' => $row->relation_type,
                    'created_at'    => $row->created_at,
                    'created_by'    => $row->created_by,
                    'ticket'        => $tickets[ $other_id ],
                );
            }
        }

        return $result;
    }
}

