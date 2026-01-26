<?php
/**
 * SLA Tracking and calculation
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_SLA_Tracker
 */
class Arkidevs_Support_SLA_Tracker {

    /**
     * Get tracking table name
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'arkidevs_sla_tracking';
    }

    /**
     * Check if tracking table exists.
     *
     * @param string|null $table Optional table name.
     * @return bool
     */
    public static function table_exists( $table = null ) {
        global $wpdb;
        $table = $table ? $table : self::get_table_name();

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            )
        );

        return ! empty( $exists );
    }

    /**
     * Initialize SLA tracking for a ticket
     *
     * @param int $ticket_id Ticket ID
     * @return int|WP_Error Tracking ID on success
     */
    public static function initialize_ticket( $ticket_id ) {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/sla/class-sla-rule.php';

        $ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );
        if ( ! $ticket ) {
            return new WP_Error( 'invalid_ticket', __( 'Invalid ticket.', 'arkidevs-support' ) );
        }

        // Get SLA rule for ticket priority
        $sla_rule = Arkidevs_Support_SLA_Rule::get_by_priority( $ticket->priority );
        if ( ! $sla_rule ) {
            return new WP_Error( 'no_sla_rule', __( 'No SLA rule found for this priority.', 'arkidevs-support' ) );
        }

        // Calculate target times
        $created_time = strtotime( $ticket->created_at );
        $first_response_target = $created_time + ( $sla_rule->first_response_hours * 3600 );
        $resolution_target = $created_time + ( $sla_rule->resolution_hours * 3600 );

        global $wpdb;
        $table = self::get_table_name();

        // Check if tracking already exists
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE ticket_id = %d",
                $ticket_id
            )
        );

        if ( $existing ) {
            // Update existing tracking
            $wpdb->update(
                $table,
                array(
                    'sla_rule_id'           => $sla_rule->id,
                    'first_response_target' => date( 'Y-m-d H:i:s', $first_response_target ),
                    'resolution_target'     => date( 'Y-m-d H:i:s', $resolution_target ),
                ),
                array( 'id' => $existing->id ),
                array( '%d', '%s', '%s' ),
                array( '%d' )
            );
            return $existing->id;
        } else {
            // Create new tracking
            $result = $wpdb->insert(
                $table,
                array(
                    'ticket_id'            => $ticket_id,
                    'sla_rule_id'          => $sla_rule->id,
                    'first_response_target' => date( 'Y-m-d H:i:s', $first_response_target ),
                    'resolution_target'     => date( 'Y-m-d H:i:s', $resolution_target ),
                ),
                array( '%d', '%d', '%s', '%s' )
            );

            if ( $result ) {
                return $wpdb->insert_id;
            }
        }

        return new WP_Error( 'tracking_failed', __( 'Failed to initialize SLA tracking.', 'arkidevs-support' ) );
    }

    /**
     * Record first response
     *
     * @param int $ticket_id Ticket ID
     * @return bool|WP_Error
     */
    public static function record_first_response( $ticket_id ) {
        global $wpdb;
        $table = self::get_table_name();

        // Check if table exists
        $table_exists = $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ) );

        if ( ! $table_exists ) {
            return new WP_Error( 'no_table', __( 'SLA tracking table does not exist.', 'arkidevs-support' ) );
        }

        // Get tracking record
        $tracking = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE ticket_id = %d",
                $ticket_id
            )
        );

        if ( ! $tracking ) {
            // Try to initialize if not exists
            $init_result = self::initialize_ticket( $ticket_id );
            if ( is_wp_error( $init_result ) ) {
                // If initialization fails (e.g., no SLA rule), just return true
                return true;
            }
            $tracking = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE ticket_id = %d",
                    $ticket_id
                )
            );
        }

        if ( ! $tracking || ! empty( $tracking->first_response_actual ) ) {
            // Already recorded or no tracking available
            return true;
        }

        $response_time = current_time( 'mysql' );
        $target_time = strtotime( $tracking->first_response_target );
        $actual_time = strtotime( $response_time );
        $met = ( $actual_time <= $target_time ) ? 1 : 0;

        // Update tracking
        $result = $wpdb->update(
            $table,
            array(
                'first_response_actual' => $response_time,
                'first_response_met'   => $met,
            ),
            array( 'id' => $tracking->id ),
            array( '%s', '%d' ),
            array( '%d' )
        );

        // Update ticket (check if column exists first)
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-ticket-table.php';
        global $wpdb;
        $tickets_table = $wpdb->prefix . 'arkidevs_tickets';
        $column_exists = $wpdb->get_var( $wpdb->prepare(
            "SHOW COLUMNS FROM {$tickets_table} LIKE %s",
            'first_response_time'
        ) );
        if ( $column_exists ) {
            Arkidevs_Support_Ticket_Table::update( $ticket_id, array(
                'first_response_time' => $response_time,
            ) );
        }

        return false !== $result;
    }

    /**
     * Record resolution
     *
     * @param int $ticket_id Ticket ID
     * @return bool|WP_Error
     */
    public static function record_resolution( $ticket_id ) {
        global $wpdb;
        $table = self::get_table_name();

        // Check if table exists
        $table_exists = $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ) );

        if ( ! $table_exists ) {
            return new WP_Error( 'no_table', __( 'SLA tracking table does not exist.', 'arkidevs-support' ) );
        }

        // Get tracking record
        $tracking = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE ticket_id = %d",
                $ticket_id
            )
        );

        if ( ! $tracking ) {
            // Try to initialize if not exists
            $init_result = self::initialize_ticket( $ticket_id );
            if ( is_wp_error( $init_result ) ) {
                // If initialization fails (e.g., no SLA rule), just return true
                return true;
            }
            $tracking = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE ticket_id = %d",
                    $ticket_id
                )
            );
        }

        if ( ! $tracking || ! empty( $tracking->resolution_actual ) ) {
            // Already recorded or no tracking available
            return true;
        }

        $resolution_time = current_time( 'mysql' );
        $target_time = strtotime( $tracking->resolution_target );
        $actual_time = strtotime( $resolution_time );
        $met = ( $actual_time <= $target_time ) ? 1 : 0;

        // Update tracking
        $result = $wpdb->update(
            $table,
            array(
                'resolution_actual' => $resolution_time,
                'resolution_met'   => $met,
            ),
            array( 'id' => $tracking->id ),
            array( '%s', '%d' ),
            array( '%d' )
        );

        // Update ticket (check if column exists first)
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-ticket-table.php';
        global $wpdb;
        $tickets_table = $wpdb->prefix . 'arkidevs_tickets';
        $column_exists = $wpdb->get_var( $wpdb->prepare(
            "SHOW COLUMNS FROM {$tickets_table} LIKE %s",
            'resolution_time'
        ) );
        if ( $column_exists ) {
            Arkidevs_Support_Ticket_Table::update( $ticket_id, array(
                'resolution_time' => $resolution_time,
            ) );
        }

        return false !== $result;
    }

    /**
     * Get SLA status for ticket
     *
     * @param int $ticket_id Ticket ID
     * @return array|WP_Error
     */
    public static function get_ticket_sla_status( $ticket_id ) {
        global $wpdb;
        $table = self::get_table_name();

        // Check if table exists
        $table_exists = $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ) );

        if ( ! $table_exists ) {
            return new WP_Error( 'no_table', __( 'SLA tracking table does not exist.', 'arkidevs-support' ) );
        }

        $tracking = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE ticket_id = %d",
                $ticket_id
            )
        );

        if ( ! $tracking ) {
            // Try to initialize if not exists
            $init_result = self::initialize_ticket( $ticket_id );
            if ( is_wp_error( $init_result ) ) {
                // Return empty status if initialization fails (e.g., no SLA rule)
                return array(
                    'first_response' => array(
                        'target'      => null,
                        'actual'      => null,
                        'met'         => false,
                        'overdue'     => false,
                        'hours_overdue' => 0,
                    ),
                    'resolution' => array(
                        'target'      => null,
                        'actual'      => null,
                        'met'         => false,
                        'overdue'     => false,
                        'hours_overdue' => 0,
                    ),
                );
            }
            $tracking = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE ticket_id = %d",
                    $ticket_id
                )
            );
        }

        if ( ! $tracking ) {
            return new WP_Error( 'no_tracking', __( 'No SLA tracking found.', 'arkidevs-support' ) );
        }

        $current_time = current_time( 'timestamp' );
        $first_response_target = strtotime( $tracking->first_response_target );
        $resolution_target = strtotime( $tracking->resolution_target );

        $status = array(
            'first_response' => array(
                'target'      => $tracking->first_response_target,
                'actual'      => $tracking->first_response_actual,
                'met'         => (bool) $tracking->first_response_met,
                'overdue'     => ! $tracking->first_response_actual && ( $current_time > $first_response_target ),
                'hours_overdue' => ! $tracking->first_response_actual && ( $current_time > $first_response_target ) 
                    ? round( ( $current_time - $first_response_target ) / 3600, 1 ) 
                    : 0,
            ),
            'resolution' => array(
                'target'      => $tracking->resolution_target,
                'actual'      => $tracking->resolution_actual,
                'met'         => (bool) $tracking->resolution_met,
                'overdue'     => ! $tracking->resolution_actual && ( $current_time > $resolution_target ),
                'hours_overdue' => ! $tracking->resolution_actual && ( $current_time > $resolution_target ) 
                    ? round( ( $current_time - $resolution_target ) / 3600, 1 ) 
                    : 0,
            ),
        );

        return $status;
    }

    /**
     * Check if ticket is overdue
     *
     * @param int $ticket_id Ticket ID
     * @return bool
     */
    public static function is_ticket_overdue( $ticket_id ) {
        $status = self::get_ticket_sla_status( $ticket_id );
        
        if ( is_wp_error( $status ) ) {
            return false;
        }

        return $status['first_response']['overdue'] || $status['resolution']['overdue'];
    }

    /**
     * Get overdue tickets
     *
     * @param array $args Query arguments
     * @return array
     */
    public static function get_overdue_tickets( $args = array() ) {
        global $wpdb;
        $table = self::get_table_name();
        $tickets_table = $wpdb->prefix . 'arkidevs_tickets';

        $current_time = current_time( 'mysql' );

        $query = "SELECT t.*, st.first_response_target, st.resolution_target, st.first_response_actual, st.resolution_actual
                  FROM {$tickets_table} t
                  INNER JOIN {$table} st ON t.id = st.ticket_id
                  WHERE t.status != 'closed'
                  AND (
                      (st.first_response_actual IS NULL AND st.first_response_target < %s)
                      OR
                      (st.resolution_actual IS NULL AND st.resolution_target < %s)
                  )
                  ORDER BY 
                      CASE 
                          WHEN st.first_response_actual IS NULL AND st.first_response_target < %s THEN st.first_response_target
                          ELSE st.resolution_target
                      END ASC";

        return $wpdb->get_results( $wpdb->prepare( $query, $current_time, $current_time, $current_time ) );
    }
}
