<?php
/**
 * Analytics and reporting utilities.
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Analytics
 */
class Arkidevs_Support_Analytics {
    /**
     * Tickets grouped by status or priority over time.
     *
     * @param int    $days     Days lookback.
     * @param string $group_by status|priority
     * @return array
     */
    public static function tickets_over_time( $days = 30, $group_by = 'status' ) {
        global $wpdb;
        $table = Arkidevs_Support_Ticket_Table::get_table_name();

        $group_by = in_array( $group_by, array( 'status', 'priority' ), true ) ? $group_by : 'status';
        $days     = max( 1, intval( $days ) );

        $query = $wpdb->prepare(
            "SELECT DATE(created_at) as day, {$group_by} as group_key, COUNT(*) as total
             FROM {$table}
             WHERE created_at >= DATE_SUB( CURRENT_DATE(), INTERVAL %d DAY )
               AND status != %s
             GROUP BY day, group_key
             ORDER BY day ASC",
            $days,
            'merged'
        );

        $rows = $wpdb->get_results( $query );

        $data = array();
        foreach ( $rows as $row ) {
            $day = $row->day;
            $group_value = $row->group_key;
            if ( ! isset( $data[ $day ] ) ) {
                $data[ $day ] = array();
            }
            $data[ $day ][ $group_value ] = intval( $row->total );
        }

        return $data;
    }

    /**
     * Agent performance metrics.
     *
     * @param int $days Days lookback.
     * @return array[]
     */
    public static function agent_performance( $days = 30 ) {
        global $wpdb;
        $tickets_table = Arkidevs_Support_Ticket_Table::get_table_name();
        $tracking_table = Arkidevs_Support_SLA_Tracker::get_table_name();
        $time_table     = Arkidevs_Support_Time_Log_Table::get_table_name();

        $days = max( 1, intval( $days ) );

        $tracking_exists = Arkidevs_Support_SLA_Tracker::table_exists( $tracking_table );
        $time_exists     = Arkidevs_Support_Time_Log_Table::table_exists();

        $select = array(
            't.agent_id',
            'COUNT(*) AS total_assigned',
            'SUM(CASE WHEN t.status = "closed" THEN 1 ELSE 0 END) AS resolved',
        );

        if ( $tracking_exists ) {
            $select[] = 'AVG(CASE WHEN st.first_response_actual IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created_at, st.first_response_actual) END) AS avg_first_response_hours';
            $select[] = 'AVG(CASE WHEN st.resolution_actual IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created_at, st.resolution_actual) END) AS avg_resolution_hours';
        } else {
            $select[] = 'NULL AS avg_first_response_hours';
            $select[] = 'NULL AS avg_resolution_hours';
        }

        if ( $time_exists ) {
            $select[] = 'SUM(tl.duration) AS time_logged';
        } else {
            $select[] = 'NULL AS time_logged';
        }

        $query  = "SELECT " . implode( ', ', $select ) . " FROM {$tickets_table} t";
        if ( $tracking_exists ) {
            $query .= " LEFT JOIN {$tracking_table} st ON st.ticket_id = t.id";
        }
        if ( $time_exists ) {
            $query .= " LEFT JOIN {$time_table} tl ON tl.ticket_id = t.id";
        }

        $query .= $wpdb->prepare(
            " WHERE t.created_at >= DATE_SUB( CURRENT_DATE(), INTERVAL %d DAY )
              AND t.agent_id IS NOT NULL
              AND t.status != %s
              GROUP BY t.agent_id",
            $days,
            'merged'
        );

        $rows = $wpdb->get_results( $query );

        $agents = array();
        foreach ( $rows as $row ) {
            $agent_id = intval( $row->agent_id );
            $user     = get_userdata( $agent_id );
            $agents[] = array(
                'agent_id'                 => $agent_id,
                'agent_name'               => $user ? Arkidevs_Support_Utils::get_user_display_name( $agent_id ) : __( 'Unassigned', 'arkidevs-support' ),
                'total_assigned'           => intval( $row->total_assigned ),
                'resolved'                 => intval( $row->resolved ),
                'avg_first_response_hours' => is_null( $row->avg_first_response_hours ) ? null : round( floatval( $row->avg_first_response_hours ), 2 ),
                'avg_resolution_hours'     => is_null( $row->avg_resolution_hours ) ? null : round( floatval( $row->avg_resolution_hours ), 2 ),
                'time_logged'              => is_null( $row->time_logged ) ? null : round( floatval( $row->time_logged ), 2 ),
            );
        }

        return $agents;
    }

    /**
     * Average resolution time (hours) for closed tickets.
     *
     * @param int $days Days lookback.
     * @return float|null
     */
    public static function average_resolution_time( $days = 30 ) {
        global $wpdb;
        $tracking_table = Arkidevs_Support_SLA_Tracker::get_table_name();
        $tickets_table  = Arkidevs_Support_Ticket_Table::get_table_name();

        if ( ! Arkidevs_Support_SLA_Tracker::table_exists( $tracking_table ) ) {
            return null;
        }

        $days = max( 1, intval( $days ) );

        $avg = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT AVG(TIMESTAMPDIFF(HOUR, t.created_at, st.resolution_actual))
                 FROM {$tracking_table} st
                 INNER JOIN {$tickets_table} t ON t.id = st.ticket_id
                 WHERE st.resolution_actual IS NOT NULL
                   AND t.status = 'closed'
                   AND t.status != %s
                   AND t.created_at >= DATE_SUB( CURRENT_DATE(), INTERVAL %d DAY )",
                'merged',
                $days
            )
        );

        return is_null( $avg ) ? null : round( floatval( $avg ), 2 );
    }

    /**
     * Customer satisfaction trend.
     *
     * @param int $days Days lookback.
     * @return array
     */
    public static function csat_trend( $days = 30 ) {
        global $wpdb;
        $table = Arkidevs_Support_Ticket_Rating::get_table_name();

        $days = max( 1, intval( $days ) );

        // Ensure ratings table exists
        Arkidevs_Support_Ticket_Rating::get_by_ticket( 0 ); // triggers ensure_table

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) as day, AVG(rating) as avg_rating, COUNT(*) as total
                 FROM {$table}
                 WHERE created_at >= DATE_SUB( CURRENT_DATE(), INTERVAL %d DAY )
                 GROUP BY day
                 ORDER BY day ASC",
                $days
            )
        );

        $data = array();
        foreach ( $rows as $row ) {
            $data[ $row->day ] = array(
                'avg_rating' => round( floatval( $row->avg_rating ), 2 ),
                'total'      => intval( $row->total ),
            );
        }

        return $data;
    }
}

