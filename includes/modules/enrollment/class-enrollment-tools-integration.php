<?php

/**
 * The Enrollment Tools Integration class
 *
 * Integrates enrollment scheduling with LearnPress Tools
 *
 * @package    Learnpressium
 * @subpackage Learnpressium/includes/modules/enrollment
 */

class Enrollment_Tools_Integration {

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
     * Initialize tools integration
     */
    public function init() {
        // REMOVED: Separate tab approach - integrating directly into existing Assign Course instead
        // add_filter('learn-press/admin/tools-tabs', array($this, 'add_tools_tab'));

        // Enhance existing assign/unassign forms with scheduling options
        add_action('admin_footer', array($this, 'enhance_assignment_forms'));

        // Add management box to Tools page
        add_action('admin_footer', array($this, 'add_management_box'));

        // Handle scheduled assignments via AJAX
        add_action('wp_ajax_learnpressium_tools_schedule_assignment', array($this, 'handle_scheduled_assignment'));

        // Handle management operations via AJAX (using unique action names to avoid conflicts)
        add_action('wp_ajax_learnpressium_tools_delete_schedule', array($this, 'handle_delete_schedule'));
        add_action('wp_ajax_learnpressium_tools_edit_schedule', array($this, 'handle_edit_schedule'));
        add_action('wp_ajax_learnpressium_tools_activate_schedule', array($this, 'handle_activate_schedule'));
        add_action('wp_ajax_learnpressium_tools_get_schedules', array($this, 'handle_get_schedules'));

        // Add REST API endpoints for tools integration
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Add scheduled enrollment tab to LearnPress Tools
     */
    public function add_tools_tab($tabs) {
        $tabs['scheduled_enrollment'] = __('Scheduled Enrollment', 'learnpressium');

        // Debug: Log that our tab is being added
        error_log('Learnpressium: Adding Scheduled Enrollment tab to LearnPress Tools');

        return $tabs;
    }

    /**
     * Temporary debug notice to verify module is working
     */
    public function debug_notice() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'learnpress_page_learn-press-tools') {
            echo '<div class="notice notice-info"><p><strong>Learnpressium Enrollment Module:</strong> Tools integration is active. Look for the "Scheduled Enrollment" tab above.</p></div>';
        }
    }

    /**
     * Display content for scheduled enrollment tools tab
     */
    public function display_tools_content() {
        // Debug: Log that content is being displayed
        error_log('Learnpressium: Displaying Scheduled Enrollment content');
        ?>
        <div id="learnpressium-scheduled-enrollment-tools">
            <div class="card">
                <h2><?php _e('Schedule Course Enrollment', 'learnpressium'); ?></h2>
                <div class="description">
                    <p><?php _e('Schedule users to access courses at specific dates and times.', 'learnpressium'); ?></p>
                    <p><strong><?php _e('Note:', 'learnpressium'); ?></strong> <?php _e('Users will be enrolled immediately but cannot access the course content until the scheduled start date.', 'learnpressium'); ?></p>
                </div>
                
                <div class="content">
                    <form id="lp-schedule-enrollment-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="schedule-course-select"><?php _e('Select Course', 'learnpressium'); ?></label>
                                </th>
                                <td>
                                    <select id="schedule-course-select" name="course_ids[]" multiple required>
                                        <!-- Populated via AJAX -->
                                    </select>
                                    <p class="description"><?php _e('Select one or more courses', 'learnpressium'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="schedule-user-select"><?php _e('Select Users', 'learnpressium'); ?></label>
                                </th>
                                <td>
                                    <select id="schedule-user-select" name="user_ids[]" multiple required>
                                        <!-- Populated via AJAX -->
                                    </select>
                                    <p class="description"><?php _e('Select one or more users', 'learnpressium'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="schedule-start-date"><?php _e('Start Date & Time', 'learnpressium'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="schedule-start-date" name="start_date" class="datepicker" required>
                                    <input type="time" id="schedule-start-time" name="start_time" value="09:00" required>
                                    <p class="description"><?php _e('When users can start accessing the course', 'learnpressium'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="schedule-end-date"><?php _e('End Date & Time (Optional)', 'learnpressium'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="schedule-end-date" name="end_date" class="datepicker">
                                    <input type="time" id="schedule-end-time" name="end_time" value="23:59">
                                    <p class="description"><?php _e('When access expires (leave empty for no expiration)', 'learnpressium'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="schedule-notes"><?php _e('Notes', 'learnpressium'); ?></label>
                                </th>
                                <td>
                                    <textarea id="schedule-notes" name="notes" rows="3" cols="50"></textarea>
                                    <p class="description"><?php _e('Optional notes about this scheduled enrollment', 'learnpressium'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="submit-section">
                            <button type="submit" class="button button-primary lp-button-schedule-enrollment">
                                <?php _e('Schedule Enrollment', 'learnpressium'); ?>
                            </button>
                            <span class="percent" style="margin-left: 10px"></span>
                            <span class="message" style="margin-left: 10px"></span>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Bulk Schedule Management -->
            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('Manage Scheduled Enrollments', 'learnpressium'); ?></h2>
                <div class="description">
                    <p><?php _e('View and manage existing scheduled enrollments.', 'learnpressium'); ?></p>
                </div>
                
                <div class="content">
                    <div class="tablenav top">
                        <div class="alignleft actions">
                            <select id="bulk-status-filter">
                                <option value=""><?php _e('All Statuses', 'learnpressium'); ?></option>
                                <option value="scheduled"><?php _e('Scheduled', 'learnpressium'); ?></option>
                                <option value="active"><?php _e('Active', 'learnpressium'); ?></option>
                                <option value="expired"><?php _e('Expired', 'learnpressium'); ?></option>
                            </select>
                            <button type="button" class="button" id="filter-bulk-schedules"><?php _e('Filter', 'learnpressium'); ?></button>
                        </div>
                        <div class="alignright actions">
                            <select id="bulk-actions">
                                <option value=""><?php _e('Bulk Actions', 'learnpressium'); ?></option>
                                <option value="activate"><?php _e('Activate Now', 'learnpressium'); ?></option>
                                <option value="expire"><?php _e('Expire Now', 'learnpressium'); ?></option>
                                <option value="delete"><?php _e('Delete', 'learnpressium'); ?></option>
                            </select>
                            <button type="button" class="button" id="apply-bulk-action"><?php _e('Apply', 'learnpressium'); ?></button>
                        </div>
                    </div>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="select-all-schedules">
                                </td>
                                <th><?php _e('User', 'learnpressium'); ?></th>
                                <th><?php _e('Course', 'learnpressium'); ?></th>
                                <th><?php _e('Start Date', 'learnpressium'); ?></th>
                                <th><?php _e('End Date', 'learnpressium'); ?></th>
                                <th><?php _e('Status', 'learnpressium'); ?></th>
                                <th><?php _e('Actions', 'learnpressium'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="bulk-schedules-tbody">
                            <tr>
                                <td colspan="7" class="text-center">
                                    <span class="spinner is-active"></span>
                                    <?php _e('Loading schedules...', 'learnpressium'); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <style>
        #learnpressium-scheduled-enrollment-tools .form-table th {
            width: 200px;
        }

        #learnpressium-scheduled-enrollment-tools .datepicker {
            width: 150px;
        }

        #learnpressium-scheduled-enrollment-tools input[type="time"] {
            width: 100px;
            margin-left: 10px;
        }

        #learnpressium-scheduled-enrollment-tools select[multiple] {
            height: 120px;
            width: 100%;
        }

        #learnpressium-scheduled-enrollment-tools .submit-section {
            padding: 20px 0;
            border-top: 1px solid #ddd;
            margin-top: 20px;
        }

        .status-scheduled {
            color: #0073aa;
            font-weight: bold;
        }

        .status-active {
            color: #46b450;
            font-weight: bold;
        }

        .status-expired {
            color: #dc3232;
            font-weight: bold;
        }

        .text-center {
            text-align: center;
        }

        .check-column {
            width: 2.2em;
        }
        </style>
        <?php
    }

    /**
     * Enhance existing assignment forms with scheduling options
     */
    public function enhance_assignment_forms() {
        $screen = get_current_screen();

        // Only add to LearnPress Tools page
        if (!$screen || $screen->id !== 'learnpress_page_learn-press-tools') {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Wait for the page to be fully loaded
            setTimeout(function() {
                // Check if the assign course form exists
                if ($('#lp-assign-user-course-form').length && $('#lp-assign-user-course-form ul').length) {

                    // Create scheduling options HTML
                    var schedulingHtml = '<li style="border-top: 1px solid #ddd; padding-top: 15px; margin-top: 15px;">' +
                        '<h4>📅 Scheduling Options (Learnpressium)</h4>' +
                        '<label style="display: block; margin-bottom: 10px;">' +
                            '<input type="checkbox" id="enable-scheduling" name="enable_scheduling" value="1" style="margin-right: 8px;">' +
                            'Schedule this enrollment for a future date' +
                        '</label>' +
                        '<div id="scheduling-options" style="display: none; margin-top: 10px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">' +
                            '<div style="margin-bottom: 15px;">' +
                                '<label style="display: block; font-weight: 600; margin-bottom: 5px;">Start Date & Time:</label>' +
                                '<input type="date" id="assign-start-date" name="schedule_start_date" style="width: 150px; margin-right: 10px;">' +
                                '<input type="time" id="assign-start-time" name="schedule_start_time" value="09:00" style="width: 100px;">' +
                            '</div>' +
                            // Removed: End Date & Time and Notes fields - simplified to only Start Date & Time
                        '</div>' +
                    '</li>';

                    // Append to the form
                    $('#lp-assign-user-course-form ul').append(schedulingHtml);

                    // Toggle scheduling options
                    $('#enable-scheduling').change(function() {
                        $('#scheduling-options').toggle(this.checked);
                    });

                    // CRITICAL FIX: Completely intercept ALL form interactions when scheduling is enabled
                    // Use capture phase to intercept before any other handlers
                    document.getElementById('lp-assign-user-course-form').addEventListener('submit', function(e) {
                        if ($('#enable-scheduling').is(':checked')) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            handleScheduledAssignment();
                            return false;
                        }
                    }, true); // Use capture phase

                    // Intercept button clicks with highest priority
                    $(document).on('click', '#lp-assign-user-course-form .lp-button-assign, #lp-assign-user-course-form .lp-button-assign-course, #lp-assign-user-course-form input[type="submit"], #lp-assign-user-course-form button[type="submit"]', function(e) {
                        if ($('#enable-scheduling').is(':checked')) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            handleScheduledAssignment();
                            return false;
                        }
                    });

                    // ADDITIONAL SAFETY: Disable the original form action when scheduling is enabled
                    $('#enable-scheduling').change(function() {
                        var $form = $('#lp-assign-user-course-form');
                        if (this.checked) {
                            // Store original action and disable it
                            $form.data('original-action', $form.attr('action'));
                            $form.attr('action', 'javascript:void(0);');
                        } else {
                            // Restore original action
                            var originalAction = $form.data('original-action');
                            if (originalAction) {
                                $form.attr('action', originalAction);
                            }
                        }
                    });

                    console.log('Learnpressium: Scheduling options added to Assign Course form');
                } else {
                    console.log('Learnpressium: Assign Course form not found');
                }
            }, 1000); // Wait 1 second for LearnPress to load its form

            // Function to handle scheduled assignment
            function handleScheduledAssignment() {
                // Get the correct field values - LearnPress uses different field names
                var courseIds = [];
                var userIds = [];

                // Try different possible field names
                var courseField = $('#lp-assign-user-course-form select[name="course_ids"]').val() ||
                                 $('#lp-assign-user-course-form select[name="course-id"]').val() ||
                                 $('#lp-assign-user-course-form select[name="course_id"]').val();

                var userField = $('#lp-assign-user-course-form select[name="user_ids"]').val() ||
                               $('#lp-assign-user-course-form select[name="user-id"]').val() ||
                               $('#lp-assign-user-course-form select[name="user_id"]').val();

                if (Array.isArray(courseField)) {
                    courseIds = courseField;
                } else if (courseField) {
                    courseIds = [courseField];
                }

                if (Array.isArray(userField)) {
                    userIds = userField;
                } else if (userField) {
                    userIds = [userField];
                }

                console.log('Course IDs:', courseIds);
                console.log('User IDs:', userIds);

                if (courseIds.length === 0 || userIds.length === 0) {
                    alert('❌ Please select both course and user before scheduling.');
                    return;
                }

                var formData = {
                    action: 'learnpressium_tools_schedule_assignment',
                    nonce: '<?php echo wp_create_nonce('learnpressium_enrollment_nonce'); ?>',
                    course_ids: courseIds,
                    user_ids: userIds,
                    start_date: $('#assign-start-date').val(),
                    start_time: $('#assign-start-time').val()
                    // Removed: end_date, end_time, notes - simplified to only start date/time
                };

                console.log('Sending data:', formData);

                // Show loading state
                var $submitBtn = $('#lp-assign-user-course-form .lp-button-assign');
                var originalText = $submitBtn.text();
                $submitBtn.prop('disabled', true).text('Scheduling...');

                // Use WordPress AJAX URL
                var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';

                $.post(ajaxUrl, formData)
                    .done(function(response) {
                        console.log('AJAX Response:', response);
                        if (response.success) {
                            // PROFESSIONAL SUCCESS MESSAGE
                            var message = '✅ Enrollment scheduled successfully!\n\n' + response.data.message;

                            // Add warning info if there were partial issues
                            if (response.data.warnings && response.data.warnings.length > 0) {
                                message += '\n\nNote: Some users may have been skipped (likely already enrolled).';
                            }

                            alert(message);

                            // Reset form and hide scheduling options
                            $('#lp-assign-user-course-form')[0].reset();
                            $('#enable-scheduling').prop('checked', false);
                            $('#scheduling-options').hide();

                            // Restore original form action
                            var $form = $('#lp-assign-user-course-form');
                            var originalAction = $form.data('original-action');
                            if (originalAction) {
                                $form.attr('action', originalAction);
                            }
                        } else {
                            var errorMsg = 'Failed to schedule enrollment';
                            if (response.data && response.data.message) {
                                errorMsg = response.data.message;
                                if (response.data.errors && response.data.errors.length > 0) {
                                    errorMsg += '\n\nErrors:\n' + response.data.errors.join('\n');
                                }
                            }
                            alert('❌ Error: ' + errorMsg);
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.log('AJAX Error:', xhr, status, error);
                        console.log('Response Text:', xhr.responseText);
                        alert('❌ Network error occurred: ' + error + '\n\nCheck browser console for details.');
                    })
                    .always(function() {
                        $submitBtn.prop('disabled', false).text(originalText);
                    });
            }
        });
        </script>

        <style>
        #scheduling-options {
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from { opacity: 0; max-height: 0; }
            to { opacity: 1; max-height: 300px; }
        }

        #enable-scheduling:checked + label {
            color: #0073aa;
            font-weight: 600;
        }
        </style>
        <?php
    }

    /**
     * Handle scheduled assignment via AJAX
     */
    public function handle_scheduled_assignment() {
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

        // Get data
        $course_ids = array_map('intval', $_POST['course_ids']);
        $user_ids = array_map('intval', $_POST['user_ids']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $start_time = sanitize_text_field($_POST['start_time']);
        // Removed: end_date, end_time, notes - simplified to only start date/time

        // Combine date and time
        $start_datetime = $start_date . ' ' . $start_time . ':00';
        // Removed: end_datetime - no longer needed

        $results = array();
        $errors = array();

        error_log('Learnpressium: Processing schedules for ' . count($course_ids) . ' courses and ' . count($user_ids) . ' users');
        error_log('Start datetime: ' . $start_datetime);
        // Removed: End datetime logging - no longer needed

        foreach ($course_ids as $course_id) {
            foreach ($user_ids as $user_id) {
                error_log("Learnpressium: Scheduling user {$user_id} for course {$course_id}");

                // CRITICAL FIX: Add validation before attempting to schedule
                if (!get_user_by('id', $user_id)) {
                    error_log("Learnpressium: Invalid user ID {$user_id}");
                    $errors[] = sprintf('Invalid user ID: %d', $user_id);
                    continue;
                }

                if (!get_post($course_id) || get_post_type($course_id) !== 'lp_course') {
                    error_log("Learnpressium: Invalid course ID {$course_id}");
                    $errors[] = sprintf('Invalid course ID: %d', $course_id);
                    continue;
                }

                // Check if user is already scheduled for this course
                global $wpdb;
                $existing_schedule = $wpdb->get_row($wpdb->prepare(
                    "SELECT schedule_id FROM {$wpdb->prefix}learnpressium_enrollment_schedules
                     WHERE user_id = %d AND course_id = %d AND status IN ('pending', 'activated')",
                    $user_id, $course_id
                ));

                if ($existing_schedule) {
                    error_log("Learnpressium: User {$user_id} already has a schedule for course {$course_id}");
                    $errors[] = sprintf('User %d already has a schedule for course %d', $user_id, $course_id);
                    continue;
                }

                // Use simple enrollment manager
                $simple_manager = new Simple_Enrollment_Manager();
                $result = $simple_manager->schedule_enrollment(
                    $user_id,
                    $course_id,
                    $start_datetime
                );

                if ($result && $result !== false) {
                    error_log("Learnpressium: Schedule successful for user {$user_id}, course {$course_id}, schedule_id: {$result}");
                    $results[] = $result;
                } else {
                    error_log("Learnpressium: Schedule failed for user {$user_id}, course {$course_id} - result: " . var_export($result, true));
                    $errors[] = sprintf(
                        'Failed to schedule user %d for course %d - user may already be enrolled',
                        $user_id,
                        $course_id
                    );
                }
            }
        }

        // PROFESSIONAL RESPONSE HANDLING
        $total_attempts = count($course_ids) * count($user_ids);
        $success_count = count($results);
        $error_count = count($errors);

        if ($success_count > 0 && $error_count === 0) {
            // Complete success
            wp_send_json_success(array(
                'message' => sprintf(
                    __('%d enrollment schedule(s) created successfully', 'learnpressium'),
                    $success_count
                ),
                'schedule_ids' => $results
            ));
        } elseif ($success_count > 0 && $error_count > 0) {
            // Partial success
            wp_send_json_success(array(
                'message' => sprintf(
                    __('%d of %d schedules created successfully. %d had issues (likely already enrolled).', 'learnpressium'),
                    $success_count,
                    $total_attempts,
                    $error_count
                ),
                'schedule_ids' => $results,
                'warnings' => $errors
            ));
        } else {
            // Complete failure
            wp_send_json_error(array(
                'message' => __('No schedules could be created. Please check the details below.', 'learnpressium'),
                'errors' => $errors
            ));
        }
    }

    /**
     * Register REST API routes for tools integration
     */
    public function register_rest_routes() {
        register_rest_route('learnpressium/v1', '/tools/courses', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_courses_for_tools'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));

        register_rest_route('learnpressium/v1', '/tools/users', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_users_for_tools'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));

        register_rest_route('learnpressium/v1', '/tools/schedules', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_schedules_for_tools'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));

        // Add AJAX endpoints for non-REST requests
        add_action('wp_ajax_get_courses', array($this, 'ajax_get_courses'));
        add_action('wp_ajax_get_users', array($this, 'ajax_get_users'));
    }

    /**
     * Get courses for tools dropdown
     */
    public function get_courses_for_tools($request) {
        $courses = get_posts(array(
            'post_type' => 'lp_course',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        $course_options = array();
        foreach ($courses as $course) {
            $course_options[] = array(
                'value' => $course->ID,
                'text' => $course->post_title . ' (#' . $course->ID . ')'
            );
        }

        return rest_ensure_response($course_options);
    }

    /**
     * Get users for tools dropdown
     */
    public function get_users_for_tools($request) {
        $users = get_users(array(
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));

        $user_options = array();
        foreach ($users as $user) {
            $user_options[] = array(
                'value' => $user->ID,
                'text' => $user->display_name . ' (' . $user->user_email . ') #' . $user->ID
            );
        }

        return rest_ensure_response($user_options);
    }

    /**
     * Get schedules for tools management
     */
    public function get_schedules_for_tools($request) {
        global $wpdb;
        
        $status = $request->get_param('status');
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
        
        return rest_ensure_response($schedules);
    }

    /**
     * Check admin permissions for REST API
     */
    public function check_admin_permissions() {
        return current_user_can('manage_options');
    }

    /**
     * AJAX handler for getting courses
     */
    public function ajax_get_courses() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $courses = get_posts(array(
            'post_type' => 'lp_course',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        $course_options = array();
        foreach ($courses as $course) {
            $course_options[] = array(
                'value' => $course->ID,
                'text' => $course->post_title . ' (#' . $course->ID . ')'
            );
        }

        wp_send_json_success($course_options);
    }

    /**
     * AJAX handler for getting users
     */
    public function ajax_get_users() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $users = get_users(array(
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));

        $user_options = array();
        foreach ($users as $user) {
            $user_options[] = array(
                'value' => $user->ID,
                'text' => $user->display_name . ' (' . $user->user_email . ') #' . $user->ID
            );
        }

        wp_send_json_success($user_options);
    }

    /**
     * Add management box to LearnPress Tools page
     */
    public function add_management_box() {
        $screen = get_current_screen();

        // Only add to LearnPress Tools page on the assign_course tab
        if (!$screen || $screen->id !== 'learnpress_page_learn-press-tools') {
            return;
        }

        // Check if we're on the assign_course tab
        $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'course';
        if ($current_tab !== 'assign_course') {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Wait for the page to be fully loaded
            setTimeout(function() {
                // Check if the assign course cards exist
                if ($('#learn-press-assign-course').length && $('#learn-press-unassigned-course').length) {
                    // Create the management card following the exact same pattern as LearnPress
                    var managementCard = `
                        <div id="learn-press-manage-scheduled-courses" class="card">
                            <h2><?php _e('Manage Scheduled Courses', 'learnpressium'); ?></h2>
                            <div class="description">
                                <div><?php _e('View, edit, delete, and activate scheduled course enrollments.', 'learnpressium'); ?></div>
                                <i style="color: #0073aa">
                                    <?php _e('Note: You can immediately activate pending schedules or modify their dates as needed.', 'learnpressium'); ?>
                                </i>
                            </div>
                            <div class="content">
                                <div style="margin-bottom: 15px;">
                                    <label for="schedule-status-filter" style="margin-right: 10px; font-weight: 600;"><?php _e('Filter by Status:', 'learnpressium'); ?></label>
                                    <select id="schedule-status-filter" style="margin-right: 10px;">
                                        <option value=""><?php _e('All Statuses', 'learnpressium'); ?></option>
                                        <option value="pending"><?php _e('Pending', 'learnpressium'); ?></option>
                                        <option value="activated"><?php _e('Activated', 'learnpressium'); ?></option>
                                    </select>
                                    <button type="button" class="button" id="load-schedules"><?php _e('Load Schedules', 'learnpressium'); ?></button>
                                </div>

                                <div id="schedules-container">
                                    <div id="schedules-loading" style="display: none; text-align: center; padding: 20px;">
                                        <span class="spinner is-active"></span>
                                        <?php _e('Loading schedules...', 'learnpressium'); ?>
                                    </div>

                                    <div id="schedules-table-container" style="display: none;">
                                        <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                                            <thead>
                                                <tr>
                                                    <th style="width: 25%;"><?php _e('User', 'learnpressium'); ?></th>
                                                    <th style="width: 25%;"><?php _e('Course', 'learnpressium'); ?></th>
                                                    <th style="width: 20%;"><?php _e('Start Date', 'learnpressium'); ?></th>
                                                    <th style="width: 10%;"><?php _e('Status', 'learnpressium'); ?></th>
                                                    <th style="width: 20%;"><?php _e('Actions', 'learnpressium'); ?></th>
                                                    <!-- Removed: End Date column - simplified to only Start Date -->
                                                </tr>
                                            </thead>
                                            <tbody id="schedules-tbody">
                                            </tbody>
                                        </table>
                                    </div>

                                    <div id="no-schedules" style="display: none; text-align: center; padding: 20px; color: #666;">
                                        <?php _e('No scheduled courses found.', 'learnpressium'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    // Insert the management card after the unassign course card
                    $('#learn-press-unassigned-course').after(managementCard);

                    // Initialize management functionality
                    initializeScheduleManagement();
                }
            }, 1000);
        });

        function initializeScheduleManagement() {
            var $ = jQuery;

            // Load schedules button click
            $('#load-schedules').on('click', function() {
                loadSchedules();
            });

            // Auto-load schedules on page load
            loadSchedules();

            function loadSchedules() {
                var status = $('#schedule-status-filter').val();

                $('#schedules-loading').show();
                $('#schedules-table-container').hide();
                $('#no-schedules').hide();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'learnpressium_tools_get_schedules',
                        status: status,
                        nonce: '<?php echo wp_create_nonce('learnpressium_management_nonce'); ?>'
                    },
                    success: function(response) {
                        $('#schedules-loading').hide();

                        if (response.success && response.data.length > 0) {
                            renderSchedulesTable(response.data);
                            $('#schedules-table-container').show();
                        } else {
                            $('#no-schedules').show();
                        }
                    },
                    error: function() {
                        $('#schedules-loading').hide();
                        alert('<?php _e('Error loading schedules', 'learnpressium'); ?>');
                    }
                });
            }

            function renderSchedulesTable(schedules) {
                var tbody = $('#schedules-tbody');
                tbody.empty();

                schedules.forEach(function(schedule) {
                    var statusClass = 'status-' + schedule.status;
                    var statusText = schedule.status.charAt(0).toUpperCase() + schedule.status.slice(1);

                    var row = `
                        <tr data-schedule-id="${schedule.schedule_id}">
                            <td>${schedule.user_name || 'Unknown User'} (#${schedule.user_id})</td>
                            <td>${schedule.course_title || 'Unknown Course'} (#${schedule.course_id})</td>
                            <td>${formatDateTime(schedule.scheduled_start_date)}</td>
                            <td><span class="${statusClass}">${statusText}</span></td>
                            <td>
                                <button type="button" class="button button-small edit-schedule" data-id="${schedule.schedule_id}" style="margin-right: 5px;"><?php _e('Edit', 'learnpressium'); ?></button>
                                ${schedule.status === 'pending' ? `<button type="button" class="button button-small activate-schedule" data-id="${schedule.schedule_id}" style="margin-right: 5px;"><?php _e('Activate', 'learnpressium'); ?></button>` : ''}
                                <button type="button" class="button button-small delete-schedule" data-id="${schedule.schedule_id}" style="color: #a00;"><?php _e('Delete', 'learnpressium'); ?></button>
                            </td>
                        </tr>
                        <!-- Removed: End Date column from table row -->
                    `;
                    tbody.append(row);
                });

                // Bind action buttons
                bindActionButtons();
            }

            function bindActionButtons() {
                // Edit schedule
                $('.edit-schedule').off('click').on('click', function() {
                    var scheduleId = $(this).data('id');
                    editSchedule(scheduleId);
                });

                // Activate schedule
                $('.activate-schedule').off('click').on('click', function() {
                    var scheduleId = $(this).data('id');
                    activateSchedule(scheduleId);
                });

                // Delete schedule
                $('.delete-schedule').off('click').on('click', function() {
                    var scheduleId = $(this).data('id');
                    deleteSchedule(scheduleId);
                });
            }

            function formatDateTime(datetime) {
                if (!datetime) return '—';
                var date = new Date(datetime);
                return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            }

            function editSchedule(scheduleId) {
                // Create simple edit modal
                var modal = `
                    <div id="edit-schedule-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100000;">
                        <div style="background: white; margin: 50px auto; padding: 20px; width: 500px; border-radius: 5px; max-height: 80vh; overflow-y: auto;">
                            <h3><?php _e('Edit Schedule', 'learnpressium'); ?></h3>
                            <form id="edit-schedule-form">
                                <table class="form-table">
                                    <tr>
                                        <th><label for="edit-start-date"><?php _e('Start Date & Time', 'learnpressium'); ?></label></th>
                                        <td>
                                            <input type="date" id="edit-start-date" name="start_date" required style="margin-right: 10px;">
                                            <input type="time" id="edit-start-time" name="start_time" value="09:00" required>
                                        </td>
                                    </tr>
                                    <!-- Removed: End Date and Notes fields - simplified to only Start Date & Time -->
                                </table>
                                <p>
                                    <button type="submit" class="button button-primary"><?php _e('Update Schedule', 'learnpressium'); ?></button>
                                    <button type="button" class="button" id="cancel-edit"><?php _e('Cancel', 'learnpressium'); ?></button>
                                </p>
                            </form>
                        </div>
                    </div>
                `;

                $('body').append(modal);

                // Handle form submission
                $('#edit-schedule-form').on('submit', function(e) {
                    e.preventDefault();

                    var formData = {
                        action: 'learnpressium_tools_edit_schedule',
                        schedule_id: scheduleId,
                        start_date: $('#edit-start-date').val(),
                        start_time: $('#edit-start-time').val(),
                        // Removed: end_date, end_time, notes - simplified to only start date/time
                        nonce: '<?php echo wp_create_nonce('learnpressium_management_nonce'); ?>'
                    };

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: formData,
                        success: function(response) {
                            if (response.success) {
                                alert('<?php _e('Schedule updated successfully!', 'learnpressium'); ?>');
                                $('#edit-schedule-modal').remove();
                                loadSchedules(); // Reload the table
                            } else {
                                alert('<?php _e('Error:', 'learnpressium'); ?> ' + (response.data || 'Unknown error'));
                            }
                        },
                        error: function() {
                            alert('<?php _e('Network error occurred', 'learnpressium'); ?>');
                        }
                    });
                });

                // Handle cancel
                $('#cancel-edit').on('click', function() {
                    $('#edit-schedule-modal').remove();
                });

                // Close modal on background click
                $('#edit-schedule-modal').on('click', function(e) {
                    if (e.target === this) {
                        $(this).remove();
                    }
                });
            }

            function activateSchedule(scheduleId) {
                if (!confirm('<?php _e('Are you sure you want to activate this scheduled course now? The user will immediately gain access to the course.', 'learnpressium'); ?>')) {
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'learnpressium_tools_activate_schedule',
                        schedule_id: scheduleId,
                        nonce: '<?php echo wp_create_nonce('learnpressium_management_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php _e('Schedule activated successfully!', 'learnpressium'); ?>');
                            loadSchedules(); // Reload the table
                        } else {
                            alert('<?php _e('Error:', 'learnpressium'); ?> ' + (response.data || 'Unknown error'));
                        }
                    },
                    error: function() {
                        alert('<?php _e('Network error occurred', 'learnpressium'); ?>');
                    }
                });
            }

            function deleteSchedule(scheduleId) {
                if (!confirm('<?php _e('Are you sure you want to delete this scheduled course? This action cannot be undone.', 'learnpressium'); ?>')) {
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'learnpressium_tools_delete_schedule',
                        schedule_id: scheduleId,
                        nonce: '<?php echo wp_create_nonce('learnpressium_management_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php _e('Schedule deleted successfully!', 'learnpressium'); ?>');
                            loadSchedules(); // Reload the table
                        } else {
                            alert('<?php _e('Error:', 'learnpressium'); ?> ' + (response.data || 'Unknown error'));
                        }
                    },
                    error: function() {
                        alert('<?php _e('Network error occurred', 'learnpressium'); ?>');
                    }
                });
            }
        }
        </script>

        <style>
        /* Status styling */
        .status-pending {
            color: #0073aa;
            font-weight: 600;
        }

        .status-activated {
            color: #46b450;
            font-weight: 600;
        }

        /* Button styling */
        .button-small {
            padding: 2px 8px;
            font-size: 11px;
            line-height: 1.4;
            height: auto;
        }

        /* Table styling */
        #learn-press-manage-scheduled-courses .wp-list-table {
            margin-top: 0;
        }

        #learn-press-manage-scheduled-courses .wp-list-table th,
        #learn-press-manage-scheduled-courses .wp-list-table td {
            padding: 8px 10px;
        }

        /* Modal styling */
        #edit-schedule-modal .form-table th {
            width: 150px;
            padding: 10px 0;
        }

        #edit-schedule-modal .form-table td {
            padding: 10px 0;
        }

        #edit-schedule-modal input[type="date"],
        #edit-schedule-modal input[type="time"] {
            padding: 3px 5px;
        }

        /* Card consistency with LearnPress */
        #learn-press-manage-scheduled-courses.card {
            margin-top: 20px;
            width: 100% !important; /* Make the Manage Scheduled Courses card full width */
            max-width: none !important; /* Remove any max-width constraints */
        }
        </style>
        <?php
    }

    /**
     * Handle get schedules AJAX request
     */
    public function handle_get_schedules() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'learnpressium_management_nonce')) {
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
     * Handle delete schedule AJAX request
     */
    public function handle_delete_schedule() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'learnpressium_management_nonce')) {
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

        // Use database manager to delete
        $database = new Enrollment_Database();
        // Get user_id before deleting
        global $wpdb;
        $scheduled_table = $wpdb->prefix . 'learnpressium_enrollment_schedules';
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$scheduled_table} WHERE schedule_id = %d",
            $schedule_id
        ));

        $result = $database->delete_schedule($schedule_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            // No caching - removed for real-time performance

            wp_send_json_success('Schedule deleted successfully');
        }
    }

    /**
     * Handle activate schedule AJAX request
     */
    public function handle_activate_schedule() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'learnpressium_management_nonce')) {
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

        // Activate immediately using direct database approach

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
     * Handle edit schedule AJAX request
     */
    public function handle_edit_schedule() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'learnpressium_management_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $schedule_id = intval($_POST['schedule_id']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $start_time = sanitize_text_field($_POST['start_time']);
        // Removed: end_date, end_time, notes - simplified to only start date/time

        if (!$schedule_id) {
            wp_send_json_error('Invalid schedule ID');
            return;
        }

        // Combine date and time
        $start_datetime = $start_date . ' ' . $start_time . ':00';
        // Removed: end_datetime - no longer needed

        // Get current schedule details to determine proper status handling
        $database = new Enrollment_Database();
        $current_schedule = $database->get_schedule($schedule_id);

        if (!$current_schedule) {
            wp_send_json_error('Schedule not found');
            return;
        }

        global $wpdb;
        $current_time = current_time('mysql');

        // CRITICAL: Determine correct status based on new start date
        $new_status = $current_schedule->status; // Keep current status by default

        // If moving to future date, ensure status is 'pending'
        if ($start_datetime > $current_time) {
            $new_status = 'pending';

            // If schedule was already activated, we need to remove the LearnPress enrollment
            if ($current_schedule->status === 'activated') {
                // Remove existing LearnPress enrollment
                $wpdb->delete(
                    $wpdb->prefix . 'learnpress_user_items',
                    array(
                        'user_id' => $current_schedule->user_id,
                        'item_id' => $current_schedule->course_id,
                        'item_type' => LP_COURSE_CPT,
                        'ref_type' => 'learnpressium_auto',
                        'ref_id' => $schedule_id
                    ),
                    array('%d', '%d', '%s', '%s', '%d')
                );

                error_log("Learnpressium: Reverted activated schedule {$schedule_id} back to pending - removed LearnPress enrollment");
            }
        }
        // If moving to past/current date and status is pending, it will be activated by scheduler

        // Prepare update data with correct status
        $update_data = array(
            'scheduled_start_date' => $start_datetime,
            'status' => $new_status
            // Removed: scheduled_end_date, notes - simplified to only start date/time
        );

        $result = $database->update_schedule($schedule_id, $update_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            // Prepare success message based on what happened
            $message = 'Schedule updated successfully';

            if ($current_schedule->status === 'activated' && $new_status === 'pending') {
                $message = 'Schedule updated successfully - Course moved back to pending status (user access revoked until new start date)';
            } elseif ($new_status === 'pending' && $start_datetime <= $current_time) {
                $message = 'Schedule updated successfully - Course will be activated immediately by the scheduler';
            } elseif ($new_status === 'pending') {
                $message = 'Schedule updated successfully - Course will activate on the new start date';
            }

            // Set a brief flag to prevent immediate scheduler activation after edit
            // This prevents race conditions where scheduler runs before user sees the result
            set_transient('learnpressium_schedule_edit_' . $schedule_id, true, 30); // 30 seconds

            wp_send_json_success($message);
        }
    }
}
