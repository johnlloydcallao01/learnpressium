# Learnpressium Enrollment Module

## Overview

The Enrollment module adds powerful course enrollment scheduling functionality to LearnPress. It allows administrators to assign users to courses with specific start dates, controlling when users can access course content.

## Features

### Core Functionality
- **Scheduled Enrollment**: Assign users to courses with future start dates
- **Access Control**: Prevent course access until scheduled start time
- **Automatic Processing**: Real-time activation when scheduled time arrives
- **Bulk Operations**: Schedule multiple users and courses simultaneously
- **Simplified Interface**: Focus on essential start date/time only

### Admin Interface
- **Dedicated Admin Page**: Manage all enrollment schedules from one location
- **LearnPress Tools Integration**: Enhanced assign/unassign functionality
- **Course Meta Box**: Manage schedules directly from course edit pages
- **User Profile Integration**: View and manage user schedules from user profiles
- **Bulk Actions**: Activate, expire, or delete multiple schedules at once

### Frontend Features
- **Schedule Notifications**: Users see when courses will be available
- **Countdown Timers**: Real-time countdown to course availability
- **User Dashboard**: Users can view their scheduled courses
- **Course Cards**: Schedule status badges on course listings
- **Shortcode Support**: Display user schedules anywhere with `[learnpressium_user_schedules]`

## Database Schema

The module creates a custom table `wp_learnpressium_enrollment_schedules` with the following structure:

```sql
CREATE TABLE wp_learnpressium_enrollment_schedules (
    schedule_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_id bigint(20) unsigned NOT NULL,
    course_id bigint(20) unsigned NOT NULL,
    scheduled_start_date datetime NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'pending',
    created_by bigint(20) unsigned NOT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (schedule_id),
    KEY user_id (user_id),
    KEY course_id (course_id),
    KEY scheduled_start_date (scheduled_start_date),
    KEY status (status),
    UNIQUE KEY unique_user_course_schedule (user_id, course_id)
);
```

## Schedule Statuses

- **pending**: Course access is scheduled for the future
- **activated**: Course access is currently available and user is enrolled

## Usage

### Admin Usage

1. **Via LearnPress Tools**:
   - Go to LearnPress → Tools → Assign Course
   - Select courses and users
   - Enable "Schedule for Future Date"
   - Set start date/time
   - Click "Assign Course"

2. **Via Enrollment Schedules Page**:
   - Go to LearnPress → Enrollment Schedules
   - Click "Add New Schedule"
   - Fill in the schedule details
   - Save the schedule

3. **Via Course Edit Page**:
   - Edit any LearnPress course
   - Scroll to "Enrollment Schedules" meta box
   - Add schedules for specific users

4. **Via User Profile**:
   - Edit any user profile
   - Scroll to "Enrollment Schedules" section
   - Add schedules for specific courses

### Frontend Usage

Users will see:
- Schedule notifications on course pages
- Countdown timers showing when courses become available
- Schedule status badges on course cards
- Their scheduled courses in their profile

## Hooks and Filters

### Actions
- `learnpressium_enrollment_scheduled` - Fired when a new schedule is created
- `learnpressium_enrollment_activated` - Fired when a schedule becomes active
- `learnpressium_enrollment_expired` - Fired when a schedule expires

### Filters
- `learnpress/course/can-view-content` - Modified to check schedule status
- `learn-press/user/can-enroll/course` - Modified to prevent enrollment of scheduled courses

## AJAX Endpoints

- `learnpressium_schedule_enrollment` - Create new schedule
- `learnpressium_get_schedules` - Retrieve schedules
- `learnpressium_get_schedule` - Get single schedule
- `learnpressium_update_schedule` - Update existing schedule
- `learnpressium_delete_schedule` - Delete schedule
- `learnpressium_bulk_schedule_action` - Bulk operations
- `learnpressium_tools_schedule_assignment` - Tools integration

## REST API Endpoints

- `GET /wp-json/learnpressium/v1/tools/courses` - Get courses for tools
- `GET /wp-json/learnpressium/v1/tools/users` - Get users for tools
- `GET /wp-json/learnpressium/v1/tools/schedules` - Get schedules for tools

## Cron Jobs

The module uses WordPress cron and real-time activation:
- **Hook**: `learnpressium_process_scheduled_enrollments`
- **Frequency**: Every minute
- **Function**: Activates scheduled enrollments when their start time arrives
- **Real-time**: Also activates on every admin page load for immediate response

## File Structure

```
enrollment/
├── class-enrollment-module.php          # Main module class
├── class-enrollment-manager.php         # Core scheduling logic
├── class-enrollment-access-controller.php # Access control logic
├── class-enrollment-database.php        # Database operations
├── class-enrollment-admin.php           # Admin interface
├── class-enrollment-tools-integration.php # LearnPress Tools integration
├── class-enrollment-frontend.php        # Frontend display
└── README.md                            # This file
```

## Requirements

- WordPress 5.0+
- LearnPress 4.0+
- PHP 7.4+
- MySQL 5.6+

## Installation

The module is automatically loaded when the Learnpressium plugin is activated. No additional installation steps are required.

## Troubleshooting

### Schedules Not Activating
- Check that WordPress cron is working properly
- Verify the `learnpressium_process_scheduled_enrollments` cron job is scheduled
- Check server timezone settings

### Access Control Not Working
- Ensure LearnPress is up to date
- Check for plugin conflicts
- Verify database table was created properly

### Frontend Not Displaying
- Check that frontend styles and scripts are loading
- Verify theme compatibility
- Check for JavaScript errors in browser console

## Support

For support and bug reports, please contact the Learnpressium development team.
