<?php
/**
 * Simple Enrollment Profile Integration - Uses LearnPress's Own Tables
 */

if (!defined('ABSPATH')) {
    exit;
}

class Enrollment_Profile_Integration {

    private $simple_manager;

    public function __construct() {
        $this->simple_manager = new Simple_Enrollment_Manager();
    }

    public function init() {
        // Add scheduled courses to profile statistics
        add_filter('learn-press/profile/student-statistics/info', array($this, 'add_scheduled_courses_to_statistics'), 10, 1);

        // Add scheduled course tab
        add_filter('lp/profile/user_courses_attend/subtask', array($this, 'add_scheduled_course_tab'));

        // Handle scheduled course tab content
        add_filter('rest_pre_dispatch', array($this, 'handle_scheduled_course_tab'), 10, 3);
    }

    /**
     * Add scheduled courses to profile statistics - PERFORMANCE OPTIMIZED
     */
    public function add_scheduled_courses_to_statistics($data) {
        // Get user ID from current request
        $user_id = 0;
        if (isset($_REQUEST['userID'])) {
            $user_id = intval($_REQUEST['userID']);
        } elseif (isset($_GET['userID'])) {
            $user_id = intval($_GET['userID']);
        }

        if (!$user_id) {
            return $data;
        }

        // REAL-TIME: No caching for immediate updates
        $count = $this->simple_manager->count_user_scheduled_courses($user_id);

        // Add to statistics
        $data['scheduled_courses'] = array(
            'title' => 'Courses scheduled for future access',
            'label' => 'Scheduled Course',
            'count' => $count
        );

        return $data;
    }

    /**
     * Add scheduled course tab
     */
    public function add_scheduled_course_tab($tabs) {
        $tabs['scheduled'] = 'Scheduled Course';
        return $tabs;
    }

    /**
     * Handle scheduled course tab content
     */
    public function handle_scheduled_course_tab($result, $server, $request) {
        // Check if this is our scheduled course tab request
        if (strpos($request->get_route(), '/lp/v1/profile/course-tab') === false) {
            return $result;
        }

        $params = $request->get_params();
        if (($params['status'] ?? '') !== 'scheduled') {
            return $result;
        }

        $user_id = intval($params['userID'] ?? 0);
        if (!$user_id) {
            return $result;
        }

        // Get scheduled courses from LearnPress's own table
        $scheduled_courses = $this->simple_manager->get_user_scheduled_courses($user_id);

        // Generate content
        $content = $this->generate_scheduled_courses_content($scheduled_courses);

        return new WP_REST_Response(array(
            'status' => 'success',
            'data' => $content,
            'message' => ''
        ), 200);
    }

    /**
     * Generate content for scheduled courses tab
     * CORRECTED: Follows LearnPress patterns for course name retrieval
     */
    private function generate_scheduled_courses_content($scheduled_courses) {
        if (empty($scheduled_courses)) {
            return '<p style="text-align: center; padding: 40px;">No scheduled courses found.</p>';
        }

        $content = '<table class="lp_profile_course_progress lp-list-table">';
        $content .= '<thead><tr>';
        $content .= '<th style="width: 80px;"></th>';
        $content .= '<th>Course Name</th>';
        $content .= '<th>Official Start</th>';
        $content .= '<th>Status</th>';
        $content .= '</tr></thead><tbody>';

        foreach ($scheduled_courses as $schedule) {
            // LEARNPRESS PATTERN: Always verify course exists and is correct type
            $course = get_post($schedule->course_id);  // CORRECTED: use course_id from staging table
            if (!$course || $course->post_type !== 'lp_course') {
                continue;
            }

            // LEARNPRESS PATTERN: Use get_the_title() for course names (never store names in custom tables)
            $course_title = get_the_title($schedule->course_id);
            $course_url = get_permalink($schedule->course_id);
            $course_thumbnail = get_the_post_thumbnail($schedule->course_id, array(60, 60));

            // Format dates according to WordPress/LearnPress patterns
            $start_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($schedule->scheduled_start_date));

            // Determine status based on staging table status
            $current_time = current_time('mysql');
            if ($schedule->status === 'pending' && $schedule->scheduled_start_date <= $current_time) {
                $status_label = '<span style="color: #f39c12;">Ready to Start</span>';
            } elseif ($schedule->status === 'pending') {
                $status_label = '<span style="color: #3498db;">Scheduled</span>';
            } else {
                $status_label = '<span style="color: #27ae60;">Activated</span>';
            }

            $content .= '<tr>';
            $content .= '<td>' . ($course_thumbnail ?: '<div style="width:60px;height:60px;background:#f0f0f0;border-radius:4px;"></div>') . '</td>';
            $content .= '<td><a href="' . ($course_url) . '" style="font-weight:600;color:#0073aa;text-decoration:none;">' . ($course_title) . '</a></td>';
            $content .= '<td>' . ($start_date) . '</td>';
            $content .= '<td>' . $status_label . '</td>';
            $content .= '</tr>';
        }

        $content .= '</tbody></table>';
        return $content;
    }
}
