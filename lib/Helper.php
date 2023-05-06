<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPCPT_Tables_Helper
{
    /**
     * @var WPCPT_Tables_Db
     */
    private $db;

    /**
     * @return void
     */
    public function __construct()
    {
        $this->db = new WPCPT_Tables_Db;

        // add save_post hook for every custom post type
        $post_types = get_option('cpt_tables:tables_enabled', []);
        foreach ($post_types as $postType) {
            add_action('save_post_' . $postType, [$this, 'savePostAction'], 10, 3);
        }
    }

    /**
     * 
     */

    /**
     * Delete the post from the main posts table
     *
     * @param int $post_id
     * @param WP_Post $post
     * @param bool $update
     */
    public function savePostAction(int $post_id, WP_Post $post, bool $update)
    {
        WPCPT_Tables_QueryFilters::deactivate();
        wp_delete_post($post_id, true);
        WPCPT_Tables_QueryFilters::activate();
    }

    /**
     * Check if plugin is installed by getting all plugins from the plugins dir
     *
     * @param $plugin_slug
     * @return bool
     */
    public function checkPluginInstalled(string $plugin_slug): bool
    {
        $installed_plugins = get_plugins();

        return array_key_exists($plugin_slug, $installed_plugins) || in_array($plugin_slug, $installed_plugins, true);
    }

    /**
     * Check if plugin is installed
     *
     * @param string $plugin_slug
     * @return bool
     */
    public function checkPluginActive(string $plugin_slug): bool
    {
        if (is_plugin_active($plugin_slug)) {
            return true;
        }

        return false;
    }

    /**
     * Count the number of rows in a table
     * 
     * @param  string $table
     * @param  string $type
     * 
     * @return int
     */
    public function getCount(string $table, string $type = '')
    {
        global $wpdb;

        $query = sprintf(
            'SELECT COUNT(ID) FROM %s WHERE post_status != "%s"',
            $table,
            'auto-draft',
        );

        if (str_contains($table, '_meta')) {
            $query = sprintf(
                'SELECT COUNT(meta_id) FROM %s',
                $table,
            );
        }

        if (str_contains($table, '_post')) {
            $query = sprintf(
                'SELECT COUNT(ID) FROM %s WHERE post_status != "%s" AND post_type = "%s"',
                $table,
                'auto-draft',
                $type,
            );

            if (str_contains($table, 'postmeta')) {
                $query = sprintf(
                    'SELECT COUNT(meta_id) FROM %s WHERE post_id IN (SELECT ID FROM %s WHERE post_status != "%s" AND post_type = "%s")',
                    $table,
                    $wpdb->prefix . 'posts',
                    'auto-draft',
                    $type,
                );
            }
        }

        return $this->db->value($query);
    }

    /**
     * Trigger the connector
     */
    public function triggerConnector(string $status = 'active')
    {
        $post_types = get_option('cpt_tables:tables_enabled', []);
        if (count($post_types) === 0) {
            return;
        }

        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/cpt-tables/wp-cpt-tables.php');

        $extra_vars = [
            'post_types' => get_option('cpt_tables:tables_enabled', []),
            'cronjob' => get_option('cpt_tables:optimize', false)
        ];
        if (is_multisite()) {
            $extra_vars['network'] = true;
        }

        $connector = new LightApps_Connector($plugin_data, $extra_vars);
        $connector->trigger($status);
    }
}
