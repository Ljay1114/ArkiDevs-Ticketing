<?php
/**
 * SLA Reports
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_SLA_Reports
 */
class Arkidevs_Support_SLA_Reports {

    /**
     * Get SLA performance report
     *
     * @param array $args Report arguments
     * @return array
     */
    public static function get_performance_report( $args = array() ) {
        global $wpdb;
        $tracking_table = $wpdb->prefix . 'arkidevs_sla_tracking';
        $tickets_table = $wpdb->prefix . 'arkidevs_tickets';

        // Check if tables exist
        $tracking_table_exists = $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $tracking_table
        ) );

        if ( ! $tracking_table_exists ) {
            // Return empty report if table doesn't exist
            return array(
                'total_tickets'           => 0,
                'first_response_met'      => 0,
                'first_response_total'    => 0,
                'first_response_rate'     => 0,
                'resolution_met'          => 0,
                'resolution_total'        => 0,
                'resolution_rate'         => 0,
                'avg_first_response_hours' => 0,
                'avg_resolution_hours'    => 0,
            );
        }

        $defaults = array(
            'date_from' => '',
            'date_to'   => '',
            'priority'  => '',
            'status'    => '',
        );

        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $args['date_from'] ) ) {
            $where[] = 't.created_at >= %s';
            $values[] = $args['date_from'];
        }

        if ( ! empty( $args['date_to'] ) ) {
            $where[] = 't.created_at <= %s';
            $values[] = $args['date_to'];
        }

        if ( ! empty( $args['priority'] ) ) {
            $where[] = 't.priority = %s';
            $values[] = $args['priority'];
        }

        if ( ! empty( $args['status'] ) ) {
            $where[] = 't.status = %s';
            $values[] = $args['status'];
        }

        $where_clause = implode( ' AND ', $where );

        $query = "SELECT 
                    COUNT(*) as total_tickets,
                    SUM(CASE WHEN st.first_response_met = 1 THEN 1 ELSE 0 END) as first_response_met,
                    SUM(CASE WHEN st.first_response_actual IS NOT NULL THEN 1 ELSE 0 END) as first_response_total,
                    SUM(CASE WHEN st.resolution_met = 1 THEN 1 ELSE 0 END) as resolution_met,
                    SUM(CASE WHEN st.resolution_actual IS NOT NULL THEN 1 ELSE 0 END) as resolution_total,
                    AVG(CASE WHEN st.first_response_actual IS NOT NULL 
                        THEN TIMESTAMPDIFF(HOUR, t.created_at, st.first_response_actual) 
                        ELSE NULL END) as avg_first_response_hours,
                    AVG(CASE WHEN st.resolution_actual IS NOT NULL 
                        THEN TIMESTAMPDIFF(HOUR, t.created_at, st.resolution_actual) 
                        ELSE NULL END) as avg_resolution_hours
                  FROM {$tickets_table} t
                  INNER JOIN {$tracking_table} st ON t.id = st.ticket_id
                  WHERE {$where_clause}";

        if ( ! empty( $values ) ) {
            $results = $wpdb->get_row( $wpdb->prepare( $query, $values ) );
        } else {
            $results = $wpdb->get_row( $query );
        }

        $report = array(
            'total_tickets'           => intval( $results->total_tickets ?? 0 ),
            'first_response_met'      => intval( $results->first_response_met ?? 0 ),
            'first_response_total'    => intval( $results->first_response_total ?? 0 ),
            'first_response_rate'     => 0,
            'resolution_met'          => intval( $results->resolution_met ?? 0 ),
            'resolution_total'        => intval( $results->resolution_total ?? 0 ),
            'resolution_rate'         => 0,
            'avg_first_response_hours' => round( floatval( $results->avg_first_response_hours ?? 0 ), 2 ),
            'avg_resolution_hours'    => round( floatval( $results->avg_resolution_hours ?? 0 ), 2 ),
        );

        // Calculate rates
        if ( $report['first_response_total'] > 0 ) {
            $report['first_response_rate'] = round( ( $report['first_response_met'] / $report['first_response_total'] ) * 100, 2 );
        }

        if ( $report['resolution_total'] > 0 ) {
            $report['resolution_rate'] = round( ( $report['resolution_met'] / $report['resolution_total'] ) * 100, 2 );
        }

        return $report;
    }

    /**
     * Get overdue tickets report
     *
     * @return array
     */
    public static function get_overdue_report() {
        global $wpdb;
        $tracking_table = $wpdb->prefix . 'arkidevs_sla_tracking';

        // Check if table exists
        $tracking_table_exists = $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $tracking_table
        ) );

        if ( ! $tracking_table_exists ) {
            return array(
                'total_overdue' => 0,
                'tickets'       => array(),
            );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/sla/class-sla-tracker.php';
        $overdue_tickets = Arkidevs_Support_SLA_Tracker::get_overdue_tickets();

        $report = array(
            'total_overdue' => count( $overdue_tickets ),
            'tickets'       => array(),
        );

        foreach ( $overdue_tickets as $ticket ) {
            $sla_status = Arkidevs_Support_SLA_Tracker::get_ticket_sla_status( $ticket->id );
            if ( ! is_wp_error( $sla_status ) ) {
                $report['tickets'][] = array(
                    'ticket_id'   => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'subject'     => $ticket->subject,
                    'priority'    => $ticket->priority,
                    'status'      => $ticket->status,
                    'sla_status' => $sla_status,
                );
            }
        }

        return $report;
    }
}
