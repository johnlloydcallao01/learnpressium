<?php

/**
 * The Trainees Profile Management
 *
 * @package    Learnpressium
 * @subpackage Learnpressium/includes/modules/trainees
 */

/**
 * The Trainees Profile class.
 *
 * Handles individual trainee profile display and editing functionality.
 */
class Trainees_Profile {

    /**
     * Display the trainee profile page
     */
    public function display_profile_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get the username from the URL
        $username = isset($_GET['username']) ? sanitize_user($_GET['username']) : '';

        if (empty($username)) {
            wp_die('No username specified.');
        }

        // Get the user by username
        $user = get_user_by('login', $username);

        if (!$user) {
            wp_die('User not found.');
        }

        // Handle form submission
        $message = '';
        $message_type = '';

        if (isset($_POST['save_trainee_profile']) && isset($_POST['trainee_profile_nonce']) && wp_verify_nonce($_POST['trainee_profile_nonce'], 'save_trainee_profile_' . $user->ID)) {
            // Update user data
            $user_data = array(
                'ID' => $user->ID,
                'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
                'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
                'user_email' => sanitize_email($_POST['email'] ?? '')
            );

            $result = wp_update_user($user_data);

            if (is_wp_error($result)) {
                $message = $result->get_error_message();
                $message_type = 'error';
            } else {
                // Update user meta
                update_user_meta($user->ID, 'middle_name', sanitize_text_field($_POST['middle_name'] ?? ''));
                update_user_meta($user->ID, 'name_extension', sanitize_text_field($_POST['name_extension'] ?? ''));
                update_user_meta($user->ID, 'gender', sanitize_text_field($_POST['gender'] ?? ''));
                update_user_meta($user->ID, 'civil_status', sanitize_text_field($_POST['civil_status'] ?? ''));
                update_user_meta($user->ID, 'srn', sanitize_text_field($_POST['srn'] ?? ''));
                update_user_meta($user->ID, 'nationality', sanitize_text_field($_POST['nationality'] ?? ''));

                // Update Requirements section fields

                // Handle ID Picture upload from device
                if (!empty($_FILES['id_picture']['name'])) {
                    $attachment_id = $this->handle_media_upload($_FILES['id_picture'], $user->ID, 'ID Picture');
                    if ($attachment_id && !is_wp_error($attachment_id)) {
                        // DO NOT delete old attachment - keep it in Media Library for persistence
                        // Just update the user meta to point to the new attachment
                        update_user_meta($user->ID, 'id_picture_attachment_id', $attachment_id);
                    }
                }

                // Handle ID Picture selection from Media Library
                if (!empty($_POST['id_picture_media_library']) && is_numeric($_POST['id_picture_media_library'])) {
                    $selected_attachment_id = intval($_POST['id_picture_media_library']);
                    // Verify the attachment exists and is valid
                    if (get_post($selected_attachment_id) && wp_attachment_is_image($selected_attachment_id)) {
                        // DO NOT delete old attachment - keep all files in Media Library for persistence
                        // Just update the user meta to point to the selected attachment
                        update_user_meta($user->ID, 'id_picture_attachment_id', $selected_attachment_id);
                    }
                }

                // Handle ID Picture removal
                if (isset($_POST['remove_id_picture']) && $_POST['remove_id_picture'] === '1') {
                    $current_attachment_id = get_user_meta($user->ID, 'id_picture_attachment_id', true);
                    if ($current_attachment_id) {
                        // Delete the attachment from WordPress Media Library
                        wp_delete_attachment($current_attachment_id, true);
                        delete_user_meta($user->ID, 'id_picture_attachment_id');
                    }
                }

                // Handle Medical Certificate upload from device
                if (!empty($_FILES['medical_certificate']['name'])) {
                    $attachment_id = $this->handle_media_upload($_FILES['medical_certificate'], $user->ID, 'Medical Certificate');
                    if ($attachment_id && !is_wp_error($attachment_id)) {
                        // DO NOT delete old attachment - keep it in Media Library for persistence
                        // Just update the user meta to point to the new attachment
                        update_user_meta($user->ID, 'medical_certificate_attachment_id', $attachment_id);
                    }
                }

                // Handle Medical Certificate selection from Media Library
                if (!empty($_POST['medical_certificate_media_library']) && is_numeric($_POST['medical_certificate_media_library'])) {
                    $selected_attachment_id = intval($_POST['medical_certificate_media_library']);
                    // Verify the attachment exists and is valid
                    if (get_post($selected_attachment_id)) {
                        // DO NOT delete old attachment - keep all files in Media Library for persistence
                        // Just update the user meta to point to the selected attachment
                        update_user_meta($user->ID, 'medical_certificate_attachment_id', $selected_attachment_id);
                    }
                }

                // Handle Medical Certificate removal
                if (isset($_POST['remove_medical_certificate']) && $_POST['remove_medical_certificate'] === '1') {
                    $current_attachment_id = get_user_meta($user->ID, 'medical_certificate_attachment_id', true);
                    if ($current_attachment_id) {
                        // Delete the attachment from WordPress Media Library
                        wp_delete_attachment($current_attachment_id, true);
                        delete_user_meta($user->ID, 'medical_certificate_attachment_id');
                    }
                }
                update_user_meta($user->ID, 'date_of_birth', sanitize_text_field($_POST['date_of_birth'] ?? ''));
                update_user_meta($user->ID, 'place_of_birth', sanitize_text_field($_POST['place_of_birth'] ?? ''));
                update_user_meta($user->ID, 'complete_address', sanitize_textarea_field($_POST['complete_address'] ?? ''));
                update_user_meta($user->ID, 'phone_number', sanitize_text_field($_POST['phone_number'] ?? ''));
                update_user_meta($user->ID, 'coupon_code', sanitize_text_field($_POST['coupon_code'] ?? ''));

                // Emergency contact information
                update_user_meta($user->ID, 'emergency_first_name', sanitize_text_field($_POST['emergency_first_name'] ?? ''));
                update_user_meta($user->ID, 'emergency_middle_name', sanitize_text_field($_POST['emergency_middle_name'] ?? ''));
                update_user_meta($user->ID, 'emergency_last_name', sanitize_text_field($_POST['emergency_last_name'] ?? ''));
                update_user_meta($user->ID, 'emergency_contact', sanitize_text_field($_POST['emergency_contact'] ?? ''));
                update_user_meta($user->ID, 'emergency_relationship', sanitize_text_field($_POST['emergency_relationship'] ?? ''));
                update_user_meta($user->ID, 'emergency_address', sanitize_textarea_field($_POST['emergency_address'] ?? ''));

                $message = 'Profile updated successfully.';
                $message_type = 'success';

                // Refresh user data
                $user = get_user_by('id', $user->ID);
            }
        }

        // Get user's name
        $first_name = get_user_meta($user->ID, 'first_name', true);
        $last_name = get_user_meta($user->ID, 'last_name', true);

        if (!empty($first_name) && !empty($last_name)) {
            $full_name = $first_name . ' ' . $last_name;
        } else {
            $full_name = $user->user_login;
        }

        ?>
        <div class="modern-trainee-wrapper">
            <!-- Modern Header -->
            <div class="modern-header">
                <div class="header-content">
                    <div class="header-left">
                        <h1 class="trainee-name"><?php echo esc_html($full_name); ?></h1>
                        <p class="trainee-subtitle">Trainee Profile Management</p>
                    </div>
                    <div class="header-right">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=trainees')); ?>" class="modern-btn modern-btn-secondary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="m15 18-6-6 6-6"/>
                            </svg>
                            Back to Trainees
                        </a>
                    </div>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="modern-alert modern-alert-<?php echo $message_type; ?>">
                    <div class="alert-content">
                        <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 12l2 2 4-4"/>
                            <circle cx="12" cy="12" r="10"/>
                        </svg>
                        <span><?php echo esc_html($message); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="modern-content">
                <?php
                // Include the trainee page content
                if (function_exists('crf_show_registration_details')) {
                    // If the function already exists, call it directly
                    crf_show_registration_details($user);
                } else {
                    // If the function doesn't exist, we need to define an editable version
                    $this->render_profile_form($user);
                }
                ?>
            </div>

        </div>
        <?php
    }

    /**
     * Render the profile form
     */
    private function render_profile_form($user) {
        ?>
        <form method="post" action="" enctype="multipart/form-data" class="modern-form">
            <?php wp_nonce_field('save_trainee_profile_' . $user->ID, 'trainee_profile_nonce'); ?>

            <!-- Personal Information Card -->
            <div class="modern-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg class="card-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        Personal Information
                    </h3>
                </div>
                <div class="card-content">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" name="first_name" id="first_name"
                                   value="<?php echo esc_attr(get_user_meta($user->ID, 'first_name', true)); ?>"
                                   class="form-input" placeholder="Enter first name" />
                        </div>
                        <div class="form-group">
                            <label for="middle_name" class="form-label">Middle Name</label>
                            <input type="text" name="middle_name" id="middle_name"
                                   value="<?php echo esc_attr(get_user_meta($user->ID, 'middle_name', true)); ?>"
                                   class="form-input" placeholder="Enter middle name" />
                        </div>
                        <div class="form-group">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" name="last_name" id="last_name"
                                   value="<?php echo esc_attr(get_user_meta($user->ID, 'last_name', true)); ?>"
                                   class="form-input" placeholder="Enter last name" />
                        </div>
                        <div class="form-group">
                            <label for="name_extension" class="form-label">Name Extension</label>
                            <input type="text" name="name_extension" id="name_extension"
                                   value="<?php echo esc_attr(get_user_meta($user->ID, 'name_extension', true)); ?>"
                                   class="form-input" placeholder="Jr., Sr., III, etc." />
                        </div>
                        <div class="form-group">
                            <label for="gender" class="form-label">Gender</label>
                            <select name="gender" id="gender" class="form-select">
                                <option value="">Select Gender</option>
                                <option value="Male" <?php selected(get_user_meta($user->ID, 'gender', true), 'Male'); ?>>Male</option>
                                <option value="Female" <?php selected(get_user_meta($user->ID, 'gender', true), 'Female'); ?>>Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="civil_status" class="form-label">Civil Status</label>
                            <select name="civil_status" id="civil_status" class="form-select">
                                <option value="">Select Civil Status</option>
                                <option value="Single" <?php selected(get_user_meta($user->ID, 'civil_status', true), 'Single'); ?>>Single</option>
                                <option value="Married" <?php selected(get_user_meta($user->ID, 'civil_status', true), 'Married'); ?>>Married</option>
                                <option value="Divorced" <?php selected(get_user_meta($user->ID, 'civil_status', true), 'Divorced'); ?>>Divorced</option>
                                <option value="Widowed" <?php selected(get_user_meta($user->ID, 'civil_status', true), 'Widowed'); ?>>Widowed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="srn" class="form-label">SRN</label>
                            <input type="text" name="srn" id="srn"
                                   value="<?php echo esc_attr(get_user_meta($user->ID, 'srn', true)); ?>"
                                   class="form-input" placeholder="Student Registration Number" />
                        </div>
                        <div class="form-group">
                            <label for="nationality" class="form-label">Nationality</label>
                            <input type="text" name="nationality" id="nationality"
                                   value="<?php echo esc_attr(get_user_meta($user->ID, 'nationality', true)); ?>"
                                   class="form-input" placeholder="Enter nationality" />
                        </div>
                        <div class="form-group">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" name="date_of_birth" id="date_of_birth"
                                   value="<?php echo esc_attr(get_user_meta($user->ID, 'date_of_birth', true)); ?>"
                                   class="form-input" />
                        </div>
                        <div class="form-group">
                            <label for="place_of_birth" class="form-label">Place of Birth</label>
                            <input type="text" name="place_of_birth" id="place_of_birth"
                                   value="<?php echo esc_attr(get_user_meta($user->ID, 'place_of_birth', true)); ?>"
                                   class="form-input" placeholder="Enter place of birth" />
                        </div>
                    </div>
                    <div class="form-grid-full">
                        <div class="form-group">
                            <label for="complete_address" class="form-label">Complete Address</label>
                            <textarea name="complete_address" id="complete_address"
                                      class="form-textarea" rows="3" placeholder="Enter complete address"><?php echo esc_textarea(get_user_meta($user->ID, 'complete_address', true)); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information Card -->
            <div class="modern-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg class="card-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                        Contact Information
                    </h3>
                </div>
                <div class="card-content">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" name="email" id="email"
                                   value="<?php echo esc_attr($user->user_email); ?>"
                                   class="form-input" placeholder="Enter email address" />
                        </div>
                        <div class="form-group">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <input type="text" name="phone_number" id="phone_number"
                                   value="<?php echo esc_attr(get_user_meta($user->ID, 'phone_number', true)); ?>"
                                   class="form-input" placeholder="Enter phone number" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Information Card -->
            <div class="modern-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg class="card-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="8.5" cy="7" r="4"/>
                            <path d="m22 2-5 10-5-4-5 10"/>
                        </svg>
                        Account & Marketing
                    </h3>
                </div>
                <div class="card-content">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" id="username" value="<?php echo esc_attr($user->user_login); ?>"
                                   class="form-input" disabled style="background: #f9fafb; color: #6b7280;" />
                            <small class="form-help">Username cannot be changed</small>
                        </div>
                        <div class="form-group">
                            <label for="coupon_code" class="form-label">Coupon Code</label>
                            <input type="text" name="coupon_code" id="coupon_code"
                                   value="<?php echo esc_attr(get_user_meta($user->ID, 'coupon_code', true)); ?>"
                                   class="form-input" placeholder="Enter coupon code" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Emergency Contact Card -->
            <div class="modern-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg class="card-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                        </svg>
                        Emergency Contact
                    </h3>
                </div>
                <div class="card-content">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="emergency_first_name" class="form-label">First Name</label>
                            <input type="text" name="emergency_first_name" id="emergency_first_name"
                                   value="<?php echo esc_attr(get_user_meta($user->ID, 'emergency_first_name', true)); ?>"
                                   class="form-input" placeholder="Emergency contact first name" />
                        </div>
                        <div class="form-group">
                            <label for="emergency_middle_name" class="form-label">Middle Name</label>
                            <input type="text" name="emergency_middle_name" id="emergency_middle_name"
                                   value="<?php echo esc_attr(get_user_meta($user->ID, 'emergency_middle_name', true)); ?>"
                                   class="form-input" placeholder="Emergency contact middle name" />
                        </div>
                        <div class="form-group">
                            <label for="emergency_last_name" class="form-label">Last Name</label>
                            <input type="text" name="emergency_last_name" id="emergency_last_name"
                                   value="<?php echo esc_attr(get_user_meta($user->ID, 'emergency_last_name', true)); ?>"
                                   class="form-input" placeholder="Emergency contact last name" />
                        </div>
                        <div class="form-group">
                            <label for="emergency_contact" class="form-label">Contact Number</label>
                            <input type="text" name="emergency_contact" id="emergency_contact"
                                   value="<?php echo esc_attr(get_user_meta($user->ID, 'emergency_contact', true)); ?>"
                                   class="form-input" placeholder="Emergency contact number" />
                        </div>
                        <div class="form-group">
                            <label for="emergency_relationship" class="form-label">Relationship</label>
                            <input type="text" name="emergency_relationship" id="emergency_relationship"
                                   value="<?php echo esc_attr(get_user_meta($user->ID, 'emergency_relationship', true)); ?>"
                                   class="form-input" placeholder="Relationship to trainee" />
                        </div>
                    </div>
                    <div class="form-grid-full">
                        <div class="form-group">
                            <label for="emergency_address" class="form-label">Complete Address</label>
                            <textarea name="emergency_address" id="emergency_address"
                                      class="form-textarea" rows="3" placeholder="Emergency contact complete address"><?php echo esc_textarea(get_user_meta($user->ID, 'emergency_address', true)); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Requirements Card -->
            <div class="modern-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg class="card-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14,2 14,8 20,8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10,9 9,9 8,9"/>
                        </svg>
                        Required Documents
                    </h3>
                </div>
                <div class="card-content">
                    <div class="form-grid-2">
                        <!-- ID Picture -->
                        <div class="form-group">
                            <label for="id_picture" class="form-label">ID Picture</label>
                            <?php
                            $current_attachment_id = get_user_meta($user->ID, 'id_picture_attachment_id', true);
                            if ($current_attachment_id && get_post($current_attachment_id)) {
                                $file_url = wp_get_attachment_url($current_attachment_id);
                                $file_type = get_post_mime_type($current_attachment_id);
                                $attachment_title = get_the_title($current_attachment_id);
                                ?>
                                <div class="file-preview">
                                    <?php if (strpos($file_type, 'image/') === 0): ?>
                                        <?php
                                        $image_src = wp_get_attachment_image_src($current_attachment_id, 'medium');
                                        if ($image_src):
                                        ?>
                                            <img src="<?php echo esc_url($image_src[0]); ?>" alt="Current ID Picture" style="max-width: 200px; max-height: 200px; border-radius: 0.5rem;" />
                                        <?php endif; ?>
                                    <?php elseif ($file_type === 'application/pdf'): ?>
                                        <div class="pdf-preview">
                                            <svg class="pdf-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                                <polyline points="14,2 14,8 20,8"/>
                                                <line x1="16" y1="13" x2="8" y2="13"/>
                                                <line x1="16" y1="17" x2="8" y2="17"/>
                                                <polyline points="10,9 9,9 8,9"/>
                                            </svg>
                                            <div>
                                                <a href="<?php echo esc_url($file_url); ?>" target="_blank" style="text-decoration: none; color: #374151; font-weight: 600;">
                                                    <?php echo esc_html($attachment_title ?: 'View PDF Document'); ?>
                                                </a>
                                                <br />
                                                <small style="color: #6b7280;">Click to open in new tab</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <label style="margin-top: 1rem; display: flex; align-items: center; gap: 0.5rem; color: #ef4444; font-size: 0.9rem;">
                                        <input type="checkbox" name="remove_id_picture" value="1" />
                                        Remove current file
                                    </label>
                                </div>
                                <?php
                            }
                            ?>
                            <div class="upload-options">
                                <div class="upload-tabs">
                                    <button type="button" class="upload-tab active" data-tab="device" data-field="id_picture">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                            <polyline points="7,10 12,15 17,10"/>
                                            <line x1="12" y1="15" x2="12" y2="3"/>
                                        </svg>
                                        Upload from Device
                                    </button>
                                    <button type="button" class="upload-tab" data-tab="library" data-field="id_picture">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                            <circle cx="8.5" cy="8.5" r="1.5"/>
                                            <polyline points="21,15 16,10 5,21"/>
                                        </svg>
                                        Media Library
                                    </button>
                                </div>

                                <div class="upload-content">
                                    <div class="upload-panel active" id="id_picture_device">
                                        <div class="file-upload-area">
                                            <input type="file" name="id_picture" id="id_picture" accept="image/*,.pdf" style="margin-bottom: 0.5rem;" />
                                            <p style="margin: 0; color: #6b7280; font-size: 0.9rem;">Upload ID picture (JPG, PNG, GIF, PDF). Max: 2MB</p>
                                        </div>
                                    </div>

                                    <div class="upload-panel" id="id_picture_library">
                                        <div class="media-library-selector">
                                            <button type="button" class="media-library-btn" data-field="id_picture_media_library">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                                    <polyline points="21,15 16,10 5,21"/>
                                                </svg>
                                                Choose from Media Library
                                            </button>
                                            <input type="hidden" name="id_picture_media_library" id="id_picture_media_library" value="" />
                                            <div class="selected-media" id="id_picture_selected" style="display: none;">
                                                <div class="selected-media-preview"></div>
                                                <button type="button" class="remove-selected" data-field="id_picture_media_library">Remove Selection</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Medical Certificate -->
                        <div class="form-group">
                            <label for="medical_certificate" class="form-label">Medical Certificate</label>
                            <?php
                            $current_attachment_id = get_user_meta($user->ID, 'medical_certificate_attachment_id', true);
                            if ($current_attachment_id && get_post($current_attachment_id)) {
                                $file_url = wp_get_attachment_url($current_attachment_id);
                                $file_type = get_post_mime_type($current_attachment_id);
                                $attachment_title = get_the_title($current_attachment_id);
                                ?>
                                <div class="file-preview">
                                    <?php if (strpos($file_type, 'image/') === 0): ?>
                                        <?php
                                        $image_src = wp_get_attachment_image_src($current_attachment_id, 'medium');
                                        if ($image_src):
                                        ?>
                                            <img src="<?php echo esc_url($image_src[0]); ?>" alt="Current Medical Certificate" style="max-width: 200px; max-height: 200px; border-radius: 0.5rem;" />
                                        <?php endif; ?>
                                    <?php elseif ($file_type === 'application/pdf'): ?>
                                        <div class="pdf-preview">
                                            <svg class="pdf-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                                <polyline points="14,2 14,8 20,8"/>
                                                <line x1="16" y1="13" x2="8" y2="13"/>
                                                <line x1="16" y1="17" x2="8" y2="17"/>
                                                <polyline points="10,9 9,9 8,9"/>
                                            </svg>
                                            <div>
                                                <a href="<?php echo esc_url($file_url); ?>" target="_blank" style="text-decoration: none; color: #374151; font-weight: 600;">
                                                    <?php echo esc_html($attachment_title ?: 'View Medical Certificate'); ?>
                                                </a>
                                                <br />
                                                <small style="color: #6b7280;">Click to open in new tab</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <label style="margin-top: 1rem; display: flex; align-items: center; gap: 0.5rem; color: #ef4444; font-size: 0.9rem;">
                                        <input type="checkbox" name="remove_medical_certificate" value="1" />
                                        Remove current file
                                    </label>
                                </div>
                                <?php
                            }
                            ?>
                            <div class="upload-options">
                                <div class="upload-tabs">
                                    <button type="button" class="upload-tab active" data-tab="device" data-field="medical_certificate">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                            <polyline points="7,10 12,15 17,10"/>
                                            <line x1="12" y1="15" x2="12" y2="3"/>
                                        </svg>
                                        Upload from Device
                                    </button>
                                    <button type="button" class="upload-tab" data-tab="library" data-field="medical_certificate">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                            <circle cx="8.5" cy="8.5" r="1.5"/>
                                            <polyline points="21,15 16,10 5,21"/>
                                        </svg>
                                        Media Library
                                    </button>
                                </div>

                                <div class="upload-content">
                                    <div class="upload-panel active" id="medical_certificate_device">
                                        <div class="file-upload-area">
                                            <input type="file" name="medical_certificate" id="medical_certificate" accept="image/*,.pdf" style="margin-bottom: 0.5rem;" />
                                            <p style="margin: 0; color: #6b7280; font-size: 0.9rem;">Upload medical certificate (JPG, PNG, GIF, PDF). Max: 2MB</p>
                                        </div>
                                    </div>

                                    <div class="upload-panel" id="medical_certificate_library">
                                        <div class="media-library-selector">
                                            <button type="button" class="media-library-btn" data-field="medical_certificate_media_library">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                                    <polyline points="21,15 16,10 5,21"/>
                                                </svg>
                                                Choose from Media Library
                                            </button>
                                            <input type="hidden" name="medical_certificate_media_library" id="medical_certificate_media_library" value="" />
                                            <div class="selected-media" id="medical_certificate_selected" style="display: none;">
                                                <div class="selected-media-preview"></div>
                                                <button type="button" class="remove-selected" data-field="medical_certificate_media_library">Remove Selection</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- About Courses Card -->
            <div class="modern-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg class="card-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                        </svg>
                        About Courses
                    </h3>
                </div>
                <div class="card-content">
                    <?php
                    // Get all course data for this user
                    $enrolled_courses = Trainees_Module::get_user_enrolled_courses($user->ID);
                    $is_enrolled = !empty($enrolled_courses);

                    $active_enrolled_courses = Trainees_Module::get_user_active_enrolled_courses($user->ID);
                    $has_active_enrolled = !empty($active_enrolled_courses);

                    $scheduled_courses = Trainees_Module::get_user_scheduled_courses($user->ID);
                    $has_scheduled = !empty($scheduled_courses);
                    ?>

                    <div class="courses-overview">
                        <!-- Course Status Grid -->
                        <div class="course-status-grid">
                            <!-- Enrolled Status -->
                            <div class="status-item">
                                <div class="status-header">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                        <polyline points="22,4 12,14.01 9,11.01"/>
                                    </svg>
                                    <span class="status-label">Enrolled</span>
                                </div>
                                <div class="status-value">
                                    <span class="modern-badge <?php echo $is_enrolled ? 'badge-success' : 'badge-warning'; ?>">
                                        <?php echo $is_enrolled ? 'Yes' : 'No'; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Active Enrolled Status -->
                            <div class="status-item">
                                <div class="status-header">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polygon points="10,8 16,12 10,16 10,8"/>
                                    </svg>
                                    <span class="status-label">Has Active Enrolled</span>
                                </div>
                                <div class="status-value">
                                    <span class="modern-badge <?php echo $has_active_enrolled ? 'badge-success' : 'badge-warning'; ?>">
                                        <?php echo $has_active_enrolled ? 'Yes' : 'No'; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Scheduled Status -->
                            <div class="status-item">
                                <div class="status-header">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polyline points="12,6 12,12 16,14"/>
                                    </svg>
                                    <span class="status-label">Has Scheduled</span>
                                </div>
                                <div class="status-value">
                                    <span class="modern-badge <?php echo $has_scheduled ? 'badge-info' : 'badge-warning'; ?>">
                                        <?php echo $has_scheduled ? 'Yes' : 'No'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Course Lists -->
                        <div class="course-lists">
                            <!-- Enrolled Courses -->
                            <div class="course-section">
                                <h4 class="course-section-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                                    </svg>
                                    Enrolled Courses
                                </h4>
                                <div class="course-items">
                                    <?php if ($is_enrolled): ?>
                                        <?php foreach ($enrolled_courses as $course): ?>
                                            <div class="course-item enrolled">
                                                <span class="course-name"><?php echo esc_html($course->post_title); ?></span>
                                                <span class="course-status">Enrolled</span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="no-courses">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                                <circle cx="12" cy="12" r="10"/>
                                                <line x1="15" y1="9" x2="9" y2="15"/>
                                                <line x1="9" y1="9" x2="15" y2="15"/>
                                            </svg>
                                            <span>No enrolled courses</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Active Enrolled Courses -->
                            <div class="course-section">
                                <h4 class="course-section-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polygon points="10,8 16,12 10,16 10,8"/>
                                    </svg>
                                    Active Enrolled Courses
                                </h4>
                                <div class="course-items">
                                    <?php if ($has_active_enrolled): ?>
                                        <?php foreach ($active_enrolled_courses as $course): ?>
                                            <div class="course-item active">
                                                <span class="course-name"><?php echo esc_html($course->post_title); ?></span>
                                                <span class="course-status">In Progress</span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="no-courses">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                                <circle cx="12" cy="12" r="10"/>
                                                <line x1="15" y1="9" x2="9" y2="15"/>
                                                <line x1="9" y1="9" x2="15" y2="15"/>
                                            </svg>
                                            <span>No active enrolled courses</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Scheduled Courses -->
                            <div class="course-section">
                                <h4 class="course-section-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polyline points="12,6 12,12 16,14"/>
                                    </svg>
                                    Scheduled Courses
                                </h4>
                                <div class="course-items">
                                    <?php if ($has_scheduled): ?>
                                        <?php foreach ($scheduled_courses as $course): ?>
                                            <div class="course-item scheduled">
                                                <span class="course-name"><?php echo esc_html($course->post_title); ?></span>
                                                <span class="course-status">
                                                    Scheduled for <?php echo date('M j, Y', strtotime($course->scheduled_start_date)); ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="no-courses">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                                <circle cx="12" cy="12" r="10"/>
                                                <line x1="15" y1="9" x2="9" y2="15"/>
                                                <line x1="9" y1="9" x2="15" y2="15"/>
                                            </svg>
                                            <span>No scheduled courses</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Section -->
            <div class="submit-section">
                <button type="submit" name="save_trainee_profile" id="save_trainee_profile" class="modern-btn modern-btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17,21 17,13 7,13 7,21"/>
                        <polyline points="7,3 7,8 15,8"/>
                    </svg>
                    Save Changes
                </button>
            </div>

        </form>

        <style>
        /* CSS Variables for Theme Colors */
        :root {
            --learnpressium-primary: <?php echo esc_attr(get_option('learnpressium_primary_color', '#201a7c')); ?>;
            --learnpressium-accent: <?php echo esc_attr(get_option('learnpressium_accent_color', '#ab3b43')); ?>;
            --learnpressium-primary-rgb: <?php echo esc_attr($this->hex_to_rgb(get_option('learnpressium_primary_color', '#201a7c'))); ?>;
            --learnpressium-accent-rgb: <?php echo esc_attr($this->hex_to_rgb(get_option('learnpressium_accent_color', '#ab3b43'))); ?>;
        }

        /* Modern Trainee Profile Styles */
        .modern-trainee-wrapper {
            max-width: 1200px;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .trainee-name {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .trainee-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0.5rem 0 0 0;
            font-weight: 400;
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

        .modern-btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .modern-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .modern-btn-primary {
            background: linear-gradient(135deg, var(--learnpressium-primary) 0%, var(--learnpressium-accent) 100%);
            color: white;
            box-shadow: 0 4px 14px 0 rgba(var(--learnpressium-primary-rgb), 0.39);
        }

        .modern-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(32, 26, 124, 0.4);
        }

        /* Modern Alert */
        .modern-alert {
            margin: 0 2rem 2rem 2rem;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            border-left: 4px solid;
            background: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .modern-alert-success {
            border-left-color: #10b981;
            background: #f0fdf4;
        }

        .alert-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-icon {
            color: #10b981;
            flex-shrink: 0;
        }

        /* Modern Content */
        .modern-content {
            padding: 0 2rem 2rem 2rem;
        }

        /* Modern Form */
        .modern-form {
            display: flex;
            flex-direction: column;
            gap: 2rem;
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

        .card-content {
            padding: 2rem;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
        }

        .form-grid-full {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        /* Form Groups */
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            font-size: 0.95rem;
        }

        .form-input, .form-select, .form-textarea {
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: white;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--learnpressium-primary);
            box-shadow: 0 0 0 3px rgba(var(--learnpressium-primary-rgb), 0.1);
        }

        .form-input::placeholder {
            color: #9ca3af;
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* File Upload Styling */
        .file-upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 0.75rem;
            padding: 2rem;
            text-align: center;
            transition: all 0.2s ease;
            background: #f9fafb;
        }

        .file-upload-area:hover {
            border-color: var(--learnpressium-primary);
            background: #f0f1ff;
        }

        .file-preview {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .file-preview img {
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .pdf-preview {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
        }

        .pdf-icon {
            color: #dc2626;
            font-size: 2rem;
        }

        /* Submit Button */
        .submit-section {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .modern-trainee-wrapper {
                padding: 0;
            }

            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                padding: 0 1rem;
            }

            .trainee-name {
                font-size: 2rem;
            }

            .modern-content {
                padding: 0 1rem 2rem 1rem;
            }

            .card-content {
                padding: 1.5rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-grid-2 {
                grid-template-columns: 1fr;
            }
        }

        /* Form Helper Text */
        .form-help {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        /* Enhanced File Upload */
        .file-upload-area input[type="file"] {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background: white;
        }

        /* Upload Options Styling */
        .upload-options {
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            overflow: hidden;
            background: white;
        }

        .upload-tabs {
            display: flex;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }

        .upload-tab {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            background: transparent;
            color: #6b7280;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .upload-tab:hover {
            background: #f3f4f6;
            color: #374151;
        }

        .upload-tab.active {
            background: var(--learnpressium-primary);
            color: white;
        }

        .upload-tab svg {
            width: 16px;
            height: 16px;
        }

        .upload-content {
            position: relative;
        }

        .upload-panel {
            padding: 1.5rem;
            display: none;
        }

        .upload-panel.active {
            display: block;
        }

        /* Media Library Selector */
        .media-library-selector {
            text-align: center;
        }

        .media-library-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, var(--learnpressium-primary) 0%, var(--learnpressium-accent) 100%);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .media-library-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(32, 26, 124, 0.3);
        }

        .selected-media {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
        }

        .selected-media-preview {
            margin-bottom: 0.75rem;
        }

        .selected-media-preview img {
            max-width: 150px;
            max-height: 150px;
            border-radius: 0.375rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .remove-selected {
            padding: 0.5rem 1rem;
            background: var(--learnpressium-accent);
            color: white;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .remove-selected:hover {
            background: #8b2f36;
        }

        /* Modern Animations */
        .modern-card, .modern-btn, .form-input, .form-select, .form-textarea {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-input:hover, .form-select:hover, .form-textarea:hover {
            border-color: #9ca3af;
        }

        /* Loading States */
        .modern-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Enhanced Shadows */
        .modern-card {
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }

        .modern-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        /* Better Typography */
        .modern-trainee-wrapper {
            line-height: 1.6;
            color: #374151;
        }

        /* Focus States */
        .modern-btn:focus {
            outline: 2px solid transparent;
            outline-offset: 2px;
            box-shadow: 0 0 0 3px rgba(32, 26, 124, 0.5);
        }

        /* About Courses Section */
        .courses-overview {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .course-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .status-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.2s ease;
        }

        .status-item:hover {
            border-color: var(--learnpressium-primary);
            box-shadow: 0 4px 6px -1px rgba(var(--learnpressium-primary-rgb), 0.1);
        }

        .status-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: #374151;
        }

        .status-label {
            font-weight: 600;
            font-size: 0.875rem;
        }

        .status-value {
            display: flex;
            justify-content: center;
        }

        /* Badge Info Style */
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        /* Course Lists */
        .course-lists {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .course-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.5rem;
        }

        .course-section-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 1rem 0;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .course-items {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .course-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }

        .course-item:hover {
            border-color: var(--learnpressium-primary);
            box-shadow: 0 2px 4px rgba(var(--learnpressium-primary-rgb), 0.1);
        }

        .course-item.enrolled {
            border-left: 4px solid #10b981;
        }

        .course-item.active {
            border-left: 4px solid #059669;
            background: #f0fdf4;
        }

        .course-item.scheduled {
            border-left: 4px solid #3b82f6;
            background: #eff6ff;
        }

        .course-name {
            font-weight: 600;
            color: #1f2937;
            flex: 1;
        }

        .course-status {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }

        .course-item.active .course-status {
            color: #059669;
        }

        .course-item.scheduled .course-status {
            color: #3b82f6;
        }

        .no-courses {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 2rem;
            color: #9ca3af;
            font-style: italic;
        }

        .no-courses svg {
            opacity: 0.5;
        }

        /* Responsive Design for Courses */
        @media (max-width: 768px) {
            .course-status-grid {
                grid-template-columns: 1fr;
            }

            .course-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .course-status {
                align-self: flex-end;
            }
        }

        /* Hide WordPress default styles */
        .modern-trainee-wrapper .form-table {
            display: none;
        }

        .modern-trainee-wrapper h2 {
            display: none;
        }

        .modern-trainee-wrapper .wrap > h1 {
            display: none;
        }

        .modern-trainee-wrapper .wrap > p {
            display: none;
        }

        /* Override WordPress admin styles */
        .modern-trainee-wrapper * {
            box-sizing: border-box;
        }
        </style>

        <?php
        // Enqueue WordPress media scripts
        wp_enqueue_media();
        wp_enqueue_script('jquery');
        ?>

        <script>
        jQuery(document).ready(function($) {
            // Upload tab switching
            $('.upload-tab').on('click', function() {
                const tab = $(this).data('tab');
                const field = $(this).data('field');

                // Update tab appearance
                $(this).siblings().removeClass('active');
                $(this).addClass('active');

                // Show/hide panels
                $(`#${field}_device, #${field}_library`).removeClass('active');
                $(`#${field}_${tab}`).addClass('active');
            });

            // Media Library button functionality
            $('.media-library-btn').on('click', function() {
                const fieldName = $(this).data('field');

                // Create WordPress media frame
                const mediaFrame = wp.media({
                    title: 'Select File',
                    button: {
                        text: 'Use this file'
                    },
                    multiple: false,
                    library: {
                        type: ['image', 'application/pdf']
                    }
                });

                // When file is selected
                mediaFrame.on('select', function() {
                    const attachment = mediaFrame.state().get('selection').first().toJSON();

                    // Set the hidden input value
                    $(`#${fieldName}`).val(attachment.id);

                    // Show preview
                    const previewContainer = $(`#${fieldName.replace('_media_library', '_selected')}`);
                    const previewContent = previewContainer.find('.selected-media-preview');

                    if (attachment.type === 'image') {
                        previewContent.html(`<img src="${attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url}" alt="${attachment.title}" />`);
                    } else {
                        previewContent.html(`
                            <div class="pdf-preview">
                                <svg class="pdf-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14,2 14,8 20,8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                    <polyline points="10,9 9,9 8,9"/>
                                </svg>
                                <div>
                                    <a href="${attachment.url}" target="_blank" style="text-decoration: none; color: #374151; font-weight: 600;">
                                        ${attachment.title}
                                    </a>
                                    <br />
                                    <small style="color: #6b7280;">Click to open in new tab</small>
                                </div>
                            </div>
                        `);
                    }

                    previewContainer.show();
                });

                // Open the media frame
                mediaFrame.open();
            });

            // Remove selected media
            $('.remove-selected').on('click', function() {
                const fieldName = $(this).data('field');
                $(`#${fieldName}`).val('');
                $(`#${fieldName.replace('_media_library', '_selected')}`).hide();
            });
        });
        </script>
        <?php
    }

    /**
     * Handle media upload using WordPress Media Library
     *
     * @param array $file The uploaded file array from $_FILES
     * @param int $user_id The user ID for organizing uploads
     * @param string $document_type The type of document (e.g., 'ID Picture', 'Medical Certificate')
     * @return int|WP_Error The attachment ID or error
     */
    private function handle_media_upload($file, $user_id, $document_type = 'Document') {
        // Check if file was uploaded without errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 'File upload failed.');
        }

        // Validate file type
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf');
        $file_type = wp_check_filetype($file['name']);

        if (!in_array($file['type'], $allowed_types) || !in_array($file_type['type'], $allowed_types)) {
            return new WP_Error('invalid_file_type', 'Only JPG, PNG, GIF, and PDF files are allowed.');
        }

        // Check file size (2MB limit)
        $max_size = 2 * 1024 * 1024; // 2MB in bytes
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', 'File size must be less than 2MB.');
        }

        // Include required WordPress files for media handling
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        if (!function_exists('media_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }

        // Handle the upload using WordPress media functions
        $upload_overrides = array(
            'test_form' => false,
            'test_size' => true,
            'test_upload' => true,
        );

        $uploaded_file = wp_handle_upload($file, $upload_overrides);

        if (isset($uploaded_file['error'])) {
            return new WP_Error('upload_failed', $uploaded_file['error']);
        }

        // Create attachment post
        $attachment = array(
            'post_mime_type' => $uploaded_file['type'],
            'post_title' => 'Trainee ' . $document_type . ' - User ' . $user_id,
            'post_content' => '',
            'post_status' => 'inherit'
        );

        // Insert the attachment
        $attachment_id = wp_insert_attachment($attachment, $uploaded_file['file']);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Generate attachment metadata
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        return $attachment_id;
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
