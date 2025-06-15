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
        <div class="wrap">
            <h1><?php echo esc_html($full_name); ?></h1>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=trainees')); ?>" class="button">
                    &laquo; Back to Trainees List
                </a>
            </p>

            <?php if (!empty($message)): ?>
                <div class="notice notice-<?php echo $message_type; ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>

            <div class="trainee-profile-content">
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
        <form method="post" action="">
            <?php wp_nonce_field('save_trainee_profile_' . $user->ID, 'trainee_profile_nonce'); ?>
            <h2>Registration Details</h2>
            <table class="form-table">
                <!-- Personal Information -->
                <tr>
                    <th colspan="2"><h3>Personal Information</h3></th>
                </tr>
                <tr>
                    <th><label for="first_name">First Name</label></th>
                    <td>
                        <input type="text" name="first_name" id="first_name"
                               value="<?php echo esc_attr(get_user_meta($user->ID, 'first_name', true)); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="middle_name">Middle Name</label></th>
                    <td>
                        <input type="text" name="middle_name" id="middle_name"
                               value="<?php echo esc_attr(get_user_meta($user->ID, 'middle_name', true)); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="last_name">Last Name</label></th>
                    <td>
                        <input type="text" name="last_name" id="last_name"
                               value="<?php echo esc_attr(get_user_meta($user->ID, 'last_name', true)); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="name_extension">Name Extension</label></th>
                    <td>
                        <input type="text" name="name_extension" id="name_extension"
                               value="<?php echo esc_attr(get_user_meta($user->ID, 'name_extension', true)); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="gender">Gender</label></th>
                    <td>
                        <select name="gender" id="gender" class="regular-text">
                            <option value="">Select Gender</option>
                            <option value="Male" <?php selected(get_user_meta($user->ID, 'gender', true), 'Male'); ?>>Male</option>
                            <option value="Female" <?php selected(get_user_meta($user->ID, 'gender', true), 'Female'); ?>>Female</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="civil_status">Civil Status</label></th>
                    <td>
                        <select name="civil_status" id="civil_status" class="regular-text">
                            <option value="">Select Civil Status</option>
                            <option value="Single" <?php selected(get_user_meta($user->ID, 'civil_status', true), 'Single'); ?>>Single</option>
                            <option value="Married" <?php selected(get_user_meta($user->ID, 'civil_status', true), 'Married'); ?>>Married</option>
                            <option value="Divorced" <?php selected(get_user_meta($user->ID, 'civil_status', true), 'Divorced'); ?>>Divorced</option>
                            <option value="Widowed" <?php selected(get_user_meta($user->ID, 'civil_status', true), 'Widowed'); ?>>Widowed</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="srn">SRN</label></th>
                    <td>
                        <input type="text" name="srn" id="srn"
                               value="<?php echo esc_attr(get_user_meta($user->ID, 'srn', true)); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="nationality">Nationality</label></th>
                    <td>
                        <input type="text" name="nationality" id="nationality"
                               value="<?php echo esc_attr(get_user_meta($user->ID, 'nationality', true)); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="date_of_birth">Date of Birth</label></th>
                    <td>
                        <input type="date" name="date_of_birth" id="date_of_birth"
                               value="<?php echo esc_attr(get_user_meta($user->ID, 'date_of_birth', true)); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="place_of_birth">Place of Birth</label></th>
                    <td>
                        <input type="text" name="place_of_birth" id="place_of_birth"
                               value="<?php echo esc_attr(get_user_meta($user->ID, 'place_of_birth', true)); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="complete_address">Complete Address</label></th>
                    <td>
                        <textarea name="complete_address" id="complete_address"
                                  class="regular-text" rows="3"><?php echo esc_textarea(get_user_meta($user->ID, 'complete_address', true)); ?></textarea>
                    </td>
                </tr>

                <!-- Contact Information -->
                <tr>
                    <th colspan="2"><h3>Contact Information</h3></th>
                </tr>
                <tr>
                    <th><label for="email">Email</label></th>
                    <td>
                        <input type="email" name="email" id="email"
                               value="<?php echo esc_attr($user->user_email); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="phone_number">Phone Number</label></th>
                    <td>
                        <input type="text" name="phone_number" id="phone_number"
                               value="<?php echo esc_attr(get_user_meta($user->ID, 'phone_number', true)); ?>"
                               class="regular-text" />
                    </td>
                </tr>

                <!-- Username & Account -->
                <tr>
                    <th colspan="2"><h3>Username & Account</h3></th>
                </tr>
                <tr>
                    <th><label for="username">Username</label></th>
                    <td>
                        <input type="text" id="username" value="<?php echo esc_attr($user->user_login); ?>" class="regular-text" disabled />
                        <p class="description">Username cannot be changed.</p>
                    </td>
                </tr>

                <!-- Marketing -->
                <tr>
                    <th colspan="2"><h3>Marketing</h3></th>
                </tr>
                <tr>
                    <th><label for="coupon_code">Coupon Code</label></th>
                    <td>
                        <input type="text" name="coupon_code" id="coupon_code"
                               value="<?php echo esc_attr(get_user_meta($user->ID, 'coupon_code', true)); ?>"
                               class="regular-text" />
                    </td>
                </tr>

                <!-- Emergency Contact -->
                <tr>
                    <th colspan="2"><h3>In Case of Emergency</h3></th>
                </tr>
                <tr>
                    <th><label for="emergency_first_name">First Name</label></th>
                    <td>
                        <input type="text" name="emergency_first_name" id="emergency_first_name"
                               value="<?php echo esc_attr(get_user_meta($user->ID, 'emergency_first_name', true)); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="emergency_middle_name">Middle Name</label></th>
                    <td>
                        <input type="text" name="emergency_middle_name" id="emergency_middle_name"
                               value="<?php echo esc_attr(get_user_meta($user->ID, 'emergency_middle_name', true)); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="emergency_last_name">Last Name</label></th>
                    <td>
                        <input type="text" name="emergency_last_name" id="emergency_last_name"
                               value="<?php echo esc_attr(get_user_meta($user->ID, 'emergency_last_name', true)); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="emergency_contact">Contact Number</label></th>
                    <td>
                        <input type="text" name="emergency_contact" id="emergency_contact"
                               value="<?php echo esc_attr(get_user_meta($user->ID, 'emergency_contact', true)); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="emergency_relationship">Relationship</label></th>
                    <td>
                        <input type="text" name="emergency_relationship" id="emergency_relationship"
                               value="<?php echo esc_attr(get_user_meta($user->ID, 'emergency_relationship', true)); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="emergency_address">Complete Address</label></th>
                    <td>
                        <textarea name="emergency_address" id="emergency_address"
                                  class="regular-text" rows="3"><?php echo esc_textarea(get_user_meta($user->ID, 'emergency_address', true)); ?></textarea>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="save_trainee_profile" id="save_trainee_profile" class="button button-primary" value="Save Changes">
            </p>

        </form>
        <?php
    }
}
