=== Honeybadger Application Monitoring ===
Contributors: pangratioscosma
Tags: honeybadger, error monitoring, error reporting, exception reporting, bug reporting
Stable tag: 0.1.0
Tested up to: 6.7
Requires at least: 5.3
Requires PHP: 7.3
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Honeybadger error monitoring for WordPress, providing PHP and JavaScript error reporting and tracking.

== Description ==
Honeybadger Application Monitoring for WordPress provides comprehensive error monitoring and reporting for both PHP and JavaScript. This plugin helps you track and resolve errors and exceptions in your WordPress site, ensuring a smooth and reliable user experience. With Honeybadger, you can easily identify and fix issues, improving the overall quality and performance of your website.

== Installation ==
1. Install the plugin from the WordPress Plugin Directory, or upload the plugin files to the `/wp-content/plugins/honeybadger-application-monitoring` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the Settings->Honeybadger screen to configure the plugin.
4. Ensure you have a Honeybadger account and obtain your API key(s). It is recommended that you have 2 separate projects, one for PHP and another for JavaScript error monitoring.
5. Enter your Honeybadger API key(s) in the plugin settings to start monitoring errors. Ensure that "PHP error reporting enabled" option is checked to enable automatic error reporting for the PHP code. Same goes for the "JS error reporting enabled" option.
6. Optionally, you can check the "Send test notification" options to test the integration upon clicking save. Note: You should uncheck these options (make sure to click Save) after you've verified that error reporting works.

== Honeybadger PHP Error Notifier ==
The PHP error notifier is based on the open source [honeybadger-io/honeybadger-php](https://packagist.org/packages/honeybadger-io/honeybadger-php) package. The source code is available [here](https://github.com/honeybadger-io/honeybadger-php).

== Honeybadger JavaScript Error Notifier and Source Code ==
To report JavaScript errors, the plugin ships with a minified version of the open source [@honeybadger-io/js](https://www.npmjs.com/package/@honeybadger-io/js) package, `honeybadger.vX.Y.min.js`. The source code is available [here](https://github.com/honeybadger-io/honeybadger-js).
The repository is a monorepo, which provides plugins for various frameworks and libraries. The source code for `@honeybadger-io/js` is in the folder [packages/js](https://github.com/honeybadger-io/honeybadger-js/tree/master/packages/js), which is an isomorphic library supporting both server and browser JavaScript applications.
More information on how the package is built can be found in the README file of the repository, under the [Bundling and types](https://github.com/honeybadger-io/honeybadger-js/tree/master/packages/js#bundling-and-types) section.

== Frequently Asked Questions ==
= I have installed the plugin but I can't see any notifications being reported. =
Ensure that you have entered your Honeybadger API key(s) correctly in the plugin settings. Also, make sure that the "PHP error reporting enabled" and "JS error reporting enabled" options are checked. If you are still not seeing notifications, check your Honeybadger account to ensure that the API keys are valid and that there are no issues with your Honeybadger projects.

= Why am I still seeing test notifications being reported to Honeybadger? =
If you are seeing test notifications, it is likely that the "Send test notification" options are still checked in the plugin settings. Uncheck these options and click Save to stop sending test notifications.

= What is the "Version" option? =
The "Version" option allows you to specify the version of your application that is being monitored. This can be useful for tracking errors across different versions of your application.

= Are there any other configuration options for Honeybadger? =
Yes, you can configure additional options such as the endpoint, environment name, excluded exceptions and more. Currently these options need to be set manually in `src/honeybadger-application-monitoring.php`. In future versions, more of these options will be configurable through the Honeybadger Settings page in WordPress.

== Screenshots ==
1. **Settings Page** - The main settings page where you can configure the Honeybadger plugin.
2. **PHP Error Reporting** - Example of a PHP exception reported to Honeybadger.
3. **JS Error Reporting** - Example of a JavaScript exception reported to Honeybadger.
