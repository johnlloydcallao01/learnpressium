<?php

/**
 * The Trainees Scripts Handler
 *
 * @package    Learnpressium
 * @subpackage Learnpressium/includes/modules/trainees
 */

/**
 * The Trainees Scripts class.
 *
 * Handles JavaScript and CSS enqueuing for trainees functionality.
 */
class Trainees_Scripts {

    /**
     * Enqueue scripts and styles for trainees admin page
     */
    public function enqueue_scripts($hook) {
        if ('toplevel_page_trainees' !== $hook) {
            return;
        }

        // Enqueue trainees admin JavaScript
        wp_enqueue_script(
            'learnpressium-trainees-admin',
            LEARNPRESSIUM_PLUGIN_URL . 'admin/js/trainees-admin.js',
            array('jquery'),
            LEARNPRESSIUM_VERSION,
            true
        );
    }
}
