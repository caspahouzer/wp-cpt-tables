<?php

// Check if needed functions exists - if not, require them
if (!function_exists('get_plugins') || !function_exists('is_plugin_active')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

class WPCPT_Tables_SettingsPage
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
     * The config array
     * @var array
     */
    private $config;

    /**
     * The redirect uri
     * @var string
     */
    private $redirect_uri;

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
     */
    public function __construct(WPCPT_Tables_Table $table, WPCPT_Tables_Triggers $triggers, $config)
    {
        // $this->enqueue_styles();
        $this->notices = new WPCPT_Tables_Notices;
        $this->helper = new WPCPT_Tables_Helper;

        $this->config = $config;

        $this->table = $table;
        $this->triggers = $triggers;

        $this->redirect_uri = admin_url('options-general.php?page=' . $this->config['plugin_slug']);

        if (isset($_GET['action']) && $_GET['action'] == 'migrate' && isset($_GET['type'])) {
            $this->startMigrateCustomPostType($_GET['type']);
            exit;
        }

        if (isset($_GET['action']) && $_GET['action'] == 'revert' && isset($_GET['type'])) {
            $this->startRevertCustomPostType($_GET['type']);
            exit;
        }

        add_filter('admin_menu', [$this, 'addSettingsPage']);
    }

    /**
     * Add settings page to admin settings menu
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
        error_log(print_r($enabledPostTypes, true));
        if (($key = array_search($postType, $enabledPostTypes)) !== false) {
            unset($enabledPostTypes[$key]);
            $enabledPostTypes = array_values($enabledPostTypes);
            update_option($this->config['tables_enabled'], $enabledPostTypes, true);
        }
        error_log(print_r($enabledPostTypes, true));

        $this->triggers->create($enabledPostTypes);

        // Revert the custom post type
        $this->revertCustomPostType($postType);

        // Add notice and redirect
        $this->notices->add(sprintf('Custom post type <strong>%s</strong> has been reverted to the posts table', $postType));
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
    private function startMigrateCustomPostType($postType)
    {
        $enabledPostTypes = $this->getEnabledPostTypes();
        if (!in_array($postType, $enabledPostTypes)) {
            $enabledPostTypes[] = $postType;
            update_option($this->config['tables_enabled'], array_values($enabledPostTypes), true);
        }

        // Create new tables and rebuild triggers
        $this->updateDatabaseSchema($enabledPostTypes);

        // Migrate all existing custom post types
        $this->migrateCustomPostTypes($postType);

        // Add notice and redirect
        $this->notices->add(sprintf('Custom post type <strong>%s</strong> has been migrated', $postType));
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
