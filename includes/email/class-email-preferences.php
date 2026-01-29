<?php
/**
 * Email preferences helper
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Email_Preferences
 *
 * Stores and reads per-user email notification preferences.
 * For now this is code-only (no UI); all prefs default to enabled.
 */
class Arkidevs_Support_Email_Preferences {

    /**
     * User meta key
     */
    const META_KEY = 'arkidevs_email_prefs';

    /**
     * Get all preferences for a user (merged with defaults).
     *
     * @param int $user_id User ID.
     * @return array
     */
    public static function get_prefs( $user_id ) {
        $user_id = intval( $user_id );
        if ( ! $user_id ) {
            return array();
        }

        $stored   = get_user_meta( $user_id, self::META_KEY, true );
        $stored   = is_array( $stored ) ? $stored : array();
        $defaults = self::get_default_prefs_for_user( $user_id );

        return array_merge( $defaults, $stored );
    }

    /**
     * Check if a single preference is enabled for a user.
     *
     * @param int    $user_id User ID.
     * @param string $key     Preference key.
     * @return bool
     */
    public static function is_enabled( $user_id, $key ) {
        $prefs = self::get_prefs( $user_id );
        if ( ! isset( $prefs[ $key ] ) ) {
            return false;
        }

        return (bool) $prefs[ $key ];
    }

    /**
     * Get default preferences based on user role.
     *
     * @param int $user_id User ID.
     * @return array
     */
    private static function get_default_prefs_for_user( $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user || empty( $user->roles ) ) {
            return array();
        }

        $roles = (array) $user->roles;

        // Customer defaults.
        if ( in_array( 'customer', $roles, true ) || in_array( 'subscriber', $roles, true ) ) {
            return array(
                'customer_ticket_created' => true,
                'customer_ticket_replied' => true,
                'customer_ticket_status'  => true,
            );
        }

        // Agent / admin defaults.
        if ( in_array( 'arkidevs_agent', $roles, true ) || in_array( 'administrator', $roles, true ) ) {
            return array(
                'agent_new_ticket'      => true,
                'agent_customer_reply'  => true,
                'agent_assignment'      => true,
                'agent_escalation'      => true,
            );
        }

        return array();
    }
}

