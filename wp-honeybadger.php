<?php
/**
 *  Honeybadger Application Monitoring
 *
 * @package           honeybadger-wordpress
 * @author            Honeybadger
 * @copyright         2025 Honeybadger Industries LLC
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Honeybadger Application Monitoring
 * Plugin URI:        https://github.com/honeybadger-io/honeybadger-wordpress
 * Description:       Honeybadger error (PHP and JavaScript) reporting for WordPress.
 * Tags:              honeybadger, error monitoring, exception tracking, error tracking, bug tracking, error reporting, exception reporting, bug reporting
 * Version:           0.1.0
 * Tested up to:      6.7.2
 * Requires at least: 5.3
 * Requires PHP:      7.3
 * Author:            Honeybadger Industries LLC
 * Author URI:        https://www.honeybadger.io
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       honeybadger-application-monitoring
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_HONEYBADGER_VERSION', '0.1.0');
define('WP_HONEYBADGER_PHP_MIN', '7.3.0');
define('WP_HONEYBADGER_PLUGIN_FILE', __FILE__);
define('WP_HONEYBADGER_PLUGIN_DIR', dirname(__FILE__));

// Check PHP version requirement
if (version_compare(PHP_VERSION, WP_HONEYBADGER_PHP_MIN, '<')) {
    function wp_honeybadger_php_version_notice() {
        $message = sprintf(
            'WordPress Honeybadger requires PHP version %s or higher. Your current PHP version is %s.',
            WP_HONEYBADGER_PHP_MIN,
            PHP_VERSION
        );
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }
    add_action('admin_notices', 'wp_honeybadger_php_version_notice');
    return;
}

// Load Composer autoloader if it exists
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Initialize the plugin
if (!class_exists('WP_Honeybadger_Settings')) {
    require_once __DIR__ . '/src/class-wp-honeybadger-settings.php';
}
if (!class_exists('WP_Honeybadger')) {
    require_once __DIR__ . '/src/class-wp-honeybadger.php';
}

// Boot the plugin
add_action('plugins_loaded', function () {
    $instance = new WP_Honeybadger();
    $instance->boot();
});

// Register uninstall hook
function wp_honeybadger_uninstall() {
    delete_option('wp_honeybadger_settings');
}

register_uninstall_hook(__FILE__, 'wp_honeybadger_uninstall');
