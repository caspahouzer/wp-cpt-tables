<?php

class Helper
{
    private $db;
    /**
     * @return void
     */
    public function __construct()
    {
        $this->db = new Db;
    }

    /**
     * Check if plugin is installed by getting all plugins from the plugins dir
     *
     * @param $plugin_slug
     *
     * @return bool
     */
    public function checkPluginInstalled($plugin_slug): bool
    {
        $installed_plugins = get_plugins();

        return array_key_exists($plugin_slug, $installed_plugins) || in_array($plugin_slug, $installed_plugins, true);
    }

    /**
     * Check if plugin is installed
     *
     * @param string $plugin_slug
     *
     * @return bool
     */
    public function checkPluginActive($plugin_slug): bool
    {
        if (is_plugin_active($plugin_slug)) {
            return true;
        }

        return false;
    }

    public function getCount($table, $type = '')
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
}
