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
     * Plugin deactivation handler
     *
     * Cleans up scheduled tasks and temporary data
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('learnpressium_process_scheduled_enrollments');

        // Flush rewrite rules
        flush_rewrite_rules();

        // Note: We don't delete options or database tables here in case user wants to reactivate
        // Data can be cleaned up on uninstall if needed
    }
}
