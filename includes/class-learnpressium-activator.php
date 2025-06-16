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
     * Plugin activation handler
     *
     * Sets up database tables and default options
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
            'enrollment_module_enabled' => true,
        ));

        // Create enrollment database tables
        self::create_enrollment_tables();

        // Trigger module activation hooks
        do_action('learnpressium_activation');

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create enrollment module database tables
     */
    private static function create_enrollment_tables() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'learnpressium_enrollment_schedules';
        $charset_collate = $wpdb->get_charset_collate();

        // FINAL CORRECTED APPROACH: Use separate staging table
        // We MUST use a separate table because inserting into user_items with any status
        // causes LearnPress to treat it as enrolled (even with custom status)

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            schedule_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            scheduled_start_date datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (schedule_id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY scheduled_start_date (scheduled_start_date),
            KEY status (status),
            UNIQUE KEY unique_user_course_schedule (user_id, course_id),
            CONSTRAINT fk_schedule_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE,
            CONSTRAINT fk_schedule_course FOREIGN KEY (course_id) REFERENCES {$wpdb->posts}(ID) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Verify table creation
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if ($table_exists) {
            error_log("Learnpressium: Database table $table_name created successfully during activation");
        } else {
            error_log("Learnpressium: FAILED to create database table $table_name during activation");
        }

        // Add database version option
        add_option('learnpressium_enrollment_db_version', '1.0');
    }
}
