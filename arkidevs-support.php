<?php
/**
 * Plugin Name: Arkidevs Support
 * Plugin URI:  https://arkidevs.com
 * Description: Complete ticketing system with time tracking, role-based access, and email integration.
 * Version:     1.0.1
 * Author:      Arkidevs
 * Author URI:  https://arkidevs.com
 * Text Domain: arkidevs-support
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'ARKIDEVS_SUPPORT_VERSION', '1.0.0' );
define( 'ARKIDEVS_SUPPORT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ARKIDEVS_SUPPORT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ARKIDEVS_SUPPORT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
final class Arkidevs_Support {

    /**
     * Plugin version
     */
    const VERSION = '1.0.1';

    /**
     * Instance of this class
     *
     * @var Arkidevs_Support
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Arkidevs_Support
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
        $this->init();
    }

    /**
     * Initialize plugin
     */
    private function init() {
        // Load plugin textdomain
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

        // Register activation and deactivation hooks
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        // Load plugin classes
        $this->load_dependencies();
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/class-loader.php';
        Arkidevs_Support_Loader::get_instance();
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'arkidevs-support',
            false,
            dirname( ARKIDEVS_SUPPORT_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/class-activator.php';
        Arkidevs_Support_Activator::activate();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/class-deactivator.php';
        Arkidevs_Support_Deactivator::deactivate();
    }
}

// Initialize plugin
Arkidevs_Support::get_instance();


