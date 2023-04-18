<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('LightApps_Connector')) {


    class LightApps_Connector
    {
        /**
         * @var string
         */
        private $api_url = 'https://plugins.lightapps.de/wp-json/slk/v1/';

        /**
         * @var array
         */
        private $plugin_data;

        /**
         * @var array
         */
        private $extra_vars;

        public function __construct($plugin_data, $extra_vars = [])
        {
            $this->plugin_data = $plugin_data;
            $this->extra_vars = $extra_vars;
        }

        /**
         * Check if can connect
         * 
         * @return boolean
         */
        private function canConnect()
        {
            // check if is ajax request
            if (defined('DOING_AJAX') && DOING_AJAX) {
                return false;
            }

            // check if is cron
            if (defined('DOING_CRON') && DOING_CRON) {
                return false;
            }

            // Check if is admin
            if (!is_admin()) {
                return false;
            }

            // check if is localhost
            $whitelist = array(
                '127.0.0.1',
                '::1'
            );
            if (in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
                return false;
            }

            return true;
        }

        /**
         * Trigger the api
         * 
         * @param string $status
         */
        public function trigger(string $status = 'active')
        {
            // check if can connect
            if ($this->canConnect() === false) {
                return;
            }

            global $wpdb;

            $is_multisite = is_multisite();

            $guid = get_option('lightapps_connector:guid');

            if ($is_multisite) {
                $network_guid = get_site_option('lightapps_connector:network_guid');
                if (!$network_guid) {
                    $new_network_guid = uniqid('', true);
                    update_site_option('lightapps_connector:network_guid', $new_network_guid);
                }
            }

            // Set guid if it doesn't exist
            if (!$guid) {
                $new_guid = uniqid('', true);
                update_option('lightapps_connector:guid', $new_guid);
                $guid = $new_guid;
            }

            $info = new stdClass();
            // Wordpress
            $info->status = $status;
            $info->guid = $guid;
            if ($status !== 'deleted') {
                $current_user = wp_get_current_user();
                $info->admin_email = $current_user->user_email;
                $info->plugin_name = $this->plugin_data['Name'];
                $info->plugin_version = $this->plugin_data['Version'];
                $info->plugin_slug = $this->plugin_data['TextDomain'];

                // get extra vars
                foreach ($this->extra_vars as $key => $value) {
                    $info->{$key} = $value;
                }

                $info->url = rtrim(home_url(), '/');
                $info->is_multisite = $is_multisite;
                if ($is_multisite) {
                    $info->network_guid = get_site_option('lightapps_connector:network_guid');
                    $info->network_url = rtrim(network_home_url(), '/');
                }
                $info->site_name = get_bloginfo('name');
                $info->language = get_bloginfo('language');
                $info->wordpress_version = get_bloginfo('version');
                // Plugins
                $plugins = get_option('active_plugins');
                $info->active_plugins = [];
                foreach ($plugins as $plugin) {
                    $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
                    $info->active_plugins[$plugin] = ['Name' => $plugin_data['Name'], 'Version' => $plugin_data['Version'], 'PluginURI' => $plugin_data['PluginURI']];
                }
                $info->active_theme = wp_get_theme()->get('Name');
                $info->active_theme_version = wp_get_theme()->get('Version');
                $info->active_theme_author = wp_get_theme()->get('Author');
                $info->active_theme_author_url = wp_get_theme()->get('AuthorURI');

                // System
                $info->php_version = phpversion();
                $info->php_max_post_size = ini_get('post_max_size');
                $info->php_max_input_vars = ini_get('max_input_vars');
                $info->database = $wpdb->get_var("SELECT VERSION()");
                $info->server_software = $_SERVER['SERVER_SOFTWARE'];
                $info->server_name = $_SERVER['SERVER_NAME'];
                $info->server_address = $_SERVER['SERVER_ADDR'];
                $info->server_port = $_SERVER['SERVER_PORT'];
                $info->user_agent = $_SERVER['HTTP_USER_AGENT'];
                $info->max_execution_time = ini_get('max_execution_time');
                $info->memory_limit = ini_get('memory_limit');
            }
            $info_array = (array) $info;

            $this->send($info_array);
        }

        /**
         * Send data
         */
        private function send(array $info_array)
        {
            // Send data via wp_remote_post
            if (function_exists('wp_remote_post')) {
                $response = wp_remote_post($this->api_url . 'ping', [
                    'method' => 'POST',
                    'body' => $info_array,
                ]);
                return;
            }

            // Send data via cURL
            if (function_exists('curl_version')) {
                // set cURL options
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $this->api_url . 'ping');
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($info_array));

                // execute the cURL request
                curl_exec($curl);
                curl_close($curl);
                return;
            }

            // Send data via file_get_contents
            if (ini_get('allow_url_fopen')) {
                $context = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => 'Content-type: application/x-www-form-urlencoded',
                        'content' => http_build_query($info_array),
                        'timeout' => 5,
                    ],
                ]);
                @file_get_contents($this->api_url . 'ping', false, $context);
                return;
            }
        }
    }
}
