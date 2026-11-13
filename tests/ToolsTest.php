<?php

namespace DataUpdateTools\Tests;

use Brain\Monkey\Functions;
use DataUpdateTools\Plugin;
use DataUpdateTools\Tools\JsonUpdater;
use DataUpdateTools\Tools\ProductFinderUpdate;
use DataUpdateTools\Tools\SampleRequestUpdate;
use DataUpdateTools\Tools\FlorimukUpdate;

class ToolsTest extends ToolTestCase {

    public static function tools(): array {
        return [
            'json updater'          => [ JsonUpdater::class, 'json_updater_trigger', 'json_updater', 'toolType', 'allbrands' ],
            'sample request update' => [ SampleRequestUpdate::class, 'sample_request_update_trigger', 'sample_request_update', 'toolType', 'pt' ],
            'product finder update' => [ ProductFinderUpdate::class, 'product_finder_update_trigger', 'product_finder_update', 'brand', 'porcelaintiles' ],
            'florimuk update'       => [ FlorimukUpdate::class, 'florimuk_update_trigger', 'florimuk_update', 'updateType', 'florimuk' ],
        ];
    }

    /**
     * @dataProvider tools
     */
    public function test_registers_ajax_action_and_shortcode( string $class, string $action, string $shortcode ): void {
        $tool = new $class();
        $tool->register();

        $this->assertNotFalse( has_action( 'wp_ajax_' . $action, [ $tool, 'handle_request' ] ) );
        $this->assertNotFalse( has_action( 'init', [ $tool, 'register_shortcode' ] ) );

        Functions\expect( 'add_shortcode' )->once()->with( $shortcode, [ $tool, 'render_shortcode' ] );
        $tool->register_shortcode();
    }

    public function test_plugin_boot_registers_every_tool(): void {
        ( new Plugin() )->boot();

        foreach ( self::tools() as [ , $action ] ) {
            $this->assertNotFalse( has_action( 'wp_ajax_' . $action ), "$action not registered" );
        }
    }

    /**
     * @dataProvider tools
     */
    public function test_shortcode_enqueues_assets_and_returns_markup( string $class, string $action ): void {
        Functions\expect( 'wp_enqueue_script' )->once();
        Functions\expect( 'wp_enqueue_style' )->once()->with( 'dupt-tools', \Mockery::type( 'string' ), [], 'test' );
        Functions\expect( 'wp_localize_script' )->once()->with(
            \Mockery::type( 'string' ),
            \Mockery::type( 'string' ),
            \Mockery::on( fn( $config ) => $config['action'] === $action && $config['nonce'] === 'nonce' )
        );

        $html = ( new $class() )->render_shortcode();

        $this->assertStringContainsString( '<select', $html );
        $this->assertStringContainsString( 'dupt-spinner', $html );
        $this->assertStringContainsString( 'outcome-message', $html );
    }

    /**
     * @dataProvider tools
     */
    public function test_rejects_user_without_permission( string $class, string $action, string $shortcode, string $field, string $brand ): void {
        Functions\when( 'current_user_can' )->justReturn( false );
        $_POST[ $field ] = $brand;

        $response = $this->respond( [ new $class(), 'handle_request' ] );

        $this->assertFalse( $response->success );
        $this->assertSame( 403, $response->status );
        $this->assertSame( [], \ResourcesApi::$calls );
    }

    /**
     * @dataProvider tools
     */
    public function test_allows_hr_manager_without_edit_posts( string $class, string $action, string $shortcode, string $field, string $brand ): void {
        Functions\when( 'current_user_can' )->justReturn( false );
        Functions\when( 'wp_get_current_user' )->justReturn( (object) [ 'roles' => [ 'erp_hr_manager' ] ] );
        \Samples\Classes\GoogleSheets::$result = 'Update successful';
        $_POST[ $field ] = $brand;

        $response = $this->respond( [ new $class(), 'handle_request' ] );

        $this->assertTrue( $response->success );
    }

    /**
     * @dataProvider tools
     */
    public function test_preserves_editor_access( string $class, string $action, string $shortcode, string $field, string $brand ): void {
        Functions\when( 'current_user_can' )->alias( fn( $capability ) => $capability === 'edit_posts' );
        \Samples\Classes\GoogleSheets::$result = 'Update successful';
        $_POST[ $field ] = $brand;

        $response = $this->respond( [ new $class(), 'handle_request' ] );

        $this->assertTrue( $response->success );
    }

    /**
     * @dataProvider tools
     */
    public function test_checks_nonce_with_the_tool_action( string $class, string $action, string $shortcode, string $field, string $brand ): void {
        $checked = [];
        Functions\when( 'check_ajax_referer' )->alias( function ( ...$args ) use ( &$checked ) {
            $checked[] = $args;
            return 1;
        } );
        $_POST[ $field ] = $brand;

        $this->respond( [ new $class(), 'handle_request' ] );

        $this->assertSame( [ [ $action, 'nonce' ] ], $checked );
    }

    /**
     * @dataProvider tools
     */
    public function test_rejects_unsupported_or_missing_brand( string $class, string $action, string $shortcode, string $field ): void {
        foreach ( [ 'nonsense', '' ] as $value ) {
            $_POST[ $field ] = $value;
            $response        = $this->respond( [ new $class(), 'handle_request' ] );

            $this->assertFalse( $response->success );
            $this->assertSame( 400, $response->status );
        }

        $this->assertSame( [], \ResourcesApi::$calls );
    }

    public function test_json_updater_calls_combined_data_endpoint(): void {
        $_POST['toolType'] = 'allpending';

        $response = $this->respond( [ new JsonUpdater(), 'handle_request' ] );

        $this->assertTrue( $response->success );
        $this->assertSame(
            [ [ 'path' => 'resources/combined-data/allpending', 'resourceType' => 'combined-data', 'brand' => '', 'tokenGroup' => 'combined-data' ] ],
            \ResourcesApi::$calls
        );
    }

    public function test_product_finder_calls_product_finder_endpoint_for_brand(): void {
        $_POST['brand'] = 'tailormade';

        $response = $this->respond( [ new ProductFinderUpdate(), 'handle_request' ] );

        $this->assertTrue( $response->success );
        $this->assertSame(
            [ [ 'path' => 'product-finder/tailormade', 'resourceType' => 'product-finder', 'brand' => 'tailormade', 'tokenGroup' => 'product-finder' ] ],
            \ResourcesApi::$calls
        );
    }

    public function test_florimuk_update_calls_florimuk_endpoint(): void {
        $_POST['updateType'] = 'florimuk';

        $response = $this->respond( [ new FlorimukUpdate(), 'handle_request' ] );

        $this->assertTrue( $response->success );
        $this->assertSame(
            [ [ 'path' => 'florimuk', 'resourceType' => 'florimuk', 'brand' => 'florimuk', 'tokenGroup' => 'florimuk' ] ],
            \ResourcesApi::$calls
        );
    }

    public function test_florimuk_update_can_refresh_all_resources(): void {
        $_POST['updateType'] = 'refresh-all';

        $response = $this->respond( [ new FlorimukUpdate(), 'handle_request' ] );

        $this->assertTrue( $response->success );
        $this->assertSame( 'All resources refreshed successfully.', $response->data['message'] );
        $this->assertSame( [ [ 'refreshAllResources' => true ] ], \ResourcesApi::$calls );
    }

    public function test_florimuk_update_reports_api_failures(): void {
        \ResourcesApi::$throw = new \RuntimeException( 'API returned HTTP 500' );
        $_POST['updateType'] = 'florimuk';

        $response = $this->respond( [ new FlorimukUpdate(), 'handle_request' ] );

        $this->assertFalse( $response->success );
        $this->assertSame( 500, $response->status );
        $this->assertSame( 'API returned HTTP 500', $response->data['message'] );
    }

    public function test_florimuk_refresh_all_reports_api_failures(): void {
        \ResourcesApi::$throw = new \RuntimeException( 'Refresh failed' );
        $_POST['updateType'] = 'refresh-all';

        $response = $this->respond( [ new FlorimukUpdate(), 'handle_request' ] );

        $this->assertFalse( $response->success );
        $this->assertSame( 500, $response->status );
        $this->assertSame( 'Refresh failed', $response->data['message'] );
    }

    public function test_api_failure_is_reported_as_error(): void {
        \ResourcesApi::$throw = new \RuntimeException( 'API returned HTTP 500' );
        $_POST['brand']       = 'porcelaintiles';

        $response = $this->respond( [ new ProductFinderUpdate(), 'handle_request' ] );

        $this->assertFalse( $response->success );
        $this->assertSame( 500, $response->status );
        $this->assertSame( 'API returned HTTP 500', $response->data['message'] );
    }

    public function test_sample_request_runs_update_for_selected_brand(): void {
        \Samples\Classes\GoogleSheets::$calls  = [];
        \Samples\Classes\GoogleSheets::$result = 'Update successful';
        $_POST['toolType']                     = 'florimuk';

        $response = $this->respond( [ new SampleRequestUpdate(), 'handle_request' ] );

        $this->assertTrue( $response->success );
        $this->assertSame( [ 'florimuk' ], \Samples\Classes\GoogleSheets::$calls );
    }

    public function test_sample_request_reports_failure_message(): void {
        \Samples\Classes\GoogleSheets::$result = 'Could not update, please try again. Error: boom';
        $_POST['toolType']                     = 'all';

        $response = $this->respond( [ new SampleRequestUpdate(), 'handle_request' ] );

        $this->assertFalse( $response->success );
        $this->assertSame( 500, $response->status );
        $this->assertStringContainsString( 'boom', $response->data['message'] );
    }
}
