<?php

namespace DataUpdateTools;

class DataUpdater {

    private const ACTION = 'data_updater_trigger';

    /**
     * Register the data updater tool with WordPress.
     */
    public function register() {
        add_action('init', [$this, 'register_shortcode']);
        add_action('wp_ajax_' . self::ACTION, [$this, 'handle_request']);
    }

    /**
     * Register the data updater shortcode.
     */
    public function register_shortcode() {
        add_shortcode('data_updater', [$this, 'render_shortcode']);
    }

    /**
     * Render the data updater shortcode.
     */
    public function render_shortcode() {
        wp_enqueue_script('dupt-refresh-stream', DUPT_URL . 'assets/refresh-stream.js', [], DUPT_VERSION, true);
        wp_enqueue_script('dupt-data-updater', DUPT_URL . 'assets/data-updater.js', ['dupt-refresh-stream'], DUPT_VERSION, true);
        wp_enqueue_style('dupt-tools', DUPT_URL . 'assets/tools.css', [], DUPT_VERSION);

        wp_localize_script('dupt-data-updater', 'dataUpdaterConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action'  => self::ACTION,
            'nonce'   => wp_create_nonce(self::ACTION),
        ]);

        return '<div class="data-updater-wrapper">
                    <button id="update-all" type="button">Update All Resources</button>
                    <span class="dupt-spinner"></span>
                    <div class="outcome-message"></div>
                </div>';
    }

    /**
     * Handle the AJAX request to update all resources.
     */
    public function handle_request() {
        check_ajax_referer(self::ACTION, 'nonce');

        if (!current_user_can('edit_posts') && !in_array('erp_hr_manager', wp_get_current_user()->roles, true)) {
            wp_send_json_error(['message' => 'You do not have permission to do this.'], 403);
        }

        RefreshAll::stream();
    }
}
