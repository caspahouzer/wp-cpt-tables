<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/Optimize.php';

// Check if needed functions exists - if not, require them
if (!function_exists('get_plugins') || !function_exists('is_plugin_active')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

class WPCPT_Tables_Settings
{
    /**
     * The menu and page name for this settings page
     * @var string
     */
    private $name = 'CPT Tables';

    /**
     * The capability required to access this page
     * @var string
     */
    private $capability = 'manage_options';

    /**
     * The table class
     * @var WPCPT_Tables_Table
     */
    private $table;

    /**
     * The triggers class
     * @var WPCPT_Tables_Triggers
     */
    private $triggers;

    /**
     * The notices class
     * @var WPCPT_Tables_Notices
     */
    private $notices;

    /**
     * The helper class
     * @var WPCPT_Tables_Helper
     */
    private $helper;

    /**
     * The connector class
     * @var LightApps_Connector
     */
    private $connector;

    /**
     * The config array
     * @var array
     */
    private $config;

    /**
     * The redirect uri
     * @var string
     */
    private $redirect_uri;

    private $optimize;

    /**
     * The public WP post types to exclude from the settings page CPT list
     * @var array
     */
    private $exclude = [
        'post',
        'page',
        'media',
        'attachment',
    ];

    /**
     * Add settings page. If form has been submitted, route to save method.
     *
     * @param WPCPT_Tables_Table   $table
     * @param WPCPT_Tables_Triggers $triggers
     * @param array $config
     * @return void
     */
    public function __construct(WPCPT_Tables_Table $table, WPCPT_Tables_Triggers $triggers, array $config)
    {
        // $this->enqueue_styles();
        $this->notices = new WPCPT_Tables_Notices;
        $this->helper = new WPCPT_Tables_Helper;

        $this->optimize = new WPCPT_Tables_Optimize();

        $this->config = $config;

        $this->connector = new LightApps_Connector($this->config);

        $this->table = $table;
        $this->triggers = $triggers;

        $this->redirect_uri = admin_url('options-general.php?page=' . $this->config['plugin_slug']);

        if (isset($_GET['action']) && sanitize_key($_GET['action']) == 'migrate' && isset($_GET['type'])) {
            $this->startMigrateCustomPostType(sanitize_key($_GET['type']));
            $this->connector->trigger();
            exit;
        }

        if (isset($_GET['action']) && sanitize_key($_GET['action']) == 'revert' && isset($_GET['type'])) {
            $this->startRevertCustomPostType(sanitize_key($_GET['type']));
            $this->connector->trigger();
            exit;
        }

        if (isset($_GET['action']) && sanitize_key($_GET['action']) == 'optimize' && isset($_GET['type'])) {
            $type = sanitize_key($_GET['type']);

            if ($type == 'uncron') {
                wp_unschedule_event(wp_next_scheduled($this->optimize->hook_cleanup), $this->optimize->hook_cleanup);
                wp_unschedule_event(wp_next_scheduled($this->optimize->hook_optimize), $this->optimize->hook_optimize);
                update_option('cpt_tables:optimize', false);
                $this->notices->add(__('Cronjobs removed', 'cpt-tables'), 'success');
                wp_safe_redirect($this->redirect_uri);
            }

            if ($type == 'cron') {
                $this->notices->add(__('Optimization cronjobs active', 'cpt-tables'), 'success');
                update_option('cpt_tables:optimize', true);
                wp_safe_redirect($this->redirect_uri);
            }

            if ($type == 'now') {
                $this->optimize->cleanup();
                $this->optimize->optimize();
                $this->notices->add(__('Tables cleaned up and optimized', 'cpt-tables'), 'success');
                wp_safe_redirect($this->redirect_uri);
            }
            $this->connector->trigger();
            exit;
        }

        add_filter('admin_menu', [$this, 'addSettingsPage']);
    }

    /**
     * Add settings page to admin settings menu
     * 
     * @return void
     */
    public function addSettingsPage()
    {
        $this->capability = apply_filters('cpt_tables:settings_capability', 'manage_options');

        add_options_page(
            $this->name,
            $this->name,
            $this->capability,
            $this->config['plugin_slug'],
            [$this, 'showSettingsPage']
        );
    }

    /**
     * Shows the settings page or a 404 if the user does not have access
     *
     * @return void
     */
    public function showSettingsPage()
    {
        if (!current_user_can(apply_filters($this->capability, 'manage_options'))) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        $postTypes = $this->getAllPostTypes();
        $unpublicPostTypes = $this->getAllPostTypes(false);
        $enabled = $this->getEnabledPostTypes();
        $settingsClass = $this;

        require_once __DIR__ . '/../settings.php';
    }

    /**
     * Revert all existing custom post type tables back to the posts and meta tables
     *
     * @return array
     */
    private function startRevertCustomPostType($postType)
    {
        $enabledPostTypes = $this->getEnabledPostTypes();
        if (($key = array_search($postType, $enabledPostTypes)) !== false) {
            unset($enabledPostTypes[$key]);
            $enabledPostTypes = array_values($enabledPostTypes);
            update_option($this->config['tables_enabled'], $enabledPostTypes, true);
        }

        $this->triggers->create($enabledPostTypes);

        // Revert the custom post type
        $this->revertCustomPostType($postType);

        // Add notice and redirect
        $this->notices->add(sprintf(__('Custom post type <strong>%s</strong> has been reverted to the posts table', 'cpt-tables'), $postType));
        wp_safe_redirect($this->redirect_uri);
    }

    /**
     * Revert the custom post types
     *
     * @return array
     */
    private function revertCustomPostType($postTypes)
    {
        if (!is_array($postTypes)) {
            $postTypes = [$postTypes];
        }
        foreach ($postTypes as $postType) {
            $this->table->revert($postType);
        }
    }

    /**
     * Migrate all existing custom post types to the new tables
     *
     * @return array
     */
    private function startMigrateCustomPostType(string $postType)
    {
        $enabledPostTypes = $this->getEnabledPostTypes();
        if (!in_array($postType, $enabledPostTypes)) {
            $enabledPostTypes[] = esc_attr($postType);
            update_option($this->config['tables_enabled'], array_values($enabledPostTypes), true);
        }

        // Create new tables and rebuild triggers
        $this->updateDatabaseSchema($enabledPostTypes);

        // Migrate all existing custom post types
        $this->migrateCustomPostTypes($postType);

        // Add notice and redirect
        $this->notices->add(sprintf(__('Custom post type <strong>%s</strong> has been migrated', 'cpt-tables'), $postType));
        wp_safe_redirect($this->redirect_uri);
    }

    /**
     * Migrate all existing custom post types to the new tables
     * 
     * @param  string|array  $newPostTypes
     *
     * @return array
     */
    private function migrateCustomPostTypes($newPostTypes)
    {
        if (!is_array($newPostTypes)) {
            $newPostTypes = [$newPostTypes];
        }
        foreach ($newPostTypes as $postType) {
            $this->table->migrate($postType);
        }
    }

    /**
     * Create new tables and rebuild triggers
     *
     * @param  array  $postTypes
     * @return void
     */
    private function updateDatabaseSchema(array $postTypes)
    {
        foreach ($postTypes as $postType) {
            $this->table->create($postType);
        }

        $this->triggers->create($postTypes);
    }

    /**
     * Gets the option that stores enabled post type tables and unserializes it
     * @return array
     */
    public function getEnabledPostTypes(): array
    {
        return array_values(get_option($this->config['tables_enabled'], []));
    }

    /**
     * Parses the public WP post types object into an array with the name
     * and the slug in. Then order alphabetically.
     *
     * @return array
     */
    public function getAllPostTypes($getPublic = true): array
    {
        $postTypes = array_map(
            function ($postType) {
                return ['name' => $postType['labels']['name'], 'slug' => $postType['name']];
            },
            json_decode(json_encode(get_post_types(['public' => $getPublic], 'object')), true)
        );

        $postTypes = array_filter($postTypes, function ($item) {
            return !in_array($item['slug'], $this->exclude);
        });

        usort($postTypes, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $postTypes;
    }

    /**
     * Get the statistics for each post type
     *
     * @return array
     */
    public function getUnmigrated()
    {
        global $wpdb;

        $postTypes = $this->getAllPostTypes();
        $enabled = $this->getEnabledPostTypes();

        $entries = [];

        foreach ($postTypes as $postType) {
            if (!in_array($postType['slug'], $enabled)) {
                $table = $this->table->getTableName($postType['slug']);
                $entries[] = [
                    'slug' => $postType['slug'],
                    'name' => $postType['name'],
                    'original_table' => $wpdb->prefix . 'posts',
                    'table' => $table,
                    'count' => $this->helper->getCount($wpdb->prefix . 'posts', $postType['slug']),
                    'count_meta' => $this->helper->getCount($wpdb->prefix . 'postmeta', $postType['slug']),
                ];
            }
        }

        return $entries;
    }

    /**
     * Get the migrated post types
     *
     * @return array
     */
    public function getMigrated()
    {
        global $wpdb;

        $postTypes = $this->getAllPostTypes();
        $enabled = $this->getEnabledPostTypes();

        $entries = [];

        foreach ($postTypes as $postType) {
            if (in_array($postType['slug'], $enabled)) {
                $table = $this->table->getTableName($postType['slug']);

                $entries[] = [
                    'slug' => $postType['slug'],
                    'name' => $postType['name'],
                    'original_table' => $wpdb->prefix . 'posts',
                    'table' => $table,
                    'count' => $this->helper->getCount($table),
                    'table_meta' => $table . '_meta',
                    'count_meta' => $this->helper->getCount($table . '_meta'),
                ];
            }
        }

        return $entries;
    }
}
