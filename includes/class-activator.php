<?php
/**
 * Plugin activation handler
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Activator
 */
class Arkidevs_Support_Activator {

    /**
     * Activate plugin
     */
    public static function activate() {
        // Create user roles
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/auth/class-roles.php';
        Arkidevs_Support_Roles::create_roles();

        // Create database tables
        self::create_tables();

        // Set default options
        update_option( 'arkidevs_support_version', ARKIDEVS_SUPPORT_VERSION );
        update_option( 'arkidevs_support_db_version', '1.0' );

        // Register rewrite rules first
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/dashboards/class-customer-dashboard.php';
        $customer_dashboard = Arkidevs_Support_Customer_Dashboard::get_instance();
        $customer_dashboard->add_rewrite_rules();

        // Flush rewrite rules
        flush_rewrite_rules();
        update_option( 'arkidevs_support_rewrite_rules_flushed', true );
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        // Tickets table
        $tickets_table = $wpdb->prefix . 'arkidevs_tickets';
        $tickets_sql = "CREATE TABLE IF NOT EXISTS {$tickets_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_number varchar(50) NOT NULL,
            customer_id bigint(20) UNSIGNED NOT NULL,
            agent_id bigint(20) UNSIGNED DEFAULT NULL,
            subject varchar(255) NOT NULL,
            description longtext NOT NULL,
            status varchar(50) DEFAULT 'open',
            priority varchar(50) DEFAULT 'medium',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            closed_at datetime DEFAULT NULL,
            merged_into_ticket_id bigint(20) UNSIGNED DEFAULT NULL,
            merged_at datetime DEFAULT NULL,
            merged_by bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY ticket_number (ticket_number),
            KEY customer_id (customer_id),
            KEY agent_id (agent_id),
            KEY status (status),
            KEY priority (priority),
            KEY merged_into_ticket_id (merged_into_ticket_id)
        ) $charset_collate;";
        dbDelta( $tickets_sql );

        // Ticket replies table
        $replies_table = $wpdb->prefix . 'arkidevs_ticket_replies';
        $replies_sql = "CREATE TABLE IF NOT EXISTS {$replies_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            parent_reply_id bigint(20) UNSIGNED DEFAULT NULL,
            message longtext NOT NULL,
            is_internal tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY ticket_id (ticket_id),
            KEY user_id (user_id),
            KEY parent_reply_id (parent_reply_id),
            KEY is_internal (is_internal)
        ) $charset_collate;";
        dbDelta( $replies_sql );

        // Add parent_reply_id column if it doesn't exist (for existing installations)
        $column_exists = $wpdb->get_results( $wpdb->prepare(
            "SHOW COLUMNS FROM {$replies_table} LIKE %s",
            'parent_reply_id'
        ) );
        if ( empty( $column_exists ) ) {
            $wpdb->query( "ALTER TABLE {$replies_table} ADD COLUMN parent_reply_id bigint(20) UNSIGNED DEFAULT NULL AFTER user_id" );
            $wpdb->query( "ALTER TABLE {$replies_table} ADD KEY parent_reply_id (parent_reply_id)" );
        }

        // Add is_internal column if it doesn't exist (for existing installations)
        $internal_column_exists = $wpdb->get_results( $wpdb->prepare(
            "SHOW COLUMNS FROM {$replies_table} LIKE %s",
            'is_internal'
        ) );
        if ( empty( $internal_column_exists ) ) {
            $wpdb->query( "ALTER TABLE {$replies_table} ADD COLUMN is_internal tinyint(1) UNSIGNED NOT NULL DEFAULT 0 AFTER message" );
            $wpdb->query( "ALTER TABLE {$replies_table} ADD KEY is_internal (is_internal)" );
        }

        // Store plugin version for future migrations
        update_option( 'arkidevs_support_version', ARKIDEVS_SUPPORT_VERSION );

        // Time logs table
        $time_logs_table = $wpdb->prefix . 'arkidevs_time_logs';
        $time_logs_sql = "CREATE TABLE IF NOT EXISTS {$time_logs_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) UNSIGNED NOT NULL,
            agent_id bigint(20) UNSIGNED NOT NULL,
            customer_id bigint(20) UNSIGNED NOT NULL,
            start_time datetime NOT NULL,
            end_time datetime DEFAULT NULL,
            duration decimal(10,2) DEFAULT 0.00 COMMENT 'Duration in hours',
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY ticket_id (ticket_id),
            KEY agent_id (agent_id),
            KEY customer_id (customer_id)
        ) $charset_collate;";
        dbDelta( $time_logs_sql );

        // Ticket ratings (customer satisfaction)
        $ratings_table = $wpdb->prefix . 'arkidevs_ticket_ratings';
        $ratings_sql   = "CREATE TABLE IF NOT EXISTS {$ratings_table} (
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
        ) $charset_collate;";
        dbDelta( $ratings_sql );

        // Customer hours table
        $customer_hours_table = $wpdb->prefix . 'arkidevs_customer_hours';
        $customer_hours_sql = "CREATE TABLE IF NOT EXISTS {$customer_hours_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) UNSIGNED NOT NULL,
            hours_allocated decimal(10,2) DEFAULT 0.00,
            hours_spent decimal(10,2) DEFAULT 0.00,
            hours_remaining decimal(10,2) DEFAULT 0.00,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY customer_id (customer_id)
        ) $charset_collate;";
        dbDelta( $customer_hours_sql );

        // Ticket attachments table
        $attachments_table = $wpdb->prefix . 'arkidevs_ticket_attachments';
        $attachments_sql = "CREATE TABLE IF NOT EXISTS {$attachments_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) UNSIGNED NOT NULL,
            reply_id bigint(20) UNSIGNED DEFAULT NULL,
            file_name varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_type varchar(100) NOT NULL,
            file_size bigint(20) UNSIGNED NOT NULL,
            uploaded_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY ticket_id (ticket_id),
            KEY reply_id (reply_id)
        ) $charset_collate;";
        dbDelta( $attachments_sql );

        // Ticket tags table
        $tags_table = $wpdb->prefix . 'arkidevs_ticket_tags';
        $tags_sql = "CREATE TABLE IF NOT EXISTS {$tags_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            color varchar(7) DEFAULT '#0073aa',
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY name (name)
        ) $charset_collate;";
        dbDelta( $tags_sql );

        // Ticket tag relationships table
        $tag_relationships_table = $wpdb->prefix . 'arkidevs_ticket_tag_relationships';
        $tag_relationships_sql = "CREATE TABLE IF NOT EXISTS {$tag_relationships_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) UNSIGNED NOT NULL,
            tag_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY ticket_tag (ticket_id, tag_id),
            KEY ticket_id (ticket_id),
            KEY tag_id (tag_id)
        ) $charset_collate;";
        dbDelta( $tag_relationships_sql );

        // Auto-assignment rules table
        $auto_assign_table = $wpdb->prefix . 'arkidevs_ticket_auto_assign';
        $auto_assign_sql = "CREATE TABLE IF NOT EXISTS {$auto_assign_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tag_id bigint(20) UNSIGNED NOT NULL,
            agent_id bigint(20) UNSIGNED NOT NULL,
            priority varchar(50) DEFAULT 'medium',
            enabled tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY tag_id (tag_id),
            KEY agent_id (agent_id),
            KEY enabled (enabled)
        ) $charset_collate;";
        dbDelta( $auto_assign_sql );

        // Ticket templates table
        $templates_table = $wpdb->prefix . 'arkidevs_ticket_templates';
        $templates_sql = "CREATE TABLE IF NOT EXISTS {$templates_table} (
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

        // Ticket audit log table (history of changes)
        $audit_table = $wpdb->prefix . 'arkidevs_ticket_audit_log';
        $audit_sql   = "CREATE TABLE IF NOT EXISTS {$audit_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) UNSIGNED NOT NULL,
            event_type varchar(50) NOT NULL,
            field_name varchar(100) DEFAULT NULL,
            old_value longtext DEFAULT NULL,
            new_value longtext DEFAULT NULL,
            actor_user_id bigint(20) UNSIGNED DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY ticket_id (ticket_id),
            KEY event_type (event_type),
            KEY actor_user_id (actor_user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta( $audit_sql );

        // Ticket relationships table
        $relations_table = $wpdb->prefix . 'arkidevs_ticket_relations';
        $relations_sql   = "CREATE TABLE IF NOT EXISTS {$relations_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) UNSIGNED NOT NULL,
            related_ticket_id bigint(20) UNSIGNED NOT NULL,
            relation_type varchar(50) DEFAULT 'related',
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY ticket_pair (ticket_id, related_ticket_id),
            KEY ticket_id (ticket_id),
            KEY related_ticket_id (related_ticket_id),
            KEY relation_type (relation_type)
        ) $charset_collate;";
        dbDelta( $relations_sql );

        // Add first_response_time and resolution_time columns to tickets table if they don't exist
        $first_response_column = $wpdb->get_results( $wpdb->prepare(
            "SHOW COLUMNS FROM {$tickets_table} LIKE %s",
            'first_response_time'
        ) );
        if ( empty( $first_response_column ) ) {
            $wpdb->query( "ALTER TABLE {$tickets_table} ADD COLUMN first_response_time datetime DEFAULT NULL AFTER closed_at" );
        }

        $resolution_time_column = $wpdb->get_results( $wpdb->prepare(
            "SHOW COLUMNS FROM {$tickets_table} LIKE %s",
            'resolution_time'
        ) );
        if ( empty( $resolution_time_column ) ) {
            $wpdb->query( "ALTER TABLE {$tickets_table} ADD COLUMN resolution_time datetime DEFAULT NULL AFTER first_response_time" );
        }

        // Add merge columns to tickets table if they don't exist (for existing installations)
        $merged_into_column = $wpdb->get_results( $wpdb->prepare(
            "SHOW COLUMNS FROM {$tickets_table} LIKE %s",
            'merged_into_ticket_id'
        ) );
        if ( empty( $merged_into_column ) ) {
            $wpdb->query( "ALTER TABLE {$tickets_table} ADD COLUMN merged_into_ticket_id bigint(20) UNSIGNED DEFAULT NULL AFTER closed_at" );
            $wpdb->query( "ALTER TABLE {$tickets_table} ADD KEY merged_into_ticket_id (merged_into_ticket_id)" );
        }

        $merged_at_column = $wpdb->get_results( $wpdb->prepare(
            "SHOW COLUMNS FROM {$tickets_table} LIKE %s",
            'merged_at'
        ) );
        if ( empty( $merged_at_column ) ) {
            $wpdb->query( "ALTER TABLE {$tickets_table} ADD COLUMN merged_at datetime DEFAULT NULL AFTER merged_into_ticket_id" );
        }

        $merged_by_column = $wpdb->get_results( $wpdb->prepare(
            "SHOW COLUMNS FROM {$tickets_table} LIKE %s",
            'merged_by'
        ) );
        if ( empty( $merged_by_column ) ) {
            $wpdb->query( "ALTER TABLE {$tickets_table} ADD COLUMN merged_by bigint(20) UNSIGNED DEFAULT NULL AFTER merged_at" );
        }

        // SLA rules table
        $sla_rules_table = $wpdb->prefix . 'arkidevs_sla_rules';
        $sla_rules_sql = "CREATE TABLE IF NOT EXISTS {$sla_rules_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            priority varchar(50) DEFAULT 'medium',
            first_response_hours int(11) UNSIGNED DEFAULT 24,
            resolution_hours int(11) UNSIGNED DEFAULT 72,
            enabled tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY priority (priority),
            KEY enabled (enabled)
        ) $charset_collate;";
        dbDelta( $sla_rules_sql );

        // SLA tracking table (for historical tracking)
        $sla_tracking_table = $wpdb->prefix . 'arkidevs_sla_tracking';
        $sla_tracking_sql = "CREATE TABLE IF NOT EXISTS {$sla_tracking_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) UNSIGNED NOT NULL,
            sla_rule_id bigint(20) UNSIGNED NOT NULL,
            first_response_target datetime DEFAULT NULL,
            first_response_actual datetime DEFAULT NULL,
            first_response_met tinyint(1) UNSIGNED DEFAULT 0,
            resolution_target datetime DEFAULT NULL,
            resolution_actual datetime DEFAULT NULL,
            resolution_met tinyint(1) UNSIGNED DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY ticket_id (ticket_id),
            KEY sla_rule_id (sla_rule_id),
            KEY first_response_met (first_response_met),
            KEY resolution_met (resolution_met)
        ) $charset_collate;";
        dbDelta( $sla_tracking_sql );
    }
}


