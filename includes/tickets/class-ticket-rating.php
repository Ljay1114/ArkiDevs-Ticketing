<?php
/**
 * Ticket rating (CSAT) handler.
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Ticket_Rating
 */
class Arkidevs_Support_Ticket_Rating {
    /**
     * Get table name.
     *
     * @return string
     */
    public static function get_table_name() {
        return Arkidevs_Support_DB::get_table_name( 'ticket_ratings' );
    }

    /**
     * Ensure table exists (runtime safety for older installs).
     */
    private static function ensure_table() {
        global $wpdb;
        $table = self::get_table_name();

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ) );

        if ( $exists ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $sql             = "CREATE TABLE {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) UNSIGNED NOT NULL,
            customer_id bigint(20) UNSIGNED NOT NULL,
            rating tinyint(1) UNSIGNED NOT NULL,
            feedback text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY ticket_id_unique (ticket_id),
            KEY ticket_id (ticket_id),
            KEY customer_id (customer_id),
            KEY rating (rating)
        ) {$charset_collate};";
        dbDelta( $sql );
    }

    /**
     * Get rating by ticket ID.
     *
     * @param int $ticket_id Ticket ID.
     * @return object|null
     */
    public static function get_by_ticket( $ticket_id ) {
        self::ensure_table();
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE ticket_id = %d",
                $ticket_id
            )
        );
    }

    /**
     * Create or update rating for a ticket.
     *
     * @param int    $ticket_id   Ticket ID.
     * @param int    $customer_id Customer ID.
     * @param int    $rating      Rating (1-5).
     * @param string $feedback    Optional feedback.
     * @return bool|WP_Error
     */
    public static function save_rating( $ticket_id, $customer_id, $rating, $feedback = '' ) {
        self::ensure_table();
        global $wpdb;
        $table = self::get_table_name();

        $rating  = max( 1, min( 5, intval( $rating ) ) );
        $feedback = wp_kses_post( $feedback );

        $existing = self::get_by_ticket( $ticket_id );
        if ( $existing ) {
            return (bool) $wpdb->update(
                $table,
                array(
                    'rating'   => $rating,
                    'feedback' => $feedback,
                ),
                array( 'ticket_id' => $ticket_id ),
                array( '%d', '%s' ),
                array( '%d' )
            );
        }

        return (bool) $wpdb->insert(
            $table,
            array(
                'ticket_id'   => $ticket_id,
                'customer_id' => $customer_id,
                'rating'      => $rating,
                'feedback'    => $feedback,
            ),
            array( '%d', '%d', '%d', '%s' )
        );
    }
}

