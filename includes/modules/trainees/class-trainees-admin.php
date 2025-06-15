<?php

/**
 * The Trainees Admin Interface
 *
 * @package    Learnpressium
 * @subpackage Learnpressium/includes/modules/trainees
 */

/**
 * The Trainees Admin class.
 *
 * Handles the admin menu and trainees list display functionality.
 */
class Trainees_Admin {

    /**
     * Add menu items to the admin sidebar
     */
    public function add_admin_menu() {
        add_menu_page(
            'Trainees', // Page title
            'Trainees', // Menu title
            'manage_options', // Capability required
            'trainees', // Menu slug
            array($this, 'display_trainees_page'), // Function to display the page
            'dashicons-groups', // Icon
            30 // Position in menu
        );

        // Add hidden submenu page for trainee profiles
        add_submenu_page(
            null, // No parent - hidden from menu
            'Trainee Profile', // Page title
            'Trainee Profile', // Menu title
            'manage_options', // Capability required
            'trainee-profile', // Menu slug
            array($this, 'display_trainee_profile_page') // Function to display the page
        );
    }

    /**
     * Display the trainees (customers) page content
     */
    public function display_trainees_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get current date for the title
        $current_date = date('F j, Y');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="trainees-actions">
                <button id="export-trainees" class="button button-primary">
                    <span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>
                    Download
                </button>
            </div>
            <div class="trainees-list">
                <?php
                // Get all users with the customer role
                $customer_users = get_users(array(
                    'role' => 'customer',
                ));

                if (empty($customer_users)) {
                    echo '<p>No customers found.</p>';
                } else {
                    // Add a title with the current date
                    ?>
                    <h2 class="table-title">Trainees Report (<?php echo esc_html($current_date); ?>)</h2>
                    <div class="table-container">
                    <table class="widefat fixed trainees-table" cellspacing="0" id="trainees-table">
                        <thead>
                            <tr>
                                <th id="id" class="manage-column column-id header-cell"><?php esc_html_e('ID', 'learnpressium'); ?></th>
                                <th id="name" class="manage-column column-name header-cell"><?php esc_html_e('Name', 'learnpressium'); ?></th>
                                <th id="username" class="manage-column column-username header-cell"><?php esc_html_e('Username', 'learnpressium'); ?></th>
                                <th id="email" class="manage-column column-email header-cell"><?php esc_html_e('Email', 'learnpressium'); ?></th>
                                <th id="enrolled" class="manage-column column-enrolled header-cell"><?php esc_html_e('Enrolled', 'learnpressium'); ?></th>
                                <th id="enrolled-courses" class="manage-column column-enrolled-courses header-cell"><?php esc_html_e('Enrolled Courses', 'learnpressium'); ?></th>
                                <th id="active-enrolled" class="manage-column column-active-enrolled header-cell"><?php esc_html_e('Has Active Enrolled Courses', 'learnpressium'); ?></th>
                                <th id="active-enrolled-courses" class="manage-column column-active-enrolled-courses header-cell"><?php esc_html_e('Active Enrolled Courses', 'learnpressium'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($customer_users as $user) {
                                $first_name = get_user_meta($user->ID, 'first_name', true);
                                $last_name = get_user_meta($user->ID, 'last_name', true);
                                $full_name = '';

                                if (!empty($first_name) && !empty($last_name)) {
                                    // Both first and last name exist
                                    $full_name = esc_html($first_name . ' ' . $last_name);
                                } else {
                                    // Use username as fallback if either first or last name is missing
                                    $full_name = esc_html($user->user_login);
                                }
                                ?>
                                <tr class="trainee-row">
                                    <td class="column-id"><?php echo esc_html($user->ID); ?></td>
                                    <td class="column-name">
                                        <div class="trainee-name-container">
                                            <span class="trainee-name"><?php echo $full_name; ?></span>
                                            <div class="row-actions">
                                                <span class="view">
                                                    <a href="<?php echo esc_url(admin_url('admin.php?page=trainee-profile&username=' . $user->user_login)); ?>" aria-label="View <?php echo esc_attr($full_name); ?>">
                                                        View
                                                    </a>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="column-username"><?php echo esc_html($user->user_login); ?></td>
                                    <td class="column-email"><?php echo esc_html($user->user_email); ?></td>
                                    <?php
                                    // Get enrolled courses for this user
                                    $enrolled_courses = Trainees_Module::get_user_enrolled_courses($user->ID);
                                    $is_enrolled = !empty($enrolled_courses);

                                    // Get active enrolled courses for this user
                                    $active_enrolled_courses = Trainees_Module::get_user_active_enrolled_courses($user->ID);
                                    $has_active_enrolled = !empty($active_enrolled_courses);
                                    ?>
                                    <td class="column-enrolled">
                                        <span class="enrollment-status <?php echo $is_enrolled ? 'enrolled' : 'not-enrolled'; ?>">
                                            <?php echo $is_enrolled ? 'Yes' : 'No'; ?>
                                        </span>
                                    </td>
                                    <td class="column-enrolled-courses">
                                        <?php
                                        if ($is_enrolled) {
                                            echo '<ul class="enrolled-courses-list">';
                                            foreach ($enrolled_courses as $course) {
                                                echo '<li>' . esc_html($course->post_title) . '</li>';
                                            }
                                            echo '</ul>';
                                        } else {
                                            echo '<span class="no-courses">No enrolled courses</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="column-active-enrolled">
                                        <span class="enrollment-status <?php echo $has_active_enrolled ? 'enrolled' : 'not-enrolled'; ?>">
                                            <?php echo $has_active_enrolled ? 'Yes' : 'No'; ?>
                                        </span>
                                    </td>
                                    <td class="column-active-enrolled-courses">
                                        <?php
                                        if ($has_active_enrolled) {
                                            echo '<ul class="enrolled-courses-list">';
                                            foreach ($active_enrolled_courses as $course) {
                                                echo '<li>' . esc_html($course->post_title) . '</li>';
                                            }
                                            echo '</ul>';
                                        } else {
                                            echo '<span class="no-courses">No active enrolled courses</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th class="manage-column column-id header-cell"><?php esc_html_e('ID', 'learnpressium'); ?></th>
                                <th class="manage-column column-name header-cell"><?php esc_html_e('Name', 'learnpressium'); ?></th>
                                <th class="manage-column column-username header-cell"><?php esc_html_e('Username', 'learnpressium'); ?></th>
                                <th class="manage-column column-email header-cell"><?php esc_html_e('Email', 'learnpressium'); ?></th>
                                <th class="manage-column column-enrolled header-cell"><?php esc_html_e('Enrolled', 'learnpressium'); ?></th>
                                <th class="manage-column column-enrolled-courses header-cell"><?php esc_html_e('Enrolled Courses', 'learnpressium'); ?></th>
                                <th class="manage-column column-active-enrolled header-cell"><?php esc_html_e('Has Active Enrolled Courses', 'learnpressium'); ?></th>
                                <th class="manage-column column-active-enrolled-courses header-cell"><?php esc_html_e('Active Enrolled Courses', 'learnpressium'); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>

        <style>
            .table-container {
                width: 100%;
                overflow-x: auto;
                margin-bottom: 15px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                border-radius: 4px;
            }

            .trainees-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
                min-width: 900px; /* Ensures table doesn't shrink too much */
            }

            .trainees-table th,
            .trainees-table td {
                padding: 8px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }

            .trainees-table th {
                background-color: #f9f9f9;
                font-weight: 700;
                font-size: 16px;
            }

            /* Additional styling for header cells */
            .header-cell {
                font-size: 16px;
                font-weight: 500 !important; /* Force boldness */
                text-transform: uppercase; /* Make headers more distinct */
            }

            .table-title {
                margin-top: 20px;
                margin-bottom: 15px;
                font-size: 24px;
                font-weight: 500;
                color: #23282d;
                text-align: left;
            }

            .trainees-table tr:hover {
                background-color: #f5f5f5;
            }

            /* Enrolled Column Styles */
            .enrollment-status {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-weight: bold;
            }

            .enrollment-status.enrolled {
                background-color: #dff0d8;
                color: #3c763d;
            }

            .enrollment-status.not-enrolled {
                background-color: #f2dede;
                color: #a94442;
            }

            /* Enrolled Courses Column Styles */
            .enrolled-courses-list {
                margin: 0;
                padding-left: 20px;
            }

            .enrolled-courses-list li {
                margin-bottom: 4px;
            }

            .no-courses {
                color: #999;
                font-style: italic;
            }

            .column-enrolled-courses,
            .column-active-enrolled-courses {
                min-width: 200px;
            }

            .column-active-enrolled {
                min-width: 100px;
            }

            /* Set ID column to be narrower */
            .column-id {
                width: 50px;
                max-width: 50px;
            }

            /* Download button styles */
            .trainees-actions {
                margin: 15px 0;
            }

            #export-trainees {
                display: inline-flex;
                align-items: center;
            }

            /* Hover effect for trainee rows */
            .trainee-name-container {
                position: relative;
            }

            .row-actions {
                position: absolute;
                left: -9999px;
                color: #666;
                font-size: 13px;
            }

            .trainee-row:hover .row-actions {
                position: static;
                left: 0;
                display: inline-block;
                padding-left: 10px;
            }

            .row-actions .view a {
                color: #0073aa;
                text-decoration: none;
            }

            .row-actions .view a:hover {
                color: #00a0d2;
                text-decoration: underline;
            }
        </style>
        <?php
    }

    /**
     * Display the trainee profile page
     */
    public function display_trainee_profile_page() {
        $profile = new Trainees_Profile();
        $profile->display_profile_page();
    }
}
