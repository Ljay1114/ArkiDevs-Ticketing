<?php
/**
 * SLA Rule management
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_SLA_Rule
 */
class Arkidevs_Support_SLA_Rule {

    /**
     * Get table name
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'arkidevs_sla_rules';
    }

    /**
     * Create SLA rule
     *
     * @param array $data Rule data
     * @return int|WP_Error Rule ID on success, WP_Error on failure
     */
    public static function create( $data ) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'name'                => '',
            'priority'           => 'medium',
            'first_response_hours' => 24,
            'resolution_hours'    => 72,
            'enabled'            => 1,
        );

        $data = wp_parse_args( $data, $defaults );

        if ( empty( $data['name'] ) ) {
            return new WP_Error( 'missing_name', __( 'Rule name is required.', 'arkidevs-support' ) );
        }

        $result = $wpdb->insert(
            $table,
            array(
                'name'                => sanitize_text_field( $data['name'] ),
                'priority'            => sanitize_text_field( $data['priority'] ),
                'first_response_hours' => intval( $data['first_response_hours'] ),
                'resolution_hours'     => intval( $data['resolution_hours'] ),
                'enabled'              => intval( $data['enabled'] ),
            ),
            array( '%s', '%s', '%d', '%d', '%d' )
        );

        if ( false === $result ) {
            return new WP_Error( 'insert_failed', __( 'Failed to create SLA rule.', 'arkidevs-support' ) );
        }

        return $wpdb->insert_id;
    }

    /**
     * Get rule by ID
     *
     * @param int $rule_id Rule ID
     * @return object|null
     */
    public static function get_by_id( $rule_id ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $rule_id
            )
        );
    }

    /**
     * Get rule by priority
     *
     * @param string $priority Priority level
     * @return object|null
     */
    public static function get_by_priority( $priority ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE priority = %s AND enabled = 1 ORDER BY id DESC LIMIT 1",
                $priority
            )
        );
    }

    /**
     * Get all rules
     *
     * @param array $args Query arguments
     * @return array
     */
    public static function get_all( $args = array() ) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'enabled' => '',
            'orderby' => 'priority',
            'order'   => 'ASC',
        );

        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $values = array();

        if ( '' !== $args['enabled'] ) {
            $where[] = 'enabled = %d';
            $values[] = intval( $args['enabled'] );
        }

        $where_clause = implode( ' AND ', $where );
        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );

        if ( ! empty( $values ) ) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby}",
                $values
            );
        } else {
            $query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby}";
        }

        return $wpdb->get_results( $query );
    }

    /**
     * Update rule
     *
     * @param int   $rule_id Rule ID
     * @param array $data Data to update
     * @return bool|WP_Error
     */
    public static function update( $rule_id, $data ) {
        global $wpdb;
        $table = self::get_table_name();

        $update_data = array();
        $format = array();

        if ( isset( $data['name'] ) ) {
            $update_data['name'] = sanitize_text_field( $data['name'] );
            $format[] = '%s';
        }

        if ( isset( $data['priority'] ) ) {
            $update_data['priority'] = sanitize_text_field( $data['priority'] );
            $format[] = '%s';
        }

        if ( isset( $data['first_response_hours'] ) ) {
            $update_data['first_response_hours'] = intval( $data['first_response_hours'] );
            $format[] = '%d';
        }

        if ( isset( $data['resolution_hours'] ) ) {
            $update_data['resolution_hours'] = intval( $data['resolution_hours'] );
            $format[] = '%d';
        }

        if ( isset( $data['enabled'] ) ) {
            $update_data['enabled'] = intval( $data['enabled'] );
            $format[] = '%d';
        }

        if ( empty( $update_data ) ) {
            return new WP_Error( 'no_data', __( 'No data to update.', 'arkidevs-support' ) );
        }

        $result = $wpdb->update(
            $table,
            $update_data,
            array( 'id' => $rule_id ),
            $format,
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Delete rule
     *
     * @param int $rule_id Rule ID
     * @return bool
     */
    public static function delete( $rule_id ) {
        global $wpdb;
        $table = self::get_table_name();

        return (bool) $wpdb->delete(
            $table,
            array( 'id' => $rule_id ),
            array( '%d' )
        );
    }
}
