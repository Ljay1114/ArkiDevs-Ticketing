<?php
/**
 * Ticket merge utilities
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Ticket_Merge
 */
class Arkidevs_Support_Ticket_Merge {

    /**
     * Merge one or more source tickets into a target ticket.
     *
     * Preserves all replies, attachments, time logs, and tags by re-pointing their ticket_id to the target.
     * Marks the source tickets as merged and stores merge metadata.
     *
     * @param int[] $source_ticket_ids Source ticket IDs (will be merged into target)
     * @param int   $target_ticket_id  Target ticket ID
     * @param int   $merged_by         User ID performing the merge
     * @return true|WP_Error
     */
    public static function merge_into( $source_ticket_ids, $target_ticket_id, $merged_by = 0 ) {
        global $wpdb;

        $source_ticket_ids = array_values( array_filter( array_map( 'intval', (array) $source_ticket_ids ) ) );
        $target_ticket_id  = (int) $target_ticket_id;
        $merged_by         = (int) $merged_by;

        if ( empty( $source_ticket_ids ) || ! $target_ticket_id ) {
            return new WP_Error( 'invalid_params', __( 'Invalid merge parameters.', 'arkidevs-support' ) );
        }

        // Remove target from sources if included.
        $source_ticket_ids = array_values( array_diff( $source_ticket_ids, array( $target_ticket_id ) ) );
        if ( empty( $source_ticket_ids ) ) {
            return new WP_Error( 'nothing_to_merge', __( 'No source tickets to merge.', 'arkidevs-support' ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-ticket-table.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';

        $target = Arkidevs_Support_Ticket_Table::get_by_id( $target_ticket_id );
        if ( ! $target ) {
            return new WP_Error( 'target_not_found', __( 'Target ticket not found.', 'arkidevs-support' ) );
        }

        // Disallow merging into a ticket that is itself merged.
        if ( ! empty( $target->merged_into_ticket_id ) ) {
            return new WP_Error( 'target_is_merged', __( 'Target ticket is already merged into another ticket. Please use the final ticket.', 'arkidevs-support' ) );
        }

        $tickets_table       = $wpdb->prefix . 'arkidevs_tickets';
        $replies_table       = $wpdb->prefix . 'arkidevs_ticket_replies';
        $attachments_table   = $wpdb->prefix . 'arkidevs_ticket_attachments';
        $time_logs_table     = $wpdb->prefix . 'arkidevs_time_logs';
        $tag_rel_table       = $wpdb->prefix . 'arkidevs_ticket_tag_relationships';

        // Fetch source tickets.
        $placeholders = implode( ',', array_fill( 0, count( $source_ticket_ids ), '%d' ) );
        $source_rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$tickets_table} WHERE id IN ({$placeholders})",
                $source_ticket_ids
            )
        );

        if ( empty( $source_rows ) ) {
            return new WP_Error( 'source_not_found', __( 'Source ticket(s) not found.', 'arkidevs-support' ) );
        }

        // Validate same customer unless admin.
        $is_admin = current_user_can( 'manage_options' );
        foreach ( $source_rows as $src ) {
            if ( ! $is_admin && (int) $src->customer_id !== (int) $target->customer_id ) {
                return new WP_Error( 'different_customer', __( 'Tickets must belong to the same customer to be merged.', 'arkidevs-support' ) );
            }
            if ( ! empty( $src->merged_into_ticket_id ) ) {
                return new WP_Error( 'already_merged', __( 'One of the selected tickets is already merged into another ticket.', 'arkidevs-support' ) );
            }
        }

        // Prevent merging tickets with active timers.
        $active_timer_ids = array();
        foreach ( $source_ticket_ids as $sid ) {
            $active = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$time_logs_table} WHERE ticket_id = %d AND end_time IS NULL",
                    $sid
                )
            );
            if ( intval( $active ) > 0 ) {
                $active_timer_ids[] = $sid;
            }
        }
        if ( ! empty( $active_timer_ids ) ) {
            return new WP_Error(
                'active_timer',
                sprintf(
                    /* translators: %s is comma-separated ticket IDs */
                    __( 'Cannot merge tickets with active timers. Stop the timers first for ticket ID(s): %s', 'arkidevs-support' ),
                    implode( ', ', array_map( 'intval', $active_timer_ids ) )
                )
            );
        }

        $merged_at = current_time( 'mysql' );

        // Attempt transaction (best-effort).
        $wpdb->query( 'START TRANSACTION' );
        $failed = false;

        try {
            foreach ( $source_rows as $src ) {
                $src_id = (int) $src->id;

                // Move replies.
                $wpdb->update(
                    $replies_table,
                    array( 'ticket_id' => $target_ticket_id ),
                    array( 'ticket_id' => $src_id ),
                    array( '%d' ),
                    array( '%d' )
                );

                // Move attachments.
                $wpdb->update(
                    $attachments_table,
                    array( 'ticket_id' => $target_ticket_id ),
                    array( 'ticket_id' => $src_id ),
                    array( '%d' ),
                    array( '%d' )
                );

                // Move time logs.
                $wpdb->update(
                    $time_logs_table,
                    array( 'ticket_id' => $target_ticket_id ),
                    array( 'ticket_id' => $src_id ),
                    array( '%d' ),
                    array( '%d' )
                );

                // Merge tags: insert missing tag relationships into target.
                $tag_ids = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT tag_id FROM {$tag_rel_table} WHERE ticket_id = %d",
                        $src_id
                    )
                );
                if ( ! empty( $tag_ids ) ) {
                    foreach ( $tag_ids as $tag_id ) {
                        $wpdb->query(
                            $wpdb->prepare(
                                "INSERT IGNORE INTO {$tag_rel_table} (ticket_id, tag_id, created_at) VALUES (%d, %d, %s)",
                                $target_ticket_id,
                                (int) $tag_id,
                                $merged_at
                            )
                        );
                    }
                    // Remove tag relationships from source.
                    $wpdb->delete(
                        $tag_rel_table,
                        array( 'ticket_id' => $src_id ),
                        array( '%d' )
                    );
                }

                // Mark source ticket as merged.
                $update = $wpdb->update(
                    $tickets_table,
                    array(
                        'status'               => 'merged',
                        'merged_into_ticket_id' => $target_ticket_id,
                        'merged_at'            => $merged_at,
                        'merged_by'            => $merged_by ? $merged_by : get_current_user_id(),
                        'closed_at'            => $merged_at,
                    ),
                    array( 'id' => $src_id ),
                    array( '%s', '%d', '%s', '%d', '%s' ),
                    array( '%d' )
                );

                if ( false === $update ) {
                    $failed = true;
                    break;
                }
            }

            if ( $failed ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'merge_failed', __( 'Ticket merge failed. No changes were applied.', 'arkidevs-support' ) );
            }

            // Add an internal system note on target describing the merge.
            $target_ticket = Arkidevs_Support_Ticket::get_ticket( $target_ticket_id );
            if ( $target_ticket ) {
                $lines = array();
                foreach ( $source_rows as $src ) {
                    $lines[] = sprintf( '#%s - %s (ID: %d)', $src->ticket_number, $src->subject, (int) $src->id );
                }
                $note = sprintf(
                    "Merged tickets into this ticket on %s by %s:\n%s",
                    $merged_at,
                    Arkidevs_Support_Utils::get_user_display_name( $merged_by ? $merged_by : get_current_user_id() ),
                    implode( "\n", $lines )
                );
                // Internal note (agents/admins only).
                $target_ticket->add_reply( $note, $merged_by ? $merged_by : get_current_user_id(), null, 1 );
            }

            // Audit log: record merge events.
            if ( class_exists( 'Arkidevs_Support_Audit_Log' ) ) {
                $actor = $merged_by ? $merged_by : get_current_user_id();
                foreach ( $source_rows as $src ) {
                    Arkidevs_Support_Audit_Log::log_event(
                        (int) $src->id,
                        'merged',
                        sprintf( 'Merged into ticket ID %d', (int) $target_ticket_id ),
                        $actor
                    );
                }
                Arkidevs_Support_Audit_Log::log_event(
                    (int) $target_ticket_id,
                    'merge_target',
                    sprintf(
                        'Received merged tickets: %s',
                        implode( ', ', array_map( function( $s ) { return (string) (int) $s->id; }, (array) $source_rows ) )
                    ),
                    $actor
                );
            }

            $wpdb->query( 'COMMIT' );
            return true;
        } catch ( Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'merge_exception', __( 'Ticket merge failed due to an unexpected error.', 'arkidevs-support' ) );
        }
    }
}

