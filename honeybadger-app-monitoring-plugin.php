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
 * Tested up to:      6.7
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

define('HONEYBADGER_APP_MONITORING_VERSION', '0.1.0');
define('HONEYBADGER_APP_MONITORING_PHP_MIN', '7.3.0');
define('HONEYBADGER_APP_MONITORING_PLUGIN_FILE', __FILE__);
define('HONEYBADGER_APP_MONITORING_PLUGIN_DIR', dirname(__FILE__));

// Check PHP version requirement
if (version_compare(PHP_VERSION, HONEYBADGER_APP_MONITORING_PHP_MIN, '<')) {
    function honeybadger_app_monitoring_php_version_notice() {
        $message = sprintf(
            'WordPress Honeybadger requires PHP version %s or higher. Your current PHP version is %s.',
            HONEYBADGER_APP_MONITORING_PHP_MIN,
            PHP_VERSION
        );
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }
    add_action('admin_notices', 'honeybadger_app_monitoring_php_version_notice');
    return;
}

// Load Composer autoloader if it exists
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Initialize the plugin
require_once __DIR__ . '/src/honeybadger-application-monitoring-settings.php';
require_once __DIR__ . '/src/honeybadger-application-monitoring.php';

// Boot the plugin
add_action('plugins_loaded', function () {
    $instance = new HoneybadgerApplicationMonitoring();
    $instance->boot();
});

// Register uninstall hook
function honeybadger_app_monitoring_uninstall() {
    delete_option('honeybadger_app_monitoring_settings');
}

register_uninstall_hook(__FILE__, 'honeybadger_app_monitoring_uninstall');
