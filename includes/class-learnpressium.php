<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 */
class Learnpressium {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct() {
        if (defined('LEARNPRESSIUM_VERSION')) {
            $this->version = LEARNPRESSIUM_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'learnpressium';

        $this->load_dependencies();
        $this->define_admin_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        
        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once LEARNPRESSIUM_PLUGIN_DIR . 'includes/class-learnpressium-loader.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once LEARNPRESSIUM_PLUGIN_DIR . 'includes/class-learnpressium-admin.php';

        /**
         * Load all module classes
         */
        $this->load_modules();

        $this->loader = new Learnpressium_Loader();
    }

    /**
     * Load all plugin modules
     */
    private function load_modules() {
        // Load Trainees Module
        require_once LEARNPRESSIUM_PLUGIN_DIR . 'includes/modules/trainees/class-trainees-module.php';
        require_once LEARNPRESSIUM_PLUGIN_DIR . 'includes/modules/trainees/class-trainees-admin.php';
        require_once LEARNPRESSIUM_PLUGIN_DIR . 'includes/modules/trainees/class-trainees-export.php';
        require_once LEARNPRESSIUM_PLUGIN_DIR . 'includes/modules/trainees/class-trainees-profile.php';

        // Load Enrollment Module
        require_once LEARNPRESSIUM_PLUGIN_DIR . 'includes/modules/enrollment/class-enrollment-module.php';
        require_once LEARNPRESSIUM_PLUGIN_DIR . 'includes/modules/enrollment/class-enrollment-manager.php';
        require_once LEARNPRESSIUM_PLUGIN_DIR . 'includes/modules/enrollment/class-enrollment-access-controller.php';
        require_once LEARNPRESSIUM_PLUGIN_DIR . 'includes/modules/enrollment/class-enrollment-database.php';
        require_once LEARNPRESSIUM_PLUGIN_DIR . 'includes/modules/enrollment/class-enrollment-admin.php';
        require_once LEARNPRESSIUM_PLUGIN_DIR . 'includes/modules/enrollment/class-enrollment-tools-integration.php';
        require_once LEARNPRESSIUM_PLUGIN_DIR . 'includes/modules/enrollment/class-enrollment-profile-integration.php';
        require_once LEARNPRESSIUM_PLUGIN_DIR . 'includes/modules/enrollment/class-enrollment-frontend.php';
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     */
    private function define_admin_hooks() {
        $plugin_admin = new Learnpressium_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

        // Initialize modules
        $trainees_module = new Trainees_Module();
        $trainees_module->init();

        $enrollment_module = new Enrollment_Module();
        $enrollment_module->init();
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }
}
