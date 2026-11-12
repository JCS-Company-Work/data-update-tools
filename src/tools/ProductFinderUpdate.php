<?php

namespace DataUpdateTools\Tools;

class ProductFinderUpdate {

    /**
     * Product finder update action name.
     */
    private const ACTION = 'product_finder_update_trigger';

    /**
     * Supported brands for the product finder update.
     */
    private const BRANDS = [ 'porcelaintiles', 'tailormade' ];

    /**
     * Register the tool with WordPress.
     */
    public function register() {
        add_action( 'init', [ $this, 'register_shortcode' ] );
        add_action( 'wp_ajax_' . self::ACTION, [ $this, 'handle_request' ] );
    }

    /**
     * Register the product finder update shortcode.
     */
    public function register_shortcode() {
        add_shortcode( 'product_finder_update', [ $this, 'render_shortcode' ] );
    }

    /**
     * Render the product finder update shortcode.
     */
    public function render_shortcode() {

        wp_enqueue_script( 'dupt-product-finder-update', DUPT_URL . 'assets/product-finder-update.js', [], DUPT_VERSION, true );
        wp_enqueue_style( 'dupt-tools', DUPT_URL . 'assets/tools.css', [], DUPT_VERSION );

        wp_localize_script( 'dupt-product-finder-update', 'productFinderUpdateConfig', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'action'  => self::ACTION,
            'nonce'   => wp_create_nonce( self::ACTION ),
        ] );

        return '<div class="product-finder-update-wrapper">

                    <select name="brand" id="product-finder-brand-select">
                        <option value="porcelaintiles">Porcelain Tiles</option>
                        <option value="tailormade">TailorMade</option>
                    </select>
                    <button id="product-finder-save-json" type="button">Update Tile Filter Data</button>
                    <span class="dupt-spinner"></span>
                    <div class="outcome-message"></div>

                </div>';
    }

    /**
     * Handle the AJAX request by asking the Resources API to regenerate the product finder data.
     */
    public function handle_request() {

        check_ajax_referer( self::ACTION, 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'You do not have permission to do this.' ], 403 );
        }

        $brand = isset( $_POST['brand'] ) ? sanitize_text_field( wp_unslash( $_POST['brand'] ) ) : '';

        if ( ! in_array( $brand, self::BRANDS, true ) ) {
            wp_send_json_error( [ 'message' => 'Unsupported brand.' ], 400 );
        }

        require_once ABSPATH . 'office/shared/ResourcesApi.php';

        try {

            ( new \ResourcesApi() )->updateResource( 'product-finder/' . $brand, 'product-finder', $brand, 'product-finder' );

        } catch ( \Throwable $e ) {
            error_log( 'Product finder update failed: ' . $e->__toString() );
            wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
        }

        wp_send_json_success( [ 'message' => 'Product finder data updated successfully.' ] );
    }

}
