<?php

class Honeybadger_Settings_Test extends WP_UnitTestCase {

    private $admin_user_id;

    public function setUp(): void {
        parent::setUp();

        // Be admin for admin area hooks and capability checks.
        $this->admin_user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $this->admin_user_id );

        // Use an admin screen context so admin hooks behave as expected.
        if ( function_exists( 'set_current_screen' ) ) {
            set_current_screen( 'options-general' );
        }
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
                'base_uri' => $config['endpoint'] ?? 'https://api.honeybadger.io/'
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
        $this->assertGreaterThanOrEqual( 1, count( $history ), 'Expected at least one HTTP request to Honeybadger.' );
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
}
