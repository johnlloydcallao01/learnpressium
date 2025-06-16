<?php

/**
 * The Enrollment Frontend class
 *
 * Handles frontend display of enrollment scheduling information
 *
 * @package    Learnpressium
 * @subpackage Learnpressium/includes/modules/enrollment
 */

class Enrollment_Frontend {

    /**
     * The enrollment manager instance
     */
    private $enrollment_manager;

    /**
     * The access controller instance
     */
    private $access_controller;

    /**
     * Constructor
     */
    public function __construct($enrollment_manager, $access_controller) {
        $this->enrollment_manager = $enrollment_manager;
        $this->access_controller = $access_controller;
    }

    /**
     * Initialize frontend hooks - PERFORMANCE OPTIMIZED
     */
    public function init() {
        // PERFORMANCE FIX: Only add hooks on relevant pages

        // Only add course-specific hooks on course pages
        if (is_singular('lp_course') || is_post_type_archive('lp_course')) {
            add_action('learn-press/before-single-course-summary', array($this, 'display_course_schedule_info'), 15);
            add_action('learn-press/after-courses-loop-item', array($this, 'display_course_card_schedule_info'));
        }

        // Only add profile hooks on profile pages
        if (is_page() && (strpos($_SERVER['REQUEST_URI'], 'profile') !== false ||
                         strpos($_SERVER['REQUEST_URI'], 'user') !== false)) {
            add_action('learn-press/user-profile/tabs-content', array($this, 'display_user_schedule_tab'));
        }

        // Always enqueue styles (lightweight)
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));

        // Always add shortcode (no performance impact)
        add_shortcode('learnpressium_user_schedules', array($this, 'user_schedules_shortcode'));
    }

    /**
     * Display schedule information on single course page
     */
    public function display_course_schedule_info() {
        if (!is_user_logged_in()) {
            return;
        }

        global $post;
        $course_id = $post->ID;
        $user_id = get_current_user_id();

        $schedule_status = $this->access_controller->get_schedule_status($user_id, $course_id);

        if (!$schedule_status['has_schedule']) {
            return;
        }

        $this->render_schedule_notice($schedule_status);
    }

    /**
     * Display schedule information on course cards
     */
    public function display_course_card_schedule_info() {
        if (!is_user_logged_in()) {
            return;
        }

        global $post;
        $course_id = $post->ID;
        $user_id = get_current_user_id();

        $schedule_status = $this->access_controller->get_schedule_status($user_id, $course_id);

        if (!$schedule_status['has_schedule']) {
            return;
        }

        echo '<div class="learnpressium-course-schedule-badge">';
        echo '<span class="schedule-status status-' . esc_attr($schedule_status['status']) . '">';
        
        switch ($schedule_status['status']) {
            case 'scheduled':
                echo '<i class="fa fa-clock-o"></i> ' . __('Scheduled', 'learnpressium');
                break;
            case 'active':
                echo '<i class="fa fa-check-circle"></i> ' . __('Active', 'learnpressium');
                break;
            case 'expired':
                echo '<i class="fa fa-times-circle"></i> ' . __('Expired', 'learnpressium');
                break;
        }
        
        echo '</span>';
        echo '</div>';
    }

    /**
     * Add user schedules tab to profile
     */
    public function display_user_schedule_tab($tabs) {
        if (!is_user_logged_in()) {
            return $tabs;
        }

        $tabs['schedules'] = array(
            'title' => __('My Schedules', 'learnpressium'),
            'callback' => array($this, 'user_schedules_tab_content')
        );

        return $tabs;
    }

    /**
     * Display user schedules tab content
     */
    public function user_schedules_tab_content() {
        $user_id = get_current_user_id();
        $schedules = $this->enrollment_manager->database->get_user_schedules($user_id);

        if (empty($schedules)) {
            echo '<p>' . __('You have no scheduled courses.', 'learnpressium') . '</p>';
            return;
        }

        echo '<div class="learnpressium-user-schedules">';
        echo '<h3>' . __('Your Scheduled Courses', 'learnpressium') . '</h3>';
        
        foreach ($schedules as $schedule) {
            $course = get_post($schedule->course_id);
            if (!$course) continue;

            echo '<div class="schedule-item status-' . esc_attr($schedule->status) . '">';
            echo '<div class="schedule-course-info">';
            echo '<h4><a href="' . get_permalink($course->ID) . '">' . esc_html($course->post_title) . '</a></h4>';
            echo '<div class="schedule-details">';
            
            echo '<p><strong>' . __('Start Date:', 'learnpressium') . '</strong> ';
            echo wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($schedule->scheduled_start_date));
            echo '</p>';
            
            if ($schedule->scheduled_end_date) {
                echo '<p><strong>' . __('End Date:', 'learnpressium') . '</strong> ';
                echo wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($schedule->scheduled_end_date));
                echo '</p>';
            }
            
            echo '<p><strong>' . __('Status:', 'learnpressium') . '</strong> ';
            echo '<span class="status-badge status-' . esc_attr($schedule->status) . '">';
            echo ucfirst($schedule->status);
            echo '</span></p>';
            
            if ($schedule->notes) {
                echo '<p><strong>' . __('Notes:', 'learnpressium') . '</strong> ' . esc_html($schedule->notes) . '</p>';
            }
            
            // Show countdown for scheduled courses
            if ($schedule->status === 'scheduled') {
                $current_time = current_time('timestamp');
                $start_time = strtotime($schedule->scheduled_start_date);
                
                if ($start_time > $current_time) {
                    echo '<div class="schedule-countdown" data-target="' . esc_attr($schedule->scheduled_start_date) . '">';
                    echo '<p><strong>' . __('Available in:', 'learnpressium') . '</strong> ';
                    echo '<span class="countdown-timer">' . $this->get_time_until($schedule->scheduled_start_date) . '</span>';
                    echo '</p>';
                    echo '</div>';
                }
            }
            
            echo '</div>'; // schedule-details
            echo '</div>'; // schedule-course-info
            echo '</div>'; // schedule-item
        }
        
        echo '</div>'; // learnpressium-user-schedules
    }

    /**
     * Render schedule notice
     */
    private function render_schedule_notice($schedule_status) {
        $notice_class = 'learnpressium-schedule-notice';
        
        switch ($schedule_status['status']) {
            case 'scheduled':
                $notice_class .= ' notice-info';
                break;
            case 'active':
                $notice_class .= ' notice-success';
                break;
            case 'expired':
                $notice_class .= ' notice-warning';
                break;
        }

        echo '<div class="' . esc_attr($notice_class) . '">';
        echo '<div class="notice-content">';
        echo '<h4>' . __('Course Schedule Information', 'learnpressium') . '</h4>';
        echo '<p>' . esc_html($schedule_status['message']) . '</p>';
        
        if ($schedule_status['status'] === 'scheduled') {
            echo '<div class="schedule-countdown" data-target="' . esc_attr($schedule_status['scheduled_start']) . '">';
            echo '<p><strong>' . __('Available in:', 'learnpressium') . '</strong> ';
            echo '<span class="countdown-timer">' . $this->get_time_until($schedule_status['scheduled_start']) . '</span>';
            echo '</p>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Get time until scheduled start
     */
    private function get_time_until($scheduled_date) {
        $current_time = current_time('timestamp');
        $scheduled_time = strtotime($scheduled_date);
        $diff = $scheduled_time - $current_time;
        
        if ($diff <= 0) {
            return __('Available now', 'learnpressium');
        }
        
        $days = floor($diff / (60 * 60 * 24));
        $hours = floor(($diff % (60 * 60 * 24)) / (60 * 60));
        $minutes = floor(($diff % (60 * 60)) / 60);
        
        $parts = array();
        
        if ($days > 0) {
            $parts[] = sprintf(_n('%d day', '%d days', $days, 'learnpressium'), $days);
        }
        
        if ($hours > 0) {
            $parts[] = sprintf(_n('%d hour', '%d hours', $hours, 'learnpressium'), $hours);
        }
        
        if ($minutes > 0 && $days == 0) {
            $parts[] = sprintf(_n('%d minute', '%d minutes', $minutes, 'learnpressium'), $minutes);
        }
        
        if (empty($parts)) {
            return __('Less than a minute', 'learnpressium');
        }
        
        return implode(', ', $parts);
    }

    /**
     * Enqueue frontend styles
     */
    public function enqueue_frontend_styles() {
        wp_enqueue_style(
            'learnpressium-enrollment-frontend',
            LEARNPRESSIUM_PLUGIN_URL . 'public/css/enrollment-frontend.css',
            array(),
            LEARNPRESSIUM_VERSION
        );

        wp_enqueue_script(
            'learnpressium-enrollment-frontend',
            LEARNPRESSIUM_PLUGIN_URL . 'public/js/enrollment-frontend.js',
            array('jquery'),
            LEARNPRESSIUM_VERSION,
            true
        );
    }

    /**
     * User schedules shortcode
     */
    public function user_schedules_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your schedules.', 'learnpressium') . '</p>';
        }

        $atts = shortcode_atts(array(
            'status' => '',
            'limit' => -1
        ), $atts);

        ob_start();
        $this->user_schedules_tab_content();
        return ob_get_clean();
    }
}
