<?php

class HoneybadgerApplicationMonitoring {
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
            'notifier' => [
                'name' => 'honeybadger-wordpress',
                'url' => 'https://github.com/honeybadger-io/honeybadger-wordpress',
                'version' => HONEYBADGER_APP_MONITORING_VERSION,
            ],
        ]);
        $this->client = Honeybadger\Honeybadger::new($config);

        $this->client->beforeNotify(function (&$notice) {
            $noticeContext = array($notice['request']['context']);
            $url = !empty($_SERVER['REQUEST_URI']) ? sanitize_url(wp_unslash($_SERVER['REQUEST_URI'])) : null;
            $context = [
                'url' => $url,
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
            $this->client->notify(new Exception('Test PHP error from WordPress Honeybadger plugin.'));
        }
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

        wp_enqueue_script('honeybadger-js',
            plugins_url('/lib/honeybadger.v6.10.min.js', __FILE__),
            [],
            '6.10',
            true
        );

        wp_add_inline_script('honeybadger-js', sprintf(
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
            esc_js(HONEYBADGER_APP_MONITORING_VERSION)
        ));

        $current_user = wp_get_current_user();
        if ($current_user && $current_user->ID) {
            wp_add_inline_script('honeybadger-js', sprintf(
                'Honeybadger.setContext({
                    user_id: "%s",
                    user_email: "%s",
                });',
                esc_js($current_user->ID),
                esc_js($current_user->user_email)
            ));
        }

        if ($this->js_send_test_notification) {
            wp_add_inline_script('honeybadger-js', 'Honeybadger.notify("Test JavaScript error from WordPress Honeybadger plugin.");');
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
        new HoneybadgerApplicationMonitoringSettings();

        $wpOptions = get_option('honeybadger_app_monitoring_settings', [
            'honeybadger_app_monitoring_php_enabled' => 1,
            'honeybadger_app_monitoring_php_api_key' => '',
            'honeybadger_app_monitoring_php_send_test_notification' => 0,
            'honeybadger_app_monitoring_endpoint' => '',
            'honeybadger_app_monitoring_environment_name' => '',
            'honeybadger_app_monitoring_version' => '',
            'honeybadger_app_monitoring_js_enabled' => 1,
            'honeybadger_app_monitoring_js_api_key' => '',
            'honeybadger_app_monitoring_js_send_test_notification' => 0,
        ]);

        $this->php_reporting_enabled = $wpOptions['honeybadger_app_monitoring_php_enabled'];
        $this->php_api_key = $wpOptions['honeybadger_app_monitoring_php_api_key'];
        $this->php_send_test_notification = $wpOptions['honeybadger_app_monitoring_php_send_test_notification'];

        $this->js_reporting_enabled = $wpOptions['honeybadger_app_monitoring_js_enabled'];
        $this->js_api_key = $wpOptions['honeybadger_app_monitoring_js_api_key'];
        $this->js_send_test_notification = $wpOptions['honeybadger_app_monitoring_js_send_test_notification'];

        $hbConfigFromWp = [];
        if (!empty($wpOptions['honeybadger_app_monitoring_endpoint'])) {
            $hbConfigFromWp['endpoint'] = $wpOptions['honeybadger_app_monitoring_endpoint'];
        }
        if (!empty($wpOptions['honeybadger_app_monitoring_environment_name'])) {
            $hbConfigFromWp['environment_name'] = $wpOptions['honeybadger_app_monitoring_environment_name'];
        }
        if (!empty($wpOptions['honeybadger_app_monitoring_version'])) {
            $hbConfigFromWp['version'] = $wpOptions['honeybadger_app_monitoring_version'];
        }

        $this->config = new \Honeybadger\Config($hbConfigFromWp);
    }
}
