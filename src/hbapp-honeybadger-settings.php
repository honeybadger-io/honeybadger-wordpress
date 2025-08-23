<?php

class HBAPP_HoneybadgerSettings {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'settings_init']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts() {
        wp_enqueue_style('hbapp_honeybadger_css', plugin_dir_url(__FILE__) . 'css/settings-style.css', array(), HBAPP_HONEYBADGER_VERSION);
    }

    public function add_admin_menu() {
        add_options_page(
            __('Honeybadger Settings', 'honeybadger-application-monitoring'),
            __('Honeybadger', 'honeybadger-application-monitoring'),
            'manage_options',
            'honeybadger-application-monitoring',
            [$this, 'options_page']
        );
    }

    public function settings_init() {
        register_setting('honeybadger-application-monitoring', 'hbapp_honeybadger_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);

        add_settings_section(
            'hbapp_honeybadger_settings_section',
            __('Honeybadger Settings', 'honeybadger-application-monitoring'),
            [$this, 'settings_section_callback'],
            'honeybadger-application-monitoring'
        );

        add_settings_field(
            'hbapp_honeybadger_endpoint',
            __('API Endpoint', 'honeybadger-application-monitoring'),
            [$this, 'endpoint_render'],
            'honeybadger-application-monitoring',
            'hbapp_honeybadger_settings_section'
        );

        add_settings_field(
            'hbapp_honeybadger_app_endpoint',
            __('App Endpoint', 'honeybadger-application-monitoring'),
            [$this, 'app_endpoint_render'],
            'honeybadger-application-monitoring',
            'hbapp_honeybadger_settings_section'
        );

        add_settings_section(
                'hbapp_honeybadger_settings_php_section',
                __('PHP Reporting Settings', 'honeybadger-application-monitoring'),
                [$this, 'settings_php_section_callback'],
                'honeybadger-application-monitoring'
        );

        add_settings_field(
            'hbapp_honeybadger_php_enabled',
            __('PHP error reporting enabled', 'honeybadger-application-monitoring'),
            [$this, 'php_enabled_render'],
            'honeybadger-application-monitoring',
            'hbapp_honeybadger_settings_php_section'
        );

        add_settings_field(
            'hbapp_honeybadger_php_api_key',
            __('PHP API Key', 'honeybadger-application-monitoring'),
            [$this, 'php_api_key_render'],
            'honeybadger-application-monitoring',
            'hbapp_honeybadger_settings_php_section'
        );

        add_settings_field(
                'hbapp_honeybadger_php_report_non_fatal',
                __('Report non-fatal PHP errors', 'honeybadger-application-monitoring'),
                [$this, 'php_report_non_fatal_render'],
                'honeybadger-application-monitoring',
                'hbapp_honeybadger_settings_php_section'
        );

        add_settings_field(
                'hbapp_honeybadger_php_capture_deprecations',
                __('Report PHP deprecations', 'honeybadger-application-monitoring'),
                [$this, 'php_capture_deprecations_render'],
                'honeybadger-application-monitoring',
                'hbapp_honeybadger_settings_php_section'
        );

        add_settings_field(
                'hbapp_honeybadger_php_send_test_notification',
                __('Send test notification from PHP', 'honeybadger-application-monitoring'),
                [$this, 'php_send_test_notification_render'],
                'honeybadger-application-monitoring',
                'hbapp_honeybadger_settings_php_section'
        );

        add_settings_section(
                'hbapp_honeybadger_settings_js_section',
                __('JavaScript Reporting Settings', 'honeybadger-application-monitoring'),
                [$this, 'settings_js_section_callback'],
                'honeybadger-application-monitoring'
        );

        add_settings_field(
            'hbapp_honeybadger_js_enabled',
            __('JS error reporting enabled', 'honeybadger-application-monitoring'),
            [$this, 'js_enabled_render'],
            'honeybadger-application-monitoring',
            'hbapp_honeybadger_settings_js_section'
        );

        add_settings_field(
            'hbapp_honeybadger_js_api_key',
            __('JS API Key', 'honeybadger-application-monitoring'),
            [$this, 'js_api_key_render'],
            'honeybadger-application-monitoring',
            'hbapp_honeybadger_settings_js_section'
        );

        add_settings_field(
            'hbapp_honeybadger_environment_name',
            __('Environment', 'honeybadger-application-monitoring'),
            [$this, 'environment_name_render'],
            'honeybadger-application-monitoring',
            'hbapp_honeybadger_settings_js_section'
        );

        add_settings_field(
            'hbapp_honeybadger_version',
            __('Version', 'honeybadger-application-monitoring'),
            [$this, 'version_render'],
            'honeybadger-application-monitoring',
            'hbapp_honeybadger_settings_js_section'
        );

        add_settings_field(
            'hbapp_honeybadger_js_send_test_notification',
            __('Send test notification from JavaScript', 'honeybadger-application-monitoring'),
            [$this, 'js_send_test_notification_render'],
            'honeybadger-application-monitoring',
            'hbapp_honeybadger_settings_js_section'
        );
    }

    public function sanitize_bool_value($input) {
        return $input ? 1 : 0;
    }

    public function sanitize_js_api_key($input) {
        if (empty($input) || !is_string($input)) {
            add_settings_error('hbapp_honeybadger_settings', 'invalid-js-api-key', 'Invalid JS API Key');
            return '';
        }
        return sanitize_text_field($input);
    }

    public function sanitize_php_api_key($input) {
        if (empty($input) || !is_string($input)) {
            add_settings_error('hbapp_honeybadger_settings', 'invalid-php-api-key', 'Invalid PHP API Key');
            return '';
        }
        return sanitize_text_field($input);
    }

    public function sanitize_environment_name($input) {
        return sanitize_text_field($input);
    }

    public function sanitize_version($input) {
        return sanitize_text_field($input);
    }

    public function sanitize_settings($settings) {
        $settings['hbapp_honeybadger_php_enabled'] = $this->sanitize_bool_value($settings['hbapp_honeybadger_php_enabled'] ?? 0);
        $settings['hbapp_honeybadger_js_enabled'] = $this->sanitize_bool_value($settings['hbapp_honeybadger_js_enabled'] ?? 0);
        $settings['hbapp_honeybadger_php_send_test_notification'] = $this->sanitize_bool_value($settings['hbapp_honeybadger_php_send_test_notification'] ?? 0);
        $settings['hbapp_honeybadger_js_send_test_notification'] = $this->sanitize_bool_value($settings['hbapp_honeybadger_js_send_test_notification'] ?? 0);
        $settings['hbapp_honeybadger_php_api_key'] = $this->sanitize_php_api_key($settings['hbapp_honeybadger_php_api_key'] ?? '');
        $settings['hbapp_honeybadger_js_api_key'] = $this->sanitize_js_api_key($settings['hbapp_honeybadger_js_api_key']) ?? '';
        $settings['hbapp_honeybadger_environment_name'] = $this->sanitize_environment_name($settings['hbapp_honeybadger_environment_name'] ?? '');
        $settings['hbapp_honeybadger_version'] = $this->sanitize_version($settings['hbapp_honeybadger_version'] ?? '');
        $settings['hbapp_honeybadger_php_report_non_fatal'] = $this->sanitize_bool_value($settings['hbapp_honeybadger_php_report_non_fatal'] ?? 0);
        $settings['hbapp_honeybadger_php_capture_deprecations'] = $this->sanitize_bool_value($settings['hbapp_honeybadger_php_capture_deprecations'] ?? 0);
        $settings['hbapp_honeybadger_endpoint'] = $this->sanitize_url_value($settings['hbapp_honeybadger_endpoint'] ?? '');
        $settings['hbapp_honeybadger_app_endpoint'] = $this->sanitize_url_value($settings['hbapp_honeybadger_app_endpoint'] ?? '');
        return $settings;
    }

    public function js_enabled_render() {
        $options = get_option('hbapp_honeybadger_settings');
        ?>
        <input type='checkbox' name='hbapp_honeybadger_settings[hbapp_honeybadger_js_enabled]' <?php checked($options['hbapp_honeybadger_js_enabled'] ?? 1, 1); ?> value='1'>
        <?php
    }

    public function php_enabled_render() {
        $options = get_option('hbapp_honeybadger_settings');
        ?>
        <input type='checkbox' name='hbapp_honeybadger_settings[hbapp_honeybadger_php_enabled]' <?php checked($options['hbapp_honeybadger_php_enabled'] ?? 1, 1); ?> value='1'>
        <?php
    }

    public function php_api_key_render() {
        $options = get_option('hbapp_honeybadger_settings');
        ?>
        <input type='text' name='hbapp_honeybadger_settings[hbapp_honeybadger_php_api_key]' value='<?php echo esc_html($options['hbapp_honeybadger_php_api_key'] ?? ''); ?>'>
        <?php
    }

    public function js_api_key_render() {
        $options = get_option('hbapp_honeybadger_settings');
        ?>
        <input type='text' name='hbapp_honeybadger_settings[hbapp_honeybadger_js_api_key]' value='<?php echo esc_html($options['hbapp_honeybadger_js_api_key'] ?? ''); ?>'>
        <?php
    }

    public function environment_name_render() {
        $options = get_option('hbapp_honeybadger_settings');
        ?>
        <input type='text' name='hbapp_honeybadger_settings[hbapp_honeybadger_environment_name]' value='<?php echo esc_html($options['hbapp_honeybadger_environment_name'] ?? 'production'); ?>'>
        <?php
    }

    public function version_render() {
        $options = get_option('hbapp_honeybadger_settings');
        ?>
        <input type='text' name='hbapp_honeybadger_settings[hbapp_honeybadger_version]' value='<?php echo esc_html($options['hbapp_honeybadger_version'] ?? ''); ?>'>
        <?php
    }

    private function sanitize_url_value($input) {
        if (empty($input)) {
            return '';
        }
        $sanitized = esc_url_raw($input);
        if ($sanitized === '') {
            add_settings_error('hbapp_honeybadger_settings', 'invalid-url', 'Invalid URL provided');
        }
        return $sanitized;
    }

    public function endpoint_render() {
        $options = get_option('hbapp_honeybadger_settings');
        $current = $options['hbapp_honeybadger_endpoint'] ?? '';
        $default = \Honeybadger\Honeybadger::API_URL;
        ?>
        <input type='text' name='hbapp_honeybadger_settings[hbapp_honeybadger_endpoint]' value='<?php echo esc_attr($current); ?>' placeholder='<?php echo esc_attr($default); ?>' style='width: 420px;'>
        <div class='description'>
            <?php echo esc_html__('URL to send notices (errors), events (Insights), and check-ins. If you are using our EU stack, this should be set to "https://eu-api.honeybadger.io/".', 'honeybadger-application-monitoring'); ?>
        </div>
        <?php
    }

    public function app_endpoint_render() {
        $options = get_option('hbapp_honeybadger_settings');
        $current = $options['hbapp_honeybadger_app_endpoint'] ?? '';
        $default = \Honeybadger\Honeybadger::APP_URL;
        ?>
        <input type='text' name='hbapp_honeybadger_settings[hbapp_honeybadger_app_endpoint]' value='<?php echo esc_attr($current); ?>' placeholder='<?php echo esc_attr($default); ?>' style='width: 420px;'>
        <div class='description'>
            <?php echo esc_html__('URL used to configure/synchronize check-ins. If you are using our EU stack, this should be set to "https://eu-app.honeybadger.io/".', 'honeybadger-application-monitoring'); ?>
        </div>
        <?php
    }

    public function php_send_test_notification_render() {
        $options = get_option('hbapp_honeybadger_settings');
        ?>
        <input type='checkbox' name='hbapp_honeybadger_settings[hbapp_honeybadger_php_send_test_notification]' <?php checked($options['hbapp_honeybadger_php_send_test_notification'] ?? 0, 1); ?> value='1'>
        <span>
            <?php
            echo esc_html__('If this is checked, a test notification will be sent from PHP to Honeybadger when you save.', 'honeybadger-application-monitoring');
            ?>
        </span>
        <?php
    }

    public function js_send_test_notification_render() {
        $options = get_option('hbapp_honeybadger_settings');
        ?>
        <input type='checkbox' name='hbapp_honeybadger_settings[hbapp_honeybadger_js_send_test_notification]' <?php checked($options['hbapp_honeybadger_js_send_test_notification'] ?? 0, 1); ?> value='1'>
        <span>
            <?php
            echo esc_html__('If this is checked, a test notification will be sent from JavaScript to Honeybadger when you save.', 'honeybadger-application-monitoring');
            ?>
        </span>
        <?php
    }

    public function php_report_non_fatal_render() {
        $options = get_option('hbapp_honeybadger_settings');
        ?>
        <input type='checkbox' name='hbapp_honeybadger_settings[hbapp_honeybadger_php_report_non_fatal]' <?php checked($options['hbapp_honeybadger_php_report_non_fatal'] ?? 0, 1); ?> value='1'>
        <span>
            <?php echo esc_html__('When enabled, the plugin will report non-fatal PHP errors (warnings/notices).', 'honeybadger-application-monitoring'); ?>
        </span>
        <?php
    }

    public function php_capture_deprecations_render() {
        $options = get_option('hbapp_honeybadger_settings');
        ?>
        <input type='checkbox' name='hbapp_honeybadger_settings[hbapp_honeybadger_php_capture_deprecations]' <?php checked($options['hbapp_honeybadger_php_capture_deprecations'] ?? 0, 1); ?> value='1'>
        <span>
            <?php echo esc_html__('When enabled, the plugin will report PHP deprecation warnings.', 'honeybadger-application-monitoring'); ?>
        </span>
        <?php
    }

    public function settings_section_callback() {
        $this->logo();
        echo wp_kses('<br />', array('br' => array()));
        echo esc_html__('Configure your Honeybadger settings below.', 'honeybadger-application-monitoring');
        echo wp_kses('<br />', array('br' => array()));
        echo esc_html__('For optimum experience, it is recommended to setup two Honeybadger projects, one for PHP and another for JavaScript.', 'honeybadger-application-monitoring');
    }

    public function settings_php_section_callback() {
        // no-op
    }

    public function settings_js_section_callback() {
        // no-op
    }

    public function options_page() {
        ?>
        <form action='options.php' method='post'>
            <?php
            settings_fields('honeybadger-application-monitoring');
            do_settings_sections('honeybadger-application-monitoring');
            submit_button();
            ?>
        </form>
        <?php
    }

    private function logo() {
        ?>
        <svg class="honeybadger-logo" height="40" viewBox="0 0 190 40" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" xmlns:serif="http://www.serif.com/" style="float: right; margin-top: -45px; margin-right: 20px; fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;">
            <path id="logoText" d="M58.59,34.57L62.786,5.798L58.115,5.798L56.398,17.497L52.161,17.497L53.878,5.798L49.207,5.798L44.978,34.57L49.649,34.57L51.527,21.799L55.764,21.799L53.886,34.57L58.59,34.57ZM67.303,28.803C71.974,28.803 73.809,26.83 74.777,19.78C75.678,13.085 74.11,10.886 69.772,10.886C65.168,10.886 63.199,13.15 62.365,20.039C61.498,26.798 63.266,28.803 67.303,28.803ZM67.77,25.504C66.602,25.504 66.302,24.372 66.936,20.006C67.537,15.479 68.104,14.153 69.438,14.153C70.606,14.153 70.84,15.22 70.239,19.683C69.639,24.114 69.071,25.504 67.77,25.504ZM81.42,12.697C82.454,11.727 83.956,10.886 85.29,10.886C87.993,10.886 88.927,12.73 88.46,15.964L86.591,28.415L82.254,28.415L84.022,16.449C84.189,15.285 83.956,14.702 83.088,14.702C82.421,14.702 81.787,15.285 81.387,15.673L79.518,28.415L75.181,28.415L77.683,11.274L81.353,11.274L81.42,12.697ZM136.677,11.274L136.176,12.536C135.442,11.468 134.341,10.886 132.973,10.886C129.804,10.886 127.868,13.409 127.067,20.103C126.266,26.733 127.801,28.803 130.304,28.803C131.805,28.803 132.973,28.092 133.84,27.089L133.874,28.415L137.878,28.415L140.413,11.274L136.677,11.274ZM134.14,24.243C133.773,24.696 133.073,25.181 132.472,25.181C131.404,25.181 131.071,24.178 131.638,20.006C132.238,15.576 132.873,14.541 133.94,14.541C134.574,14.541 135.108,14.993 135.408,15.479L134.14,24.243ZM154.727,5.798L151.39,28.415L147.42,28.415L147.386,27.089C146.519,28.124 145.318,28.803 143.85,28.803C141.347,28.803 139.779,26.733 140.614,20.103C141.414,13.409 143.316,10.886 146.519,10.886C147.754,10.886 148.721,11.404 149.422,12.212L150.391,5.798L154.727,5.798ZM145.185,20.006C144.584,24.178 144.951,25.181 146.019,25.181C146.586,25.181 147.32,24.696 147.687,24.243L148.955,15.479C148.655,14.993 148.087,14.541 147.487,14.541C146.419,14.541 145.786,15.576 145.185,20.006ZM107.145,34.563L158.879,34.563C161.976,34.563 164.601,32.283 165.035,29.215L167.572,11.274L163.835,11.274L163.435,12.568C162.768,11.63 161.633,10.886 160.399,10.886C157.029,10.886 155.194,12.73 154.259,19.909C153.359,26.442 154.827,28.447 157.463,28.447C158.797,28.447 160.032,27.865 160.866,26.895L160.733,28.124C160.375,29.66 158.797,30.879 156.204,30.879L109.231,30.879C108.618,32.327 108.021,33.499 107.315,34.363C107.259,34.431 107.203,34.498 107.145,34.563ZM159.698,24.89C158.63,24.89 158.23,23.952 158.797,19.877C159.365,15.608 160.032,14.541 161.132,14.541C161.733,14.541 162.267,14.993 162.567,15.446L161.333,23.952C160.965,24.405 160.265,24.89 159.698,24.89ZM177.647,27.283C175.88,28.318 174.044,28.803 172.443,28.803C168.706,28.803 167.004,25.569 167.738,20.071C168.606,13.473 171.075,10.886 175.312,10.886C177.948,10.886 179.583,12.245 179.583,14.638C179.583,17.839 177.782,21.332 171.909,22.4C171.909,22.575 171.919,22.703 171.928,22.82C171.935,22.918 171.942,23.008 171.942,23.111C171.975,24.437 172.676,25.148 173.844,25.148C174.645,25.148 175.579,24.89 176.914,24.275L177.647,27.283ZM174.812,14.088C173.778,14.088 172.843,15.64 172.276,19.068C174.745,18.745 175.679,16.772 175.679,15.349C175.679,14.573 175.412,14.088 174.812,14.088ZM183.133,28.415L184.901,16.675C185.769,15.899 186.736,15.479 187.703,15.479C187.971,15.479 188.204,15.479 188.471,15.511L189.339,11.339C188.871,11.016 188.471,10.886 187.971,10.886C186.703,10.886 185.602,11.889 184.801,13.312L184.734,11.274L181.331,11.274L178.795,28.415L183.133,28.415ZM99.105,27.283C97.337,28.318 95.502,28.803 93.9,28.803C90.164,28.803 88.462,25.569 89.196,20.071C90.064,13.473 92.532,10.886 96.77,10.886C99.406,10.886 101.04,12.245 101.04,14.638C101.04,17.839 99.239,21.332 93.367,22.4C93.367,22.576 93.376,22.704 93.385,22.82C93.393,22.918 93.4,23.008 93.4,23.111C93.433,24.437 94.134,25.148 95.302,25.148C96.103,25.148 97.037,24.89 98.372,24.275L99.105,27.283ZM96.269,14.088C95.235,14.088 94.301,15.64 93.734,19.068C96.203,18.745 97.137,16.772 97.137,15.349C97.137,14.573 96.87,14.088 96.269,14.088ZM119.965,28.803C123.769,28.803 125.237,25.795 126.038,18.777C126.805,12.503 125.571,10.886 122.935,10.886C121.601,10.886 120.432,11.436 119.398,12.245L120.266,6.273L115.93,6.273L112.692,28.415L116.629,28.415L117.029,27.121C117.597,28.059 118.631,28.803 119.965,28.803ZM119.131,25.181C118.531,25.181 117.897,24.857 117.63,24.243L118.931,15.446C119.431,14.929 120.098,14.541 120.733,14.541C121.734,14.541 121.934,15.543 121.5,18.939C121.1,22.982 120.432,25.181 119.131,25.181ZM105.859,23.758L109.629,11.274L114.1,11.274L107.427,28.609C105.592,33.331 104.558,34.564 101.656,34.564L60.91,34.564L61.451,30.879L96.923,30.878L100.944,30.878C102.286,30.878 103.023,29.947 103.023,28.738L101.522,11.274L106.226,11.274L105.859,23.758Z"></path>
            <path id="logoBorder" d="M28.229,12.072L36.157,20L20,36.157L18.241,34.399L15.854,36.786L18.408,39.341C19.287,40.22 20.713,40.22 21.592,39.341L39.341,21.592C40.22,20.713 40.22,19.287 39.341,18.408L29.651,8.719L28.229,12.072ZM27.934,7.002L26.512,10.355L23.521,7.364L25.909,4.977L27.934,7.002ZM24.317,3.385L21.592,0.659C20.713,-0.22 19.287,-0.22 18.408,0.659L0.659,18.408C-0.22,19.287 -0.22,20.713 0.659,21.592L11.063,31.995L12.587,28.744L3.843,20L20,3.843L21.93,5.772L24.317,3.385ZM12.755,33.688L14.279,30.437L16.65,32.807L14.262,35.195L12.755,33.688Z"></path>
            <g>
                <path id="logoBolt" d="M29.788,2.63C30.029,2.061 29.309,1.575 28.872,2.013L9.533,21.352L17.411,21.352L11.142,36.785C10.911,37.353 11.627,37.829 12.061,37.395L30.918,18.538L23.039,18.538L29.788,2.63Z"></path>
            </g>
            <defs>
                <linearGradient id="_Linear1" x1="0" y1="0" x2="1" y2="0" gradientUnits="userSpaceOnUse" gradientTransform="matrix(2.18719e-15,35.7195,-35.7195,2.18719e-15,20.2253,1.84375)"><stop offset="0" style="stop-color:white;stop-opacity:1"></stop><stop offset="0.46" style="stop-color:rgb(255,204,0);stop-opacity:1"></stop><stop offset="1" style="stop-color:rgb(255,113,67);stop-opacity:1"></stop></linearGradient>
            </defs>
        </svg>
        <?php
    }
}
