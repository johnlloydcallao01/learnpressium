<?php

/**
 * The Trainees Module
 *
 * @package    Learnpressium
 * @subpackage Learnpressium/includes/modules/trainees
 */

/**
 * The Trainees Module class.
 *
 * This class coordinates all trainee-related functionality including
 * admin interface, export capabilities, and profile management.
 */
class Trainees_Module {

    /**
     * The admin interface handler
     */
    private $admin;

    /**
     * The export functionality handler
     */
    private $export;

    /**
     * The profile management handler
     */
    private $profile;

    /**
     * Initialize the module
     */
    public function init() {
        // Initialize sub-components
        $this->admin = new Trainees_Admin();
        $this->export = new Trainees_Export();
        $this->profile = new Trainees_Profile();

        // Hook into WordPress
        add_action('admin_menu', array($this->admin, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this->export, 'enqueue_scripts'));
        add_action('admin_footer', array($this->export, 'add_export_script'));
    }

    /**
     * Get enrolled courses for a user
     *
     * @param int $user_id The user ID
     * @return array Array of course objects
     */
    public static function get_user_enrolled_courses($user_id) {
        global $wpdb;

        // Get courses from learnpress_user_items table
        $query = $wpdb->prepare(
            "SELECT p.ID, p.post_title
            FROM {$wpdb->learnpress_user_items} ui
            JOIN {$wpdb->posts} p ON p.ID = ui.item_id
            WHERE ui.user_id = %d
            AND ui.item_type = %s
            AND (ui.status = %s OR ui.status = %s)
            GROUP BY p.ID",
            $user_id,
            'lp_course',
            'enrolled',
            'finished'
        );

        $courses = $wpdb->get_results($query);

        return $courses;
    }

    /**
     * Get active enrolled courses for a user (courses that are not finished)
     *
     * @param int $user_id The user ID
     * @return array Array of course objects
     */
    public static function get_user_active_enrolled_courses($user_id) {
        global $wpdb;

        // Get courses from learnpress_user_items table that are only enrolled (not finished)
        $query = $wpdb->prepare(
            "SELECT p.ID, p.post_title
            FROM {$wpdb->learnpress_user_items} ui
            JOIN {$wpdb->posts} p ON p.ID = ui.item_id
            WHERE ui.user_id = %d
            AND ui.item_type = %s
            AND ui.status = %s
            GROUP BY p.ID",
            $user_id,
            'lp_course',
            'enrolled'
        );

        $courses = $wpdb->get_results($query);

        return $courses;
    }

    /**
     * Get scheduled courses for a user
     *
     * @param int $user_id The user ID
     * @return array Array of course objects with schedule information
     */
    public static function get_user_scheduled_courses($user_id) {
        global $wpdb;

        // Get scheduled courses from learnpressium_enrollment_schedules table
        $scheduled_table = $wpdb->prefix . 'learnpressium_enrollment_schedules';
        $query = $wpdb->prepare(
            "SELECT p.ID, p.post_title, es.scheduled_start_date, es.status
            FROM {$scheduled_table} es
            JOIN {$wpdb->posts} p ON p.ID = es.course_id
            WHERE es.user_id = %d
            AND es.status = %s
            AND p.post_type = %s
            ORDER BY es.scheduled_start_date ASC",
            $user_id,
            'pending',
            'lp_course'
        );

        $courses = $wpdb->get_results($query);

        return $courses;
    }
}
