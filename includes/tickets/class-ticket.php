<?php
/**
 * Ticket management class
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Ticket
 */
class Arkidevs_Support_Ticket {

    /**
     * Ticket data
     *
     * @var object
     */
    public $data;

    /**
     * Constructor
     *
     * @param int|object $ticket Ticket ID or ticket object
     */
    public function __construct( $ticket = null ) {
        if ( is_numeric( $ticket ) ) {
            $this->data = Arkidevs_Support_Ticket_Table::get_by_id( $ticket );
        } elseif ( is_object( $ticket ) ) {
            $this->data = $ticket;
        }
    }

    /**
     * Get ticket by ID (static method)
     *
     * @param int $ticket_id Ticket ID
     * @return Arkidevs_Support_Ticket|null
     */
    public static function get_ticket( $ticket_id ) {
        $ticket_data = Arkidevs_Support_Ticket_Table::get_by_id( $ticket_id );
        return $ticket_data ? new self( $ticket_data ) : null;
    }

    /**
     * Create new ticket
     *
     * @param array $data Ticket data
     * @return Arkidevs_Support_Ticket|WP_Error
     */
    public static function create( $data ) {
        // Validate customer_id first
        $customer_id = isset( $data['customer_id'] ) ? intval( $data['customer_id'] ) : 0;
        if ( empty( $customer_id ) || $customer_id === 0 ) {
            return new WP_Error( 'missing_customer', __( 'Customer ID is required. Please make sure you are logged in.', 'arkidevs-support' ) );
        }

        // Sanitize data (now preserves customer_id)
        $data = Arkidevs_Support_Utils::sanitize_ticket_data( $data );

        // Ensure customer_id is set
        $data['customer_id'] = $customer_id;

        // Generate ticket number
        $data['ticket_number'] = Arkidevs_Support_Utils::generate_ticket_number();

        // Validate required fields
        if ( empty( $data['subject'] ) ) {
            return new WP_Error( 'missing_subject', __( 'Ticket subject is required.', 'arkidevs-support' ) );
        }

        if ( empty( $data['description'] ) ) {
            return new WP_Error( 'missing_description', __( 'Ticket description is required.', 'arkidevs-support' ) );
        }

        // Final validation of customer_id
        if ( empty( $data['customer_id'] ) || $data['customer_id'] === 0 ) {
            return new WP_Error( 'missing_customer', __( 'Customer ID is required. Please make sure you are logged in.', 'arkidevs-support' ) );
        }

        // Validate priority
        if ( ! empty( $data['priority'] ) && ! Arkidevs_Support_Priority::is_valid( $data['priority'] ) ) {
            return new WP_Error( 'invalid_priority', __( 'Invalid priority level.', 'arkidevs-support' ) );
        }

        // Extract tags from data (if provided)
        $tag_ids = isset( $data['tags'] ) ? $data['tags'] : array();
        unset( $data['tags'] ); // Remove from ticket data

        // Insert ticket
        $ticket_id = Arkidevs_Support_Ticket_Table::insert( $data );

        if ( ! $ticket_id ) {
            return new WP_Error( 'create_failed', __( 'Failed to create ticket.', 'arkidevs-support' ) );
        }

        // Get created ticket
        $ticket = self::get_ticket( $ticket_id );

        // Initialize SLA tracking (gracefully handle if no SLA rule exists)
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/sla/class-sla-tracker.php';
        $sla_init = Arkidevs_Support_SLA_Tracker::initialize_ticket( $ticket_id );
        // Don't fail ticket creation if SLA initialization fails (e.g., no SLA rule configured)
        if ( is_wp_error( $sla_init ) ) {
            // Log but don't block ticket creation
            error_log( 'SLA initialization failed for ticket ' . $ticket_id . ': ' . $sla_init->get_error_message() );
        }

        // Add tags to ticket
        if ( ! empty( $tag_ids ) && is_array( $tag_ids ) ) {
            require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-tag.php';
            Arkidevs_Support_Tag::set_ticket_tags( $ticket_id, $tag_ids );
        }

        // Check for auto-assignment rules
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-auto-assign.php';
        $assigned_agent_id = Arkidevs_Support_Auto_Assign::get_agent_for_ticket( $ticket_id );
        if ( $assigned_agent_id ) {
            $ticket->update( array( 'agent_id' => $assigned_agent_id ) );
        }

        // Send email notification
        do_action( 'arkidevs_ticket_created', $ticket );

        return $ticket;
    }

    /**
     * Update ticket
     *
     * @param array $data Data to update
     * @return bool|WP_Error
     */
    public function update( $data ) {
        if ( ! $this->data ) {
            return new WP_Error( 'invalid_ticket', __( 'Invalid ticket.', 'arkidevs-support' ) );
        }

        // Snapshot "before" for audit logging.
        $before = clone $this->data;

        // Sanitize data
        $data = Arkidevs_Support_Utils::sanitize_ticket_data( $data );

        // Validate priority if provided
        if ( ! empty( $data['priority'] ) && ! Arkidevs_Support_Priority::is_valid( $data['priority'] ) ) {
            return new WP_Error( 'invalid_priority', __( 'Invalid priority level.', 'arkidevs-support' ) );
        }

        // Validate status if provided
        if ( ! empty( $data['status'] ) && ! Arkidevs_Support_Status::is_valid( $data['status'] ) ) {
            return new WP_Error( 'invalid_status', __( 'Invalid status.', 'arkidevs-support' ) );
        }

        $old_status   = $this->data->status;
        $old_agent_id = isset( $this->data->agent_id ) ? intval( $this->data->agent_id ) : 0;
        $result = Arkidevs_Support_Ticket_Table::update( $this->data->id, $data );

        if ( $result ) {
            // Reload ticket data
            $this->data = Arkidevs_Support_Ticket_Table::get_by_id( $this->data->id );

            // Audit log: record field changes (status, priority, assignment, edits).
            if ( class_exists( 'Arkidevs_Support_Audit_Log' ) ) {
                $actor_id = get_current_user_id();
                $watch_fields = array( 'status', 'priority', 'agent_id', 'subject', 'description' );
                foreach ( $watch_fields as $field ) {
                    $old_val = isset( $before->{$field} ) ? $before->{$field} : null;
                    $new_val = isset( $this->data->{$field} ) ? $this->data->{$field} : null;
                    // Only log fields that were part of the update request (except empty update() calls).
                    if ( ! empty( $data ) && ! array_key_exists( $field, $data ) ) {
                        continue;
                    }
                    Arkidevs_Support_Audit_Log::log_field_change( $this->data->id, $field, $old_val, $new_val, $actor_id, 'field_change' );
                }
            }

            // Fire status changed event if needed.
            if ( isset( $data['status'] ) && $data['status'] !== $old_status ) {
                // Track resolution if ticket is being closed
                if ( 'closed' === $data['status'] && 'closed' !== $old_status ) {
                    require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/sla/class-sla-tracker.php';
                    Arkidevs_Support_SLA_Tracker::record_resolution( $this->data->id );
                }
                do_action( 'arkidevs_ticket_status_changed', $this, $data['status'] );
            } else {
                do_action( 'arkidevs_ticket_updated', $this );
            }

            // Fire assignment event if agent_id changed.
            $new_agent_id = isset( $this->data->agent_id ) ? intval( $this->data->agent_id ) : 0;
            if ( isset( $data['agent_id'] ) && $new_agent_id !== $old_agent_id ) {
                /**
                 * Fires when a ticket is assigned to an agent or reassigned.
                 *
                 * @param Arkidevs_Support_Ticket $ticket       Ticket object.
                 * @param int                     $new_agent_id New agent ID (0 if unassigned).
                 * @param int                     $old_agent_id Previous agent ID.
                 */
                do_action( 'arkidevs_ticket_assigned', $this, $new_agent_id, $old_agent_id );
            }
        }

        return $result;
    }

    /**
     * Delete ticket
     *
     * @return bool
     */
    public function delete() {
        if ( ! $this->data ) {
            return false;
        }

        return Arkidevs_Support_Ticket_Table::delete( $this->data->id );
    }

    /**
     * Get ticket property
     *
     * @param string $property Property name
     * @return mixed
     */
    public function __get( $property ) {
        if ( $this->data && isset( $this->data->$property ) ) {
            return $this->data->$property;
        }
        return null;
    }

    /**
     * Get ticket replies (nested structure)
     *
     * @return array
     */
    public function get_replies() {
        global $wpdb;
        $table = $wpdb->prefix . 'arkidevs_ticket_replies';

        // Determine whether to include internal notes based on user capability.
        $include_internal = class_exists( 'Arkidevs_Support_Security' ) && Arkidevs_Support_Security::user_can( 'manage_tickets' );

        $where = 'ticket_id = %d';
        if ( ! $include_internal ) {
            // Customers and non-agent users should not see internal notes.
            $where .= ' AND (is_internal IS NULL OR is_internal = 0)';
        }

        // Get all replies for this ticket
        $all_replies = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at ASC",
                $this->data->id
            )
        );

        // Organize into nested structure
        $replies_by_id = array();
        $top_level_replies = array();

        // First pass: index all replies by ID
        foreach ( $all_replies as $reply ) {
            $reply->children = array();
            $replies_by_id[ $reply->id ] = $reply;
        }

        // Second pass: build tree structure
        foreach ( $all_replies as $reply ) {
            // parent_reply_id may not exist on older rows/tables, so guard access
            $parent_id = isset( $reply->parent_reply_id ) ? intval( $reply->parent_reply_id ) : 0;

            if ( $parent_id && isset( $replies_by_id[ $parent_id ] ) ) {
                // This is a child reply
                $replies_by_id[ $parent_id ]->children[] = $reply;
            } else {
                // This is a top-level reply
                $top_level_replies[] = $reply;
            }
        }

        return $top_level_replies;
    }

    /**
     * Add reply to ticket
     *
     * @param string $message Reply message
     * @param int    $user_id User ID
     * @param int    $parent_reply_id Parent reply ID (for nested replies)
     * @return int|false Reply ID on success, false on failure
     */
    public function add_reply( $message, $user_id = null, $parent_reply_id = null, $is_internal = 0 ) {
        if ( ! $this->data ) {
            return false;
        }

        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }

        // Validate parent_reply_id if provided
        if ( $parent_reply_id ) {
            $parent_reply_id = intval( $parent_reply_id );
            global $wpdb;
            $table = $wpdb->prefix . 'arkidevs_ticket_replies';
            $parent = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE id = %d AND ticket_id = %d",
                    $parent_reply_id,
                    $this->data->id
                )
            );
            if ( ! $parent ) {
                $parent_reply_id = null; // Invalid parent, treat as top-level
            }
        } else {
            $parent_reply_id = null;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'arkidevs_ticket_replies';

        // Detect whether the is_internal column exists (for older installs) and cache the result.
        static $has_internal_column = null;
        if ( null === $has_internal_column ) {
            $column = $wpdb->get_var(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$table} LIKE %s",
                    'is_internal'
                )
            );
            $has_internal_column = ! empty( $column );
        }

        $insert_data = array(
            'ticket_id' => $this->data->id,
            'user_id'   => $user_id,
            'message'   => wp_kses_post( $message ),
        );

        $insert_format = array( '%d', '%d', '%s' );

        if ( $has_internal_column ) {
            $insert_data['is_internal'] = $is_internal ? 1 : 0;
            $insert_format[]            = '%d';
        }

        if ( $parent_reply_id ) {
            $insert_data['parent_reply_id'] = $parent_reply_id;
            $insert_format[] = '%d';
        }

        $result = $wpdb->insert(
            $table,
            $insert_data,
            $insert_format
        );

        if ( $result ) {
            // Update ticket updated_at timestamp
            $this->update( array() );

            // Track first response if this is an agent reply (not internal) and it's the first agent response
            if ( ! $is_internal && Arkidevs_Support_Security::user_can( 'manage_tickets', $user_id ) ) {
                // Check if this is the first agent reply
                $existing_agent_replies = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table} 
                        WHERE ticket_id = %d 
                        AND user_id != %d 
                        AND (is_internal = 0 OR is_internal IS NULL)
                        AND id != %d",
                        $this->data->id,
                        $this->data->customer_id,
                        $wpdb->insert_id
                    )
                );

                if ( 0 === intval( $existing_agent_replies ) ) {
                    // This is the first agent reply - track first response
                    require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/sla/class-sla-tracker.php';
                    Arkidevs_Support_SLA_Tracker::record_first_response( $this->data->id );
                }
            }

            // Send email notification
            do_action( 'arkidevs_ticket_replied', $this, $wpdb->insert_id );

            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Update a reply
     *
     * @param int    $reply_id Reply ID
     * @param string $message  New reply message
     * @return bool
     */
    public function update_reply( $reply_id, $message ) {
        if ( ! $this->data ) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'arkidevs_ticket_replies';

        $result = $wpdb->update(
            $table,
            array(
                'message' => wp_kses_post( $message ),
            ),
            array(
                'id'       => intval( $reply_id ),
                'ticket_id'=> $this->data->id,
            ),
            array( '%s' ),
            array( '%d', '%d' )
        );

        if ( false === $result ) {
            return false;
        }

        // Update ticket updated_at timestamp
        $this->update( array() );

        return true;
    }

    /**
     * Delete a reply
     *
     * @param int $reply_id Reply ID
     * @return bool
     */
    public function delete_reply( $reply_id ) {
        if ( ! $this->data ) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'arkidevs_ticket_replies';

        $result = $wpdb->delete(
            $table,
            array(
                'id'        => intval( $reply_id ),
                'ticket_id' => $this->data->id,
            ),
            array( '%d', '%d' )
        );

        if ( false === $result ) {
            return false;
        }

        // Update ticket updated_at timestamp
        $this->update( array() );

        return true;
    }

    /**
     * Get ticket tags
     *
     * @return array
     */
    public function get_tags() {
        if ( ! $this->data ) {
            return array();
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-tag.php';
        return Arkidevs_Support_Tag::get_ticket_tags( $this->data->id );
    }

    /**
     * Add tag to ticket
     *
     * @param int $tag_id Tag ID
     * @return bool
     */
    public function add_tag( $tag_id ) {
        if ( ! $this->data ) {
            return false;
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-tag.php';
        return Arkidevs_Support_Tag::add_tag_to_ticket( $this->data->id, $tag_id );
    }

    /**
     * Remove tag from ticket
     *
     * @param int $tag_id Tag ID
     * @return bool
     */
    public function remove_tag( $tag_id ) {
        if ( ! $this->data ) {
            return false;
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-tag.php';
        return Arkidevs_Support_Tag::remove_tag_from_ticket( $this->data->id, $tag_id );
    }

    /**
     * Set tags for ticket
     *
     * @param array $tag_ids Array of tag IDs
     * @return bool
     */
    public function set_tags( $tag_ids ) {
        if ( ! $this->data ) {
            return false;
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-tag.php';
        return Arkidevs_Support_Tag::set_ticket_tags( $this->data->id, $tag_ids );
    }
}

