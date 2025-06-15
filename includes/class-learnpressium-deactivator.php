<?php

/**
 * Fired during plugin deactivation
 *
 * @package    Learnpressium
 * @subpackage Learnpressium/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 */
class Learnpressium_Deactivator {

    /**
     * Short Description. (use period)
     *
     * Long Description.
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Note: We don't delete options here in case user wants to reactivate
        // Options can be cleaned up on uninstall if needed
    }
}
