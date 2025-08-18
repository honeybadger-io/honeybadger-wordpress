<?php

class Honeybadger_Init_Test extends WP_UnitTestCase {

    private $admin_user_id;

    public function setUp(): void {
        parent::setUp();

        // Ensure we run menu-related tests with a user who can manage options.
        $this->admin_user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $this->admin_user_id );

        // Use an admin screen context so admin hooks behave as expected.
        if ( function_exists( 'set_current_screen' ) ) {
            set_current_screen( 'options-general' );
        }
    }

    public function tearDown(): void {
        wp_set_current_user( 0 );
        parent::tearDown();
    }


    public function test_env_variables_are_set() {
        $this->assertTrue( defined( 'HBAPP_HONEYBADGER_VERSION' ), 'HBAPP_HONEYBADGER_VERSION should be defined.' );
        $this->assertTrue( defined( 'HBAPP_HONEYBADGER_PHP_MIN' ), 'HBAPP_HONEYBADGER_PHP_MIN should be defined.' );
        $this->assertTrue( defined( 'HBAPP_HONEYBADGER_PLUGIN_FILE' ), 'HBAPP_HONEYBADGER_PLUGIN_FILE should be defined.' );
        $this->assertTrue( defined( 'HBAPP_HONEYBADGER_PLUGIN_DIR' ), 'HBAPP_HONEYBADGER_PLUGIN_DIR should be defined.' );

        $this->assertSame( '0.1.1', HBAPP_HONEYBADGER_VERSION );
        $this->assertSame( '7.3.0', HBAPP_HONEYBADGER_PHP_MIN );

        $this->assertFileExists( HBAPP_HONEYBADGER_PLUGIN_FILE );
        $this->assertDirectoryExists( HBAPP_HONEYBADGER_PLUGIN_DIR );

        // ABSPATH should be defined in WP test environment.
        $this->assertTrue( defined( 'ABSPATH' ), 'ABSPATH should be defined by WordPress.' );
    }

    public function test_plugin_is_loaded() {
        // The main plugin class should be available once the plugin file is loaded.
        $this->assertTrue( class_exists( 'HBAPP_Honeybadger' ), 'HBAPP_Honeybadger class should exist after plugin load.' );

        // WordPress should have fired the plugins_loaded hook by now (plugin boots on this hook).
        $this->assertGreaterThan( 0, did_action( 'plugins_loaded' ), 'plugins_loaded should have fired.' );

        // Ensure the settings page was registered by the plugin boot via HBAPP_HoneybadgerSettings.
        // Trigger admin_menu to make sure menus are built for this request.
        do_action( 'admin_menu' );
        $url = menu_page_url( 'honeybadger-application-monitoring', false );
        $this->assertIsString( $url );
        $this->assertStringContainsString( 'page=honeybadger-application-monitoring', $url );
    }
}
