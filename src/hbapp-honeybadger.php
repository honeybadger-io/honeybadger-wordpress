<?php

class HBAPP_Honeybadger {
    /**
     * @var Honeybadger\Honeybadger
     */
    private $client;

    /**
     * @var Honeybadger\Config
     */
    private $config;

    /**
     * @var bool
     */
    private $php_reporting_enabled = true;

    /**
     * @var bool
     */
    private $js_reporting_enabled = true;

    /**
     * @var string
     */
    private $php_api_key;

    /**
     * @var string
     */
    private $js_api_key;

    /**
     * @var bool
     */
    private $js_send_test_notification = false;

    /**
     * @var bool
     */
    private $php_send_test_notification = false;

    /**
     * @var bool
     */
    private $php_report_non_fatal = false;

    /**
     * @var array
     */
    private const NON_FATAL_ERRORS = [
        'E_WARNING',
        'E_NOTICE',
        'E_USER_WARNING',
        'E_USER_NOTICE',
        'E_CORE_WARNING',
        'E_COMPILE_WARNING',
        'E_STRICT',
    ];

    /**
     * Boot the plugin
     */
    public function boot() {
        $this->init_settings();
        $this->init_client();
        $this->register_js_hooks();
    }

    /**
     * Initialize Honeybadger client for PHP error reporting.
     */
    private function init_client() {
        if (empty($this->php_api_key)) {
            return;
        }

        $config = array_merge($this->config->all(), [
            'api_key' => $this->php_api_key,
            'report_data' => $this->php_reporting_enabled,
            'service_exception_handler' => function (Honeybadger\Exceptions\ServiceException $e) {
                if (is_admin()) {
                    // show an error message if we are in an Admin page
                    wp_admin_notice($e->getMessage(), ['type' => 'error', 'attributes' => ['style' => 'margin-left:195px;"']]);
                }
                error_log($e->getMessage() . ': ' . $e->getTraceAsString());
            },
            'notifier' => [
                'name' => 'honeybadger-wordpress',
                'url' => 'https://github.com/honeybadger-io/honeybadger-wordpress',
                'version' => HBAPP_HONEYBADGER_VERSION,
            ],
        ]);

        // Allow tests or integrators to inject a custom Guzzle client to avoid real HTTP requests.
        // The filter should return an instance of \GuzzleHttp\Client or null to use the default.
        $injectedClient = apply_filters('hbapp_honeybadger_http_client', null, $config);

        $this->client = Honeybadger\Honeybadger::new($config, $injectedClient);

        $this->client->beforeNotify(function (&$notice) {

            $errorClass = (string) ($notice['error']['class'] ?? '');

            // Honeybadger's ErrorHandler from the PHP SDK should handle this automatically
            // if error_reporting() is configured properly.
            // However, if it has not been configured to silence these errors
            // we can catch them here
            if (!$this->php_report_non_fatal) {
                $drop = array_map(static function ($level) {
                    return "Error ($level)";
                }, self::NON_FATAL_ERRORS);
                if (in_array($errorClass, $drop, true)) {
                    return false;
                }
            }

            $noticeContext = $notice['request']['context'];
            if ($noticeContext instanceof stdClass) {
                // if context is stdClass, we consider it as empty
                $noticeContext = [];
            }
            else if (!is_array($noticeContext)) {
                $noticeContext = (array) $noticeContext;
            }

            $context = [
                'url' => $this->get_request_url(),
                'version' => $this->config->get('version'),
                'wordpress_version' => get_bloginfo('version'),
            ];

            $current_user = wp_get_current_user();
            if ($current_user && $current_user->ID) {
                $context['user_id'] = $current_user->ID;
                $context['user_email'] = $current_user->user_email;
            }

            $notice['request']['context'] = array_merge($context, $noticeContext);
            $notice['request']['component'] = 'wordpress';
            $notice['request']['action'] = current_action();
        });

        if ($this->php_send_test_notification) {
            $this->update_settings(['hbapp_honeybadger_php_send_test_notification' => 0]);
            try {
                $this->client->notify(new Exception('Test PHP error from WordPress Honeybadger plugin.'));
            }
            catch (Throwable $e) {
                wp_admin_notice('Honeybadger - Could not send test notification: ' . $e->getMessage(), ['type' => 'error', 'attributes' => ['style' => 'margin-left:195px;"']]);
            }
        }
    }

    private function get_request_url() {
        return !empty($_SERVER['REQUEST_URI']) ? sanitize_url(wp_unslash($_SERVER['REQUEST_URI'])) : null;
    }

    /**
     * Register WordPress hooks for the JavaScript configuration.
     */
    private function register_js_hooks() {
        if (empty($this->js_api_key)) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('login_enqueue_scripts', [$this, 'enqueue_login_scripts']);
    }

    /**
     * Enqueue JavaScript reporting script
     */
    public function enqueue_scripts() {
        if (empty($this->js_api_key)) {
            return;
        }

        wp_enqueue_script('hbapp_honeybadger_js',
            plugins_url('/lib/honeybadger.v6.10.min.js', __FILE__),
            [],
            '6.10',
            true
        );

        wp_add_inline_script('hbapp_honeybadger_js', sprintf(
            'Honeybadger.configure({
                apiKey: "%s",
                environment: "%s",
                revision: "%s",
                reportData: %s,
            });
            Honeybadger.setNotifier({
                name: "honeybadger-wordpress",
                url: "https://github.com/honeybadger-io/honeybadger-wordpress",
                version: "%s"
            })',
            esc_js($this->js_api_key),
            esc_js($this->config->get('environment_name')),
            esc_js($this->config->get('version')),
            esc_js($this->js_reporting_enabled ? 'true' : 'false'),
            esc_js(HBAPP_HONEYBADGER_VERSION)
        ));

        $current_user = wp_get_current_user();
        if ($current_user && $current_user->ID) {
            wp_add_inline_script('hbapp_honeybadger_js', sprintf(
                'Honeybadger.setContext({
                    user_id: "%s",
                    user_email: "%s",
                    wordpress_version: "%s",
                    version: "%s",
                    url: "%s"
                });',
                esc_js($current_user->ID),
                esc_js($current_user->user_email),
                esc_js(get_bloginfo('version')),
                esc_js($this->config->get('version')),
                esc_js($this->get_request_url())
            ));
        }

        if ($this->js_send_test_notification) {
            $this->update_settings(['hbapp_honeybadger_js_send_test_notification' => 0]);
            wp_add_inline_script('hbapp_honeybadger_js', 'Honeybadger.notify("Test JavaScript error from WordPress Honeybadger plugin.");');
        }
    }

    /**
     * Enqueue JavaScript error reporting script in admin
     */
    public function enqueue_admin_scripts() {
        $this->enqueue_scripts();
    }

    /**
     * Enqueue JavaScript error reporting script on login page
     */
    public function enqueue_login_scripts() {
        $this->enqueue_scripts();
    }

    /**
     * 1. Setup the options page.
     * 2. Get settings from the options table.
     */
    private function init_settings() {
        // setup settings page
        new HBAPP_HoneybadgerSettings();

        $this->load_settings_from_options();
    }

    /**
     * Load settings from WordPress Options API and initialize instance variables
     */
    private function load_settings_from_options() {
        $wpOptions = get_option('hbapp_honeybadger_settings', [
            'hbapp_honeybadger_php_enabled' => 1,
            'hbapp_honeybadger_php_report_non_fatal' => 0,
            'hbapp_honeybadger_php_capture_deprecations' => 0,
            'hbapp_honeybadger_php_api_key' => '',
            'hbapp_honeybadger_php_send_test_notification' => 0,
            'hbapp_honeybadger_endpoint' => '',
            'hbapp_honeybadger_app_endpoint' => '',
            'hbapp_honeybadger_environment_name' => '',
            'hbapp_honeybadger_version' => '',
            'hbapp_honeybadger_js_enabled' => 1,
            'hbapp_honeybadger_js_api_key' => '',
            'hbapp_honeybadger_js_send_test_notification' => 0,
        ]);

        $this->php_reporting_enabled = ($wpOptions['hbapp_honeybadger_php_enabled'] ?? 1) === 1;
        $this->php_api_key = $wpOptions['hbapp_honeybadger_php_api_key'] ?? '';
        $this->php_send_test_notification = ($wpOptions['hbapp_honeybadger_php_send_test_notification'] ?? 0) === 1;

        $this->js_reporting_enabled = ($wpOptions['hbapp_honeybadger_js_enabled'] ?? 1) === 1;
        $this->js_api_key = $wpOptions['hbapp_honeybadger_js_api_key'] ?? '';
        $this->js_send_test_notification = ($wpOptions['hbapp_honeybadger_js_send_test_notification'] ?? 0) === 1;
        $this->php_report_non_fatal = ($wpOptions['hbapp_honeybadger_php_report_non_fatal'] ?? 0) === 1;

        $hbConfigFromWp = [];
        if (!empty($wpOptions['hbapp_honeybadger_endpoint'])) {
            $hbConfigFromWp['endpoint'] = $wpOptions['hbapp_honeybadger_endpoint'];
        }
        if (!empty($wpOptions['hbapp_honeybadger_app_endpoint'])) {
            $hbConfigFromWp['app_endpoint'] = $wpOptions['hbapp_honeybadger_app_endpoint'];
        }
        if (!empty($wpOptions['hbapp_honeybadger_environment_name'])) {
            $hbConfigFromWp['environment_name'] = $wpOptions['hbapp_honeybadger_environment_name'];
        }
        if (!empty($wpOptions['hbapp_honeybadger_version'])) {
            $hbConfigFromWp['version'] = $wpOptions['hbapp_honeybadger_version'];
        }

        $hbConfigFromWp['capture_deprecations'] = ($wpOptions['hbapp_honeybadger_php_capture_deprecations'] ?? 0) === 1;

        $this->config = new \Honeybadger\Config($hbConfigFromWp);
    }

    /**
     * Update specific settings in the WordPress Options API and refresh instance variables
     *
     * @param array $settings Associative array of settings to update
     * @return bool True if settings were updated successfully, false otherwise
     */
    private function update_settings($settings) {
        if (!is_array($settings) || empty($settings)) {
            return false;
        }

        $wpOptions = get_option('hbapp_honeybadger_settings', []);
        foreach ($settings as $key => $value) {
            $wpOptions[$key] = $value;
        }

        $updated = update_option('hbapp_honeybadger_settings', $wpOptions);
        if ($updated) {
            $this->load_settings_from_options();
        }

        return $updated;
    }
}
