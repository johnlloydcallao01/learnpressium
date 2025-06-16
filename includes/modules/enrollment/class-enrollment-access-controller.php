<?php

/**
 * The Enrollment Access Controller class
 *
 * Handles access control for scheduled enrollments
 *
 * @package    Learnpressium
 * @subpackage Learnpressium/includes/modules/enrollment
 */

class Enrollment_Access_Controller {

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
     * Filter course access based on enrollment schedule
     */
    public function filter_course_access($view, $user_id, $course) {
        // If user already has access, don't interfere
        if ($view->flag) {
            return $view;
        }

        // Get course ID
        $course_id = is_object($course) ? $course->get_id() : $course;
        
        // Check if user has a scheduled enrollment
        $schedule = $this->enrollment_manager->get_user_schedule($user_id, $course_id);
        
        if (!$schedule) {
            return $view; // No schedule, return original view
        }

        $current_time = current_time('mysql');
        
        // Handle different schedule statuses
        switch ($schedule->status) {
            case 'scheduled':
                if ($schedule->scheduled_start_date > $current_time) {
                    // Course is scheduled for future access
                    $view->flag = false;
                    $view->key = 'scheduled_enrollment';
                    $view->message = $this->get_scheduled_message($schedule);
                } else {
                    // Schedule should be activated (fallback)
                    $view->flag = true;
                    $view->key = 'scheduled_access_granted';
                    $view->message = '';
                }
                break;
                
            case 'active':
                // Check if not expired
                if (!$schedule->scheduled_end_date || $schedule->scheduled_end_date > $current_time) {
                    $view->flag = true;
                    $view->key = 'scheduled_access_active';
                    $view->message = '';
                } else {
                    // Schedule has expired
                    $view->flag = false;
                    $view->key = 'scheduled_enrollment_expired';
                    $view->message = __('Your access to this course has expired.', 'learnpressium');
                }
                break;
                
            case 'expired':
                $view->flag = false;
                $view->key = 'scheduled_enrollment_expired';
                $view->message = __('Your access to this course has expired.', 'learnpressium');
                break;
        }

        return $view;
    }

    /**
     * Filter enrollment access for scheduled courses
     */
    public function filter_enrollment_access($can_enroll, $course, $user) {
        // If enrollment is already blocked, don't interfere
        if (is_wp_error($can_enroll)) {
            return $can_enroll;
        }

        $user_id = is_object($user) ? $user->get_id() : $user;
        $course_id = is_object($course) ? $course->get_id() : $course;
        
        // Check if user has a scheduled enrollment
        $schedule = $this->enrollment_manager->get_user_schedule($user_id, $course_id);
        
        if ($schedule) {
            $current_time = current_time('mysql');
            
            switch ($schedule->status) {
                case 'scheduled':
                    if ($schedule->scheduled_start_date > $current_time) {
                        return new WP_Error(
                            'scheduled_enrollment',
                            sprintf(
                                __('You are scheduled to access this course on %s', 'learnpressium'),
                                wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($schedule->scheduled_start_date))
                            )
                        );
                    }
                    break;
                    
                case 'expired':
                    return new WP_Error(
                        'enrollment_expired',
                        __('Your enrollment period for this course has expired.', 'learnpressium')
                    );
            }
        }

        return $can_enroll;
    }

    /**
     * Get scheduled access message
     */
    private function get_scheduled_message($schedule) {
        $start_date = wp_date(
            get_option('date_format') . ' ' . get_option('time_format'), 
            strtotime($schedule->scheduled_start_date)
        );
        
        $time_until = $this->get_time_until($schedule->scheduled_start_date);
        
        $message = sprintf(
            __('This course will be available on %s (%s)', 'learnpressium'),
            $start_date,
            $time_until
        );
        
        if ($schedule->scheduled_end_date) {
            $end_date = wp_date(
                get_option('date_format') . ' ' . get_option('time_format'), 
                strtotime($schedule->scheduled_end_date)
            );
            $message .= sprintf(
                __(' and will expire on %s', 'learnpressium'),
                $end_date
            );
        }
        
        return $message;
    }

    /**
     * Get human readable time until scheduled start
     */
    private function get_time_until($scheduled_date) {
        $current_time = current_time('timestamp');
        $scheduled_time = strtotime($scheduled_date);
        $diff = $scheduled_time - $current_time;
        
        if ($diff <= 0) {
            return __('available now', 'learnpressium');
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
        
        if ($minutes > 0 && $days == 0) { // Only show minutes if less than a day
            $parts[] = sprintf(_n('%d minute', '%d minutes', $minutes, 'learnpressium'), $minutes);
        }
        
        if (empty($parts)) {
            return __('less than a minute', 'learnpressium');
        }
        
        return implode(', ', $parts);
    }

    /**
     * Check if user can view course content based on schedule
     */
    public function can_view_scheduled_content($user_id, $course_id) {
        $schedule = $this->enrollment_manager->get_user_schedule($user_id, $course_id);
        
        if (!$schedule) {
            return null; // No schedule, let LearnPress handle normally
        }
        
        $current_time = current_time('mysql');
        
        switch ($schedule->status) {
            case 'scheduled':
                return $schedule->scheduled_start_date <= $current_time;
                
            case 'active':
                if (!$schedule->scheduled_end_date) {
                    return true;
                }
                return $schedule->scheduled_end_date > $current_time;
                
            case 'expired':
                return false;
                
            default:
                return null;
        }
    }

    /**
     * Get schedule status for user and course
     */
    public function get_schedule_status($user_id, $course_id) {
        $schedule = $this->enrollment_manager->get_user_schedule($user_id, $course_id);
        
        if (!$schedule) {
            return array(
                'has_schedule' => false,
                'status' => null,
                'message' => null
            );
        }
        
        $current_time = current_time('mysql');
        $status_info = array(
            'has_schedule' => true,
            'status' => $schedule->status,
            'scheduled_start' => $schedule->scheduled_start_date,
            'scheduled_end' => $schedule->scheduled_end_date,
            'notes' => $schedule->notes
        );
        
        switch ($schedule->status) {
            case 'scheduled':
                if ($schedule->scheduled_start_date > $current_time) {
                    $status_info['message'] = $this->get_scheduled_message($schedule);
                    $status_info['can_access'] = false;
                } else {
                    $status_info['message'] = __('Course access is now available', 'learnpressium');
                    $status_info['can_access'] = true;
                }
                break;
                
            case 'active':
                if (!$schedule->scheduled_end_date || $schedule->scheduled_end_date > $current_time) {
                    $status_info['message'] = __('Course access is active', 'learnpressium');
                    $status_info['can_access'] = true;
                } else {
                    $status_info['message'] = __('Course access has expired', 'learnpressium');
                    $status_info['can_access'] = false;
                }
                break;
                
            case 'expired':
                $status_info['message'] = __('Course access has expired', 'learnpressium');
                $status_info['can_access'] = false;
                break;
                
            default:
                $status_info['message'] = __('Unknown schedule status', 'learnpressium');
                $status_info['can_access'] = false;
        }
        
        return $status_info;
    }

    /**
     * Add scheduled course info to course data
     */
    public function add_schedule_info_to_course($course_data, $course_id, $user_id) {
        $schedule_status = $this->get_schedule_status($user_id, $course_id);
        
        if ($schedule_status['has_schedule']) {
            $course_data['schedule_info'] = $schedule_status;
        }
        
        return $course_data;
    }
}
