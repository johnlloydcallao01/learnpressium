<?php

/**
 * The Enrollment Manager class
 *
 * Handles core enrollment scheduling logic
 *
 * @package    Learnpressium
 * @subpackage Learnpressium/includes/modules/enrollment
 */

class Enrollment_Manager {

    /**
     * The database manager instance
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct($database) {
        $this->database = $database;

        // Force database migration on initialization
        $this->database->force_migration();
    }

    /**
     * Schedule an enrollment
     */
    public function schedule_enrollment($user_id, $course_id, $start_date) {
        // Validate inputs
        if (!$this->validate_schedule_data($user_id, $course_id, $start_date)) {
            return new WP_Error('invalid_data', 'Invalid schedule data provided');
        }

        // Check if user is already enrolled in LearnPress
        $user_course = $this->get_user_course_item($user_id, $course_id);
        if ($user_course) {
            error_log("Learnpressium: User {$user_id} is already enrolled in course {$course_id} with status: {$user_course->status}");

            // For scheduling to work properly, we need to remove the existing enrollment
            // and replace it with a scheduled enrollment
            error_log("Learnpressium: Removing existing LearnPress enrollment to create scheduled enrollment");

            global $wpdb;
            $wpdb->delete(
                $wpdb->prefix . 'learnpress_user_items',
                array('user_item_id' => $user_course->user_item_id),
                array('%d')
            );

            // Also clean up any related meta
            $wpdb->delete(
                $wpdb->prefix . 'learnpress_user_itemmeta',
                array('learnpress_user_item_id' => $user_course->user_item_id),
                array('%d')
            );

            error_log("Learnpressium: Removed existing enrollment for user {$user_id} course {$course_id}");
        }

        // CRITICAL: Ensure no LearnPress enrollment exists before creating schedule
        // This prevents the user from accessing the course until the scheduled date
        $this->ensure_no_learnpress_enrollment($user_id, $course_id);

        error_log("Learnpressium: User {$user_id} is ready for scheduling in course {$course_id}");

        // Check if there's already an active schedule for this user/course
        $existing_schedule = $this->get_user_schedule($user_id, $course_id);
        if ($existing_schedule && $existing_schedule->status === 'pending') {
            return new WP_Error('already_scheduled', 'User already has an active schedule for this course');
        }

        // If there's an old schedule (expired, cancelled, etc.), clean it up first
        if ($existing_schedule) {
            $this->database->delete_schedule($existing_schedule->schedule_id);
            error_log("Learnpressium: Cleaned up old schedule {$existing_schedule->schedule_id} for user {$user_id} course {$course_id}");
        }

        // Create schedule record WITHOUT creating LearnPress enrollment
        $schedule_data = array(
            'user_id' => $user_id,
            'course_id' => $course_id,
            'user_item_id' => null, // Will be set when enrollment is actually created
            'scheduled_start_date' => $start_date,
            'created_by' => get_current_user_id()
        );

        $schedule_id = $this->database->insert_schedule($schedule_data);

        if (is_wp_error($schedule_id)) {
            return $schedule_id;
        }

        // No caching - removed for real-time performance

        // Trigger action
        do_action('learnpressium_enrollment_scheduled', $schedule_id, $user_id, $course_id, $start_date);

        return $schedule_id;
    }

    /**
     * Create a LearnPress enrollment (used when activating scheduled enrollments)
     */
    private function create_learnpress_enrollment($user_id, $course_id) {
        // Use LearnPress's proper enrollment method
        try {
            // Check if LearnPress UserCourseModel is available
            if (class_exists('UserCourseModel')) {
                // Use LearnPress's UserCourseModel
                $user_course = new UserCourseModel();
                $user_course->user_id = $user_id;
                $user_course->item_id = $course_id;
                $user_course->item_type = defined('LP_COURSE_CPT') ? LP_COURSE_CPT : 'lp_course';
                $user_course->status = defined('LP_COURSE_ENROLLED') ? LP_COURSE_ENROLLED : 'enrolled';
                $user_course->graduation = defined('LP_COURSE_GRADUATION_IN_PROGRESS') ? LP_COURSE_GRADUATION_IN_PROGRESS : 'in-progress';
                $user_course->start_time = null; // Will be set when activated
                $user_course->end_time = null;
                $user_course->ref_type = 'learnpressium_schedule';
                $user_course->ref_id = 0;

                $result = $user_course->save();

                if ($result) {
                    return $user_course->get_user_item_id();
                } else {
                    return new WP_Error('db_error', 'Failed to create enrollment record using UserCourseModel');
                }
            } else {
                // Fallback to direct database insertion with proper LearnPress status
                global $wpdb;

                $user_item_data = array(
                    'user_id' => $user_id,
                    'item_id' => $course_id,
                    'item_type' => 'lp_course',
                    'status' => 'enrolled', // Use standard LearnPress status
                    'graduation' => 'in-progress',
                    'start_time' => null,
                    'end_time' => null,
                    'ref_type' => 'learnpressium_schedule',
                    'ref_id' => 0
                );

                $result = $wpdb->insert(
                    $wpdb->prefix . 'learnpress_user_items',
                    $user_item_data,
                    array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
                );

                if ($result === false) {
                    return new WP_Error('db_error', 'Failed to create enrollment record: ' . $wpdb->last_error);
                }

                return $wpdb->insert_id;
            }
        } catch (Exception $e) {
            return new WP_Error('enrollment_error', 'Failed to create enrollment: ' . $e->getMessage());
        }
    }

    /**
     * Update user item meta with schedule information
     */
    private function update_user_item_meta($user_item_id, $start_date) {
        if (function_exists('learn_press_update_user_item_meta')) {
            learn_press_update_user_item_meta($user_item_id, '_learnpressium_scheduled_start', $start_date);
            learn_press_update_user_item_meta($user_item_id, '_learnpressium_schedule_status', 'pending');
            learn_press_update_user_item_meta($user_item_id, '_learnpressium_original_enrollment', current_time('mysql'));
        } else {
            // Fallback to WordPress meta functions
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'learnpress_user_itemmeta',
                array(
                    'learnpress_user_item_id' => $user_item_id,
                    'meta_key' => '_learnpressium_scheduled_start',
                    'meta_value' => $start_date
                )
            );
            $wpdb->insert(
                $wpdb->prefix . 'learnpress_user_itemmeta',
                array(
                    'learnpress_user_item_id' => $user_item_id,
                    'meta_key' => '_learnpressium_schedule_status',
                    'meta_value' => 'scheduled'
                )
            );
            $wpdb->insert(
                $wpdb->prefix . 'learnpress_user_itemmeta',
                array(
                    'learnpress_user_item_id' => $user_item_id,
                    'meta_key' => '_learnpressium_original_enrollment',
                    'meta_value' => current_time('mysql')
                )
            );

            // Removed: End date meta - no longer needed
        }
    }

    /**
     * Get user course item from LearnPress
     */
    private function get_user_course_item($user_id, $course_id) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d AND item_id = %d AND item_type = 'lp_course'
             ORDER BY user_item_id DESC LIMIT 1",
            $user_id,
            $course_id
        );

        return $wpdb->get_row($sql);
    }

    /**
     * Ensure no LearnPress enrollment exists for user/course
     * This is critical to prevent immediate access when scheduling
     */
    private function ensure_no_learnpress_enrollment($user_id, $course_id) {
        global $wpdb;

        // Get all enrollments for this user/course combination
        $user_items = $wpdb->get_results($wpdb->prepare(
            "SELECT user_item_id FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d AND item_id = %d AND item_type = 'lp_course'",
            $user_id,
            $course_id
        ));

        if ($user_items) {
            foreach ($user_items as $item) {
                // Delete the enrollment record
                $wpdb->delete(
                    $wpdb->prefix . 'learnpress_user_items',
                    array('user_item_id' => $item->user_item_id),
                    array('%d')
                );

                // Delete related meta
                $wpdb->delete(
                    $wpdb->prefix . 'learnpress_user_itemmeta',
                    array('learnpress_user_item_id' => $item->user_item_id),
                    array('%d')
                );

                error_log("Learnpressium: Removed LearnPress enrollment {$item->user_item_id} for user {$user_id} course {$course_id}");
            }
        }

        error_log("Learnpressium: Ensured no LearnPress enrollment exists for user {$user_id} course {$course_id}");
    }

    /**
     * Validate schedule data
     */
    private function validate_schedule_data($user_id, $course_id, $start_date) {
        // Check if user exists
        if (!get_user_by('id', $user_id)) {
            return false;
        }

        // Check if course exists
        if (!get_post($course_id) || get_post_type($course_id) !== 'lp_course') {
            return false;
        }

        // Validate start date
        if (!$this->validate_datetime($start_date)) {
            return false;
        }

        return true;
    }

    /**
     * Validate datetime string
     */
    private function validate_datetime($datetime) {
        $d = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        return $d && $d->format('Y-m-d H:i:s') === $datetime;
    }

    /**
     * Process scheduled enrollments (called by cron)
     */
    public function process_scheduled_enrollments() {
        // Activate schedules that are ready
        $schedules_to_activate = $this->database->get_schedules_to_activate();
        foreach ($schedules_to_activate as $schedule) {
            $this->activate_schedule($schedule);
        }

        // Expire schedules that have ended
        $schedules_to_expire = $this->database->get_schedules_to_expire();
        foreach ($schedules_to_expire as $schedule) {
            $this->expire_schedule($schedule);
        }
    }

    /**
     * Activate a scheduled enrollment
     */
    private function activate_schedule($schedule) {
        // Create the actual LearnPress enrollment now
        $user_item_id = $this->create_learnpress_enrollment($schedule->user_id, $schedule->course_id);

        if (is_wp_error($user_item_id)) {
            error_log('Learnpressium: Failed to create LearnPress enrollment for schedule ' . $schedule->schedule_id . ': ' . $user_item_id->get_error_message());
            return false;
        }

        // Update schedule with the new user_item_id and set to active
        $update_data = array(
            'status' => 'active',
            'user_item_id' => $user_item_id,
            'updated_at' => current_time('mysql')
        );

        $result = $this->database->update_schedule($schedule->schedule_id, $update_data);

        if (!is_wp_error($result)) {
            // Update user item meta to indicate it was scheduled
            $this->update_user_item_meta($user_item_id, $schedule->scheduled_start_date);

            // Trigger LearnPress enrollment action
            do_action('learnpress/user/course-enrolled', 0, $schedule->course_id, $schedule->user_id);

            // Trigger our custom action
            do_action('learnpressium_enrollment_activated', $schedule);

            error_log("Learnpressium: Successfully activated schedule {$schedule->schedule_id} for user {$schedule->user_id} in course {$schedule->course_id}");
            return true;
        }

        return false;
    }

    /**
     * Expire a scheduled enrollment
     */
    private function expire_schedule($schedule) {
        global $wpdb;

        // Update user item to finished status
        $result = $wpdb->update(
            $wpdb->prefix . 'learnpress_user_items',
            array(
                'status' => 'finished',
                'end_time' => current_time('mysql')
            ),
            array('user_item_id' => $schedule->user_item_id),
            array('%s', '%s'),
            array('%d')
        );

        if ($result !== false) {
            // Update schedule status
            $this->database->update_schedule($schedule->schedule_id, array('status' => 'expired'));
            
            // Update meta
            if (function_exists('learn_press_update_user_item_meta')) {
                learn_press_update_user_item_meta($schedule->user_item_id, '_learnpressium_schedule_status', 'expired');
            }
            
            // Trigger our custom action
            do_action('learnpressium_enrollment_expired', $schedule);
        }
    }

    /**
     * Check if user has scheduled access to course
     */
    public function has_scheduled_access($user_id, $course_id) {
        $schedule = $this->database->get_user_course_schedule($user_id, $course_id);
        
        if (!$schedule) {
            return false;
        }

        $current_time = current_time('mysql');
        
        // Check if schedule is active
        if ($schedule->status === 'active') {
            // Check if not expired
            if (!$schedule->scheduled_end_date || $schedule->scheduled_end_date > $current_time) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get user schedule for course
     */
    public function get_user_schedule($user_id, $course_id) {
        return $this->database->get_user_course_schedule($user_id, $course_id);
    }

    /**
     * Get database manager instance
     *
     * @return Enrollment_Database
     */
    public function get_database() {
        return $this->database;
    }

    /**
     * Process enrollment when user is enrolled through normal LearnPress flow
     */
    public function process_enrollment($order_id, $course_id, $user_id) {
        // Check if this user has a scheduled enrollment for this course
        $schedule = $this->database->get_user_course_schedule($user_id, $course_id);
        
        if ($schedule && $schedule->status === 'pending') {
            // If enrollment happens before scheduled time, update the schedule
            $current_time = current_time('mysql');
            if ($schedule->scheduled_start_date > $current_time) {
                // Update schedule to be active immediately
                $this->database->update_schedule($schedule->schedule_id, array(
                    'status' => 'active',
                    'scheduled_start_date' => $current_time
                ));
                
                if (function_exists('learn_press_update_user_item_meta')) {
                    learn_press_update_user_item_meta($schedule->user_item_id, '_learnpressium_schedule_status', 'active');
                }
            }
        }
    }

    /**
     * AJAX handler for scheduling enrollment
     */
    public function ajax_schedule_enrollment() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'learnpressium_enrollment_nonce')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $user_id = intval($_POST['user_id']);
        $course_id = intval($_POST['course_id']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
        $notes = sanitize_textarea_field($_POST['notes']);

        $result = $this->schedule_enrollment($user_id, $course_id, $start_date, $end_date, $notes);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array('schedule_id' => $result));
        }
    }

    /**
     * AJAX handler for getting schedules
     */
    public function ajax_get_schedules() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'learnpressium_enrollment_nonce')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $user_id = !empty($_POST['user_id']) ? intval($_POST['user_id']) : null;
        $course_id = !empty($_POST['course_id']) ? intval($_POST['course_id']) : null;

        if ($user_id) {
            $schedules = $this->database->get_user_schedules($user_id);
        } elseif ($course_id) {
            $schedules = $this->database->get_course_schedules($course_id);
        } else {
            wp_send_json_error('Invalid parameters');
            return;
        }

        wp_send_json_success($schedules);
    }

    /**
     * AJAX handler for updating schedule
     */
    public function ajax_update_schedule() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'learnpressium_enrollment_nonce')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $schedule_id = intval($_POST['schedule_id']);
        $data = array();

        if (!empty($_POST['start_date'])) {
            $data['scheduled_start_date'] = sanitize_text_field($_POST['start_date']);
        }

        if (!empty($_POST['end_date'])) {
            $data['scheduled_end_date'] = sanitize_text_field($_POST['end_date']);
        }

        if (isset($_POST['notes'])) {
            $data['notes'] = sanitize_textarea_field($_POST['notes']);
        }

        if (isset($_POST['status'])) {
            $data['status'] = sanitize_text_field($_POST['status']);
        }

        $result = $this->database->update_schedule($schedule_id, $data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success();
        }
    }

    /**
     * AJAX handler for deleting schedule
     */
    public function ajax_delete_schedule() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'learnpressium_enrollment_nonce')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $schedule_id = intval($_POST['schedule_id']);
        $result = $this->database->delete_schedule($schedule_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success();
        }
    }

    /**
     * AJAX handler for getting single schedule
     */
    public function ajax_get_schedule() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'learnpressium_enrollment_nonce')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $schedule_id = intval($_POST['schedule_id']);
        $schedule = $this->database->get_schedule($schedule_id);

        if ($schedule) {
            wp_send_json_success($schedule);
        } else {
            wp_send_json_error('Schedule not found');
        }
    }

    /**
     * AJAX handler for bulk schedule actions
     */
    public function ajax_bulk_schedule_action() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'learnpressium_enrollment_nonce')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $action = sanitize_text_field($_POST['bulk_action']);
        $schedule_ids = array_map('intval', $_POST['schedule_ids']);

        $results = array();
        $errors = array();

        foreach ($schedule_ids as $schedule_id) {
            $schedule = $this->database->get_schedule($schedule_id);
            if (!$schedule) {
                $errors[] = "Schedule {$schedule_id} not found";
                continue;
            }

            switch ($action) {
                case 'activate':
                    $this->activate_schedule($schedule);
                    $results[] = $schedule_id;
                    break;

                case 'expire':
                    $this->expire_schedule($schedule);
                    $results[] = $schedule_id;
                    break;

                case 'delete':
                    $result = $this->database->delete_schedule($schedule_id);
                    if (is_wp_error($result)) {
                        $errors[] = "Failed to delete schedule {$schedule_id}";
                    } else {
                        $results[] = $schedule_id;
                    }
                    break;

                default:
                    $errors[] = "Unknown action: {$action}";
            }
        }

        if (!empty($errors)) {
            wp_send_json_error(array(
                'message' => 'Some actions failed',
                'errors' => $errors,
                'success_count' => count($results)
            ));
        } else {
            wp_send_json_success(array(
                'message' => sprintf('%d schedules processed successfully', count($results)),
                'processed_ids' => $results
            ));
        }
    }

    /**
     * Clean up orphaned schedules (schedules without corresponding LearnPress enrollments)
     */
    public function cleanup_orphaned_schedules() {
        global $wpdb;

        // Get all active schedules
        $schedules = $this->database->get_all_schedules(array('status' => 'active'));

        $cleaned_count = 0;

        foreach ($schedules as $schedule) {
            // Check if the corresponding LearnPress enrollment still exists
            if ($schedule->user_item_id) {
                $user_item = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}learnpress_user_items WHERE user_item_id = %d",
                    $schedule->user_item_id
                ));

                // If LearnPress enrollment doesn't exist, clean up our schedule
                if (!$user_item) {
                    $this->database->delete_schedule($schedule->schedule_id);
                    $cleaned_count++;
                    error_log("Learnpressium: Cleaned up orphaned schedule {$schedule->schedule_id}");
                }
            }
        }

        error_log("Learnpressium: Cleaned up {$cleaned_count} orphaned schedules");
        return $cleaned_count;
    }

    /**
     * Clean up all schedules for a specific user and course
     */
    public function cleanup_user_course_schedules($user_id, $course_id) {
        $schedules = $this->database->get_user_course_schedules($user_id, $course_id);
        $cleaned_count = 0;

        foreach ($schedules as $schedule) {
            $this->database->delete_schedule($schedule->schedule_id);
            $cleaned_count++;
            error_log("Learnpressium: Cleaned up schedule {$schedule->schedule_id} for user {$user_id} course {$course_id}");
        }

        return $cleaned_count;
    }
}
