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

    /**
     * The plugin constructor
     */
    public function __construct()
    {
        $this->db = new WPCPT_Tables_Db;

        $this->initConfig();
    }

    /**
     * Initialize the plugin configuration
     */
    public function initConfig()
    {
        global $wpdb;
        $this->config = [
            'post_types' => array_map(function ($postType) {
                return esc_html($postType);
            }, get_option('cpt_tables:tables_enabled', [])),
            'tables_enabled' => 'cpt_tables:tables_enabled',
            'prefix' => $wpdb->prefix . 'cpt_',
            'plugin_slug' => 'cpt-tables',
            'default_post_table' => $wpdb->prefix . 'posts',
            'default_meta_table' => $wpdb->prefix . 'postmeta',
        ];
    }

    /**
     * Load the plugin filter and setup the classes
     */
    public function load()
    {
        $self = new self();

        add_filter('plugin_action_links_cpt-tables/cpt-tables.php', [$this, 'addActionLinks'], 10, 2);
        add_filter('network_admin_plugin_action_links_cpt-tables/cpt-tables.php', [$this, 'addActionLinksNetwork'], 10, 2);
        add_filter('plugin_row_meta', array($this, 'filterPluginRowMeta'), 10, 2);

        $self->setupAdminFilters();
        $self->setupQueryFilters();
        $self->setupSettingsPage();
        $self->setupHelper();

        // Check for triggers on existing cpt tables
        if (count($this->config['post_types']) > 0) {
            $self->checkExistingTriggers();
        }
    }

    /**
     * Clear enabled post types if they don't exist
     * This is needed because the plugin is not aware of post types that are deleted
     * after they were enabled in the plugin settings
     * 
     * This is executed on wp_loaded hook
     */
    public function clearEnabledPostTypes()
    {
        $cleared = false;
        foreach ($this->config['post_types'] as $i => $postType) {
            if (!post_type_exists($postType)) {
                error_log('Post type "' . $postType . '" does not exist. Removing from enabled tables.');
                unset($this->config['post_types'][$i]);
                $cleared = true;
            }
        }
        if ($cleared) {
            update_option($this->config['tables_enabled'], $this->config['post_types']);
        }
    }

    /**
     * Add triggers to cpt tables if they don't exist
     */
    public function checkExistingTriggers()
    {
        global $wpdb;

        foreach ($this->config['post_types'] as $postType) {
            $table_name = $wpdb->prefix . 'cpt_' . str_replace('-', '_', $postType);
            $triggers = $wpdb->get_results("SHOW TRIGGERS LIKE '$table_name'");

            if (count($triggers) === 0) {
                error_log('Table "' . $table_name . '" does not have triggers. Creating triggers.');
                $triggers = new WPCPT_Tables_Triggers($this->db, $this->config);
                $triggers->create($this->config['post_types']);
            }
        }
    }

    /**
     * Add scripts and styles
     */
    public function enqueue_scripts_styles()
    {
        wp_enqueue_style($this->config['plugin_slug'] . '-css', plugin_dir_url(__FILE__) . 'css/styles.css', [], $this->version, 'all');
        wp_enqueue_script($this->config['plugin_slug'] . '-js', plugin_dir_url(__FILE__) . 'js/scripts.js', ['jquery'], $this->version, false);
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

    /**
     * @return void
     */
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
     * Filters the array of row meta for each plugin in the Plugins list table.
     *
     * @param array $plugin_meta An array of the plugin's metadata.
     * @param string   $plugin_file Path to the plugin file relative to the plugins directory.
     * @return array An array of the plugin's metadata.
     */
    public function filterPluginRowMeta(array $plugin_meta, $plugin_file): array
    {
        if ('cpt-tables/cpt-tables.php' !== $plugin_file) {
            return $plugin_meta;
        }

        $plugin_meta[] = sprintf(
            '<a href="%1$s" target="_blank"><span class="dashicons dashicons-star-filled" aria-hidden="true" style="font-size:14px;line-height:1.3"></span>%2$s</a>',
            'https://www.paypal.com/donate/?hosted_button_id=JNA8L66BWE2AA',
            esc_html_x('Buy me a coffee', 'verb', 'cpt-tables')
        );

        return $plugin_meta;
    }

    /**
     * @param array $links
     * @param string $file
     * @return array
     */
    public function addActionLinks($links, $file)
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
    public function addActionLinksNetwork($links, $file)
    {
        $settings = '<a href="' . admin_url('options-general.php?page=' . $this->config['plugin_slug']) . '">' . __('Settings') . '</a>';
        array_unshift($links, $settings);

        return $links;
    }
}
