<?php

class Honeybadger_Settings_Test extends WP_UnitTestCase {

    private $adminUserId;

    public function set_up(): void {
        parent::set_up();

        // Be admin for admin area hooks and capability checks.
        $this->adminUserId = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $this->adminUserId );

        // Use an admin screen context so admin hooks behave as expected.
        if ( function_exists( 'set_current_screen' ) ) {
            set_current_screen( 'options-general' );
        }
    }

    public function tear_down(): void {
        // need to manually restore error and exception handlers that are registered by the honeybadger sdk
        restore_error_handler();
        restore_exception_handler();

        wp_set_current_user( 0 );

        parent::tear_down();
    }

    public function test_settings_page_renders_without_errors() {
        // The settings page callback outputs a form with sections/fields.
        // Ensure settings have been registered via admin_init.
        $settings = new HBAPP_HoneybadgerSettings();

        // Register settings/sections/fields without triggering global WP header-sending hooks.
        $settings->add_admin_menu();
        $settings->settings_init();

        ob_start();
        // Now render the options page.
        $settings->options_page();
        $output = ob_get_clean();

        $this->assertNotEmpty( $output, 'Expected settings page to produce output.' );
        $this->assertStringContainsString( 'Honeybadger Settings', $output );
        $this->assertStringContainsString( "name='hbapp_honeybadger_settings[hbapp_honeybadger_php_enabled]'", $output );
        $this->assertStringContainsString( "name='hbapp_honeybadger_settings[hbapp_honeybadger_php_api_key]'", $output );
        $this->assertStringContainsString( "name='hbapp_honeybadger_settings[hbapp_honeybadger_php_report_non_fatal]'", $output );
        $this->assertStringContainsString( "name='hbapp_honeybadger_settings[hbapp_honeybadger_php_capture_deprecations]'", $output );
    }

    /**
     * Test that when hbapp_honeybadger_php_send_test_notification is set, a test notification is sent
     * to Honeybadger and then the flag is reset to 0.
     */
    public function test_enabling_php_test_notification_triggers_and_resets_flag() {
        // Arrange: turn on PHP reporting, set an API key, and enable the test notification flag.
        update_option( 'hbapp_honeybadger_settings', [
            'hbapp_honeybadger_php_enabled' => 1,
            'hbapp_honeybadger_php_api_key' => 'dummy-key-for-tests',
            'hbapp_honeybadger_php_send_test_notification' => 1,
            // js defaults
            'hbapp_honeybadger_js_enabled' => 0,
            'hbapp_honeybadger_js_api_key' => '',
            'hbapp_honeybadger_js_send_test_notification' => 0,
        ] );

        // Install a mock Guzzle client so no real HTTP requests are made by Honeybadger
        // and capture request history for assertions.
        $history = [];
        add_filter('hbapp_honeybadger_http_client', function($client, $config) use (&$history) {
            $mock = new \GuzzleHttp\Handler\MockHandler([
                new \GuzzleHttp\Psr7\Response(201, [], json_encode(['id' => 'test']))
            ]);
            $handlerStack = \GuzzleHttp\HandlerStack::create($mock);

            // Record all requests in $history so we can assert they were made.
            $historyMiddleware = \GuzzleHttp\Middleware::history($history);
            $handlerStack->push($historyMiddleware);

            return new \GuzzleHttp\Client([
                'handler' => $handlerStack,
                'base_uri' => $config['endpoint'] ?? \Honeybadger\Honeybadger::API_URL
            ]);
        }, 10, 2);

        // Act: boot a new plugin instance so it reloads settings and runs the notify path.
        $plugin = new HBAPP_Honeybadger();
        $plugin->boot();

        // Assert: the flag should be reset to 0, which only happens after notify path runs.
        $opts = get_option( 'hbapp_honeybadger_settings' );
        $this->assertIsArray( $opts );
        $this->assertArrayHasKey( 'hbapp_honeybadger_php_send_test_notification', $opts );
        $this->assertSame( 0, $opts['hbapp_honeybadger_php_send_test_notification'] );
        $this->assertSame( 1, $opts['hbapp_honeybadger_php_enabled'] );
        $this->assertSame( 'dummy-key-for-tests', $opts['hbapp_honeybadger_php_api_key'] );

        // Assert: the mock HTTP client was called to send the test notification.
        $this->assertCount( 1, $history, 'Expected at least one HTTP request to Honeybadger.' );
        $transaction = $history[0];
        $this->assertArrayHasKey( 'request', $transaction );
        $request = $transaction['request'];
        $this->assertSame( 'POST', $request->getMethod() );
        $this->assertSame( '/v1/notices', $request->getUri()->getPath() );
    }

    /**
     * Test that the hbapp_honeybadger_js_send_test_notification setting is reset to 0.
     * Note that the HTTP request won't be executed because we are not in a real browser to execute
     * the javascript code. Hence, we only assert that the js scripts are "queued".
     */
    public function test_enabling_js_test_notification_enqueues_and_resets_flag() {
        // Arrange
        update_option( 'hbapp_honeybadger_settings', [
            'hbapp_honeybadger_js_enabled' => 1,
            'hbapp_honeybadger_js_api_key' => 'dummy-js-key',
            'hbapp_honeybadger_js_send_test_notification' => 1,
            // php defaults
            'hbapp_honeybadger_php_enabled' => 0,
            'hbapp_honeybadger_php_api_key' => '',
            'hbapp_honeybadger_php_send_test_notification' => 0,
        ] );
        $plugin = new HBAPP_Honeybadger();
        $plugin->boot();

        // Act
        do_action( 'wp_enqueue_scripts' );

        // Assert
        $this->assertTrue( wp_script_is( 'hbapp_honeybadger_js', 'enqueued' ) );

        $opts = get_option( 'hbapp_honeybadger_settings' );
        $this->assertSame( 1, $opts['hbapp_honeybadger_js_enabled'] );
        // Flag should be reset after enqueue added the inline notify() call.
        $this->assertSame( 0, $opts['hbapp_honeybadger_js_send_test_notification'] );
    }

    /**
     * When capture_deprecations is enabled, E_USER_DEPRECATED should be reported.
     * We use E_USER_DEPRECATED for portability across PHP versions in CI.
     */
    public function test_deprecation_reported_when_capture_deprecations_enabled() {
        update_option( 'hbapp_honeybadger_settings', [
            'hbapp_honeybadger_php_enabled' => 1,
            'hbapp_honeybadger_php_api_key' => 'dummy-key',
            'hbapp_honeybadger_php_report_non_fatal' => 0, // doesn't matter for deprecations
            'hbapp_honeybadger_php_capture_deprecations' => 1,
            // js defaults
            'hbapp_honeybadger_js_enabled' => 0,
            'hbapp_honeybadger_js_api_key' => '',
            'hbapp_honeybadger_js_send_test_notification' => 0,
        ] );

        // Ensure deprecations are reported by PHP
        $prev = error_reporting();
        error_reporting( E_ALL );

        $history = [];
        add_filter('hbapp_honeybadger_http_client', function(?\GuzzleHttp\Client $client, array $config) use (&$history) {
            $mock = new \GuzzleHttp\Handler\MockHandler([
                new \GuzzleHttp\Psr7\Response(201, [], json_encode(['id' => 'test']))
            ]);
            $handlerStack = \GuzzleHttp\HandlerStack::create($mock);

            // Record all requests in $history so we can assert they were made.
            $historyMiddleware = \GuzzleHttp\Middleware::history($history);
            $handlerStack->push($historyMiddleware);

            return new \GuzzleHttp\Client([
                'handler' => $handlerStack,
                'base_uri' => $config['endpoint'] ?? \Honeybadger\Honeybadger::API_URL
            ]);
        }, 10, 2);
        $plugin = new HBAPP_Honeybadger();
        $plugin->boot();

        // Trigger a user deprecation (portable across PHP versions)
        trigger_error( 'Test user deprecation', E_USER_DEPRECATED );

        // Restore error_reporting
        error_reporting( $prev );

        $this->assertCount( 1, $history, 'Expected a deprecation to be reported to Honeybadger.' );
        $this->assertSame( 'POST', $history[0]['request']->getMethod() );
        $this->assertSame( '/v1/notices', $history[0]['request']->getUri()->getPath() );
    }

    /**
     * When capture_deprecations is disabled, E_USER_DEPRECATED should NOT be reported.
     */
    public function test_deprecation_not_reported_when_capture_deprecations_disabled() {
        update_option( 'hbapp_honeybadger_settings', [
            'hbapp_honeybadger_php_enabled' => 1,
            'hbapp_honeybadger_php_api_key' => 'dummy-key',
            'hbapp_honeybadger_php_report_non_fatal' => 1, // irrelevant here
            'hbapp_honeybadger_php_capture_deprecations' => 0,
        ] );

        $prev = error_reporting();
        error_reporting( E_ALL );

        $history = [];
        add_filter( 'hbapp_honeybadger_http_client', function( $client, $config ) use ( &$history ) {
            $mock = new \GuzzleHttp\Handler\MockHandler([
            ]);
            $stack = \GuzzleHttp\HandlerStack::create( $mock );
            $stack->push( \GuzzleHttp\Middleware::history( $history ) );
            return new \GuzzleHttp\Client([ 'handler' => $stack, 'base_uri' => $config['endpoint'] ?? \Honeybadger\Honeybadger::API_URL ]);
        }, 10, 2 );

        $plugin = new HBAPP_Honeybadger();
        $plugin->boot();

        trigger_error( 'Test user deprecation', E_USER_DEPRECATED );

        error_reporting( $prev );

        $this->assertCount( 0, $history, 'Did not expect deprecation to be reported when capture_deprecations is disabled.' );
    }

    /**
     * When php_report_non_fatal is enabled, E_USER_WARNING should be reported.
     */
    public function test_warning_reported_when_non_fatal_enabled() {
        update_option( 'hbapp_honeybadger_settings', [
            'hbapp_honeybadger_php_enabled' => 1,
            'hbapp_honeybadger_php_api_key' => 'dummy-key',
            'hbapp_honeybadger_php_send_test_notification' => 0,
            'hbapp_honeybadger_php_report_non_fatal' => 1,
            'hbapp_honeybadger_php_capture_deprecations' => 0,
            'hbapp_honeybadger_js_send_test_notification' => 0,
        ] );

        $prev = error_reporting();
        error_reporting( E_ALL );

        $history = [];
        add_filter('hbapp_honeybadger_http_client', function(?\GuzzleHttp\Client $client, array $config) use (&$history) {
            $mock = new \GuzzleHttp\Handler\MockHandler([
                new \GuzzleHttp\Psr7\Response(201, [], json_encode(['id' => 'test']))
            ]);
            $handlerStack = \GuzzleHttp\HandlerStack::create($mock);

            // Record all requests in $history so we can assert they were made.
            $historyMiddleware = \GuzzleHttp\Middleware::history($history);
            $handlerStack->push($historyMiddleware);

            return new \GuzzleHttp\Client([
                'handler' => $handlerStack,
                'base_uri' => $config['endpoint'] ?? \Honeybadger\Honeybadger::API_URL
            ]);
        }, 10, 2);
        $plugin = new HBAPP_Honeybadger();
        $plugin->boot();

        trigger_error( 'Test user warning: report non-fatal is true', E_USER_WARNING );

        error_reporting( $prev );

        $this->assertCount( 1, $history, 'Expected a single warning to be reported to Honeybadger.' );
        $this->assertSame( 'POST', $history[0]['request']->getMethod() );
        $this->assertSame( '/v1/notices', $history[0]['request']->getUri()->getPath() );
    }

    /**
     * When php_report_non_fatal is disabled, E_USER_WARNING should NOT be reported.
     */
    public function test_warning_not_reported_when_non_fatal_disabled() {
        update_option( 'hbapp_honeybadger_settings', [
            'hbapp_honeybadger_php_enabled' => 1,
            'hbapp_honeybadger_php_api_key' => 'dummy-key',
            'hbapp_honeybadger_php_report_non_fatal' => 0,
            'hbapp_honeybadger_php_send_test_notification' => 0,
            'hbapp_honeybadger_php_capture_deprecations' => 0,
            'hbapp_honeybadger_js_send_test_notification' => 0,
        ] );

        $prev = error_reporting();
        error_reporting( E_ALL );

        $history = [];
        add_filter('hbapp_honeybadger_http_client', function(?\GuzzleHttp\Client $client, array $config) use (&$history) {
            $mock = new \GuzzleHttp\Handler\MockHandler([
            ]);
            $handlerStack = \GuzzleHttp\HandlerStack::create($mock);

            // Record all requests in $history so we can assert they were made.
            $historyMiddleware = \GuzzleHttp\Middleware::history($history);
            $handlerStack->push($historyMiddleware);

            return new \GuzzleHttp\Client([
                'handler' => $handlerStack,
                'base_uri' => $config['endpoint'] ?? \Honeybadger\Honeybadger::API_URL
            ]);
        }, 10, 2);
        $plugin = new HBAPP_Honeybadger();
        $plugin->boot();

        trigger_error( 'Test user warning: report non-fatal is false', E_USER_WARNING );

        error_reporting( $prev );

        $this->assertCount( 0, $history, 'Did not expect a warning to be reported when non-fatal reporting is disabled.' );
    }

    public function test_default_endpoint_used_when_setting_empty() {
        update_option('hbapp_honeybadger_settings', [
            'hbapp_honeybadger_php_enabled' => 1,
            'hbapp_honeybadger_php_api_key' => 'dummy-key',
            'hbapp_honeybadger_endpoint' => '',
            'hbapp_honeybadger_app_endpoint' => '',
            'hbapp_honeybadger_js_enabled' => 0,
            'hbapp_honeybadger_js_api_key' => '',
        ]);

        $captured = [];
        add_filter('hbapp_honeybadger_http_client', function($client, $config) use (&$captured) {
            $captured = $config;
            $mock = new \GuzzleHttp\Handler\MockHandler([]);
            $stack = \GuzzleHttp\HandlerStack::create($mock);
            return new \GuzzleHttp\Client(['handler' => $stack, 'base_uri' => $config['endpoint'] ?? \Honeybadger\Honeybadger::API_URL]);
        }, 10, 2);

        $plugin = new HBAPP_Honeybadger();
        $plugin->boot();

        $this->assertArrayHasKey('endpoint', $captured);
        $this->assertArrayHasKey('app_endpoint', $captured);
        $this->assertSame(\Honeybadger\Honeybadger::API_URL, $captured['endpoint']);
        $this->assertSame(\Honeybadger\Honeybadger::APP_URL, $captured['app_endpoint']);
    }

    public function test_custom_endpoint_used_when_setting_has_value() {
        $custom = 'https://example.test/api';
        update_option('hbapp_honeybadger_settings', [
            'hbapp_honeybadger_php_enabled' => 1,
            'hbapp_honeybadger_php_api_key' => 'dummy-key',
            'hbapp_honeybadger_endpoint' => $custom,
            'hbapp_honeybadger_js_enabled' => 0,
        ]);

        $captured = [];
        add_filter('hbapp_honeybadger_http_client', function($client, $config) use (&$captured) {
            $captured = $config;
            $mock = new \GuzzleHttp\Handler\MockHandler([]);
            $stack = \GuzzleHttp\HandlerStack::create($mock);
            return new \GuzzleHttp\Client(['handler' => $stack, 'base_uri' => $config['endpoint'] ?? \Honeybadger\Honeybadger::API_URL]);
        }, 10, 2);

        $plugin = new HBAPP_Honeybadger();
        $plugin->boot();

        $this->assertSame($custom, $captured['endpoint']);
    }

    public function test_default_app_endpoint_used_when_setting_empty() {
        update_option('hbapp_honeybadger_settings', [
            'hbapp_honeybadger_php_enabled' => 1,
            'hbapp_honeybadger_php_api_key' => 'dummy-key',
            'hbapp_honeybadger_app_endpoint' => '',
            'hbapp_honeybadger_js_enabled' => 0,
        ]);

        $captured = [];
        add_filter('hbapp_honeybadger_http_client', function($client, $config) use (&$captured) {
            $captured = $config;
            $mock = new \GuzzleHttp\Handler\MockHandler([]);
            $stack = \GuzzleHttp\HandlerStack::create($mock);
            return new \GuzzleHttp\Client(['handler' => $stack, 'base_uri' => $config['endpoint'] ?? \Honeybadger\Honeybadger::API_URL]);
        }, 10, 2);

        $plugin = new HBAPP_Honeybadger();
        $plugin->boot();

        $this->assertSame(\Honeybadger\Honeybadger::APP_URL, $captured['app_endpoint']);
    }

    public function test_custom_app_endpoint_used_when_setting_has_value() {
        $custom = 'https://app.example.test/';
        update_option('hbapp_honeybadger_settings', [
            'hbapp_honeybadger_php_enabled' => 1,
            'hbapp_honeybadger_php_api_key' => 'dummy-key',
            'hbapp_honeybadger_app_endpoint' => $custom,
            'hbapp_honeybadger_js_enabled' => 0,
        ]);

        $captured = [];
        add_filter('hbapp_honeybadger_http_client', function($client, $config) use (&$captured) {
            $captured = $config;
            $mock = new \GuzzleHttp\Handler\MockHandler([]);
            $stack = \GuzzleHttp\HandlerStack::create($mock);
            return new \GuzzleHttp\Client(['handler' => $stack, 'base_uri' => $config['endpoint'] ?? \Honeybadger\Honeybadger::API_URL]);
        }, 10, 2);

        $plugin = new HBAPP_Honeybadger();
        $plugin->boot();

        $this->assertSame($custom, $captured['app_endpoint']);
    }
}
