<?php

class Core
{
    /**
     * @var array
     */
    private $config = [];

    /**
     * @var Db
     */
    private $db;

    /**
     * @var array
     */
    private $helper;

    /**
     * @var string
     */
    public $version = '1.0.0';

    public function __construct()
    {
        global $wpdb;

        $this->db = new Db;

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

        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_action_links'], 10, 2);
        add_filter('network_admin_plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_action_links_network'], 10, 2);

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
        wp_enqueue_style($this->config['plugin_slug'] . '-css-select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', [], $this->version, 'all');
        wp_enqueue_style($this->config['plugin_slug'] . '-css', plugin_dir_url(__FILE__) . 'assets/css/styles.css', [], $this->version, 'all');
        wp_enqueue_script($this->config['plugin_slug'] . '-js-select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', ['jquery'], $this->version, false);
        wp_enqueue_script($this->config['plugin_slug'] . '-js', plugin_dir_url(__FILE__) . 'assets/js/scripts.js', ['jquery'], $this->version, false);
    }

    /**
     * @return void
     */
    private function setupAdminFilters()
    {
        new AdminFilters;
    }

    /**
     * @return void
     */
    private function setupQueryFilters()
    {
        new QueryFilters($this->db, $this->config);
    }

    /**
     * @return void
     */
    private function setupSettingsPage()
    {
        new SettingsPage(
            new Table($this->db, $this->config),
            new Triggers($this->db, $this->config),
            $this->config
        );
    }

    private function setupHelper()
    {
        new Helper;
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
        $links[] = '<a href="' . admin_url('settings.php?page=' . $this->config['plugin_slug']) . '">' . __('Settings', $this->config['plugin_slug']) . '</a>';

        return $links;
    }
}
