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

        // Add hidden submenu page for trainees settings
        add_submenu_page(
            null, // No parent - hidden from menu
            'Trainees Settings', // Page title
            'Trainees Settings', // Menu title
            'manage_options', // Capability required
            'trainees-settings', // Menu slug
            array($this, 'display_trainees_settings_page') // Function to display the page
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
                        <div class="header-actions">
                            <button id="export-trainees" class="modern-btn modern-btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7,10 12,15 17,10"/>
                                    <line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                                Export Data
                            </button>

                            <div class="dropdown-menu">
                                <button class="dropdown-trigger" id="settings-menu-trigger">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="1"/>
                                        <circle cx="12" cy="5" r="1"/>
                                        <circle cx="12" cy="19" r="1"/>
                                    </svg>
                                </button>
                                <div class="dropdown-content" id="settings-dropdown">
                                    <a href="<?php echo admin_url('admin.php?page=trainees-settings'); ?>" class="dropdown-item">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="3"/>
                                            <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"/>
                                        </svg>
                                        Preferences
                                    </a>
                                </div>
                            </div>
                        </div>
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
                                <span id="total-trainees-count"><?php echo $total_trainees; ?></span> Total Trainees
                            </span>
                        </div>
                    </div>

                    <!-- Search and Sort Controls -->
                    <div class="table-controls">
                        <div class="search-container">
                            <div class="search-input-wrapper">
                                <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <path d="m21 21-4.35-4.35"/>
                                </svg>
                                <input type="text" id="trainees-search" placeholder="Search trainees..." class="search-input">
                                <button type="button" id="clear-search" class="clear-search" style="display: none;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="6" x2="6" y2="18"/>
                                        <line x1="6" y1="6" x2="18" y2="18"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="search-results-info" id="search-results-info" style="display: none;">
                                <span id="search-results-count">0</span> results found
                            </div>
                        </div>

                        <div class="sort-container">
                            <label for="sort-select" class="sort-label">Sort by:</label>
                            <select id="sort-select" class="sort-select">
                                <option value="id-asc">ID (Ascending)</option>
                                <option value="id-desc">ID (Descending)</option>
                                <option value="name-asc">Name (A to Z)</option>
                                <option value="name-desc">Name (Z to A)</option>
                                <option value="username-asc">Username (A to Z)</option>
                                <option value="username-desc">Username (Z to A)</option>
                                <option value="email-asc">Email (A to Z)</option>
                                <option value="email-desc">Email (Z to A)</option>
                            </select>
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

                                <!-- No Search Results State -->
                                <div id="no-search-results" class="no-search-results" style="display: none;">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                        <circle cx="11" cy="11" r="8"/>
                                        <path d="m21 21-4.35-4.35"/>
                                    </svg>
                                    <h3>No trainees found</h3>
                                    <p>Try adjusting your search terms or clear the search to see all trainees.</p>
                                </div>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <style>
        /* CSS Variables for Theme Colors */
        :root {
            --learnpressium-primary: <?php echo esc_attr(get_option('learnpressium_primary_color', '#201a7c')); ?>;
            --learnpressium-accent: <?php echo esc_attr(get_option('learnpressium_accent_color', '#ab3b43')); ?>;
            --learnpressium-primary-rgb: <?php echo esc_attr($this->hex_to_rgb(get_option('learnpressium_primary_color', '#201a7c'))); ?>;
            --learnpressium-accent-rgb: <?php echo esc_attr($this->hex_to_rgb(get_option('learnpressium_accent_color', '#ab3b43'))); ?>;
        }

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
            background: linear-gradient(135deg, var(--learnpressium-primary) 0%, var(--learnpressium-accent) 100%);
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

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Dropdown Menu Styles */
        .dropdown-menu {
            position: relative;
            display: inline-block;
        }

        .dropdown-trigger {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.5rem;
            color: white;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .dropdown-trigger:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        .dropdown-content {
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            border: 1px solid #e5e7eb;
            min-width: 180px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
            z-index: 1000;
        }

        .dropdown-content.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #374151;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border-radius: 0.375rem;
            margin: 0.25rem;
        }

        .dropdown-item:hover {
            background: #f3f4f6;
            color: var(--learnpressium-primary);
            text-decoration: none;
        }

        .dropdown-item svg {
            color: #6b7280;
            transition: color 0.2s ease;
        }

        .dropdown-item:hover svg {
            color: var(--learnpressium-primary);
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
            background: linear-gradient(135deg, var(--learnpressium-primary) 0%, var(--learnpressium-accent) 100%);
            color: white;
            box-shadow: 0 4px 14px 0 rgba(var(--learnpressium-primary-rgb), 0.39);
        }

        .modern-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(var(--learnpressium-primary-rgb), 0.4);
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
            color: var(--learnpressium-primary);
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
            background: rgba(var(--learnpressium-primary-rgb), 0.1);
            color: var(--learnpressium-primary);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
        }

        /* Table Controls Section */
        .table-controls {
            background: white;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 2rem;
            flex-wrap: wrap;
        }

        /* Search Functionality Styles */
        .search-container {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex: 1;
            max-width: 400px;
        }

        .search-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 3rem 0.75rem 3.25rem; /* Left padding for icon space */
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            background: white;
            transition: all 0.2s ease;
            outline: none;
        }

        /* Center the placeholder text */
        .search-input::placeholder {
            color: #9ca3af;
            text-align: center;
            text-indent: -1.625rem; /* Center the placeholder considering the left padding */
            transition: opacity 0.2s ease;
        }

        /* Webkit browsers */
        .search-input::-webkit-input-placeholder {
            color: #9ca3af;
            text-align: center;
            text-indent: -1.625rem;
            transition: opacity 0.2s ease;
        }

        /* Firefox */
        .search-input::-moz-placeholder {
            color: #9ca3af;
            text-align: center;
            text-indent: -1.625rem;
            transition: opacity 0.2s ease;
        }

        /* When input has focus or content, placeholder disappears */
        .search-input:focus::placeholder {
            opacity: 0;
        }

        /* Hide placeholder when input has content */
        .search-input.has-content::placeholder {
            opacity: 0;
        }

        .search-input:focus {
            border-color: var(--learnpressium-primary);
            box-shadow: 0 0 0 3px rgba(var(--learnpressium-primary-rgb), 0.1);
        }

        .search-icon {
            position: absolute;
            left: 1rem; /* Positioned with proper spacing from left edge */
            top: 50%;
            transform: translateY(-50%); /* Center vertically */
            color: #9ca3af;
            pointer-events: none;
            z-index: 1;
        }

        .clear-search {
            position: absolute;
            right: 1rem; /* Consistent spacing with search icon */
            top: 50%;
            transform: translateY(-50%); /* Center vertically */
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 0.25rem;
            transition: all 0.2s ease;
        }

        .clear-search:hover {
            color: #374151;
            background: #f3f4f6;
        }

        .search-results-info {
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: 500;
        }

        /* Sort Functionality Styles */
        .sort-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        .sort-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            white-space: nowrap;
        }

        .sort-select {
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            background: white;
            color: #374151;
            cursor: pointer;
            transition: all 0.2s ease;
            outline: none;
            min-width: 180px;
        }

        .sort-select:focus {
            border-color: var(--learnpressium-primary);
            box-shadow: 0 0 0 3px rgba(var(--learnpressium-primary-rgb), 0.1);
        }

        .sort-select:hover {
            border-color: #d1d5db;
        }

        /* Search highlighting */
        .search-highlight {
            background: #fef3c7;
            color: #92400e;
            padding: 0.1rem 0.2rem;
            border-radius: 0.2rem;
            font-weight: 600;
        }

        /* No search results state */
        .no-search-results {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }

        .no-search-results svg {
            margin: 0 auto 1rem;
            color: #d1d5db;
        }

        .no-search-results h3 {
            margin: 0 0 0.5rem;
            font-size: 1.125rem;
            font-weight: 600;
            color: #374151;
        }

        .no-search-results p {
            margin: 0;
            font-size: 0.875rem;
        }

        /* Responsive design for table controls */
        @media (max-width: 768px) {
            .table-controls {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }

            .search-container {
                max-width: none;
            }

            .sort-container {
                justify-content: space-between;
            }

            .sort-select {
                min-width: auto;
                flex: 1;
            }
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
            position: relative;
            overflow: auto;
            cursor: grab;
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            max-height: 600px; /* Taller table - approximately 8-9 rows + header */
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
        }

        .modern-table-container:active {
            cursor: grabbing;
        }

        /* Custom thin scrollbars for both directions */
        .modern-table-container::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }

        .modern-table-container::-webkit-scrollbar-track {
            background: transparent;
        }

        .modern-table-container::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 2px;
        }

        .modern-table-container::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.4);
        }

        .modern-table-container::-webkit-scrollbar-corner {
            background: transparent;
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
            position: relative;
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
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            position: relative;
        }

        /* Sort indicators */
        .modern-table th.sorted-asc::after {
            content: ' ↑';
            color: var(--learnpressium-primary);
            font-weight: bold;
        }

        .modern-table th.sorted-desc::after {
            content: ' ↓';
            color: var(--learnpressium-primary);
            font-weight: bold;
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

        /* Prevent text selection during swipe */
        .modern-table-container.dragging {
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
        }

        /* Only prevent pointer events on text content, not on interactive elements */
        .modern-table-container.dragging .trainee-name,
        .modern-table-container.dragging .course-tag,
        .modern-table-container.dragging .modern-badge {
            pointer-events: none;
        }

        /* Clean table background */
        .modern-table-container {
            background: white;
            /* Ensure no visual artifacts or stains */
            box-shadow: none;
        }

        /* Remove any potential visual artifacts */
        .modern-table-container::before,
        .modern-table-container::after {
            display: none;
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
            background: linear-gradient(135deg, var(--learnpressium-primary) 0%, var(--learnpressium-accent) 100%);
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
            color: var(--learnpressium-primary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .action-link:hover {
            color: var(--learnpressium-accent);
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

    /**
     * Display the trainees settings page
     */
    public function display_trainees_settings_page() {
        $settings = new Trainees_Settings();
        $settings->display_settings_page();
    }

    /**
     * Convert hex color to RGB values
     */
    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');

        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "$r, $g, $b";
    }
}
