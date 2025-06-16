<?php

/**
 * The Enrollment Database class
 *
 * Handles database operations for enrollment scheduling
 *
 * @package    Learnpressium
 * @subpackage Learnpressium/includes/modules/enrollment
 */

class Enrollment_Database {

    /**
     * The table name for enrollment schedules
     */
    private $table_name;

    /**
     * WordPress database instance
     */
    private $wpdb;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'learnpressium_enrollment_schedules';
    }

    /**
     * Create the enrollment schedules table
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            schedule_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            user_item_id bigint(20) unsigned NULL,
            scheduled_start_date datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (schedule_id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY user_item_id (user_item_id),
            KEY scheduled_start_date (scheduled_start_date),
            KEY status (status),
            UNIQUE KEY unique_user_course_schedule (user_id, course_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Run migration to fix existing table structure
        $this->migrate_table_structure();
    }

    /**
     * Migrate existing table structure to support NULL user_item_id
     */
    private function migrate_table_structure() {
        // Check if table exists and needs migration
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;

        if (!$table_exists) {
            return; // Table doesn't exist yet, no migration needed
        }

        // Check current column definition
        $column_info = $this->wpdb->get_row(
            "SHOW COLUMNS FROM {$this->table_name} WHERE Field = 'user_item_id'"
        );

        if ($column_info && strpos($column_info->Null, 'NO') !== false) {
            // Column is NOT NULL, need to migrate
            error_log('Learnpressium: Migrating database table structure...');

            // First, update any existing NULL values to 0 temporarily
            $this->wpdb->query("UPDATE {$this->table_name} SET user_item_id = 0 WHERE user_item_id IS NULL");

            // Alter the column to allow NULL
            $alter_result = $this->wpdb->query(
                "ALTER TABLE {$this->table_name} MODIFY COLUMN user_item_id bigint(20) unsigned NULL"
            );

            if ($alter_result === false) {
                error_log('Learnpressium: Failed to alter user_item_id column: ' . $this->wpdb->last_error);
                return;
            }

            // Update the unique constraint
            $this->wpdb->query("ALTER TABLE {$this->table_name} DROP INDEX IF EXISTS unique_user_course_schedule");
            $this->wpdb->query("ALTER TABLE {$this->table_name} ADD UNIQUE KEY unique_user_course_schedule (user_id, course_id)");

            // Set the temporary 0 values back to NULL
            $this->wpdb->query("UPDATE {$this->table_name} SET user_item_id = NULL WHERE user_item_id = 0");

            error_log('Learnpressium: Database migration completed successfully');
        }
    }

    /**
     * Insert a new enrollment schedule
     */
    public function insert_schedule($data) {
        $defaults = array(
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $data = wp_parse_args($data, $defaults);

        $result = $this->wpdb->insert(
            $this->table_name,
            $data,
            array(
                '%d', // user_id
                '%d', // course_id
                '%d', // user_item_id
                '%s', // scheduled_start_date
                '%s', // status
                '%d', // created_by
                '%s', // created_at
                '%s'  // updated_at
            )
        );

        if ($result === false) {
            $error_msg = 'Failed to insert enrollment schedule';
            if ($this->wpdb->last_error) {
                $error_msg .= ': ' . $this->wpdb->last_error;
            }
            error_log('Learnpressium DB Error: ' . $error_msg);
            error_log('Data being inserted: ' . print_r($data, true));
            return new WP_Error('db_insert_error', $error_msg);
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Update an enrollment schedule
     */
    public function update_schedule($schedule_id, $data) {
        $data['updated_at'] = current_time('mysql');

        $result = $this->wpdb->update(
            $this->table_name,
            $data,
            array('schedule_id' => $schedule_id),
            array('%s', '%s', '%s', '%s', '%s'), // format for data
            array('%d') // format for where
        );

        if ($result === false) {
            return new WP_Error('db_update_error', 'Failed to update enrollment schedule', $this->wpdb->last_error);
        }

        return $result;
    }

    /**
     * Delete an enrollment schedule
     */
    public function delete_schedule($schedule_id) {
        $result = $this->wpdb->delete(
            $this->table_name,
            array('schedule_id' => $schedule_id),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_delete_error', 'Failed to delete enrollment schedule', $this->wpdb->last_error);
        }

        return $result;
    }

    /**
     * Get enrollment schedule by ID
     */
    public function get_schedule($schedule_id) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE schedule_id = %d",
            $schedule_id
        );

        return $this->wpdb->get_row($sql);
    }

    /**
     * Get enrollment schedules by user ID
     */
    public function get_user_schedules($user_id, $status = null) {
        $sql = "SELECT * FROM {$this->table_name} WHERE user_id = %d";
        $params = array($user_id);

        if ($status) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }

        $sql .= " ORDER BY scheduled_start_date ASC";

        return $this->wpdb->get_results($this->wpdb->prepare($sql, $params));
    }

    /**
     * Get enrollment schedules by course ID
     */
    public function get_course_schedules($course_id, $status = null) {
        $sql = "SELECT * FROM {$this->table_name} WHERE course_id = %d";
        $params = array($course_id);

        if ($status) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }

        $sql .= " ORDER BY scheduled_start_date ASC";

        return $this->wpdb->get_results($this->wpdb->prepare($sql, $params));
    }

    /**
     * Get specific user-course schedule
     */
    public function get_user_course_schedule($user_id, $course_id) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE user_id = %d AND course_id = %d ORDER BY schedule_id DESC LIMIT 1",
            $user_id,
            $course_id
        );

        return $this->wpdb->get_row($sql);
    }

    /**
     * Get schedules ready to be activated
     */
    public function get_schedules_to_activate() {
        $current_time = current_time('mysql');
        
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE status = 'pending'
             AND scheduled_start_date <= %s 
             ORDER BY scheduled_start_date ASC",
            $current_time
        );

        return $this->wpdb->get_results($sql);
    }

    // Removed: get_schedules_to_expire() - No longer needed since we removed end dates

    /**
     * Get table name
     */
    public function get_table_name() {
        return $this->table_name;
    }

    /**
     * Get all schedules with optional filters
     */
    public function get_all_schedules($filters = array()) {
        $where_conditions = array('1=1');
        $values = array();

        if (!empty($filters['status'])) {
            $where_conditions[] = 'status = %s';
            $values[] = $filters['status'];
        }

        if (!empty($filters['user_id'])) {
            $where_conditions[] = 'user_id = %d';
            $values[] = $filters['user_id'];
        }

        if (!empty($filters['course_id'])) {
            $where_conditions[] = 'course_id = %d';
            $values[] = $filters['course_id'];
        }

        $where_clause = implode(' AND ', $where_conditions);

        if (!empty($values)) {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY created_at DESC",
                ...$values
            );
        } else {
            $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY created_at DESC";
        }

        return $this->wpdb->get_results($query);
    }

    /**
     * Get all schedules for a specific user and course
     */
    public function get_user_course_schedules($user_id, $course_id) {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE user_id = %d AND course_id = %d ORDER BY created_at DESC",
            $user_id,
            $course_id
        );

        return $this->wpdb->get_results($query);
    }

    /**
     * Force migration of table structure (for manual triggering)
     */
    public function force_migration() {
        // First ensure table is created
        $this->create_tables();

        // Then run migration
        return $this->migrate_table_structure();
    }

    /**
     * Check if table exists and has proper structure
     */
    public function verify_table_exists() {
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;

        if (!$table_exists) {
            error_log("Learnpressium: Table {$this->table_name} does not exist. Creating...");
            $this->create_tables();

            // Verify creation
            $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
            if ($table_exists) {
                error_log("Learnpressium: Table {$this->table_name} created successfully");
            } else {
                error_log("Learnpressium: FAILED to create table {$this->table_name}");
            }
        }

        return $table_exists;
    }

    /**
     * Get debug information about the table
     */
    public function get_debug_info() {
        $info = array();

        // Check if table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
        $info['table_exists'] = $table_exists;

        if ($table_exists) {
            // Get record count
            $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
            $info['record_count'] = intval($count);

            // Get recent records
            $recent = $this->wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT 3");
            $info['recent_records'] = $recent;

            // Get table structure
            $columns = $this->wpdb->get_results("DESCRIBE {$this->table_name}");
            $info['table_structure'] = $columns;
        }

        return $info;
    }
}
