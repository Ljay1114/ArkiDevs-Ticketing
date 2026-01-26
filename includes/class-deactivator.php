<?php
/**
 * Plugin deactivation handler
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Deactivator
 */
class Arkidevs_Support_Deactivator {

    /**
     * Deactivate plugin
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}


