<?php
/**
 * Plugin Name: Learnpressium
 * Plugin URI: https://yourwebsite.com/learnpressium
 * Description: A comprehensive plugin to extend LearnPress functionality with advanced trainee management, reporting, and additional features.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: learnpressium
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * Network: false
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 */
define('LEARNPRESSIUM_VERSION', '1.0.0');

/**
 * Plugin directory path.
 */
define('LEARNPRESSIUM_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Plugin directory URL.
 */
define('LEARNPRESSIUM_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Plugin file path.
 */
define('LEARNPRESSIUM_PLUGIN_FILE', __FILE__);

/**
 * The code that runs during plugin activation.
 */
function activate_learnpressium() {
    require_once LEARNPRESSIUM_PLUGIN_DIR . 'includes/class-learnpressium-activator.php';
    Learnpressium_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_learnpressium() {
    require_once LEARNPRESSIUM_PLUGIN_DIR . 'includes/class-learnpressium-deactivator.php';
    Learnpressium_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_learnpressium');
register_deactivation_hook(__FILE__, 'deactivate_learnpressium');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require LEARNPRESSIUM_PLUGIN_DIR . 'includes/class-learnpressium.php';

/**
 * Begins execution of the plugin.
 */
function run_learnpressium() {
    $plugin = new Learnpressium();
    $plugin->run();
}

/**
 * Check if LearnPress is active before running the plugin
 */
function learnpressium_check_dependencies() {
    if (!is_plugin_active('learnpress/learnpress.php')) {
        add_action('admin_notices', 'learnpressium_missing_learnpress_notice');
        return false;
    }
    return true;
}

/**
 * Display admin notice if LearnPress is not active
 */
function learnpressium_missing_learnpress_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e('Learnpressium', 'learnpressium'); ?></strong>
            <?php esc_html_e('requires LearnPress plugin to be installed and activated.', 'learnpressium'); ?>
        </p>
    </div>
    <?php
}

/**
 * Initialize the plugin after all plugins are loaded
 */
function learnpressium_init() {
    if (learnpressium_check_dependencies()) {
        run_learnpressium();
    }
}

add_action('plugins_loaded', 'learnpressium_init');
