<?php
/**
 * User roles management
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Roles
 */
class Arkidevs_Support_Roles {

    /**
     * Instance
     *
     * @var Arkidevs_Support_Roles
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Arkidevs_Support_Roles
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
        add_filter( 'editable_roles', array( $this, 'add_roles_to_editor' ) );
    }

    /**
     * Create custom roles
     */
    public static function create_roles() {
        // Agent role
        add_role(
            'arkidevs_agent',
            __( 'Support Agent', 'arkidevs-support' ),
            array(
                'read'                    => true,
                'edit_posts'              => false,
                'publish_posts'           => false,
                'delete_posts'            => false,
                'arkidevs_manage_tickets' => true,
                'arkidevs_track_time'     => true,
            )
        );

        // Customer role
        add_role(
            'arkidevs_customer',
            __( 'Customer', 'arkidevs-support' ),
            array(
                'read'                  => true,
                'arkidevs_create_ticket' => true,
                'arkidevs_view_tickets'  => true,
            )
        );
    }

    /**
     * Add roles to editable roles list
     *
     * @param array $roles Roles array
     * @return array
     */
    public function add_roles_to_editor( $roles ) {
        if ( isset( $roles['arkidevs_agent'] ) || isset( $roles['arkidevs_customer'] ) ) {
            return $roles;
        }

        // Roles are already added by create_roles()
        return $roles;
    }

    /**
     * Remove custom roles
     */
    public static function remove_roles() {
        remove_role( 'arkidevs_agent' );
        remove_role( 'arkidevs_customer' );
    }
}


