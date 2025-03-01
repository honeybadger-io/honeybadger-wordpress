# WordPress Honeybadger

A WordPress plugin for error tracking with [Honeybadger](https://www.honeybadger.io/).

## Requirements

- PHP 7.2 or higher
- WordPress 5.0 or higher
- Honeybadger account and API key

## Installation

1. Download the plugin
2. Upload it to your WordPress plugins directory
3. Run `composer install` in the plugin directory
4. Activate the plugin through the WordPress admin interface

## Configuration

### PHP Error Tracking

Add this to your `wp-config.php`:

```php
// Required - Your Honeybadger API key
define('WP_HONEYBADGER_API_KEY', 'your-api-key-here');

// Optional - Environment name (defaults to WP environment type)
define('WP_HONEYBADGER_ENV', 'production');

// Optional - Application version
define('WP_HONEYBADGER_VERSION', 'v1.0.0');

// Optional - Send user information (default: false)
define('WP_HONEYBADGER_CONTEXT_CAPTURE_USER', true);

// Optional - Send a test notification from the PHP integration (default: false)
define('WP_HONEYBADGER_PHP_TEST_NOTIFICATION', false);
```

### JavaScript Error Tracking

Add this to your `wp-config.php`:

```php
// Required - Your Honeybadger JavaScript API key
define('WP_HONEYBADGER_JS_API_KEY', 'your-js-api-key-here');

// Optional - Enable/disable JavaScript tracking in admin area (default: true)
define('WP_HONEYBADGER_ADMIN_JS_ENABLED', true);

// Optional - Enable/disable JavaScript tracking on login page (default: true)
define('WP_HONEYBADGER_LOGIN_JS_ENABLED', true);

// Optional - Send a test notification from the JS integration (default: false)
define('WP_HONEYBADGER_JS_TEST_NOTIFICATION', false);
```

## Features

- PHP error tracking
- JavaScript error tracking
- Environment-specific configuration
- User context tracking (optional)
- Custom error type filtering
- WordPress admin integration

## Support

For issues and feature requests, please [create an issue](https://github.com/honeybadger-io/honeybadger-wordpress/issues) on GitHub.

## License

This project is licensed under the MIT License - see the LICENSE file for details.
