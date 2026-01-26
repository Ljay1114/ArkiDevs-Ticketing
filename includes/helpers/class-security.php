<?php
/**
 * Security helper functions
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Security
 */
class Arkidevs_Support_Security {

    /**
     * Verify nonce
     *
     * @param string $nonce Nonce to verify
     * @param string $action Action name
     * @return bool
     */
    public static function verify_nonce( $nonce, $action = 'arkidevs_support_nonce' ) {
        return wp_verify_nonce( $nonce, $action );
    }

    /**
     * Check user capability
     *
     * @param string $capability Capability to check
     * @param int    $user_id User ID (optional)
     * @return bool
     */
    public static function user_can( $capability, $user_id = null ) {
        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            return false;
        }

        $user = get_userdata( $user_id );

        // Administrator has all capabilities
        if ( in_array( 'administrator', $user->roles, true ) ) {
            return true;
        }

        // Check specific capability based on role
        switch ( $capability ) {
            case 'manage_tickets':
                return in_array( 'arkidevs_agent', $user->roles, true ) || in_array( 'administrator', $user->roles, true );
            
            case 'create_ticket':
                return in_array( 'arkidevs_customer', $user->roles, true ) || in_array( 'arkidevs_agent', $user->roles, true ) || in_array( 'administrator', $user->roles, true );
            
            case 'track_time':
                return in_array( 'arkidevs_agent', $user->roles, true ) || in_array( 'administrator', $user->roles, true );
            
            default:
                return false;
        }
    }

    /**
     * Sanitize file upload
     *
     * @param array $file File array from $_FILES
     * @return array|WP_Error
     */
    public static function sanitize_file_upload( $file ) {
        if ( empty( $file['name'] ) ) {
            return new WP_Error( 'no_file', __( 'No file provided.', 'arkidevs-support' ) );
        }

        // Check file size (max 10MB)
        $max_size = 10 * 1024 * 1024; // 10MB
        if ( $file['size'] > $max_size ) {
            return new WP_Error( 'file_too_large', __( 'File size exceeds maximum allowed size of 10MB.', 'arkidevs-support' ) );
        }

        // Check file type
        $allowed_types = array( 'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip' );
        $file_ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

        if ( ! in_array( $file_ext, $allowed_types, true ) ) {
            return new WP_Error( 'invalid_file_type', __( 'File type not allowed.', 'arkidevs-support' ) );
        }

        return $file;
    }
}


