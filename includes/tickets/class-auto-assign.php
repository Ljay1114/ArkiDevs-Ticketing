<?php
/**
 * Auto-assignment rules management class
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Auto_Assign
 */
class Arkidevs_Support_Auto_Assign {

    /**
     * Get table name
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'arkidevs_ticket_auto_assign';
    }

    /**
     * Create an auto-assignment rule
     *
     * @param array $data Rule data (tag_id, agent_id, priority, enabled)
     * @return int|WP_Error Rule ID on success, WP_Error on failure
     */
    public static function create( $data ) {
        global $wpdb;
        $table = self::get_table_name();

        $tag_id = isset( $data['tag_id'] ) ? intval( $data['tag_id'] ) : 0;
        $agent_id = isset( $data['agent_id'] ) ? intval( $data['agent_id'] ) : 0;
        $priority = isset( $data['priority'] ) ? sanitize_text_field( $data['priority'] ) : 'medium';
        $enabled = isset( $data['enabled'] ) ? (int) $data['enabled'] : 1;

        if ( ! $tag_id ) {
            return new WP_Error( 'missing_tag', __( 'Tag ID is required.', 'arkidevs-support' ) );
        }

        if ( ! $agent_id ) {
            return new WP_Error( 'missing_agent', __( 'Agent ID is required.', 'arkidevs-support' ) );
        }

        // Verify tag exists
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-tag.php';
        $tag = Arkidevs_Support_Tag::get_by_id( $tag_id );
        if ( ! $tag ) {
            return new WP_Error( 'invalid_tag', __( 'Invalid tag ID.', 'arkidevs-support' ) );
        }

        // Verify agent exists and is an agent
        $agent = get_userdata( $agent_id );
        if ( ! $agent ) {
            return new WP_Error( 'invalid_agent', __( 'Invalid agent ID.', 'arkidevs-support' ) );
        }

        $is_valid_agent = in_array( 'arkidevs_agent', $agent->roles, true ) || in_array( 'administrator', $agent->roles, true );
        if ( ! $is_valid_agent ) {
            return new WP_Error( 'invalid_agent', __( 'Selected user is not an agent.', 'arkidevs-support' ) );
        }

        $result = $wpdb->insert(
            $table,
            array(
                'tag_id'   => $tag_id,
                'agent_id' => $agent_id,
                'priority' => $priority,
                'enabled'  => $enabled,
            ),
            array( '%d', '%d', '%s', '%d' )
        );

        if ( $result ) {
            return $wpdb->insert_id;
        }

        return new WP_Error( 'create_failed', __( 'Failed to create auto-assignment rule.', 'arkidevs-support' ) );
    }

    /**
     * Get all auto-assignment rules
     *
     * @param bool $enabled_only Only return enabled rules
     * @return array
     */
    public static function get_all( $enabled_only = false ) {
        global $wpdb;
        $table = self::get_table_name();

        $where = $enabled_only ? 'WHERE enabled = 1' : '';

        return $wpdb->get_results(
            "SELECT * FROM {$table} {$where} ORDER BY id ASC"
        );
    }

    /**
     * Get rules for a specific tag
     *
     * @param int  $tag_id       Tag ID
     * @param bool $enabled_only Only return enabled rules
     * @return array
     */
    public static function get_by_tag( $tag_id, $enabled_only = true ) {
        global $wpdb;
        $table = self::get_table_name();

        $where = 'WHERE tag_id = %d';
        if ( $enabled_only ) {
            $where .= ' AND enabled = 1';
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} {$where} ORDER BY id ASC",
                $tag_id
            )
        );
    }

    /**
     * Get agent assignment for a ticket based on tags
     *
     * @param int $ticket_id Ticket ID
     * @return int|null Agent ID or null if no match
     */
    public static function get_agent_for_ticket( $ticket_id ) {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-tag.php';
        
        $tags = Arkidevs_Support_Tag::get_ticket_tags( $ticket_id );
        if ( empty( $tags ) ) {
            return null;
        }

        // Get all enabled rules
        $rules = self::get_all( true );

        // Find matching rule for any of the ticket's tags
        foreach ( $tags as $tag ) {
            foreach ( $rules as $rule ) {
                if ( (int) $rule->tag_id === (int) $tag->id ) {
                    return (int) $rule->agent_id;
                }
            }
        }

        return null;
    }

    /**
     * Update rule
     *
     * @param int   $rule_id Rule ID
     * @param array $data    Data to update
     * @return bool|WP_Error
     */
    public static function update( $rule_id, $data ) {
        global $wpdb;
        $table = self::get_table_name();

        $update_data = array();
        $format = array();

        if ( isset( $data['tag_id'] ) ) {
            $tag_id = intval( $data['tag_id'] );
            require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-tag.php';
            $tag = Arkidevs_Support_Tag::get_by_id( $tag_id );
            if ( ! $tag ) {
                return new WP_Error( 'invalid_tag', __( 'Invalid tag ID.', 'arkidevs-support' ) );
            }
            $update_data['tag_id'] = $tag_id;
            $format[] = '%d';
        }

        if ( isset( $data['agent_id'] ) ) {
            $agent_id = intval( $data['agent_id'] );
            $agent = get_userdata( $agent_id );
            if ( ! $agent ) {
                return new WP_Error( 'invalid_agent', __( 'Invalid agent ID.', 'arkidevs-support' ) );
            }
            $is_valid_agent = in_array( 'arkidevs_agent', $agent->roles, true ) || in_array( 'administrator', $agent->roles, true );
            if ( ! $is_valid_agent ) {
                return new WP_Error( 'invalid_agent', __( 'Selected user is not an agent.', 'arkidevs-support' ) );
            }
            $update_data['agent_id'] = $agent_id;
            $format[] = '%d';
        }

        if ( isset( $data['priority'] ) ) {
            $update_data['priority'] = sanitize_text_field( $data['priority'] );
            $format[] = '%s';
        }

        if ( isset( $data['enabled'] ) ) {
            $update_data['enabled'] = (int) $data['enabled'];
            $format[] = '%d';
        }

        if ( empty( $update_data ) ) {
            return true;
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
