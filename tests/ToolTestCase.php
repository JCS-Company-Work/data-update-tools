<?php

namespace DataUpdateTools\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

abstract class ToolTestCase extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        \ResourcesApi::$calls = [];
        \ResourcesApi::$throw = null;
        $_POST = [];
        ini_set( 'error_log', '/dev/null' );

        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'admin_url' )->returnArg();
        Functions\when( 'wp_create_nonce' )->justReturn( 'nonce' );
        Functions\when( 'check_ajax_referer' )->justReturn( 1 );
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'wp_send_json_success' )->alias(
            fn( $data = [], $status = 200 ) => throw new JsonResponse( true, $data, $status )
        );
        Functions\when( 'wp_send_json_error' )->alias(
            fn( $data = [], $status = 400 ) => throw new JsonResponse( false, $data, $status )
        );
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Run a handler and return the JSON response it produced.
     */
    protected function respond( callable $handler ): JsonResponse {
        try {
            $handler();
        } catch ( JsonResponse $response ) {
            return $response;
        }

        $this->fail( 'Handler did not send a JSON response.' );
    }
}
