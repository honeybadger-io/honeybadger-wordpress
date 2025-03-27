# WordPress Honeybadger

A WordPress plugin for error tracking with [Honeybadger](https://www.honeybadger.io/).

## Requirements

- PHP 7.3 or higher
- WordPress 5.3 or higher
- Honeybadger account and API key

## Installation

1. Install the plugin from the WordPress Plugin Directory, or upload the plugin files to the `/wp-content/plugins/honeybadger-application-monitoring` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.

## Configuration

1. Use the Settings->Honeybadger screen to configure the plugin.
2. Ensure you have a Honeybadger account and obtain your API key(s). It is recommended that you have 2 separate projects, one for PHP and another for JavaScript error monitoring.
3. Enter your Honeybadger API key(s) in the plugin settings to start monitoring errors. Ensure that "PHP error reporting enabled" option is checked to enable automatic error reporting for the PHP code. Same goes for the "JS error reporting enabled" option.
4. Optionally, you can check the "Send test notification" options to test the integration upon clicking save. Note: You should uncheck these options (make sure to click Save) after you've verified that error reporting works.

## Features

- [x] WordPress admin integration
- [x] PHP error tracking
- [x] JavaScript error tracking
- [x] User context tracking
- [ ] Custom error type filtering


## Releasing

1. Update the version number in `honeybadger-application-monitoring.php`.
2. Update the `readme.txt` file with the new version number.
3. Push a new tag to the repository with the new version number.
4. The previous step should trigger the GitHub Actions workflow to build the plugin and deploy it to the WordPress Plugin Directory.

## Support

For issues and feature requests, please [create an issue](https://github.com/honeybadger-io/honeybadger-wordpress/issues) on GitHub.

## License

This project is licensed under the GPLv2 license - see the LICENSE file for details.
