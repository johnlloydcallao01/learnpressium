<?php

/**
 * The Enrollment Module class
 *
 * Handles enrollment scheduling functionality for LearnPress courses
 *
 * @package    Learnpressium
 * @subpackage Learnpressium/includes/modules/enrollment
 */

class Enrollment_Module {

    /**
     * The enrollment manager instance
     */
    private $enrollment_manager;

    /**
     * The access controller instance
     */
    private $access_controller;

    /**
     * The database manager instance
     */
    private $database_manager;

    /**
     * The admin interface instance
     */
    private $admin_interface;

    /**
     * The tools integration instance
     */
    private $tools_integration;

    /**
     * The frontend instance
     */
    private $frontend;

    /**
     * Initialize the module
     */
    public function init() {
        // Delay initialization until WordPress is fully loaded
        add_action('wp_loaded', array($this, 'delayed_init'));
    }

    /**
     * Delayed initialization after WordPress is fully loaded
     */
    public function delayed_init() {
        // Check if LearnPress is available
        if (!function_exists('learn_press_update_user_item_meta')) {
            add_action('admin_notices', array($this, 'learnpress_not_available_notice'));
            return;
        }

        // Initialize database manager first
        $this->database_manager = new Enrollment_Database();

        // PERFORMANCE FIX: Only verify table exists on admin pages, not every page load
        if (is_admin()) {
            $this->database_manager->verify_table_exists();
        }

        // Load simple enrollment manager
        require_once LEARNPRESSIUM_PLUGIN_DIR . 'includes/modules/enrollment/class-simple-enrollment-manager.php';

        // Initialize core components with simple approach
        $this->enrollment_manager = new Enrollment_Manager($this->database_manager);
        $this->access_controller = new Enrollment_Access_Controller($this->enrollment_manager);
        $this->admin_interface = new Enrollment_Admin($this->enrollment_manager);
        $this->tools_integration = new Enrollment_Tools_Integration($this->enrollment_manager);
        $this->frontend = new Enrollment_Frontend($this->enrollment_manager, $this->access_controller);
        $this->profile_integration = new Enrollment_Profile_Integration(); // Now uses simple manager internally

        // Hook into WordPress
        add_action('init', array($this, 'setup_hooks'));
        add_action('admin_init', array($this, 'setup_admin_hooks'));
        add_action('wp', array($this, 'setup_frontend_hooks'));

        // Initialize tools integration immediately (needs to be early for tabs filter)
        $this->tools_integration->init();

        // Initialize profile integration immediately (needs to be early for profile filters)
        $this->profile_integration->init();

        // Initialize scheduler for automatic activation
        require_once LEARNPRESSIUM_PLUGIN_DIR . 'includes/modules/enrollment/class-enrollment-scheduler.php';
        $this->scheduler = new Enrollment_Scheduler();
        $this->scheduler->init();

        // CRITICAL FIX: Ensure cron job is scheduled immediately
        add_action('wp_loaded', array($this->scheduler, 'schedule_cron'));

        // REMOVED: Duplicate AJAX handler registration - handled by tools_integration class directly

        // Database setup - will be handled by the main activator
        add_action('learnpressium_activation', array($this->database_manager, 'create_tables'));
    }

    /**
     * Setup main hooks
     */
    public function setup_hooks() {
        // LearnPress integration hooks
        add_filter('learnpress/course/can-view-content', array($this->access_controller, 'filter_course_access'), 10, 3);
        add_filter('learn-press/user/can-enroll/course', array($this->access_controller, 'filter_enrollment_access'), 10, 3);
        add_action('learnpress/user/course-enrolled', array($this->enrollment_manager, 'process_enrollment'), 10, 3);

        // CRITICAL: Filter out scheduled courses from LearnPress profile queries
        add_filter('lp/api/profile/courses/purchased/filter', array($this, 'filter_scheduled_courses_from_profile'), 10, 2);
        add_filter('learnpress/rest/frontend/profile/course_tab/query', array($this, 'filter_scheduled_courses_from_rest_api'), 10, 2);
        
        // AJAX hooks
        add_action('wp_ajax_learnpressium_schedule_enrollment', array($this->enrollment_manager, 'ajax_schedule_enrollment'));
        add_action('wp_ajax_learnpressium_get_schedules', array($this->enrollment_manager, 'ajax_get_schedules'));
        add_action('wp_ajax_learnpressium_get_schedule', array($this->enrollment_manager, 'ajax_get_schedule'));
        add_action('wp_ajax_learnpressium_update_schedule', array($this->enrollment_manager, 'ajax_update_schedule'));
        add_action('wp_ajax_learnpressium_delete_schedule', array($this->enrollment_manager, 'ajax_delete_schedule'));
        add_action('wp_ajax_learnpressium_bulk_schedule_action', array($this->enrollment_manager, 'ajax_bulk_schedule_action'));
        // Note: wp_ajax_learnpressium_tools_schedule_assignment is registered earlier in delayed_init()
        
        // REMOVED: Duplicate scheduler initialization - already done in delayed_init()
        // The scheduler is already initialized in delayed_init() method above
    }

    /**
     * Setup admin-specific hooks
     */
    public function setup_admin_hooks() {
        // Admin interface hooks
        $this->admin_interface->init();
        // Note: tools_integration->init() is called earlier in delayed_init()
    }

    /**
     * Setup frontend-specific hooks
     */
    public function setup_frontend_hooks() {
        // Only initialize frontend on frontend pages
        if (!is_admin()) {
            $this->frontend->init();
        }
    }

    /**
     * Get enrollment manager instance
     */
    public function get_enrollment_manager() {
        return $this->enrollment_manager;
    }

    /**
     * Get access controller instance
     */
    public function get_access_controller() {
        return $this->access_controller;
    }

    /**
     * Get database manager instance
     */
    public function get_database_manager() {
        return $this->database_manager;
    }

    /**
     * Display notice when LearnPress is not available
     */
    public function learnpress_not_available_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('Learnpressium Enrollment Module', 'learnpressium'); ?></strong>
                <?php esc_html_e('requires LearnPress to be fully loaded. Please ensure LearnPress is active and properly configured.', 'learnpressium'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Filter scheduled courses from LearnPress profile queries
     * This prevents scheduled courses from appearing in "In Progress" and other tabs
     */
    public function filter_scheduled_courses_from_profile($filter, $args) {
        global $wpdb;

        // Get all scheduled course IDs for this user
        $scheduled_table = $wpdb->prefix . 'learnpressium_enrollment_schedules';
        $scheduled_course_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT course_id FROM {$scheduled_table}
             WHERE user_id = %d AND status = 'pending'",
            $filter->user_id
        ));

        if (!empty($scheduled_course_ids)) {
            // Exclude scheduled courses from the query
            $filter->where[] = "AND ui.item_id NOT IN (" . implode(',', array_map('intval', $scheduled_course_ids)) . ")";

            error_log("Learnpressium: Filtered out " . count($scheduled_course_ids) . " scheduled courses from profile query for user {$filter->user_id}");
        }

        return $filter;
    }

    /**
     * Filter scheduled courses from REST API queries
     */
    public function filter_scheduled_courses_from_rest_api($query_args, $request) {
        global $wpdb;

        $user_id = isset($request['userID']) ? intval($request['userID']) : 0;
        if (!$user_id) {
            return $query_args;
        }

        // Get all scheduled course IDs for this user
        $scheduled_table = $wpdb->prefix . 'learnpressium_enrollment_schedules';
        $scheduled_course_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT course_id FROM {$scheduled_table}
             WHERE user_id = %d AND status = 'pending'",
            $user_id
        ));

        if (!empty($scheduled_course_ids)) {
            // Add exclusion to query args
            if (!isset($query_args['post__not_in'])) {
                $query_args['post__not_in'] = array();
            }
            $query_args['post__not_in'] = array_merge($query_args['post__not_in'], $scheduled_course_ids);

            error_log("Learnpressium: Filtered out " . count($scheduled_course_ids) . " scheduled courses from REST API query for user {$user_id}");
        }

        return $query_args;
    }

    /**
     * Module deactivation cleanup
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('learnpressium_process_scheduled_enrollments');
    }
}
