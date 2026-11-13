<?php

namespace DataUpdateTools\Tools;

class FlorimukUpdate {

    /**
     * Action name for the AJAX request.
     */
    private const ACTION = 'florimuk_update_trigger';

    /**
     * Supported update types.
     */
    private const UPDATE_TYPES = [ 'florimuk', 'refresh-all' ];

    /**
     * Register hooks for the plugin.
     */
    public function register() {
        add_action( 'init', [ $this, 'register_shortcode' ] );
        add_action( 'wp_ajax_' . self::ACTION, [ $this, 'handle_request' ] );
    }

    /**
     * Register the shortcode for the plugin.
     */
    public function register_shortcode() {
        add_shortcode( 'florimuk_update', [ $this, 'render_shortcode' ] );
    }

    /**
     * Render the shortcode for the plugin.
     */
    public function render_shortcode() {

        // Enqueue the necessary scripts and styles for the plugin.
        wp_enqueue_script( 'dupt-florimuk-update', DUPT_URL . 'assets/florimuk-update.js', [], DUPT_VERSION, true );
        
        // Enqueue the necessary styles for the plugin.
        wp_enqueue_style( 'dupt-tools', DUPT_URL . 'assets/tools.css', [], DUPT_VERSION );

        // Pass configuration to the JavaScript file.
        wp_localize_script( 'dupt-florimuk-update', 'florimukUpdateConfig', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'action'  => self::ACTION,
            'nonce'   => wp_create_nonce( self::ACTION ),
        ] );

        // Render the HTML for the plugin interface.
        return '<div class="florimuk-update-wrapper">
                    <select name="updateType" id="florimuk-update-select">
                        <option value="florimuk">Florim UK</option>
                        <option value="refresh-all">Refresh all resources</option>
                    </select>
                    <button id="florimuk-save-json" type="button">Update Tile Data</button>
                    <span class="dupt-spinner"></span>
                    <div class="outcome-message"></div>
                </div>';
    }

    /**
     * Handle the AJAX request for the plugin.
     */
    public function handle_request() {

        // Verify the nonce for security.
        check_ajax_referer( self::ACTION, 'nonce' );

        // Check if the current user has the required capability.
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'You do not have permission to do this.' ], 403 );
        }

        // Get the update type from the request and validate it.
        $update_type = isset( $_POST['updateType'] ) ? sanitize_text_field( wp_unslash( $_POST['updateType'] ) ) : '';
        if ( ! in_array( $update_type, self::UPDATE_TYPES, true ) ) {
            wp_send_json_error( [ 'message' => 'Unsupported update type.' ], 400 );
        }

        // Perform the requested update based on the update type.
        try {
            require_once ABSPATH . 'office/shared/ResourcesApi.php';

            if ( $update_type === 'refresh-all' ) {
                ( new \ResourcesApi() )->refreshAllResources();
            } else {
                ( new \ResourcesApi() )->updateResource( 'florimuk', 'florimuk', 'florimuk', 'florimuk' );
            }
        } catch ( \Throwable $e ) {
            error_log( 'Florim UK update failed: ' . $e->__toString() );
            wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
        }

        $message = $update_type === 'refresh-all'
            ? 'All resources refreshed successfully.'
            : 'Florim UK data updated successfully.';
        wp_send_json_success( [ 'message' => $message ] );
    }
}
