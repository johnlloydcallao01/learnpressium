<?php

/**
 * The Enrollment Admin class
 *
 * Handles admin interface for enrollment scheduling
 *
 * @package    Learnpressium
 * @subpackage Learnpressium/includes/modules/enrollment
 */

class Enrollment_Admin {

    /**
     * The enrollment manager instance
     */
    private $enrollment_manager;

    /**
     * Constructor
     */
    public function __construct($enrollment_manager) {
        $this->enrollment_manager = $enrollment_manager;
    }

    /**
     * Initialize admin interface
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('add_meta_boxes', array($this, 'add_course_meta_boxes'));
        add_action('save_post', array($this, 'save_course_meta_box'));
        add_action('show_user_profile', array($this, 'add_user_profile_section'));
        add_action('edit_user_profile', array($this, 'add_user_profile_section'));

        // Add AJAX handlers for schedule management
        add_action('wp_ajax_learnpressium_admin_get_schedules', array($this, 'ajax_get_schedules'));
        add_action('wp_ajax_learnpressium_admin_delete_schedule', array($this, 'ajax_delete_schedule'));
        add_action('wp_ajax_learnpressium_admin_activate_schedule', array($this, 'ajax_activate_schedule'));
        add_action('wp_ajax_learnpressium_admin_save_schedule', array($this, 'ajax_save_schedule'));

        // CRITICAL FIX: Add manual activation handler and admin notice
        add_action('wp_ajax_learnpressium_manual_activate_all', array($this, 'ajax_manual_activate_all'));
        add_action('admin_notices', array($this, 'show_scheduler_status_notice'));
    }

    /**
     * Add admin menu for enrollment management
     */
    public function add_admin_menu() {
        add_submenu_page(
            'learn_press',
            __('Enrollment Schedules', 'learnpressium'),
            __('Enrollment Schedules', 'learnpressium'),
            'manage_options',
            'learnpressium-enrollment-schedules',
            array($this, 'display_schedules_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'learnpressium') === false && 
            strpos($hook, 'learn-press') === false && 
            $hook !== 'post.php' && 
            $hook !== 'post-new.php' &&
            $hook !== 'profile.php' &&
            $hook !== 'user-edit.php') {
            return;
        }

        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-datepicker', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css');
        
        wp_enqueue_script(
            'learnpressium-enrollment-admin',
            LEARNPRESSIUM_PLUGIN_URL . 'admin/js/enrollment-admin.js',
            array('jquery', 'jquery-ui-datepicker'),
            LEARNPRESSIUM_VERSION,
            true
        );

        wp_enqueue_style(
            'learnpressium-enrollment-admin',
            LEARNPRESSIUM_PLUGIN_URL . 'admin/css/enrollment-admin.css',
            array(),
            LEARNPRESSIUM_VERSION
        );

        wp_localize_script('learnpressium-enrollment-admin', 'learnpressium_enrollment', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('learnpressium_enrollment_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this schedule?', 'learnpressium'),
                'schedule_saved' => __('Schedule saved successfully', 'learnpressium'),
                'schedule_deleted' => __('Schedule deleted successfully', 'learnpressium'),
                'error_occurred' => __('An error occurred', 'learnpressium'),
                'loading' => __('Loading...', 'learnpressium')
            )
        ));
    }

    /**
     * Display enrollment schedules page
     */
    public function display_schedules_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Enrollment Schedules', 'learnpressium'); ?></h1>
            
            <div id="learnpressium-enrollment-schedules">
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <select id="filter-by-status">
                            <option value=""><?php _e('All Statuses', 'learnpressium'); ?></option>
                            <option value="scheduled"><?php _e('Scheduled', 'learnpressium'); ?></option>
                            <option value="active"><?php _e('Active', 'learnpressium'); ?></option>
                            <option value="expired"><?php _e('Expired', 'learnpressium'); ?></option>
                        </select>
                        <button type="button" class="button" id="filter-schedules"><?php _e('Filter', 'learnpressium'); ?></button>
                    </div>
                    <div class="alignright actions">
                        <button type="button" class="button button-primary" id="add-new-schedule">
                            <?php _e('Add New Schedule', 'learnpressium'); ?>
                        </button>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('User', 'learnpressium'); ?></th>
                            <th><?php _e('Course', 'learnpressium'); ?></th>
                            <th><?php _e('Start Date', 'learnpressium'); ?></th>
                            <th><?php _e('End Date', 'learnpressium'); ?></th>
                            <th><?php _e('Status', 'learnpressium'); ?></th>
                            <th><?php _e('Actions', 'learnpressium'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="schedules-table-body">
                        <tr>
                            <td colspan="6" class="text-center">
                                <span class="spinner is-active"></span>
                                <?php _e('Loading schedules...', 'learnpressium'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Add/Edit Schedule Modal -->
            <div id="schedule-modal" class="learnpressium-modal" style="display: none;">
                <div class="learnpressium-modal-content">
                    <div class="learnpressium-modal-header">
                        <h2 id="modal-title"><?php _e('Add New Schedule', 'learnpressium'); ?></h2>
                        <span class="learnpressium-modal-close">&times;</span>
                    </div>
                    <div class="learnpressium-modal-body">
                        <form id="schedule-form">
                            <input type="hidden" id="schedule-id" name="schedule_id" value="">
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="user-select"><?php _e('User', 'learnpressium'); ?></label>
                                    </th>
                                    <td>
                                        <select id="user-select" name="user_id" required>
                                            <option value=""><?php _e('Select User', 'learnpressium'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="course-select"><?php _e('Course', 'learnpressium'); ?></label>
                                    </th>
                                    <td>
                                        <select id="course-select" name="course_id" required>
                                            <option value=""><?php _e('Select Course', 'learnpressium'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="start-date"><?php _e('Start Date', 'learnpressium'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="start-date" name="start_date" class="datepicker" required>
                                        <input type="time" id="start-time" name="start_time" value="09:00">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="end-date"><?php _e('End Date (Optional)', 'learnpressium'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="end-date" name="end_date" class="datepicker">
                                        <input type="time" id="end-time" name="end_time" value="23:59">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="schedule-notes"><?php _e('Notes', 'learnpressium'); ?></label>
                                    </th>
                                    <td>
                                        <textarea id="schedule-notes" name="notes" rows="3" cols="50"></textarea>
                                    </td>
                                </tr>
                            </table>
                        </form>
                    </div>
                    <div class="learnpressium-modal-footer">
                        <button type="button" class="button button-primary" id="save-schedule">
                            <?php _e('Save Schedule', 'learnpressium'); ?>
                        </button>
                        <button type="button" class="button" id="cancel-schedule">
                            <?php _e('Cancel', 'learnpressium'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .learnpressium-modal {
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .learnpressium-modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 0;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 4px;
        }

        .learnpressium-modal-header {
            padding: 15px 20px;
            background-color: #f1f1f1;
            border-bottom: 1px solid #ddd;
            position: relative;
        }

        .learnpressium-modal-header h2 {
            margin: 0;
        }

        .learnpressium-modal-close {
            position: absolute;
            right: 15px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .learnpressium-modal-body {
            padding: 20px;
        }

        .learnpressium-modal-footer {
            padding: 15px 20px;
            background-color: #f1f1f1;
            border-top: 1px solid #ddd;
            text-align: right;
        }

        .learnpressium-modal-footer .button {
            margin-left: 10px;
        }

        .datepicker {
            width: 150px;
        }

        input[type="time"] {
            width: 100px;
            margin-left: 10px;
        }

        .text-center {
            text-align: center;
        }

        .status-scheduled {
            color: #0073aa;
        }

        .status-active {
            color: #46b450;
        }

        .status-expired {
            color: #dc3232;
        }
        </style>
        <?php
    }

    /**
     * Add meta box to course edit page
     */
    public function add_course_meta_boxes() {
        add_meta_box(
            'learnpressium-enrollment-schedules',
            __('Enrollment Schedules', 'learnpressium'),
            array($this, 'display_course_schedules_meta_box'),
            'lp_course',
            'normal',
            'default'
        );
    }

    /**
     * Display course schedules meta box
     */
    public function display_course_schedules_meta_box($post) {
        $course_id = $post->ID;
        ?>
        <div id="learnpressium-course-schedules">
            <p><?php _e('Manage enrollment schedules for this course:', 'learnpressium'); ?></p>
            
            <button type="button" class="button button-secondary" id="add-course-schedule" data-course-id="<?php echo $course_id; ?>">
                <?php _e('Add New Schedule', 'learnpressium'); ?>
            </button>
            
            <div id="course-schedules-list">
                <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th><?php _e('User', 'learnpressium'); ?></th>
                            <th><?php _e('Start Date', 'learnpressium'); ?></th>
                            <th><?php _e('End Date', 'learnpressium'); ?></th>
                            <th><?php _e('Status', 'learnpressium'); ?></th>
                            <th><?php _e('Actions', 'learnpressium'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="course-schedules-tbody">
                        <tr>
                            <td colspan="5" class="text-center">
                                <span class="spinner is-active"></span>
                                <?php _e('Loading schedules...', 'learnpressium'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Save course meta box data
     */
    public function save_course_meta_box($post_id) {
        // This is handled via AJAX, so no action needed here
        // But we keep this method for potential future use
    }

    /**
     * Add enrollment schedules section to user profile
     */
    public function add_user_profile_section($user) {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <h3><?php _e('Enrollment Schedules', 'learnpressium'); ?></h3>
        <div id="learnpressium-user-schedules">
            <p><?php _e('Manage enrollment schedules for this user:', 'learnpressium'); ?></p>
            
            <button type="button" class="button button-secondary" id="add-user-schedule" data-user-id="<?php echo $user->ID; ?>">
                <?php _e('Add New Schedule', 'learnpressium'); ?>
            </button>
            
            <div id="user-schedules-list">
                <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th><?php _e('Course', 'learnpressium'); ?></th>
                            <th><?php _e('Start Date', 'learnpressium'); ?></th>
                            <th><?php _e('End Date', 'learnpressium'); ?></th>
                            <th><?php _e('Status', 'learnpressium'); ?></th>
                            <th><?php _e('Actions', 'learnpressium'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="user-schedules-tbody">
                        <tr>
                            <td colspan="5" class="text-center">
                                <span class="spinner is-active"></span>
                                <?php _e('Loading schedules...', 'learnpressium'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler to get schedules for admin page
     */
    public function ajax_get_schedules() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'learnpressium_enrollment_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        global $wpdb;
        $status = sanitize_text_field($_POST['status']);
        $table_name = $wpdb->prefix . 'learnpressium_enrollment_schedules';

        $sql = "SELECT s.*, u.display_name as user_name, u.user_email, p.post_title as course_title
                FROM {$table_name} s
                LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
                LEFT JOIN {$wpdb->posts} p ON s.course_id = p.ID";

        if ($status) {
            $sql .= $wpdb->prepare(" WHERE s.status = %s", $status);
        }

        $sql .= " ORDER BY s.scheduled_start_date DESC";

        $schedules = $wpdb->get_results($sql);

        wp_send_json_success($schedules);
    }

    /**
     * AJAX handler to delete schedule
     */
    public function ajax_delete_schedule() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'learnpressium_enrollment_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $schedule_id = intval($_POST['schedule_id']);

        if (!$schedule_id) {
            wp_send_json_error('Invalid schedule ID');
            return;
        }

        // Get user_id before deleting
        global $wpdb;
        $scheduled_table = $wpdb->prefix . 'learnpressium_enrollment_schedules';
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$scheduled_table} WHERE schedule_id = %d",
            $schedule_id
        ));

        // Use database manager to delete
        $database = new Enrollment_Database();
        $result = $database->delete_schedule($schedule_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            // No caching - removed for real-time performance

            wp_send_json_success('Schedule deleted successfully');
        }
    }

    /**
     * AJAX handler to activate schedule
     */
    public function ajax_activate_schedule() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'learnpressium_enrollment_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $schedule_id = intval($_POST['schedule_id']);

        if (!$schedule_id) {
            wp_send_json_error('Invalid schedule ID');
            return;
        }

        // Get the schedule details
        $database = new Enrollment_Database();
        $schedule = $database->get_schedule($schedule_id);

        if (!$schedule) {
            wp_send_json_error('Schedule not found');
            return;
        }

        if ($schedule->status !== 'pending') {
            wp_send_json_error('Only pending schedules can be activated');
            return;
        }

        // Manually activate this specific schedule
        global $wpdb;
        $current_time = current_time('mysql');

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
                'ref_type' => 'learnpressium_manual',
                'ref_id' => $schedule_id,
                'parent_id' => 0
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d')
        );

        if ($result) {
            // Update schedule status to 'activated'
            $update_result = $database->update_schedule($schedule_id, array(
                'status' => 'activated',
                'updated_at' => $current_time
            ));

            // CRITICAL: Trigger LearnPress enrollment action to ensure all hooks fire
            if (function_exists('do_action')) {
                do_action('learnpress/user/course-enrolled', $wpdb->insert_id, $schedule->course_id, $schedule->user_id);
            }

            if (is_wp_error($update_result)) {
                wp_send_json_error('Failed to update schedule status: ' . $update_result->get_error_message());
            } else {
                // No caching - removed for real-time performance

                wp_send_json_success('Schedule activated successfully - User can now access course IMMEDIATELY');
            }
        } else {
            wp_send_json_error('Failed to create LearnPress enrollment');
        }
    }

    /**
     * AJAX handler to save/update schedule
     */
    public function ajax_save_schedule() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'learnpressium_enrollment_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $schedule_id = intval($_POST['schedule_id']);
        $user_id = intval($_POST['user_id']);
        $course_id = intval($_POST['course_id']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $start_time = sanitize_text_field($_POST['start_time']);
        $end_date = !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
        $end_time = !empty($_POST['end_time']) ? sanitize_text_field($_POST['end_time']) : null;
        $notes = sanitize_textarea_field($_POST['notes']);

        // Combine date and time
        $start_datetime = $start_date . ' ' . $start_time . ':00';
        $end_datetime = null;
        if ($end_date && $end_time) {
            $end_datetime = $end_date . ' ' . $end_time . ':00';
        }

        $database = new Enrollment_Database();

        if ($schedule_id) {
            // Update existing schedule
            $update_data = array(
                'scheduled_start_date' => $start_datetime,
                'scheduled_end_date' => $end_datetime,
                'notes' => $notes
            );

            $result = $database->update_schedule($schedule_id, $update_data);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } else {
                // No caching - removed for real-time performance

                wp_send_json_success('Schedule updated successfully');
            }
        } else {
            // Create new schedule
            $insert_data = array(
                'user_id' => $user_id,
                'course_id' => $course_id,
                'scheduled_start_date' => $start_datetime,
                'scheduled_end_date' => $end_datetime,
                'status' => 'pending',
                'created_by' => get_current_user_id(),
                'notes' => $notes
            );

            $result = $database->insert_schedule($insert_data);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } else {
                // No caching - removed for real-time performance

                wp_send_json_success('Schedule created successfully');
            }
        }
    }

    /**
     * CRITICAL FIX: Show admin notice about scheduler status and provide manual activation
     */
    public function show_scheduler_status_notice() {
        // Only show on relevant admin pages
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array(
            'learnpress_page_learn-press-tools',
            'learnpress_page_learnpressium-enrollment-schedules',
            'dashboard'
        ))) {
            return;
        }

        global $wpdb;
        $scheduled_table = $wpdb->prefix . 'learnpressium_enrollment_schedules';

        // Check for overdue schedules
        $overdue_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$scheduled_table}
             WHERE status = 'pending'
             AND scheduled_start_date <= %s",
            current_time('mysql')
        ));

        if ($overdue_count > 0) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <h3>🚨 LearnPressium: Overdue Scheduled Courses</h3>
                <p><strong><?php echo $overdue_count; ?> scheduled courses are overdue for activation!</strong></p>
                <p>These courses should have been automatically activated but weren't due to a system issue that has now been fixed.</p>
                <p>
                    <button type="button" class="button button-primary" id="learnpressium-manual-activate"
                            onclick="learnpressiumManualActivate()">
                        🚀 Activate Overdue Courses Now
                    </button>
                    <span id="learnpressium-activation-status" style="margin-left: 10px;"></span>
                </p>
            </div>

            <script>
            function learnpressiumManualActivate() {
                var button = document.getElementById('learnpressium-manual-activate');
                var status = document.getElementById('learnpressium-activation-status');

                button.disabled = true;
                button.textContent = '⏳ Activating...';
                status.innerHTML = '<span class="spinner is-active" style="float: none; margin: 0;"></span>';

                jQuery.post(ajaxurl, {
                    action: 'learnpressium_manual_activate_all',
                    nonce: '<?php echo wp_create_nonce('learnpressium_manual_activate'); ?>'
                }, function(response) {
                    if (response.success) {
                        status.innerHTML = '<span style="color: #46b450;">✅ ' + response.data + '</span>';
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        status.innerHTML = '<span style="color: #dc3232;">❌ ' + response.data + '</span>';
                        button.disabled = false;
                        button.textContent = '🚀 Activate Overdue Courses Now';
                    }
                });
            }
            </script>
            <?php
        }
    }

    /**
     * CRITICAL FIX: AJAX handler for manual activation of all overdue schedules
     */
    public function ajax_manual_activate_all() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'learnpressium_manual_activate')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Use simple manager to activate all due schedules
        $simple_manager = new Simple_Enrollment_Manager();
        $activation_count = $simple_manager->activate_due_schedules();

        if ($activation_count > 0) {
            wp_send_json_success("Successfully activated {$activation_count} overdue courses! Users can now access them.");
        } else {
            wp_send_json_success("No courses were due for activation at this time.");
        }
    }
}
