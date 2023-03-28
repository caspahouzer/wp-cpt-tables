<?php

class WPCPT_Tables_Core
{
    /**
     * @var array
     */
    private $config = [];

    /**
     * @var WPCPT_Tables_Db
     */
    private $db;

    /**
     * @var WPCPT_Tables_Helper
     */
    private $helper;

    /**
     * @var string
     */
    public $version = '1.0.0';

    public function __construct()
    {
        $this->db = new WPCPT_Tables_Db;

        $this->initConfig();
    }

    public function initConfig()
    {
        global $wpdb;
        $this->config = [
            'post_types' => array_map(function ($postType) {
                return esc_html($postType);
            }, get_option('cpt_tables:tables_enabled', [])),
            'tables_enabled' => 'cpt_tables:tables_enabled',
            'prefix' => $wpdb->prefix . 'cpt_',
            'plugin_slug' => 'wp-cpt-tables',
            'default_post_table' => $wpdb->prefix . 'posts',
            'default_meta_table' => $wpdb->prefix . 'postmeta',
        ];
    }

    /**
     * @return void
     */
    public function load()
    {
        $self = new self();

        add_filter('plugin_action_links_wp-cpt-tables/wp-cpt-tables.php', [$this, 'add_action_links'], 10, 2);
        add_filter('network_admin_plugin_action_links_wp-cpt-tables/wp-cpt-tables.php', [$this, 'add_action_links_network'], 10, 2);
        add_filter('plugin_row_meta', array($this, 'filter_plugin_row_meta'), 10, 2);

        $self->setupAdminFilters();
        $self->setupQueryFilters();
        $self->setupSettingsPage();
        $self->setupHelper();
    }

    /**
     * Add scripts and styles
     */
    public function enqueue_scripts_styles()
    {
        wp_enqueue_style($this->config['plugin_slug'] . '-css', plugin_dir_url(__FILE__) . 'assets/css/styles.css', [], $this->version, 'all');
        wp_enqueue_script($this->config['plugin_slug'] . '-js', plugin_dir_url(__FILE__) . 'assets/js/scripts.js', ['jquery'], $this->version, false);
    }

    /**
     * @return void
     */
    private function setupAdminFilters()
    {
        new WPCPT_Tables_AdminFilters;
    }

    /**
     * @return void
     */
    private function setupQueryFilters()
    {
        new WPCPT_Tables_QueryFilters($this->db, $this->config);
    }

    /**
     * @return void
     */
    private function setupSettingsPage()
    {
        new WPCPT_Tables_SettingsPage(
            new WPCPT_Tables_Table($this->db, $this->config),
            new WPCPT_Tables_Triggers($this->db, $this->config),
            $this->config
        );
    }

    private function setupHelper()
    {
        new WPCPT_Tables_Helper;
    }

    /**
     * @return void
     */
    public function activate()
    {
        flush_rewrite_rules();
    }

    /**
     * @return void
     */
    public function deactivate()
    {
        flush_rewrite_rules();
    }

    /**
     * Gets the option that stores enabled post type tables and unserializes it
     * 
     * @return array
     */
    private function getEnabledPostTypes(): array
    {
        return array_values(get_option($this->config['tables_enabled'], []));
    }

    /**
     * Filters the array of row meta for each plugin in the Plugins list table.
     *
     * @param string[] $plugin_meta An array of the plugin's metadata.
     * @param string   $plugin_file Path to the plugin file relative to the plugins directory.
     * @return string[] An array of the plugin's metadata.
     */
    public function filter_plugin_row_meta(array $plugin_meta, $plugin_file)
    {
        if ('wp-cpt-tables/wp-cpt-tables.php' !== $plugin_file) {
            return $plugin_meta;
        }

        $plugin_meta[] = sprintf(
            '<a href="%1$s" target="_blank"><span class="dashicons dashicons-star-filled" aria-hidden="true" style="font-size:14px;line-height:1.3"></span>%2$s</a>',
            'https://www.paypal.com/donate/?hosted_button_id=JNA8L66BWE2AA',
            esc_html_x('Sponsor', 'verb', 'query-monitor')
        );

        return $plugin_meta;
    }

    /**
     * @param array $links
     * @param string $file
     * @return array
     */
    public function add_action_links($links, $file)
    {
        $settings = '<a href="' . admin_url('options-general.php?page=' . $this->config['plugin_slug']) . '">' . __('Settings') . '</a>';
        array_unshift($links, $settings);

        // $premium = '<a href="' . admin_url('options-general.php?page=' . $this->config['plugin_slug']) . '" style="font-weight:bold">' . __('Go pro', $this->config['plugin_slug']) . '</a>';
        // array_unshift($links, $premium);

        return $links;
    }

    /**
     * @param array $links
     * @param string $file
     * @return array
     */
    public function add_action_links_network($links, $file)
    {
        $settings = '<a href="' . admin_url('options-general.php?page=' . $this->config['plugin_slug']) . '">' . __('Settings') . '</a>';
        array_unshift($links, $settings);

        return $links;
    }
}
