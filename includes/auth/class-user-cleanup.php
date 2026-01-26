<?php
/**
 * User deletion cleanup handler
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_User_Cleanup
 */
class Arkidevs_Support_User_Cleanup {

    /**
     * Instance
     *
     * @var Arkidevs_Support_User_Cleanup
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Arkidevs_Support_User_Cleanup
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Hook into WordPress user deletion
        add_action( 'delete_user', array( $this, 'cleanup_user_data' ), 10, 2 );
        add_action( 'wpmu_delete_user', array( $this, 'cleanup_user_data' ), 10, 1 );
    }

    /**
     * Cleanup user data when user is deleted
     *
     * @param int      $user_id User ID being deleted
     * @param int|null $reassign_user_id User ID to reassign content to (if provided)
     */
    public function cleanup_user_data( $user_id, $reassign_user_id = null ) {
        if ( ! $user_id ) {
            return;
        }

        global $wpdb;

        // Get user to check their role
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        // Check if user is a customer
        $is_customer = in_array( 'arkidevs_customer', $user->roles, true );
        
        // Check if user is an agent
        $is_agent = in_array( 'arkidevs_agent', $user->roles, true ) || in_array( 'administrator', $user->roles, true );

        // If user is a customer, delete all their tickets and related data
        if ( $is_customer ) {
            $this->delete_customer_tickets( $user_id );
            $this->delete_customer_hours( $user_id );
        }

        // If user is an agent, unassign tickets (don't delete them)
        if ( $is_agent ) {
            $this->unassign_agent_tickets( $user_id, $reassign_user_id );
        }

        // Always delete user's replies/comments (they may have commented on tickets)
        $this->delete_user_replies( $user_id );

        // Delete user's time logs
        $this->delete_user_time_logs( $user_id );

        // Delete user's attachments
        $this->delete_user_attachments( $user_id );
    }

    /**
     * Delete all tickets for a customer
     *
     * @param int $customer_id Customer ID
     */
    private function delete_customer_tickets( $customer_id ) {
        global $wpdb;

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-ticket-table.php';
        
        // Get all tickets for this customer
        $tickets = Arkidevs_Support_Ticket_Table::get_by_customer( $customer_id );

        if ( empty( $tickets ) ) {
            return;
        }

        // Delete each ticket and its related data
        foreach ( $tickets as $ticket ) {
            $this->delete_ticket_data( $ticket->id );
        }

        // Delete tickets from database
        $tickets_table = $wpdb->prefix . 'arkidevs_tickets';
        $wpdb->delete(
            $tickets_table,
            array( 'customer_id' => $customer_id ),
            array( '%d' )
        );
    }

    /**
     * Delete all data related to a ticket
     *
     * @param int $ticket_id Ticket ID
     */
    private function delete_ticket_data( $ticket_id ) {
        global $wpdb;

        // Delete ticket replies/comments
        $replies_table = $wpdb->prefix . 'arkidevs_ticket_replies';
        $wpdb->delete(
            $replies_table,
            array( 'ticket_id' => $ticket_id ),
            array( '%d' )
        );

        // Delete ticket attachments
        $attachments_table = $wpdb->prefix . 'arkidevs_ticket_attachments';
        $attachments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT file_path FROM {$attachments_table} WHERE ticket_id = %d",
                $ticket_id
            )
        );

        // Delete attachment files from filesystem
        foreach ( $attachments as $attachment ) {
            if ( file_exists( $attachment->file_path ) ) {
                @unlink( $attachment->file_path );
            }
        }

        // Delete attachment records
        $wpdb->delete(
            $attachments_table,
            array( 'ticket_id' => $ticket_id ),
            array( '%d' )
        );

        // Delete time logs
        $time_logs_table = $wpdb->prefix . 'arkidevs_time_logs';
        $wpdb->delete(
            $time_logs_table,
            array( 'ticket_id' => $ticket_id ),
            array( '%d' )
        );
    }

    /**
     * Delete customer hours record
     *
     * @param int $customer_id Customer ID
     */
    private function delete_customer_hours( $customer_id ) {
        global $wpdb;

        $customer_hours_table = $wpdb->prefix . 'arkidevs_customer_hours';
        $wpdb->delete(
            $customer_hours_table,
            array( 'customer_id' => $customer_id ),
            array( '%d' )
        );
    }

    /**
     * Unassign tickets from an agent (set agent_id to NULL)
     *
     * @param int      $agent_id Agent ID being deleted
     * @param int|null $reassign_user_id User ID to reassign to (optional)
     */
    private function unassign_agent_tickets( $agent_id, $reassign_user_id = null ) {
        global $wpdb;

        $tickets_table = $wpdb->prefix . 'arkidevs_tickets';

        if ( $reassign_user_id && get_userdata( $reassign_user_id ) ) {
            // Reassign tickets to another user
            $wpdb->update(
                $tickets_table,
                array( 'agent_id' => $reassign_user_id ),
                array( 'agent_id' => $agent_id ),
                array( '%d' ),
                array( '%d' )
            );
        } else {
            // Unassign tickets (set agent_id to NULL)
            $wpdb->update(
                $tickets_table,
                array( 'agent_id' => null ),
                array( 'agent_id' => $agent_id ),
                array( '%d' ),
                array( '%d' )
            );
        }
    }

    /**
     * Delete all replies/comments by a user
     *
     * @param int $user_id User ID
     */
    private function delete_user_replies( $user_id ) {
        global $wpdb;

        $replies_table = $wpdb->prefix . 'arkidevs_ticket_replies';
        
        // Get all replies by this user
        $replies = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, ticket_id FROM {$replies_table} WHERE user_id = %d",
                $user_id
            )
        );

        // Delete child replies first (replies to this user's replies)
        foreach ( $replies as $reply ) {
            $wpdb->delete(
                $replies_table,
                array( 'parent_reply_id' => $reply->id ),
                array( '%d' )
            );
        }

        // Delete user's replies
        $wpdb->delete(
            $replies_table,
            array( 'user_id' => $user_id ),
            array( '%d' )
        );
    }

    /**
     * Delete all time logs for a user
     *
     * @param int $user_id User ID
     */
    private function delete_user_time_logs( $user_id ) {
        global $wpdb;

        $time_logs_table = $wpdb->prefix . 'arkidevs_time_logs';
        
        // Delete logs where user is agent
        $wpdb->delete(
            $time_logs_table,
            array( 'agent_id' => $user_id ),
            array( '%d' )
        );

        // Delete logs where user is customer
        $wpdb->delete(
            $time_logs_table,
            array( 'customer_id' => $user_id ),
            array( '%d' )
        );
    }

    /**
     * Delete all attachments uploaded by a user
     *
     * @param int $user_id User ID
     */
    private function delete_user_attachments( $user_id ) {
        global $wpdb;

        $attachments_table = $wpdb->prefix . 'arkidevs_ticket_attachments';
        
        // Get all attachments by this user
        $attachments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT file_path FROM {$attachments_table} WHERE uploaded_by = %d",
                $user_id
            )
        );

        // Delete attachment files from filesystem
        foreach ( $attachments as $attachment ) {
            if ( file_exists( $attachment->file_path ) ) {
                @unlink( $attachment->file_path );
            }
        }

        // Delete attachment records
        $wpdb->delete(
            $attachments_table,
            array( 'uploaded_by' => $user_id ),
            array( '%d' )
        );
    }

    /**
     * Cleanup orphaned tickets (tickets from users that no longer exist)
     * This can be called manually to clean up existing orphaned data
     *
     * @return array Statistics about cleanup
     */
    public function cleanup_orphaned_tickets() {
        global $wpdb;

        $stats = array(
            'tickets_deleted' => 0,
            'replies_deleted' => 0,
            'attachments_deleted' => 0,
            'time_logs_deleted' => 0,
        );

        $tickets_table = $wpdb->prefix . 'arkidevs_tickets';
        
        // Get all tickets
        $tickets = $wpdb->get_results( "SELECT id, customer_id FROM {$tickets_table}" );

        foreach ( $tickets as $ticket ) {
            // Check if customer still exists
            $customer = get_userdata( $ticket->customer_id );
            
            if ( ! $customer ) {
                // Customer doesn't exist, delete ticket and related data
                $this->delete_ticket_data( $ticket->id );
                
                $wpdb->delete(
                    $tickets_table,
                    array( 'id' => $ticket->id ),
                    array( '%d' )
                );
                
                $stats['tickets_deleted']++;
            }
        }

        // Clean up orphaned replies
        $replies_table = $wpdb->prefix . 'arkidevs_ticket_replies';
        $replies = $wpdb->get_results( "SELECT id, user_id FROM {$replies_table}" );
        
        foreach ( $replies as $reply ) {
            $user = get_userdata( $reply->user_id );
            if ( ! $user ) {
                $wpdb->delete(
                    $replies_table,
                    array( 'id' => $reply->id ),
                    array( '%d' )
                );
                $stats['replies_deleted']++;
            }
        }

        // Clean up orphaned time logs
        $time_logs_table = $wpdb->prefix . 'arkidevs_time_logs';
        $time_logs = $wpdb->get_results( "SELECT id, agent_id, customer_id FROM {$time_logs_table}" );
        
        foreach ( $time_logs as $log ) {
            $agent = get_userdata( $log->agent_id );
            $customer = get_userdata( $log->customer_id );
            
            if ( ! $agent || ! $customer ) {
                $wpdb->delete(
                    $time_logs_table,
                    array( 'id' => $log->id ),
                    array( '%d' )
                );
                $stats['time_logs_deleted']++;
            }
        }

        // Clean up orphaned attachments
        $attachments_table = $wpdb->prefix . 'arkidevs_ticket_attachments';
        $attachments = $wpdb->get_results( "SELECT id, uploaded_by, file_path FROM {$attachments_table}" );
        
        foreach ( $attachments as $attachment ) {
            $user = get_userdata( $attachment->uploaded_by );
            if ( ! $user ) {
                // Delete file if exists
                if ( file_exists( $attachment->file_path ) ) {
                    @unlink( $attachment->file_path );
                }
                
                $wpdb->delete(
                    $attachments_table,
                    array( 'id' => $attachment->id ),
                    array( '%d' )
                );
                $stats['attachments_deleted']++;
            }
        }

        return $stats;
    }
}

