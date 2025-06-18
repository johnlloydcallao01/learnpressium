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
        <div class="modern-trainees-wrapper">
            <!-- Modern Header -->
            <div class="modern-header">
                <div class="header-content">
                    <div class="header-left">
                        <h1 class="page-title"><?php echo esc_html(get_admin_page_title()); ?></h1>
                        <p class="page-subtitle">Manage and monitor trainee enrollment and progress</p>
                    </div>
                    <div class="header-right">
                        <button id="export-trainees" class="modern-btn modern-btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="7,10 12,15 17,10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            Export Data
                        </button>
                    </div>
                </div>
            </div>

            <div class="modern-content">
                <!-- Trainees Data Card -->
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <svg class="card-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="m22 2-5 10-5-4-5 10"/>
                            </svg>
                            Trainees Report - <?php echo esc_html($current_date); ?>
                        </h3>
                        <div class="card-stats">
                            <?php
                            // Get all users with the customer role
                            $customer_users = get_users(array(
                                'role' => 'customer',
                            ));
                            $total_trainees = count($customer_users);
                            ?>
                            <span class="stat-badge">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="8.5" cy="7" r="4"/>
                                    <path d="m22 2-5 10-5-4-5 10"/>
                                </svg>
                                <?php echo $total_trainees; ?> Total Trainees
                            </span>
                        </div>
                    </div>
                    <div class="card-content">
                        <?php
                        if (empty($customer_users)) {
                            ?>
                            <div class="empty-state">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="8.5" cy="7" r="4"/>
                                    <path d="m22 2-5 10-5-4-5 10"/>
                                </svg>
                                <h3>No Trainees Found</h3>
                                <p>There are currently no trainees in the system.</p>
                            </div>
                            <?php
                        } else {
                            ?>
                            <div class="modern-table-container">
                                <table class="modern-table" id="trainees-table">
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
                                <th id="scheduled" class="manage-column column-scheduled header-cell"><?php esc_html_e('Has Scheduled Courses', 'learnpressium'); ?></th>
                                <th id="scheduled-courses" class="manage-column column-scheduled-courses header-cell"><?php esc_html_e('Scheduled Courses', 'learnpressium'); ?></th>
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
                                <tr class="modern-row">
                                    <td class="column-id">
                                        <span class="id-badge"><?php echo esc_html($user->ID); ?></span>
                                    </td>
                                    <td class="column-name">
                                        <div class="trainee-info">
                                            <div class="trainee-details">
                                                <span class="trainee-name"><?php echo $full_name; ?></span>
                                                <div class="trainee-actions">
                                                    <a href="<?php echo esc_url(admin_url('admin.php?page=trainee-profile&username=' . $user->user_login)); ?>"
                                                       class="action-link" aria-label="View <?php echo esc_attr($full_name); ?>">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                            <circle cx="12" cy="12" r="3"/>
                                                        </svg>
                                                        View Profile
                                                    </a>
                                                </div>
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

                                    // Get scheduled courses for this user
                                    $scheduled_courses = Trainees_Module::get_user_scheduled_courses($user->ID);
                                    $has_scheduled = !empty($scheduled_courses);
                                    ?>
                                    <td class="column-enrolled">
                                        <span class="modern-badge <?php echo $is_enrolled ? 'badge-success' : 'badge-warning'; ?>">
                                            <?php if ($is_enrolled): ?>
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="20,6 9,17 4,12"/>
                                                </svg>
                                                Enrolled
                                            <?php else: ?>
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"/>
                                                    <line x1="15" y1="9" x2="9" y2="15"/>
                                                    <line x1="9" y1="9" x2="15" y2="15"/>
                                                </svg>
                                                Not Enrolled
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="column-enrolled-courses">
                                        <?php
                                        if ($is_enrolled) {
                                            echo '<div class="courses-list">';
                                            foreach ($enrolled_courses as $course) {
                                                echo '<span class="course-tag">' . esc_html($course->post_title) . '</span>';
                                            }
                                            echo '</div>';
                                        } else {
                                            echo '<span class="no-data">No courses</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="column-active-enrolled">
                                        <span class="modern-badge <?php echo $has_active_enrolled ? 'badge-success' : 'badge-warning'; ?>">
                                            <?php if ($has_active_enrolled): ?>
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="20,6 9,17 4,12"/>
                                                </svg>
                                                Active
                                            <?php else: ?>
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"/>
                                                    <line x1="15" y1="9" x2="9" y2="15"/>
                                                    <line x1="9" y1="9" x2="15" y2="15"/>
                                                </svg>
                                                Inactive
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="column-active-enrolled-courses">
                                        <?php
                                        if ($has_active_enrolled) {
                                            echo '<div class="courses-list">';
                                            foreach ($active_enrolled_courses as $course) {
                                                echo '<span class="course-tag active">' . esc_html($course->post_title) . '</span>';
                                            }
                                            echo '</div>';
                                        } else {
                                            echo '<span class="no-data">No active courses</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="column-scheduled">
                                        <span class="modern-badge <?php echo $has_scheduled ? 'badge-info' : 'badge-warning'; ?>">
                                            <?php if ($has_scheduled): ?>
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"/>
                                                    <polyline points="12,6 12,12 16,14"/>
                                                </svg>
                                                Scheduled
                                            <?php else: ?>
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"/>
                                                    <line x1="15" y1="9" x2="9" y2="15"/>
                                                    <line x1="9" y1="9" x2="15" y2="15"/>
                                                </svg>
                                                No Schedule
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="column-scheduled-courses">
                                        <?php
                                        if ($has_scheduled) {
                                            echo '<div class="courses-list">';
                                            foreach ($scheduled_courses as $course) {
                                                $start_date = date('M j, Y', strtotime($course->scheduled_start_date));
                                                echo '<span class="course-tag scheduled" title="Scheduled for: ' . esc_attr($start_date) . '">' . esc_html($course->post_title) . '</span>';
                                            }
                                            echo '</div>';
                                        } else {
                                            echo '<span class="no-data">No scheduled courses</span>';
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
                                <th class="manage-column column-scheduled header-cell"><?php esc_html_e('Has Scheduled Courses', 'learnpressium'); ?></th>
                                <th class="manage-column column-scheduled-courses header-cell"><?php esc_html_e('Scheduled Courses', 'learnpressium'); ?></th>
                            </tr>
                        </tfoot>
                                </table>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <style>
        /* Modern Trainees List Styles */
        .modern-trainees-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
        }

        /* Modern Header */
        .modern-header {
            background: linear-gradient(135deg, #201a7c 0%, #ab3b43 100%);
            color: white;
            padding: 2rem 0;
            margin: -20px -20px 2rem -20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            flex: 1;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0.5rem 0 0 0;
            font-weight: 400;
        }

        .header-right {
            flex-shrink: 0;
        }

        /* Modern Button */
        .modern-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .modern-btn-primary {
            background: linear-gradient(135deg, #201a7c 0%, #ab3b43 100%);
            color: white;
            box-shadow: 0 4px 14px 0 rgba(32, 26, 124, 0.39);
        }

        .modern-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(32, 26, 124, 0.4);
        }

        /* Modern Content */
        .modern-content {
            padding: 0 2rem 2rem 2rem;
        }

        /* Modern Card */
        .modern-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            transition: box-shadow 0.2s ease;
        }

        .modern-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .card-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        .card-icon {
            color: #201a7c;
            flex-shrink: 0;
        }

        .card-stats {
            display: flex;
            gap: 1rem;
        }

        .stat-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(32, 26, 124, 0.1);
            color: #201a7c;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .card-content {
            padding: 0;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }

        .empty-state svg {
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
            margin: 0 0 0.5rem 0;
        }

        .empty-state p {
            margin: 0;
            font-size: 1rem;
        }

        /* Modern Table */
        .modern-table-container {
            overflow-x: auto;
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .modern-table th {
            background: #f8fafc;
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e5e7eb;
        }

        .modern-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        .modern-row {
            transition: all 0.2s ease;
        }

        .modern-row:hover {
            background: #f8fafc;
        }

        /* Column Specific Styles */
        .column-id {
            width: 80px;
        }

        .id-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #201a7c 0%, #ab3b43 100%);
            color: white;
            border-radius: 50%;
            font-weight: 600;
            font-size: 0.8rem;
        }

        /* Trainee Info */
        .trainee-info {
            display: flex;
            align-items: center;
        }

        .trainee-details {
            flex: 1;
        }

        .trainee-name {
            font-weight: 600;
            color: #1f2937;
            font-size: 1rem;
            display: block;
            margin-bottom: 0.25rem;
        }

        .trainee-actions {
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .modern-row:hover .trainee-actions {
            opacity: 1;
        }

        .action-link {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            color: #201a7c;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .action-link:hover {
            color: #ab3b43;
        }

        /* Modern Badges */
        .modern-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        /* Course Lists */
        .courses-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .course-tag {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: #f1f5f9;
            color: #475569;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid #e2e8f0;
        }

        .course-tag.active {
            background: #dcfce7;
            color: #166534;
            border-color: #bbf7d0;
        }

        .course-tag.scheduled {
            background: #dbeafe;
            color: #1e40af;
            border-color: #bfdbfe;
        }

        .no-data {
            color: #9ca3af;
            font-style: italic;
            font-size: 0.875rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .modern-trainees-wrapper {
                padding: 0;
            }

            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                padding: 0 1rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .modern-content {
                padding: 0 1rem 2rem 1rem;
            }

            .card-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .card-stats {
                width: 100%;
                justify-content: center;
            }

            .modern-table {
                min-width: 800px;
            }

            .trainee-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .courses-list {
                flex-direction: column;
            }
        }

        /* Hide WordPress default styles */
        .modern-trainees-wrapper .wrap > h1 {
            display: none;
        }

        .modern-trainees-wrapper * {
            box-sizing: border-box;
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
