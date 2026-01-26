<?php
/**
 * Email parser for ticket creation
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Email_Parser
 */
class Arkidevs_Support_Email_Parser {

    /**
     * Parse email content and extract ticket data
     *
     * @param string $from_email From email address
     * @param string $subject Email subject
     * @param string $body Email body
     * @return array Ticket data
     */
    public static function parse_email( $from_email, $subject, $body ) {
        // Find or create customer by email
        $user = get_user_by( 'email', $from_email );
        
        if ( ! $user ) {
            // Create customer user if doesn't exist
            $username = sanitize_user( $from_email );
            $password = wp_generate_password();
            
            $user_id = wp_create_user( $username, $password, $from_email );
            
            if ( is_wp_error( $user_id ) ) {
                // Try with a different username
                $username = sanitize_user( $from_email ) . '_' . time();
                $user_id = wp_create_user( $username, $password, $from_email );
            }

            if ( ! is_wp_error( $user_id ) ) {
                $user = new WP_User( $user_id );
                $user->set_role( 'arkidevs_customer' );
            } else {
                return new WP_Error( 'user_creation_failed', __( 'Failed to create user from email.', 'arkidevs-support' ) );
            }
        } else {
            // Ensure user has customer role
            if ( ! in_array( 'arkidevs_customer', $user->roles, true ) ) {
                $user->add_role( 'arkidevs_customer' );
            }
        }

        // Clean email body (remove replies, signatures, etc.)
        $body = self::clean_email_body( $body );

        // Extract priority from subject if present
        $priority = self::extract_priority( $subject );

        return array(
            'customer_id' => $user->ID,
            'subject'     => ! empty( $subject ) ? $subject : __( 'Ticket from Email', 'arkidevs-support' ),
            'description' => $body,
            'priority'    => $priority,
        );
    }

    /**
     * Clean email body
     *
     * @param string $body Raw email body
     * @return string Cleaned body
     */
    private static function clean_email_body( $body ) {
        // Remove HTML tags if present
        $body = wp_strip_all_tags( $body );

        // Remove common email signatures and replies
        $patterns = array(
            '/^On .* wrote:.*$/ms',
            '/^From:.*$/m',
            '/^Sent:.*$/m',
            '/^To:.*$/m',
            '/^Subject:.*$/m',
            '/^---.*---$/m',
            '/^--\s*$/m',
        );

        foreach ( $patterns as $pattern ) {
            $body = preg_replace( $pattern, '', $body );
        }

        // Trim whitespace
        $body = trim( $body );

        return $body;
    }

    /**
     * Extract priority from subject
     *
     * @param string $subject Email subject
     * @return string Priority level
     */
    private static function extract_priority( $subject ) {
        $subject_lower = strtolower( $subject );

        if ( strpos( $subject_lower, '[critical]' ) !== false || strpos( $subject_lower, 'urgent' ) !== false ) {
            return 'critical';
        } elseif ( strpos( $subject_lower, '[high]' ) !== false ) {
            return 'high';
        } elseif ( strpos( $subject_lower, '[low]' ) !== false ) {
            return 'low';
        }

        return 'medium'; // Default priority
    }
}


