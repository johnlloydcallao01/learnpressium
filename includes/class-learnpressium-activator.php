<?php

/**
 * Fired during plugin activation
 *
 * @package    Learnpressium
 * @subpackage Learnpressium/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */
class Learnpressium_Activator {

    /**
     * Short Description. (use period)
     *
     * Long Description.
     */
    public static function activate() {
        // Check if LearnPress is active
        if (!is_plugin_active('learnpress/learnpress.php')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('Learnpressium requires LearnPress plugin to be installed and activated.', 'learnpressium'),
                __('Plugin Activation Error', 'learnpressium'),
                array('back_link' => true)
            );
        }

        // Add plugin version to options
        add_option('learnpressium_version', LEARNPRESSIUM_VERSION);
        
        // Set default options
        add_option('learnpressium_settings', array(
            'trainees_module_enabled' => true,
        ));

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
