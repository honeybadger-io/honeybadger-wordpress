<?php

class Honeybadger_Init_Test extends WP_UnitTestCase {

    private $adminUserId;

    public function set_up(): void {
        parent::set_up();

        // Ensure we run menu-related tests with a user who can manage options.
        $this->adminUserId = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $this->adminUserId );

        // Use an admin screen context so admin hooks behave as expected.
        if ( function_exists( 'set_current_screen' ) ) {
            set_current_screen( 'options-general' );
        }
    }

    public function tear_down(): void {
        wp_set_current_user( 0 );
        parent::tear_down();
    }


    public function test_env_variables_are_set() {
        $this->assertTrue( defined( 'HBAPP_HONEYBADGER_VERSION' ), 'HBAPP_HONEYBADGER_VERSION should be defined.' );
        $this->assertTrue( defined( 'HBAPP_HONEYBADGER_PHP_MIN' ), 'HBAPP_HONEYBADGER_PHP_MIN should be defined.' );
        $this->assertTrue( defined( 'HBAPP_HONEYBADGER_PLUGIN_FILE' ), 'HBAPP_HONEYBADGER_PLUGIN_FILE should be defined.' );
        $this->assertTrue( defined( 'HBAPP_HONEYBADGER_PLUGIN_DIR' ), 'HBAPP_HONEYBADGER_PLUGIN_DIR should be defined.' );

        $readmeVersion = $this->get_version_tag_from_readme();
        $this->assertSame($readmeVersion, HBAPP_HONEYBADGER_VERSION, 'HBAPP_HONEYBADGER_VERSION should match the Stable tag in readme.txt.');
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

    /**
     * Read version from readme.txt (line 4: "Stable tag: X.Y.Z").
     */
    private function get_version_tag_from_readme() {
        $readme_file = dirname(__DIR__) . '/readme.txt';
        $this->assertFileExists($readme_file, 'readme.txt should exist at the project root.');
        $lines = @file($readme_file, FILE_IGNORE_NEW_LINES);
        $this->assertIsArray($lines, 'Failed to read readme.txt');
        $this->assertTrue(isset($lines[3]), 'readme.txt should have at least 4 lines to read the Stable tag.');
        $line4 = trim($lines[3]);
        $this->assertMatchesRegularExpression('/^Stable tag:\s*(.+)$/i', $line4, 'Line 4 of readme.txt should define the Stable tag.');

        if (preg_match('/^Stable tag:\s*(.+)$/i', $line4, $m)) {
            return trim($m[1]);
        }

        return null;
    }
}
