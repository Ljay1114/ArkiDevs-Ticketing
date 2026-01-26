<?php
/**
 * Authentication redirects based on user roles
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Auth_Redirect
 */
class Arkidevs_Support_Auth_Redirect {

    /**
     * Instance
     *
     * @var Arkidevs_Support_Auth_Redirect
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Arkidevs_Support_Auth_Redirect
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
        add_filter( 'login_redirect', array( $this, 'login_redirect' ), 10, 3 );
        add_action( 'template_redirect', array( $this, 'template_redirect' ) );
    }

    /**
     * Redirect user after login based on role
     *
     * @param string  $redirect_to URL to redirect to
     * @param string  $request Requested redirect URL
     * @param WP_User $user User object
     * @return string
     */
    public function login_redirect( $redirect_to, $request, $user ) {
        if ( is_wp_error( $user ) || ! $user ) {
            return $redirect_to;
        }

        // Check user roles
        if ( in_array( 'administrator', $user->roles, true ) ) {
            return admin_url( 'admin.php?page=arkidevs-support-admin' );
        } elseif ( in_array( 'arkidevs_agent', $user->roles, true ) ) {
            return admin_url( 'admin.php?page=arkidevs-support-agent' );
        } elseif ( in_array( 'arkidevs_customer', $user->roles, true ) ) {
            return home_url( '/support/' );
        }

        return $redirect_to;
    }

    /**
     * Redirect users based on role when accessing certain pages
     */
    public function template_redirect() {
        // Handle redirects for logged-in users
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();

            // Prevent customers from accessing admin area
            if ( is_admin() && ! wp_doing_ajax() ) {
                if ( in_array( 'arkidevs_customer', $user->roles, true ) ) {
                    wp_safe_redirect( home_url( '/support/' ) );
                    exit;
                }
            }

            // Redirect agents and admins away from customer dashboard if accessing via frontend
            // Check if current page has the customer dashboard shortcode
            global $post;
            if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'arkidevs_customer_dashboard' ) ) {
                if ( in_array( 'arkidevs_agent', $user->roles, true ) || in_array( 'administrator', $user->roles, true ) ) {
                    if ( in_array( 'administrator', $user->roles, true ) ) {
                        wp_safe_redirect( admin_url( 'admin.php?page=arkidevs-support-admin' ) );
                    } else {
                        wp_safe_redirect( admin_url( 'admin.php?page=arkidevs-support-agent' ) );
                    }
                    exit;
                }
            }

            // Redirect agents/admins away from public /support/ page
            $is_support_page = get_query_var( 'arkidevs_support_page' );
            if ( $is_support_page ) {
                if ( in_array( 'arkidevs_agent', $user->roles, true ) || in_array( 'administrator', $user->roles, true ) ) {
                    if ( in_array( 'administrator', $user->roles, true ) ) {
                        wp_safe_redirect( admin_url( 'admin.php?page=arkidevs-support-admin' ) );
                    } else {
                        wp_safe_redirect( admin_url( 'admin.php?page=arkidevs-support-agent' ) );
                    }
                    exit;
                }
            }
        }
    }
}

