<?php
/**
 * Plugin Name: WordPress Honeybadger
 * Plugin URI: https://github.com/honeybadger-io/honeybadger-wordpress
 * Description: Honeybadger error (PHP and JavaScript) reporting for WordPress.
 * Tags: honeybadger, error monitoring, exception tracking, error tracking, bug tracking, error reporting, exception reporting, bug reporting
 * Version: 0.0.1
 * Author: Honeybadger
 * Author URI: https://www.honeybadger.io
 * License: MIT
 * License URI: https://github.com/honeybadger-io/honeybadger-wordpress/LICENSE.md
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_HONEYBADGER_VERSION', '1.0.0');
define('WP_HONEYBADGER_PHP_MIN', '7.2.0');
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
