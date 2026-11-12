<?php

namespace DataUpdateTools\Tools;

class SampleRequestUpdate {

    /**
     * Sample request update action name.
     */
    private const ACTION = 'sample_request_update_trigger';

    /**
     * Supported brand values for the sample request update.
     */
    private const BRANDS = [ 'all', 'pt', 'pp', 'tm', 'florim', 'florimuk', 'domus', 'emilgroup' ];

    /**
     * Register the tool with WordPress.
     */
    public function register() {
        add_action( 'init', [ $this, 'register_shortcode' ] );
        add_action( 'wp_ajax_' . self::ACTION, [ $this, 'handle_request' ] );
    }

    /**
     * Register the sample request update shortcode.
     */
    public function register_shortcode() {
        add_shortcode( 'sample_request_update', [ $this, 'render_shortcode' ] );
    }

    /**
     * Render the sample request update shortcode.
     */
    public function render_shortcode() {

        wp_enqueue_script( 'dupt-sample-request-update', DUPT_URL . 'assets/sample-request-update.js', [], DUPT_VERSION, true );

        wp_enqueue_style( 'dupt-tools', DUPT_URL . 'assets/tools.css', [], DUPT_VERSION );

        wp_localize_script( 'dupt-sample-request-update', 'sampleRequestUpdateConfig', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'action'  => self::ACTION,
            'nonce'   => wp_create_nonce( self::ACTION ),
        ] );

        return '<div class="sample-request-update-wrapper">

                    <select name="brand" id="sample-brand-select">
                        <option value="" disabled selected>Choose an option</option>
                        <option value="all">All</option>
                        <option value="pt">Porcelain Tiles</option>
                        <option value="pp">Platform Plus</option>
                        <option value="tm">Tailor-Made</option>
                        <option value="florim">Florim</option>
                        <option value="florimuk">Florim UK</option>
                        <option value="domus">Living Ceramics</option>
                        <option value="emilgroup">Emilgroup</option>
                    </select>
                    <button id="sample-save-json" type="button">Update Tile Data</button>
                    <span class="dupt-spinner"></span>
                    <div class="outcome-message update-result"></div>

                </div>';
    }

    /**
     * Handle the AJAX request by running the samples tool data update.
     */
    public function handle_request() {

        check_ajax_referer( self::ACTION, 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'You do not have permission to do this.' ], 403 );
        }

        $brand = isset( $_POST['toolType'] ) ? sanitize_text_field( wp_unslash( $_POST['toolType'] ) ) : '';

        if ( ! in_array( $brand, self::BRANDS, true ) ) {
            wp_send_json_error( [ 'message' => 'Unsupported brand.' ], 400 );
        }

        // The samples tool's composer autoload uses an absolute path from another machine, so map the namespace here.
        $samples_src    = ABSPATH . 'systems/tools/samples/src/';
        $samples_vendor = ABSPATH . 'systems/tools/samples/vendor/autoload.php';

        if ( file_exists( $samples_vendor ) ) {
            require_once $samples_vendor;
        }

        spl_autoload_register( static function ( $class ) use ( $samples_src ) {
            if ( strpos( $class, 'Samples\\' ) !== 0 ) {
                return;
            }
            $file = $samples_src . str_replace( '\\', '/', substr( $class, 8 ) ) . '.php';
            if ( file_exists( $file ) ) {
                require_once $file;
            }
        } );

        try {
            $result = ( new \Samples\Classes\GoogleSheets() )->createJSON( $brand );
        } catch ( \Throwable $e ) {
            error_log( 'Sample request update failed: ' . $e->__toString() );
            wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
        }

        if ( $result !== 'Update successful' ) {
            wp_send_json_error( [ 'message' => (string) $result ], 500 );
        }

        wp_send_json_success( [ 'message' => 'Sample request data updated successfully.' ] );
    }

}
