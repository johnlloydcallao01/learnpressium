<?php
/**
 * Enrollment Scheduler - Automatically activates scheduled courses
 * 
 * This class handles the automatic activation of scheduled courses when their date arrives.
 * It changes the status from 'scheduled' to 'enrolled' in LearnPress's user_items table,
 * which automatically makes them appear in the "In Progress" tab.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Enrollment_Scheduler {
    
    private $simple_manager;
    
    public function __construct() {
        $this->simple_manager = new Simple_Enrollment_Manager();
    }
    
    /**
     * Initialize the scheduler
     */
    public function init() {
        // Add custom cron schedule
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));

        // Schedule the cron job
        add_action('wp', array($this, 'schedule_cron'));

        // Hook the activation function to the cron event
        add_action('learnpressium_activate_scheduled_courses', array($this, 'activate_scheduled_courses'));

        // REAL-TIME: Check and activate on every admin page load for immediate activation
        add_action('admin_init', array($this, 'check_and_activate_immediate'));

        // REAL-TIME: Also check on frontend page loads for logged-in users
        add_action('wp', array($this, 'check_and_activate_immediate'));
    }

    /**
     * Add custom cron schedules - REAL-TIME: Every minute for immediate activation
     */
    public function add_cron_schedules($schedules) {
        $schedules['every_minute'] = array(
            'interval' => 60, // 1 minute in seconds
            'display'  => __('Every Minute', 'learnpressium')
        );
        return $schedules;
    }
    
    /**
     * Schedule the cron job if not already scheduled - REAL-TIME: Every minute
     */
    public function schedule_cron() {
        if (!wp_next_scheduled('learnpressium_activate_scheduled_courses')) {
            // Use 1-minute schedule for real-time activation
            wp_schedule_event(time(), 'every_minute', 'learnpressium_activate_scheduled_courses');
        }
    }
    
    /**
     * Activate scheduled courses that are due
     * This is the main function that moves courses from "Scheduled" to "In Progress"
     */
    public function activate_scheduled_courses() {
        $activated_count = $this->simple_manager->activate_due_schedules();
        
        if ($activated_count > 0) {
            error_log("Learnpressium: Activated {$activated_count} scheduled courses");
            
            // Optional: Send notification to admin
            $this->notify_admin_of_activations($activated_count);
        }
        
        return $activated_count;
    }
    
    /**
     * REAL-TIME: Immediate activation check without any delays or caching
     */
    public function check_and_activate_immediate() {
        // Always check and activate immediately - no caching, no delays
        $this->activate_scheduled_courses();
    }

    /**
     * Legacy method for backward compatibility
     */
    public function check_and_activate() {
        // Redirect to immediate check for real-time activation
        $this->check_and_activate_immediate();
    }
    
    /**
     * Notify admin of course activations
     */
    private function notify_admin_of_activations($count) {
        // Get admin email
        $admin_email = get_option('admin_email');
        
        // Prepare email
        $subject = sprintf('[%s] %d Scheduled Courses Activated', get_bloginfo('name'), $count);
        $message = sprintf(
            "Hello,\n\n%d scheduled courses have been automatically activated and are now available to students.\n\nYou can view the course enrollments in your LearnPress Tools section.\n\nBest regards,\nLearnpressium Plugin",
            $count
        );
        
        // Send email (optional - can be disabled)
        if (apply_filters('learnpressium_send_activation_notifications', false)) {
            wp_mail($admin_email, $subject, $message);
        }
    }
    
    /**
     * Manual activation trigger (for testing or manual runs)
     */
    public function manual_activate() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        return $this->activate_scheduled_courses();
    }
    
    /**
     * Get next scheduled activation time
     * FIXED: Look in the correct table where schedules are actually stored
     */
    public function get_next_activation_time() {
        global $wpdb;

        $scheduled_table = $wpdb->prefix . 'learnpressium_enrollment_schedules';

        $next_activation = $wpdb->get_var($wpdb->prepare(
            "SELECT MIN(scheduled_start_date)
             FROM {$scheduled_table}
             WHERE status = 'pending'
             AND scheduled_start_date > %s",
            current_time('mysql')
        ));

        return $next_activation;
    }
    
    /**
     * Get statistics about scheduled courses
     * FIXED: Look in the correct table where schedules are actually stored
     */
    public function get_scheduler_stats() {
        global $wpdb;

        $scheduled_table = $wpdb->prefix . 'learnpressium_enrollment_schedules';
        $stats = array();

        // Total scheduled courses (pending status)
        $stats['total_scheduled'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$scheduled_table} WHERE status = 'pending'"
        ));

        // Courses due for activation (past their scheduled start date)
        $stats['due_for_activation'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$scheduled_table}
             WHERE status = 'pending'
             AND scheduled_start_date <= %s",
            current_time('mysql')
        )));

        // Next activation time
        $stats['next_activation'] = $this->get_next_activation_time();

        // Add cron job status
        $stats['cron_scheduled'] = wp_next_scheduled('learnpressium_activate_scheduled_courses') ? true : false;
        $stats['next_cron_run'] = wp_next_scheduled('learnpressium_activate_scheduled_courses');

        return $stats;
    }
    
    /**
     * Clean up cron job on deactivation
     */
    public function cleanup() {
        wp_clear_scheduled_hook('learnpressium_activate_scheduled_courses');
    }
}
