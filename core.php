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
     * @var LightApps_Connector
     */
    private $connector;

    /**
     * @var string
     */
    public $version = '';

    /**
     * The plugin constructor
     */
    public function __construct()
    {
        $this->db = new WPCPT_Tables_Db;
        $this->helper = new WPCPT_Tables_Helper;

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

        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/cpt-tables/wp-cpt-tables.php');
        $this->version = $plugin_data['Version'];
        $this->config['version'] = $this->version;
    }

    /**
     * Load the plugin filter and setup the classes
     */
    public function load()
    {
        $self = new self();

        add_filter('plugin_action_links_cpt-tables/wp-cpt-tables.php', [$this, 'addActionLinks'], 10, 2);
        add_filter('network_admin_plugin_action_links_cpt-tables/wp-cpt-tables.php', [$this, 'addActionLinksNetwork'], 10, 2);
        add_filter('plugin_row_meta', array($this, 'filterPluginRowMeta'), 10, 2);

        $self->loadLocalization();
        $self->setupConnector();
        $self->setupAdminFilters();
        $self->setupQueryFilters();
        $self->setupSettings();
        $self->checkVersion();

        // Check for triggers on existing cpt tables
        if (count($this->config['post_types']) > 0) {
            $self->checkTablesAndCompareToPostTypes();
            $self->checkExistingTriggers();
        }

        add_action('wp_loaded', [$self, 'initFilters']);
    }

    /**
     * Load localization
     */
    private function loadLocalization()
    {
        load_plugin_textdomain('cpt-tables', false, 'cpt-tables/languages/');
    }

    /**
     * Check the new version
     */
    private function checkVersion()
    {
        $version = get_option('cpt_tables:version', '0.0.0');
        if (version_compare($version, $this->version) === -1) {
            $this->helper->triggerConnector();
            update_option('cpt_tables:version', $this->version);
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
     * Check if tables exist and compare to enabled post types
     */
    public function checkTablesAndCompareToPostTypes()
    {
        global $wpdb;
        $tables = $wpdb->get_col("SHOW TABLES LIKE '{$this->config['prefix']}%'");
        $tables = array_map(function ($table) {
            return str_replace($this->config['prefix'], '', $table);
        }, $tables);
        // remove array entries with _meta
        $tables = array_filter($tables, function ($table) {
            return strpos($table, '_meta') === false;
        });
        $missing_tables = array_diff($tables, $this->config['post_types']);
        if (count($missing_tables) > 0) {
            error_log('Tables found that are not enabled in the plugin settings. Adding to migrated tables.');
            $this->config['post_types'] = array_merge($this->config['post_types'], $missing_tables);
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
    public function enqueueScriptsStyles()
    {
        wp_enqueue_style($this->config['plugin_slug'] . '-css', plugin_dir_url(__FILE__) . 'css/styles.css', [], $this->version, 'all');
        wp_enqueue_script($this->config['plugin_slug'] . '-js', plugin_dir_url(__FILE__) . 'js/scripts.js', ['jquery'], $this->version, false);
    }

    private function setupConnector()
    {
        $plugin_data = get_plugin_data(dirname(__FILE__) . '/wp-cpt-tables.php');
        $this->connector = new LightApps_Connector($plugin_data, $this->config);
    }

    /**
     * Init filters again after wp_loaded hook
     * 
     */
    public function initFilters()
    {
        $this->setupAdminFilters();
        $this->setupQueryFilters();
    }

    /**
     * Start admin filter
     */
    private function setupAdminFilters()
    {
        new WPCPT_Tables_AdminFilters;
    }

    /**
     * Start query filter
     */
    private function setupQueryFilters()
    {
        new WPCPT_Tables_QueryFilters($this->db, $this->config);
    }

    /**
     * Start settings
     */
    private function setupSettings()
    {
        new WPCPT_Tables_Settings(
            new WPCPT_Tables_Table($this->db, $this->config),
            new WPCPT_Tables_Triggers($this->db, $this->config),
            $this->config
        );
    }

    /**
     * Activate hook
     */
    public function activatePlugin()
    {
        register_uninstall_hook(__FILE__, [$this, 'delete_plugin']);
        $this->helper->triggerConnector();
        flush_rewrite_rules();
    }

    /**
     * Deactivate hook
     */
    public function deactivatePlugin()
    {
        $this->helper->triggerConnector('draft');
        flush_rewrite_rules();
    }

    public function delete_plugin()
    {
        $this->helper->triggerConnector('delete');
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
        if ('cpt-tables/wp-cpt-tables.php' !== $plugin_file) {
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
