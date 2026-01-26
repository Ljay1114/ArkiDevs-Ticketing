<?php
/**
 * REST API handler
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_REST_API
 */
class Arkidevs_Support_REST_API {

    /**
     * Instance
     *
     * @var Arkidevs_Support_REST_API
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Arkidevs_Support_REST_API
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
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // This is a placeholder for future REST API endpoints
        // Currently using AJAX handlers for simplicity
    }
}


