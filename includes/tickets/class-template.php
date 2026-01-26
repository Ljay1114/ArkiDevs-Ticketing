<?php
/**
 * Ticket template handler
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Template
 */
class Arkidevs_Support_Template {

    /**
     * Get table name
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'arkidevs_ticket_templates';
        
        // Ensure table exists (for existing installations)
        self::ensure_table_exists();
        
        return $table_name;
    }

    /**
     * Ensure templates table exists
     */
    private static function ensure_table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'arkidevs_ticket_templates';
        
        // Static flag to only check once per request
        static $checked = false;
        if ( $checked ) {
            return;
        }
        $checked = true;
        
        // Check if table exists by querying information_schema
        $table_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $table_name
        ) );
        
        if ( ! $table_exists ) {
            // Table doesn't exist, create it
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            
            $charset_collate = $wpdb->get_charset_collate();
            
            $templates_sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                content longtext NOT NULL,
                category varchar(100) DEFAULT 'general',
                created_by bigint(20) UNSIGNED NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY category (category),
                KEY created_by (created_by)
            ) $charset_collate;";
            
            dbDelta( $templates_sql );
        }
    }

    /**
     * Create template
     *
     * @param array $data Template data
     * @return int|WP_Error Template ID on success, WP_Error on failure
     */
    public static function create( $data ) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'name'       => '',
            'content'   => '',
            'category'  => 'general',
            'created_by' => get_current_user_id(),
        );

        $data = wp_parse_args( $data, $defaults );

        if ( empty( $data['name'] ) ) {
            return new WP_Error( 'missing_name', __( 'Template name is required.', 'arkidevs-support' ) );
        }

        if ( empty( $data['content'] ) ) {
            return new WP_Error( 'missing_content', __( 'Template content is required.', 'arkidevs-support' ) );
        }

        $result = $wpdb->insert(
            $table,
            array(
                'name'       => sanitize_text_field( $data['name'] ),
                'content'   => wp_kses_post( $data['content'] ),
                'category'  => sanitize_text_field( $data['category'] ),
                'created_by' => (int) $data['created_by'],
            ),
            array( '%s', '%s', '%s', '%d' )
        );

        if ( false === $result ) {
            return new WP_Error( 'insert_failed', __( 'Failed to create template.', 'arkidevs-support' ) );
        }

        return $wpdb->insert_id;
    }

    /**
     * Get template by ID
     *
     * @param int $template_id Template ID
     * @return object|null
     */
    public static function get_by_id( $template_id ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $template_id
            )
        );
    }

    /**
     * Get all templates
     *
     * @param array $args Query arguments
     * @return array
     */
    public static function get_all( $args = array() ) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'category' => '',
            'orderby'  => 'name',
            'order'    => 'ASC',
        );

        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $args['category'] ) ) {
            $where[] = 'category = %s';
            $values[] = $args['category'];
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
     * Update template
     *
     * @param int   $template_id Template ID
     * @param array $data Data to update
     * @return bool|WP_Error
     */
    public static function update( $template_id, $data ) {
        global $wpdb;
        $table = self::get_table_name();

        $update_data = array();
        $format = array();

        if ( isset( $data['name'] ) ) {
            $update_data['name'] = sanitize_text_field( $data['name'] );
            $format[] = '%s';
        }

        if ( isset( $data['content'] ) ) {
            $update_data['content'] = wp_kses_post( $data['content'] );
            $format[] = '%s';
        }

        if ( isset( $data['category'] ) ) {
            $update_data['category'] = sanitize_text_field( $data['category'] );
            $format[] = '%s';
        }

        if ( empty( $update_data ) ) {
            return new WP_Error( 'no_data', __( 'No data to update.', 'arkidevs-support' ) );
        }

        $result = $wpdb->update(
            $table,
            $update_data,
            array( 'id' => $template_id ),
            $format,
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Delete template
     *
     * @param int $template_id Template ID
     * @return bool
     */
    public static function delete( $template_id ) {
        global $wpdb;
        $table = self::get_table_name();

        return (bool) $wpdb->delete(
            $table,
            array( 'id' => $template_id ),
            array( '%d' )
        );
    }

    /**
     * Get available categories
     *
     * @return array
     */
    public static function get_categories() {
        return array(
            'general'     => __( 'General', 'arkidevs-support' ),
            'greeting'    => __( 'Greeting', 'arkidevs-support' ),
            'closing'     => __( 'Closing', 'arkidevs-support' ),
            'technical'   => __( 'Technical Support', 'arkidevs-support' ),
            'billing'     => __( 'Billing', 'arkidevs-support' ),
            'refund'      => __( 'Refund', 'arkidevs-support' ),
            'escalation'  => __( 'Escalation', 'arkidevs-support' ),
            'follow_up'   => __( 'Follow Up', 'arkidevs-support' ),
        );
    }

    /**
     * Replace variables in template content
     *
     * @param string $content Template content with variables
     * @param array  $context Context data (ticket, customer, agent, etc.)
     * @return string Processed content
     */
    public static function replace_variables( $content, $context = array() ) {
        $defaults = array(
            'ticket'   => null,
            'customer' => null,
            'agent'    => null,
        );

        $context = wp_parse_args( $context, $defaults );

        // Available variables
        $variables = array();

        // Ticket variables
        if ( $context['ticket'] ) {
            $ticket = $context['ticket'];
            $variables['{ticket_number}'] = isset( $ticket->ticket_number ) ? $ticket->ticket_number : '';
            $variables['{ticket_subject}'] = isset( $ticket->subject ) ? $ticket->subject : '';
            $variables['{ticket_status}'] = isset( $ticket->status ) ? $ticket->status : '';
            $variables['{ticket_priority}'] = isset( $ticket->priority ) ? $ticket->priority : '';
            $variables['{ticket_id}'] = isset( $ticket->id ) ? $ticket->id : '';
        }

        // Load Utils class if not already loaded
        if ( ! class_exists( 'Arkidevs_Support_Utils' ) ) {
            require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';
        }

        // Customer variables
        if ( $context['customer'] ) {
            $customer = $context['customer'];
            if ( is_object( $customer ) && isset( $customer->ID ) ) {
                $variables['{customer_name}'] = Arkidevs_Support_Utils::get_user_display_name( $customer->ID );
                $variables['{customer_email}'] = isset( $customer->user_email ) ? $customer->user_email : '';
                $variables['{customer_first_name}'] = isset( $customer->first_name ) ? $customer->first_name : '';
                $variables['{customer_last_name}'] = isset( $customer->last_name ) ? $customer->last_name : '';
            } elseif ( is_numeric( $customer ) ) {
                $customer_obj = get_userdata( $customer );
                if ( $customer_obj ) {
                    $variables['{customer_name}'] = Arkidevs_Support_Utils::get_user_display_name( $customer );
                    $variables['{customer_email}'] = $customer_obj->user_email;
                    $variables['{customer_first_name}'] = $customer_obj->first_name;
                    $variables['{customer_last_name}'] = $customer_obj->last_name;
                }
            }
        }

        // Agent variables
        if ( $context['agent'] ) {
            $agent = $context['agent'];
            if ( is_object( $agent ) && isset( $agent->ID ) ) {
                $variables['{agent_name}'] = Arkidevs_Support_Utils::get_user_display_name( $agent->ID );
                $variables['{agent_email}'] = isset( $agent->user_email ) ? $agent->user_email : '';
            } elseif ( is_numeric( $agent ) ) {
                $agent_obj = get_userdata( $agent );
                if ( $agent_obj ) {
                    $variables['{agent_name}'] = Arkidevs_Support_Utils::get_user_display_name( $agent );
                    $variables['{agent_email}'] = $agent_obj->user_email;
                }
            }
        } else {
            // Use current user if agent not provided
            $current_user = wp_get_current_user();
            if ( $current_user && $current_user->ID ) {
                $variables['{agent_name}'] = Arkidevs_Support_Utils::get_user_display_name( $current_user->ID );
                $variables['{agent_email}'] = $current_user->user_email;
            }
        }

        // Date/time variables
        $variables['{current_date}'] = date_i18n( get_option( 'date_format' ) );
        $variables['{current_time}'] = date_i18n( get_option( 'time_format' ) );
        $variables['{current_datetime}'] = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );

        // Site variables
        $variables['{site_name}'] = get_bloginfo( 'name' );
        $variables['{site_url}'] = home_url();

        // Replace all variables
        $content = str_replace( array_keys( $variables ), array_values( $variables ), $content );

        return $content;
    }
}
