<?php

namespace DataUpdateTools\Tests;

use Brain\Monkey\Functions;
use DataUpdateTools\DataUpdater;

class ToolsTest extends ToolTestCase {

    public function test_data_updater_registers_ajax_action_and_shortcode(): void {
        $updater = new DataUpdater();
        $updater->register();

        $this->assertNotFalse(has_action('wp_ajax_data_updater_trigger', [$updater, 'handle_request']));
        $this->assertNotFalse(has_action('init', [$updater, 'register_shortcode']));

        Functions\expect('add_shortcode')->once()->with('data_updater', [$updater, 'render_shortcode']);
        $updater->register_shortcode();
    }

    public function test_shortcode_enqueues_assets_and_returns_markup(): void {
        Functions\expect('wp_enqueue_script')->twice();
        Functions\expect('wp_enqueue_style')->once()->with('dupt-tools', \Mockery::type('string'), [], 'test');
        Functions\expect('wp_localize_script')->once()->with(
            \Mockery::type('string'),
            \Mockery::type('string'),
            \Mockery::on(fn($config) => $config['action'] === 'data_updater_trigger' && $config['nonce'] === 'nonce')
        );

        $html = (new DataUpdater())->render_shortcode();

        $this->assertStringContainsString('Update All Resources', $html);
        $this->assertStringContainsString('dupt-spinner', $html);
        $this->assertStringContainsString('outcome-message', $html);
    }

    public function test_rejects_user_without_permission(): void {
        Functions\when('current_user_can')->justReturn(false);

        $response = $this->respond([new DataUpdater(), 'handle_request']);

        $this->assertFalse($response->success);
        $this->assertSame(403, $response->status);
    }

    public function test_allows_hr_manager_without_edit_posts(): void {
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('wp_get_current_user')->justReturn((object)['roles' => ['erp_hr_manager']]);
        \ResourcesApi::$events = [
            ['event' => 'resource_updated', 'resource' => 'combined-data'],
            ['event' => 'completed', 'message' => 'All resources updated.'],
        ];

        $output = '';
        ob_start(static function ($chunk) use (&$output) {
            $output .= $chunk;
            return '';
        });
        try {
            $this->respond([new DataUpdater(), 'handle_request']);
        } finally {
            ob_end_flush();
        }

        $lines = array_filter(explode("\n", trim($output)));
        $this->assertCount(2, $lines);
    }

    public function test_preserves_editor_access(): void {
        Functions\when('current_user_can')->alias(fn($capability) => $capability === 'edit_posts');
        \ResourcesApi::$events = [
            ['event' => 'resource_updated', 'resource' => 'combined-data'],
            ['event' => 'completed', 'message' => 'All resources updated.'],
        ];

        $output = '';
        ob_start(static function ($chunk) use (&$output) {
            $output .= $chunk;
            return '';
        });
        try {
            $this->respond([new DataUpdater(), 'handle_request']);
        } finally {
            ob_end_flush();
        }

        $lines = array_filter(explode("\n", trim($output)));
        $this->assertCount(2, $lines);
    }

    public function test_checks_nonce_with_the_action(): void {
        $checked = [];
        Functions\when('check_ajax_referer')->alias(function (...$args) use (&$checked) {
            $checked[] = $args;
            return 1;
        });

        $this->respond([new DataUpdater(), 'handle_request']);

        $this->assertSame([['data_updater_trigger', 'nonce']], $checked);
    }

    public function test_streams_all_resources_and_purge_result(): void {
        \ResourcesApi::$events = [
            ['event' => 'resource_updated', 'resource' => 'combined-data/allbrands'],
            ['event' => 'resource_updated', 'resource' => 'product-finder'],
            ['event' => 'completed', 'message' => 'All resources updated.'],
        ];

        $output = '';
        ob_start(static function ($chunk) use (&$output) {
            $output .= $chunk;
            return '';
        });
        try {
            $this->respond([new DataUpdater(), 'handle_request']);
        } finally {
            ob_end_flush();
        }

        $lines = explode("\n", trim($output));
        $events = array_map(fn($line) => json_decode($line, true), array_filter($lines));
        
        $this->assertCount(3, $events);
        $this->assertSame('resource_updated', $events[0]['event']);
        $this->assertSame('resource_updated', $events[1]['event']);
        $this->assertSame('completed', $events[2]['event']);
        $this->assertSame([['refreshAllResources' => true]], \ResourcesApi::$calls);
    }

    public function test_reports_api_failures(): void {
        \ResourcesApi::$throw = new \RuntimeException('API returned HTTP 500');

        $output = '';
        ob_start(static function ($chunk) use (&$output) {
            $output .= $chunk;
            return '';
        });
        try {
            $this->respond([new DataUpdater(), 'handle_request']);
        } finally {
            ob_end_flush();
        }

        $result = json_decode(trim($output), true);
        $this->assertSame('failed', $result['event']);
        $this->assertSame('API returned HTTP 500', $result['message']);
    }

    public function test_purge_message_included_in_completion(): void {
        Functions\when('wp_remote_post')->justReturn([
            'body' => json_encode(['success' => true, 'message' => 'Cache purged for Porcelain Tiles Information Hub'])
        ]);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['success' => true, 'message' => 'Cache purged for Porcelain Tiles Information Hub']));
        
        \ResourcesApi::$events = [
            ['event' => 'resource_updated', 'resource' => 'combined-data'],
            ['event' => 'completed', 'message' => 'All resources updated.'],
        ];

        $output = '';
        ob_start(static function ($chunk) use (&$output) {
            $output .= $chunk;
            return '';
        });
        try {
            $this->respond([new DataUpdater(), 'handle_request']);
        } finally {
            ob_end_flush();
        }

        $lines = array_filter(explode("\n", trim($output)));
        $this->assertCount(2, $lines);
    }
}
