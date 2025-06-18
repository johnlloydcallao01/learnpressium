<?php

/**
 * The Trainees Settings Handler
 *
 * @package    Learnpressium
 * @subpackage Learnpressium/includes/modules/trainees
 */

/**
 * The Trainees Settings class.
 *
 * Handles theme color customization settings for the trainees module.
 */
class Trainees_Settings {

    /**
     * Display the settings page
     */
    public function display_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['trainees_settings_nonce'], 'trainees_settings')) {
            $this->save_settings();
        }

        // Get current settings
        $primary_color = get_option('learnpressium_primary_color', '#201a7c');
        $accent_color = get_option('learnpressium_accent_color', '#ab3b43');

        ?>
        <div class="wrap trainees-settings-wrap">
            <style>
            /* CSS Variables for Settings Page */
            :root {
                --learnpressium-primary: <?php echo esc_attr($primary_color); ?>;
                --learnpressium-accent: <?php echo esc_attr($accent_color); ?>;
                --learnpressium-primary-rgb: <?php echo esc_attr($this->hex_to_rgb($primary_color)); ?>;
                --learnpressium-accent-rgb: <?php echo esc_attr($this->hex_to_rgb($accent_color)); ?>;
            }

            /* Settings Page Styles */
            .trainees-settings-wrap {
                background: #f8fafc;
                margin: -20px -20px -20px -20px;
                padding: 0;
                min-height: 100vh;
            }

            .settings-header {
                background: linear-gradient(135deg, var(--learnpressium-primary) 0%, var(--learnpressium-accent) 100%);
                color: white;
                padding: 2rem 0;
                margin-bottom: 2rem;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }

            .settings-header-content {
                max-width: 1200px;
                margin: 0 auto;
                padding: 0 2rem;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .settings-title {
                font-size: 2rem;
                font-weight: 700;
                margin: 0;
                color: white;
                text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .settings-subtitle {
                font-size: 1rem;
                opacity: 0.9;
                margin: 0.5rem 0 0 0;
                font-weight: 400;
            }

            .back-link {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                color: white;
                text-decoration: none;
                padding: 0.75rem 1.5rem;
                border: 2px solid rgba(255, 255, 255, 0.3);
                border-radius: 0.5rem;
                font-weight: 600;
                transition: all 0.2s ease;
            }

            .back-link:hover {
                background: rgba(255, 255, 255, 0.1);
                border-color: rgba(255, 255, 255, 0.5);
                color: white;
                text-decoration: none;
            }

            .settings-content {
                max-width: 800px;
                margin: 0 auto;
                padding: 0 2rem 2rem 2rem;
            }

            .settings-card {
                background: white;
                border-radius: 1rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                overflow: hidden;
                margin-bottom: 2rem;
            }

            .settings-card-header {
                background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                padding: 1.5rem 2rem;
                border-bottom: 1px solid #e2e8f0;
            }

            .settings-card-title {
                font-size: 1.25rem;
                font-weight: 600;
                color: #1e293b;
                margin: 0;
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }

            .settings-card-content {
                padding: 2rem;
            }

            .color-setting {
                margin-bottom: 2rem;
            }

            .color-setting:last-child {
                margin-bottom: 0;
            }

            .color-label {
                display: block;
                font-weight: 600;
                color: #374151;
                margin-bottom: 0.5rem;
                font-size: 0.875rem;
            }

            .color-description {
                color: #6b7280;
                font-size: 0.75rem;
                margin-bottom: 1rem;
            }

            .color-input-wrapper {
                display: flex;
                align-items: center;
                gap: 1rem;
            }

            .color-input {
                width: 60px;
                height: 40px;
                border: 2px solid #e5e7eb;
                border-radius: 0.5rem;
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .color-input:hover {
                border-color: var(--learnpressium-primary);
            }

            .color-text-input {
                flex: 1;
                padding: 0.75rem 1rem;
                border: 2px solid #e5e7eb;
                border-radius: 0.5rem;
                font-size: 0.875rem;
                font-family: monospace;
                transition: all 0.2s ease;
            }

            .color-text-input:focus {
                outline: none;
                border-color: var(--learnpressium-primary);
                box-shadow: 0 0 0 3px rgba(var(--learnpressium-primary-rgb), 0.1);
            }

            .color-preview {
                width: 40px;
                height: 40px;
                border-radius: 0.5rem;
                border: 2px solid #e5e7eb;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: 600;
                font-size: 0.75rem;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            }

            .submit-section {
                background: white;
                padding: 2rem;
                border-radius: 1rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                text-align: center;
            }

            .submit-btn {
                background: linear-gradient(135deg, var(--learnpressium-primary) 0%, var(--learnpressium-accent) 100%);
                color: white;
                border: none;
                padding: 0.75rem 2rem;
                border-radius: 0.5rem;
                font-weight: 600;
                font-size: 0.95rem;
                cursor: pointer;
                transition: all 0.2s ease;
                box-shadow: 0 4px 14px 0 rgba(var(--learnpressium-primary-rgb), 0.39);
            }

            .submit-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(var(--learnpressium-primary-rgb), 0.4);
            }

            .reset-btn {
                background: #6b7280;
                color: white;
                border: none;
                padding: 0.75rem 1.5rem;
                border-radius: 0.5rem;
                font-weight: 600;
                font-size: 0.95rem;
                cursor: pointer;
                transition: all 0.2s ease;
                margin-left: 1rem;
            }

            .reset-btn:hover {
                background: #4b5563;
                transform: translateY(-1px);
            }

            .success-message {
                background: #dcfce7;
                color: #166534;
                padding: 1rem 1.5rem;
                border-radius: 0.5rem;
                margin-bottom: 2rem;
                border-left: 4px solid #10b981;
            }
            </style>

            <div class="settings-header">
                <div class="settings-header-content">
                    <div>
                        <h1 class="settings-title">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"/>
                            </svg>
                            Trainees Preferences
                        </h1>
                        <p class="settings-subtitle">Customize the theme colors for your trainees module</p>
                    </div>
                    <a href="<?php echo admin_url('admin.php?page=trainees'); ?>" class="back-link">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 12H5m7-7-7 7 7 7"/>
                        </svg>
                        Back to Trainees
                    </a>
                </div>
            </div>

            <div class="settings-content">
                <?php if (isset($_GET['updated']) && $_GET['updated'] === 'true'): ?>
                    <div class="success-message">
                        <strong>Settings saved!</strong> Your theme colors have been updated successfully.
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <?php wp_nonce_field('trainees_settings', 'trainees_settings_nonce'); ?>
                    
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <h2 class="settings-card-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="13.5" cy="6.5" r=".5"/>
                                    <circle cx="17.5" cy="10.5" r=".5"/>
                                    <circle cx="8.5" cy="7.5" r=".5"/>
                                    <circle cx="6.5" cy="11.5" r=".5"/>
                                    <circle cx="12.5" cy="13.5" r=".5"/>
                                    <circle cx="16.5" cy="17.5" r=".5"/>
                                    <circle cx="8.5" cy="16.5" r=".5"/>
                                    <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>
                                </svg>
                                Theme Colors
                            </h2>
                        </div>
                        <div class="settings-card-content">
                            <div class="color-setting">
                                <label class="color-label" for="primary_color">Primary Brand Color</label>
                                <p class="color-description">This color is used for headers, buttons, links, and primary interface elements.</p>
                                <div class="color-input-wrapper">
                                    <input type="color" id="primary_color" name="primary_color" value="<?php echo esc_attr($primary_color); ?>" class="color-input">
                                    <input type="text" id="primary_color_text" value="<?php echo esc_attr($primary_color); ?>" class="color-text-input" placeholder="#201a7c">
                                    <div class="color-preview" style="background: <?php echo esc_attr($primary_color); ?>;">1°</div>
                                </div>
                            </div>

                            <div class="color-setting">
                                <label class="color-label" for="accent_color">Accent Color</label>
                                <p class="color-description">This color is used for hover states, gradients, and secondary accent elements.</p>
                                <div class="color-input-wrapper">
                                    <input type="color" id="accent_color" name="accent_color" value="<?php echo esc_attr($accent_color); ?>" class="color-input">
                                    <input type="text" id="accent_color_text" value="<?php echo esc_attr($accent_color); ?>" class="color-text-input" placeholder="#ab3b43">
                                    <div class="color-preview" style="background: <?php echo esc_attr($accent_color); ?>;">2°</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="submit-section">
                        <button type="submit" name="submit" class="submit-btn">Save Changes</button>
                        <button type="button" class="reset-btn" onclick="resetToDefaults()">Reset to Defaults</button>
                    </div>
                </form>
            </div>

            <script>
            // Sync color picker with text input
            document.getElementById('primary_color').addEventListener('change', function() {
                document.getElementById('primary_color_text').value = this.value;
                document.querySelector('.color-preview[style*="primary"]').style.background = this.value;
            });

            document.getElementById('primary_color_text').addEventListener('input', function() {
                if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                    document.getElementById('primary_color').value = this.value;
                    document.querySelector('.color-preview[style*="primary"]').style.background = this.value;
                }
            });

            document.getElementById('accent_color').addEventListener('change', function() {
                document.getElementById('accent_color_text').value = this.value;
                document.querySelector('.color-preview[style*="accent"]').style.background = this.value;
            });

            document.getElementById('accent_color_text').addEventListener('input', function() {
                if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                    document.getElementById('accent_color').value = this.value;
                    document.querySelector('.color-preview[style*="accent"]').style.background = this.value;
                }
            });

            function resetToDefaults() {
                if (confirm('Are you sure you want to reset to default colors? This will change the primary color to #201a7c and accent color to #ab3b43.')) {
                    document.getElementById('primary_color').value = '#201a7c';
                    document.getElementById('primary_color_text').value = '#201a7c';
                    document.getElementById('accent_color').value = '#ab3b43';
                    document.getElementById('accent_color_text').value = '#ab3b43';
                }
            }
            </script>
        </div>
        <?php
    }

    /**
     * Save settings
     */
    private function save_settings() {
        $primary_color = sanitize_hex_color($_POST['primary_color']);
        $accent_color = sanitize_hex_color($_POST['accent_color']);

        if ($primary_color) {
            update_option('learnpressium_primary_color', $primary_color);
        }

        if ($accent_color) {
            update_option('learnpressium_accent_color', $accent_color);
        }

        // Redirect with success message
        wp_redirect(admin_url('admin.php?page=trainees-settings&updated=true'));
        exit;
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
