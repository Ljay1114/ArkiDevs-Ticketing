<?php
/**
 * Escalation Rule management
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Escalation_Rule
 */
class Arkidevs_Support_Escalation_Rule {

    /**
     * Get table name
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'arkidevs_escalation_rules';
    }

    /**
     * Ensure table exists
     */
    public static function ensure_table_exists() {
        global $wpdb;
        $table = self::get_table_name();

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
                name varchar(255) NOT NULL,
                trigger_type varchar(50) NOT NULL COMMENT 'time, priority, inactivity',
                trigger_value int(11) UNSIGNED NOT NULL COMMENT 'Hours or priority level',
                priority_filter varchar(50) DEFAULT NULL COMMENT 'Filter by priority (optional)',
                status_filter varchar(50) DEFAULT NULL COMMENT 'Filter by status (optional)',
                action_type varchar(50) NOT NULL COMMENT 'notify, assign, priority_change',
                action_value varchar(255) DEFAULT NULL COMMENT 'Supervisor ID, new priority, etc.',
                enabled tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY trigger_type (trigger_type),
                KEY enabled (enabled),
                KEY priority_filter (priority_filter),
                KEY status_filter (status_filter)
            ) $charset_collate;";
            dbDelta( $sql );
        }
    }

    /**
     * Create escalation rule
     *
     * @param array $data Rule data
     * @return int|WP_Error Rule ID on success, WP_Error on failure
     */
    public static function create( $data ) {
        self::ensure_table_exists();

        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'name'           => '',
            'trigger_type'  => 'time',
            'trigger_value' => 24,
            'priority_filter' => null,
            'status_filter'  => null,
            'action_type'    => 'notify',
            'action_value'   => null,
            'enabled'        => 1,
        );

        $data = wp_parse_args( $data, $defaults );

        if ( empty( $data['name'] ) ) {
            return new WP_Error( 'missing_name', __( 'Rule name is required.', 'arkidevs-support' ) );
        }

        if ( ! in_array( $data['trigger_type'], array( 'time', 'priority', 'inactivity' ), true ) ) {
            return new WP_Error( 'invalid_trigger', __( 'Invalid trigger type.', 'arkidevs-support' ) );
        }

        if ( ! in_array( $data['action_type'], array( 'notify', 'assign', 'priority_change' ), true ) ) {
            return new WP_Error( 'invalid_action', __( 'Invalid action type.', 'arkidevs-support' ) );
        }

        $result = $wpdb->insert(
            $table,
            array(
                'name'           => sanitize_text_field( $data['name'] ),
                'trigger_type'  => sanitize_text_field( $data['trigger_type'] ),
                'trigger_value' => intval( $data['trigger_value'] ),
                'priority_filter' => ! empty( $data['priority_filter'] ) ? sanitize_text_field( $data['priority_filter'] ) : null,
                'status_filter'  => ! empty( $data['status_filter'] ) ? sanitize_text_field( $data['status_filter'] ) : null,
                'action_type'    => sanitize_text_field( $data['action_type'] ),
                'action_value'   => ! empty( $data['action_value'] ) ? sanitize_text_field( $data['action_value'] ) : null,
                'enabled'        => intval( $data['enabled'] ),
            ),
            array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d' )
        );

        if ( false === $result ) {
            return new WP_Error( 'insert_failed', __( 'Failed to create escalation rule.', 'arkidevs-support' ) );
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
        self::ensure_table_exists();

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
     * Get all rules
     *
     * @param array $args Query arguments
     * @return array
     */
    public static function get_all( $args = array() ) {
        self::ensure_table_exists();

        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'enabled' => '',
            'trigger_type' => '',
            'orderby' => 'id',
            'order'   => 'ASC',
        );

        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $values = array();

        if ( '' !== $args['enabled'] ) {
            $where[] = 'enabled = %d';
            $values[] = intval( $args['enabled'] );
        }

        if ( ! empty( $args['trigger_type'] ) ) {
            $where[] = 'trigger_type = %s';
            $values[] = sanitize_text_field( $args['trigger_type'] );
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
        self::ensure_table_exists();

        global $wpdb;
        $table = self::get_table_name();

        $update_data = array();
        $format = array();

        if ( isset( $data['name'] ) ) {
            $update_data['name'] = sanitize_text_field( $data['name'] );
            $format[] = '%s';
        }

        if ( isset( $data['trigger_type'] ) ) {
            if ( ! in_array( $data['trigger_type'], array( 'time', 'priority', 'inactivity' ), true ) ) {
                return new WP_Error( 'invalid_trigger', __( 'Invalid trigger type.', 'arkidevs-support' ) );
            }
            $update_data['trigger_type'] = sanitize_text_field( $data['trigger_type'] );
            $format[] = '%s';
        }

        if ( isset( $data['trigger_value'] ) ) {
            $update_data['trigger_value'] = intval( $data['trigger_value'] );
            $format[] = '%d';
        }

        if ( isset( $data['priority_filter'] ) ) {
            $update_data['priority_filter'] = ! empty( $data['priority_filter'] ) ? sanitize_text_field( $data['priority_filter'] ) : null;
            $format[] = '%s';
        }

        if ( isset( $data['status_filter'] ) ) {
            $update_data['status_filter'] = ! empty( $data['status_filter'] ) ? sanitize_text_field( $data['status_filter'] ) : null;
            $format[] = '%s';
        }

        if ( isset( $data['action_type'] ) ) {
            if ( ! in_array( $data['action_type'], array( 'notify', 'assign', 'priority_change' ), true ) ) {
                return new WP_Error( 'invalid_action', __( 'Invalid action type.', 'arkidevs-support' ) );
            }
            $update_data['action_type'] = sanitize_text_field( $data['action_type'] );
            $format[] = '%s';
        }

        if ( isset( $data['action_value'] ) ) {
            $update_data['action_value'] = ! empty( $data['action_value'] ) ? sanitize_text_field( $data['action_value'] ) : null;
            $format[] = '%s';
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
        self::ensure_table_exists();

        global $wpdb;
        $table = self::get_table_name();

        return (bool) $wpdb->delete(
            $table,
            array( 'id' => $rule_id ),
            array( '%d' )
        );
    }
}
