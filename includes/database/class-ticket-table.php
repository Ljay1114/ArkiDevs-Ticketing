<?php
/**
 * Ticket table handler
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Ticket_Table
 */
class Arkidevs_Support_Ticket_Table {
    /**
     * Check if merge columns exist on tickets table
     *
     * @return bool
     */
    private static function has_merge_columns() {
        static $has = null;
        if ( null !== $has ) {
            return $has;
        }

        global $wpdb;
        $table = self::get_table_name();

        $col = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$table} LIKE %s",
                'merged_into_ticket_id'
            )
        );

        $has = ! empty( $col );
        return $has;
    }

    /**
     * Get table name
     *
     * @return string
     */
    public static function get_table_name() {
        return Arkidevs_Support_DB::get_table_name( 'tickets' );
    }

    /**
     * Insert ticket
     *
     * @param array $data Ticket data
     * @return int|false Ticket ID on success, false on failure
     */
    public static function insert( $data ) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'ticket_number' => '',
            'customer_id'   => 0,
            'agent_id'      => null,
            'subject'       => '',
            'description'   => '',
            'status'        => 'open',
            'priority'      => 'medium',
        );

        $data = wp_parse_args( $data, $defaults );

        $result = $wpdb->insert(
            $table,
            $data,
            array( '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
        );

        if ( $result ) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get ticket by ID
     *
     * @param int $ticket_id Ticket ID
     * @return object|null
     */
    public static function get_by_id( $ticket_id ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $ticket_id
            )
        );
    }

    /**
     * Get ticket by ticket number
     *
     * @param string $ticket_number Ticket number
     * @return object|null
     */
    public static function get_by_ticket_number( $ticket_number ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE ticket_number = %s",
                $ticket_number
            )
        );
    }

    /**
     * Update ticket
     *
     * @param int   $ticket_id Ticket ID
     * @param array $data Data to update
     * @return bool
     */
    public static function update( $ticket_id, $data ) {
        global $wpdb;
        $table = self::get_table_name();

        // Always update updated_at timestamp
        $data['updated_at'] = current_time( 'mysql' );

        // If status is being set to closed, set closed_at
        if ( isset( $data['status'] ) && 'closed' === $data['status'] ) {
            $existing = self::get_by_id( $ticket_id );
            if ( $existing && 'closed' !== $existing->status ) {
                $data['closed_at'] = current_time( 'mysql' );
            }
        }

        $result = $wpdb->update(
            $table,
            $data,
            array( 'id' => $ticket_id ),
            null,
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Delete ticket
     *
     * @param int $ticket_id Ticket ID
     * @return bool
     */
    public static function delete( $ticket_id ) {
        global $wpdb;
        $table = self::get_table_name();

        return (bool) $wpdb->delete(
            $table,
            array( 'id' => $ticket_id ),
            array( '%d' )
        );
    }

    /**
     * Get tickets by customer
     *
     * @param int $customer_id Customer ID
     * @return array
     */
    public static function get_by_customer( $customer_id ) {
        global $wpdb;
        $table = self::get_table_name();

        $where = 'customer_id = %d';
        if ( self::has_merge_columns() ) {
            $where .= ' AND merged_into_ticket_id IS NULL';
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC",
                $customer_id
            )
        );
    }

    /**
     * Get tickets by agent
     *
     * @param int $agent_id Agent ID
     * @return array
     */
    public static function get_by_agent( $agent_id ) {
        global $wpdb;
        $table = self::get_table_name();

        $where = 'agent_id = %d';
        if ( self::has_merge_columns() ) {
            $where .= ' AND merged_into_ticket_id IS NULL';
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC",
                $agent_id
            )
        );
    }

    /**
     * Get tickets by status
     *
     * @param string $status Status
     * @return array
     */
    public static function get_by_status( $status ) {
        global $wpdb;
        $table = self::get_table_name();

        $where = 'status = %s';
        if ( self::has_merge_columns() ) {
            $where .= ' AND merged_into_ticket_id IS NULL';
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC",
                $status
            )
        );
    }

    /**
     * Get all tickets
     *
     * @param array $args Query arguments
     * @return array
     */
    public static function get_all( $args = array() ) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'status'   => '',
            'priority' => '',
            'limit'    => 100,
            'offset'   => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ( ! empty( $args['priority'] ) ) {
            $where[] = 'priority = %s';
            $values[] = $args['priority'];
        }

        // Hide merged tickets by default.
        if ( self::has_merge_columns() ) {
            $where[] = 'merged_into_ticket_id IS NULL';
        }

        $where_clause = implode( ' AND ', $where );

        $query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];

        if ( ! empty( $values ) ) {
            return $wpdb->get_results( $wpdb->prepare( $query, $values ) );
        }

        return $wpdb->get_results( $query );
    }

    /**
     * Search tickets with optional filters (for agents/admins)
     *
     * @param array $args Search and filter arguments.
     * @return array
     */
    public static function search( $args = array() ) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'search'      => '',
            'status'      => '',
            'priority'    => '',
            'customer_id' => 0,
            'agent_id'    => 0,
            'tag_id'      => 0,
            'date_from'   => '',
            'date_to'     => '',
            'limit'       => 100,
            'offset'      => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $values = array();
        $join   = '';

        // Hide merged tickets by default.
        if ( self::has_merge_columns() ) {
            $where[] = 'merged_into_ticket_id IS NULL';
        }

        // Text search in subject, description, and ticket number.
        if ( ! empty( $args['search'] ) ) {
            $search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[]     = '(subject LIKE %s OR description LIKE %s OR ticket_number LIKE %s)';
            $values[]    = $search_term;
            $values[]    = $search_term;
            $values[]    = $search_term;
        }

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'status = %s';
            $values[] = $args['status'];
        }

        if ( ! empty( $args['priority'] ) ) {
            $where[]  = 'priority = %s';
            $values[] = $args['priority'];
        }

        if ( ! empty( $args['customer_id'] ) ) {
            $where[]  = 'customer_id = %d';
            $values[] = (int) $args['customer_id'];
        }

        if ( ! empty( $args['agent_id'] ) ) {
            if ( 'unassigned' === $args['agent_id'] ) {
                $where[] = '(agent_id IS NULL OR agent_id = 0)';
            } else {
                $where[]  = 'agent_id = %d';
                $values[] = (int) $args['agent_id'];
            }
        }

        if ( ! empty( $args['date_from'] ) ) {
            $where[]  = 'DATE(created_at) >= %s';
            $values[] = $args['date_from'];
        }

        if ( ! empty( $args['date_to'] ) ) {
            $where[]  = 'DATE(created_at) <= %s';
            $values[] = $args['date_to'];
        }

        // Tag filtering
        if ( ! empty( $args['tag_id'] ) ) {
            $tag_relationships_table = $wpdb->prefix . 'arkidevs_ticket_tag_relationships';
            $join = "INNER JOIN {$tag_relationships_table} tr ON t.id = tr.ticket_id";
            $where[]  = 'tr.tag_id = %d';
            $values[] = (int) $args['tag_id'];
        }

        $where_clause = implode( ' AND ', $where );

        // Use alias for table if join is present
        $table_alias = ! empty( $join ) ? 't' : $table;
        $from_clause = ! empty( $join ) ? "{$table} t {$join}" : $table;

        $query    = "SELECT DISTINCT {$table_alias}.* FROM {$from_clause} WHERE {$where_clause} ORDER BY {$table_alias}.created_at DESC LIMIT %d OFFSET %d";
        $values[] = (int) $args['limit'];
        $values[] = (int) $args['offset'];

        return $wpdb->get_results( $wpdb->prepare( $query, $values ) );
    }

    /**
     * Search tickets for a specific customer.
     *
     * @param int   $customer_id Customer ID.
     * @param array $args        Search and filter arguments.
     * @return array
     */
    public static function search_by_customer( $customer_id, $args = array() ) {
        $args['customer_id'] = (int) $customer_id;
        return self::search( $args );
    }
}


