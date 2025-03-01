<?php

class WP_Honeybadger {
    /**
     * @var Honeybadger\Honeybadger
     */
    private $client;

    /**
     * @var bool
     */
    private $php_tracking_enabled = false;

    /**
     * @var bool
     */
    private $js_tracking_enabled = false;

    /**
     * Boot the plugin
     */
    public function boot() {
        $this->check_requirements();
        $this->init_client();
        $this->register_hooks();
    }

    /**
     * Check if all requirements are met
     */
    private function check_requirements() {
        if (!defined('WP_HONEYBADGER_API_KEY')) {
            add_action('admin_notices', function() {
                $message = 'Honeybadger API key not set. Add WP_HONEYBADGER_API_KEY to your wp-config.php';
                echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
            });
            return;
        }

        $this->php_tracking_enabled = defined('WP_HONEYBADGER_API_KEY') && !empty(WP_HONEYBADGER_API_KEY);
        $this->js_tracking_enabled = defined('WP_HONEYBADGER_JS_API_KEY') && !empty(WP_HONEYBADGER_JS_API_KEY);
    }

    /**
     * Initialize Honeybadger client
     */
    private function init_client() {
        if (!$this->php_tracking_enabled) {
            return;
        }

        $config = [
            'api_key' => WP_HONEYBADGER_API_KEY,
            'environment' => defined('WP_HONEYBADGER_ENV') ? WP_HONEYBADGER_ENV : wp_get_environment_type(),
            'version' => defined('WP_HONEYBADGER_VERSION') ? WP_HONEYBADGER_VERSION : $this->get_version(),
            'handlers' => [
	            'exception' => false, // we will handle error manually here, adding more context
	            'error' => false, // we will handle error manually here, adding more context
	            'shutdown' => true, // the current shutdown handler in Honeybadger is only used to flush send events for Insights
            ],
        ];

        $this->client = Honeybadger\Honeybadger::new($config);

	    if (defined('WP_HONEYBADGER_CONTEXT_CAPTURE_USER') && WP_HONEYBADGER_CONTEXT_CAPTURE_USER) {
		    $current_user = wp_get_current_user();
		    if ($current_user && $current_user->ID) {
			    $this->client->context([
					'user_id' => $current_user->ID,
				    'user_email' => $current_user->user_email,
			    ]);
		    }
	    }

        if (defined('WP_HONEYBADGER_PHP_TEST_NOTIFICATION') && WP_HONEYBADGER_PHP_TEST_NOTIFICATION) {
            $this->client->notify(new Exception('Test PHP error from WP Honeybadger plugin'));
        }
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // PHP Error tracking
        if ($this->php_tracking_enabled) {
            $error_types = defined('WP_HONEYBADGER_ERROR_TYPES')
                ? WP_HONEYBADGER_ERROR_TYPES
                : (E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_USER_DEPRECATED & ~E_USER_NOTICE);

            set_error_handler([$this, 'handle_error'], $error_types);
            set_exception_handler([$this, 'handle_exception']);
            register_shutdown_function([$this, 'handle_shutdown']);
        }

        // JavaScript Error tracking
        if ($this->js_tracking_enabled) {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
            add_action('login_enqueue_scripts', [$this, 'enqueue_login_scripts']);
        }
    }

    /**
     * Handle PHP errors
     */
    public function handle_error(int $level, string $error, ?string $file = null, ?int $line = null) {
	    // When the @ operator is used, it temporarily changes `error_reporting()`'s return value
	    // to reflect what error types should be reported. This means we should get 0 (no errors).
	    $errorReportingLevel = error_reporting();
	    $isSilenced = ($errorReportingLevel == 0);

	    if (PHP_MAJOR_VERSION >= 8) {

		    $php8UnsilenceableErrors = [
			    E_ERROR,
			    E_PARSE,
			    E_CORE_ERROR,
			    E_COMPILE_ERROR,
			    E_USER_ERROR,
			    E_RECOVERABLE_ERROR,
		    ];

		    // In PHP 8+, some errors are unsilenceable, so we should respect that.
		    if (in_array($level, $php8UnsilenceableErrors)) {
			    $isSilenced = false;
		    } else {
			    // If an error is silenced, `error_reporting()` won't return 0,
			    // but rather a bitmask of the unsilenceable errors.
			    $unsilenceableErrorsBitmask = array_reduce(
				    $php8UnsilenceableErrors, function ($bitMask, $errLevel) {
				    return $bitMask | $errLevel;
			    });
			    $isSilenced = $errorReportingLevel === $unsilenceableErrorsBitmask;
		    }
	    }

	    if ($isSilenced) {
		    return false;
	    }

		$this->client->context($this->get_error_context());
		$this->client->notify(new ErrorException($error, 0, $level, $file, $line));

        return true;
    }

    /**
     * Handle PHP exceptions
     */
    public function handle_exception($exception) {
	    $this->client->context($this->get_error_context());
        $this->client->notify($exception);
    }

    /**
     * Handle fatal errors
     */
    public function handle_shutdown() {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
	        $this->client->context($this->get_error_context());
	        $this->client->notify(new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
        }
    }

    /**
     * Enqueue JavaScript tracking script
     */
    public function enqueue_scripts() {
        if (!defined('WP_HONEYBADGER_JS_API_KEY') || empty(WP_HONEYBADGER_JS_API_KEY)) {
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
                revision: "%s"
            });',
            esc_js(WP_HONEYBADGER_JS_API_KEY),
            esc_js(defined('WP_HONEYBADGER_ENV') ? WP_HONEYBADGER_ENV : wp_get_environment_type()),
            esc_js(defined('WP_HONEYBADGER_VERSION') ? WP_HONEYBADGER_VERSION : $this->get_version())
        ));

	    if (defined('WP_HONEYBADGER_CONTEXT_CAPTURE_USER') && WP_HONEYBADGER_CONTEXT_CAPTURE_USER) {
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
	    }

	    if (defined('WP_HONEYBADGER_JS_TEST_NOTIFICATION') && WP_HONEYBADGER_JS_TEST_NOTIFICATION) {
		    wp_add_inline_script('honeybadger-js', 'Honeybadger.notify("Test JS error from WP Honeybadger plugin");');
	    }
    }

    /**
     * Enqueue JavaScript tracking script in admin
     */
    public function enqueue_admin_scripts() {
        if (defined('WP_HONEYBADGER_ADMIN_JS_ENABLED') && !WP_HONEYBADGER_ADMIN_JS_ENABLED) {
            return;
        }
        $this->enqueue_scripts();
    }

    /**
     * Enqueue JavaScript tracking script on login page
     */
    public function enqueue_login_scripts() {
        if (defined('WP_HONEYBADGER_LOGIN_JS_ENABLED') && !WP_HONEYBADGER_LOGIN_JS_ENABLED) {
            return;
        }
        $this->enqueue_scripts();
    }

    /**
     * Get error context
     */
    private function get_error_context() {
        return [
	        'url' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null,
	        'honeybadger_component' => 'wordpress',
	        'honeybadger_action' => current_action(),
	        'wordpress_version' => get_bloginfo('version'),
        ];
    }

    /**
     * Get plugin version
     */
    private function get_version() {
        if (function_exists('get_plugin_data')) {
            $plugin_data = get_plugin_data(WP_HONEYBADGER_PLUGIN_FILE);
            return $plugin_data['Version'];
        }
        return 'unknown';
    }
}
