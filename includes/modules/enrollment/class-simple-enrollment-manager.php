<?php
/**
 * Simple Enrollment Manager - Uses LearnPress's Own Tables
 * 
 * This approach perfectly mimics LearnPress's "In Progress" structure by:
 * 1. Using learnpress_user_items table directly
 * 2. Using custom status 'scheduled' 
 * 3. Storing schedule info in learnpress_user_itemmeta
 * 4. Simply changing status to 'enrolled' when date arrives
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_Enrollment_Manager {
    
    /**
     * Schedule a course enrollment using SEPARATE staging table
     * CORRECTED: Don't insert into user_items until scheduled date arrives
     */
    public function schedule_enrollment($user_id, $course_id, $start_date) {
        global $wpdb;

        // Check if user already has this course enrolled
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT user_item_id FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d AND item_id = %d AND item_type = 'lp_course'",
            $user_id, $course_id
        ));

        if ($existing) {
            return false; // Already enrolled
        }

        // Check if already scheduled
        $scheduled_table = $wpdb->prefix . 'learnpressium_enrollment_schedules';
        $existing_schedule = $wpdb->get_row($wpdb->prepare(
            "SELECT schedule_id FROM {$scheduled_table}
             WHERE user_id = %d AND course_id = %d AND status = 'pending'",
            $user_id, $course_id
        ));

        if ($existing_schedule) {
            return false; // Already scheduled
        }

        // Insert into our SEPARATE staging table (NOT user_items)
        $result = $wpdb->insert(
            $scheduled_table,
            array(
                'user_id' => $user_id,
                'course_id' => $course_id,
                'scheduled_start_date' => $start_date,
                'status' => 'pending',
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%d', '%s', '%s')
        );

        if ($result) {
            error_log("Learnpressium: Schedule created for user {$user_id}");
            return $wpdb->insert_id;
        }

        return false;
    }
    
    // Removed: add_schedule_meta() - No longer needed since we removed end_date and notes
    
    /**
     * Get scheduled courses for a user from our staging table
     * CORRECTED: Query from separate staging table, not user_items
     */
    public function get_user_scheduled_courses($user_id) {
        global $wpdb;

        $scheduled_table = $wpdb->prefix . 'learnpressium_enrollment_schedules';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$scheduled_table}
             WHERE user_id = %d AND status = 'pending'
             ORDER BY scheduled_start_date ASC",
            $user_id
        ));
    }
    
    /**
     * Count scheduled courses for a user from our staging table
     * CORRECTED: Count from separate staging table, not user_items
     */
    public function count_user_scheduled_courses($user_id) {
        global $wpdb;

        $scheduled_table = $wpdb->prefix . 'learnpressium_enrollment_schedules';

        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$scheduled_table}
             WHERE user_id = %d AND status = 'pending'",
            $user_id
        )));
    }
    
    /**
     * Activate scheduled courses - Move from staging table to LearnPress user_items
     * CORRECTED: Create proper LearnPress enrollments when date arrives
     */
    public function activate_due_schedules() {
        global $wpdb;

        $current_time = current_time('mysql');
        $scheduled_table = $wpdb->prefix . 'learnpressium_enrollment_schedules';

        // Find all scheduled courses that are due to start
        $due_courses = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$scheduled_table}
             WHERE status = 'pending'
             AND scheduled_start_date <= %s",
            $current_time
        ));

        $activated_count = 0;

        foreach ($due_courses as $schedule) {
            // Check if this schedule was recently edited (prevent race conditions)
            if (get_transient('learnpressium_schedule_edit_' . $schedule->schedule_id)) {
                error_log("Learnpressium: Skipping activation of schedule {$schedule->schedule_id} - recently edited");
                continue;
            }

            // Check if user already has an active enrollment for this course
            $existing_enrollment = $wpdb->get_row($wpdb->prepare(
                "SELECT user_item_id FROM {$wpdb->prefix}learnpress_user_items
                 WHERE user_id = %d AND item_id = %d AND item_type = %s
                 AND status IN ('enrolled', 'completed', 'finished')",
                $schedule->user_id, $schedule->course_id, LP_COURSE_CPT
            ));

            if ($existing_enrollment) {
                error_log("Learnpressium: Skipping activation of schedule {$schedule->schedule_id} - user already has active enrollment");
                // Update schedule status to activated anyway (since user has access)
                $wpdb->update(
                    $scheduled_table,
                    array('status' => 'activated', 'updated_at' => $current_time),
                    array('schedule_id' => $schedule->schedule_id),
                    array('%s', '%s'),
                    array('%d')
                );
                continue;
            }

            // CRITICAL FIX: Create IMMEDIATE LearnPress enrollment with proper status
            $result = $wpdb->insert(
                $wpdb->prefix . 'learnpress_user_items',
                array(
                    'user_id' => $schedule->user_id,
                    'item_id' => $schedule->course_id,
                    'item_type' => LP_COURSE_CPT,  // Use LearnPress constant
                    'status' => LP_COURSE_ENROLLED,  // Use LearnPress constant: 'enrolled'
                    'graduation' => LP_COURSE_GRADUATION_IN_PROGRESS,  // Use LearnPress constant: 'in-progress'
                    'start_time' => $current_time,  // CRITICAL: Set start time to NOW
                    'end_time' => null,
                    'ref_type' => 'learnpressium_auto',
                    'ref_id' => $schedule->schedule_id,
                    'parent_id' => 0
                ),
                array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d')
            );

            if ($result) {
                // Update schedule status to 'activated'
                $wpdb->update(
                    $scheduled_table,
                    array(
                        'status' => 'activated',
                        'updated_at' => $current_time
                    ),
                    array('schedule_id' => $schedule->schedule_id),
                    array('%s', '%s'),
                    array('%d')
                );

                // CRITICAL: Trigger LearnPress enrollment action to ensure all hooks fire
                if (function_exists('do_action')) {
                    do_action('learnpress/user/course-enrolled', $wpdb->insert_id, $schedule->course_id, $schedule->user_id);
                }

                // No caching - removed for real-time performance
                $activated_count++;

                error_log("Learnpressium: INSTANTLY activated schedule {$schedule->schedule_id} - User {$schedule->user_id} can now access course {$schedule->course_id} IMMEDIATELY");
            }
        }

        // No caching - removed for real-time performance

        return $activated_count;
    }
    
    /**
     * Check if a course is scheduled for a user
     */
    public function is_course_scheduled($user_id, $course_id) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT user_item_id FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d AND item_id = %d AND item_type = 'lp_course' AND status = 'scheduled'",
            $user_id, $course_id
        )) ? true : false;
    }

    // REMOVED: All caching logic for real-time performance

    /**
     * Handle status changes when schedule dates are modified
     * This ensures proper status transitions and LearnPress enrollment management
     */
    public function handle_schedule_status_change($schedule_id, $old_status, $new_start_date) {
        global $wpdb;

        $current_time = current_time('mysql');
        $scheduled_table = $wpdb->prefix . 'learnpressium_enrollment_schedules';

        // Get schedule details
        $schedule = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$scheduled_table} WHERE schedule_id = %d",
            $schedule_id
        ));

        if (!$schedule) {
            return false;
        }

        // Determine new status based on date
        $new_status = ($new_start_date > $current_time) ? 'pending' : 'activated';

        // Handle status transitions
        if ($old_status === 'activated' && $new_status === 'pending') {
            // Moving from activated back to pending - remove LearnPress enrollment
            $wpdb->delete(
                $wpdb->prefix . 'learnpress_user_items',
                array(
                    'user_id' => $schedule->user_id,
                    'item_id' => $schedule->course_id,
                    'item_type' => LP_COURSE_CPT,
                    'ref_type' => 'learnpressium_auto',
                    'ref_id' => $schedule_id
                ),
                array('%d', '%d', '%s', '%s', '%d')
            );

            error_log("Learnpressium: Reverted schedule {$schedule_id} from activated to pending - removed LearnPress enrollment");
        }

        return $new_status;
    }

    /**
     * Delete a schedule
     */
    public function delete_schedule($user_id, $course_id) {
        global $wpdb;

        $scheduled_table = $wpdb->prefix . 'learnpressium_enrollment_schedules';

        $result = $wpdb->delete(
            $scheduled_table,
            array(
                'user_id' => $user_id,
                'course_id' => $course_id,
                'status' => 'pending'
            ),
            array('%d', '%d', '%s')
        );

        if ($result) {
            error_log("Learnpressium: Schedule deleted for user {$user_id}");
        }

        return $result;
    }

    /**
     * Update a schedule
     */
    public function update_schedule($schedule_id, $data) {
        global $wpdb;

        $scheduled_table = $wpdb->prefix . 'learnpressium_enrollment_schedules';

        // Get the user_id before updating
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$scheduled_table} WHERE schedule_id = %d",
            $schedule_id
        ));

        if (!$user_id) {
            return false;
        }

        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $scheduled_table,
            $data,
            array('schedule_id' => $schedule_id),
            null, // Let WordPress determine format
            array('%d')
        );

        if ($result !== false) {
            error_log("Learnpressium: Schedule {$schedule_id} updated for user {$user_id}");
        }

        return $result;
    }
}
