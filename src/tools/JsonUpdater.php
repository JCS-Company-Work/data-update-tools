<?php

namespace DataUpdateTools\Tools;

class JsonUpdater {

    /**
     * JSON updater action name.
     */
    private const ACTION = 'json_updater_trigger';

    /**
     * Supported tool types for the JSON updater.
     */
    private const TOOL_TYPES = ['allbrands', 'allpending', 'refresh-all'];

    /**
     * Register the JSON updater tool with WordPress.
     */
    public function register() {
        add_action('init', [$this, 'register_shortcode']);
        add_action('wp_ajax_' . self::ACTION, [$this, 'handle_request']);
    }

    /**
     * Register the JSON updater shortcode with WordPress.
     */
    public function register_shortcode() {
        // Register shortcode
        add_shortcode('json_updater', [$this, 'render_shortcode']);
    }

    /**
     * Render the JSON updater shortcode.
     */
    public function render_shortcode() {

        // Enqueue the JSON updater script
        wp_enqueue_script( 'dupt-json-updater', DUPT_URL . 'assets/json-updater.js', [], DUPT_VERSION, true );

        wp_enqueue_style( 'dupt-tools', DUPT_URL . 'assets/tools.css', [], DUPT_VERSION );

        // Localize script with AJAX configuration
        wp_localize_script( 'dupt-json-updater', 'jsonUpdaterConfig', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'action'  => self::ACTION,
            'nonce'   => wp_create_nonce( self::ACTION ),
        ] );

        return '<div class="json-updater-wrapper">

                    <select name="brand" id="brand-select">
                        <option value="allbrands">All (excluding pending)</option>
                        <option value="allpending">All (including pending)</option>
                        <option value="refresh-all">Refresh all resources</option>
                    </select>
                    <button id="save-json" type="button">Update Tile Data</button>
                    <span class="dupt-spinner"></span>
                    <div class="outcome-message"></div>

                </div>';
    }

    /**
     * Handle the AJAX request and route it through the Resources API.
     */
    public function handle_request() {

        // Check if the request is an AJAX request
        check_ajax_referer( self::ACTION, 'nonce' );

        // Check if the current user has permission to update data.
        if ( ! current_user_can( 'edit_posts' ) && ! in_array( 'erp_hr_manager', wp_get_current_user()->roles, true ) ) {
            wp_send_json_error( [ 'message' => 'You do not have permission to do this.' ], 403 );
        }

        // Validate the tool type parameter
        $tool_type = isset( $_POST['toolType'] ) ? sanitize_text_field( wp_unslash( $_POST['toolType'] ) ) : '';

        // Ensure the tool type is provided
        if ( ! in_array( $tool_type, self::TOOL_TYPES, true ) ) {
            wp_send_json_error( [ 'message' => 'Unsupported update type.' ], 400 );
        }

        // Include the Resources API for handling the update request
        require_once ABSPATH . 'office/shared/ResourcesApi.php';

        // Attempt to update the resource using the Resources API
        try {
            if ( $tool_type === 'refresh-all' ) {
                ( new \ResourcesApi() )->refreshAllResources();

                wp_send_json_success( [ 'message' => 'All resources refreshed successfully.' ] );
            }

            ( new \ResourcesApi() )->updateResource( 'resources/combined-data/' . $tool_type, 'combined-data', '', 'combined-data' );
        } catch ( \Throwable $e ) {
            error_log( 'JSON updater failed: ' . $e->__toString() );
            wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
        }

        // Respond with a success message if the update was successful
        wp_send_json_success( [ 'message' => 'Tile data updated successfully.' ] );

    }

}