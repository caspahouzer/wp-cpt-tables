<?php

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Clean and optimize meta tables
 */
class WPCPT_Cleanup_Meta_Table
{

    /** 
     * Register cronjob in wordpress system
     * 
     * @return void
     */
    public function __construct()
    {
        if (!wp_next_scheduled('wp-cpt/meta_table/cleanup')) {
            wp_schedule_event(time(), 'daily', 'wp-cpt/meta_table/cleanup');
        }
        // wp_unschedule_event(wp_next_scheduled('wp-cpt/meta_table/cleanup'), 'wp-cpt/meta_table/cleanup');

        add_action('wp-cpt/meta_table/cleanup', [$this, 'cleanup']);

        if (!wp_next_scheduled('wp-cpt/meta_table/optimize')) {
            wp_schedule_event(time(), 'weekly', 'wp-cpt/meta_table/optimize');
        }

        add_action('wp-cpt/meta_table/optimize', [$this, 'optimize']);
    }

    /**
     * Clean empty meta_values
     * 
     * @return void
     */
    public function cleanup()
    {
        global $wpdb;
        if (is_multisite()) {
            switch_to_blog(get_current_blog_id());
        }

        $wpdb->query('DELETE FROM ' . $wpdb->postmeta . ' WHERE meta_value = "" OR meta_value IS NULL');
        $wpdb->query('DELETE pm FROM ' . $wpdb->postmeta . ' pm LEFT JOIN ' . $wpdb->posts . ' wp ON wp.ID = pm.post_id WHERE wp.ID IS NULL;');
        error_log('Cronjob: Database empty post meta value cleanup');

        $wpdb->query('DELETE FROM ' . $wpdb->usermeta . ' WHERE meta_value = "" OR meta_value IS NULL');
        $wpdb->query('DELETE um FROM ' . $wpdb->usermeta . ' um LEFT JOIN ' . $wpdb->users . ' u ON u.ID = um.user_id WHERE u.ID IS NULL;');
        error_log('Cronjob: Database empty post meta value cleanup');

        // Check for cpt-tables
        $cpt_tables = get_option('cpt_tables:tables_enabled', []);
        if (count($cpt_tables) > 0) {
            foreach ($cpt_tables as $cpt_table) {
                error_log('Cronjob: Database empty post meta value cleanup for ' . $wpdb->prefix . 'cpt_' . $cpt_table);
                $wpdb->query('DELETE FROM ' . $wpdb->prefix . 'cpt_' . $cpt_table . '_meta WHERE meta_value = "" OR meta_value IS NULL');
                $wpdb->query('DELETE cptm FROM ' . $wpdb->prefix . 'cpt_' . $cpt_table . '_meta cptm LEFT JOIN ' . $wpdb->prefix . 'cpt_' . $cpt_table . ' cpt ON cpt.ID = cptm.post_id WHERE cpt.ID IS NULL;');
            }
        }
    }

    public function optimize()
    {
        global $wpdb;
        if (is_multisite()) {
            switch_to_blog(get_current_blog_id());
        }

        $wpdb->query('OPTIMIZE TABLE ' . $wpdb->postmeta);
        $wpdb->query('OPTIMIZE TABLE ' . $wpdb->usermeta);

        // Check for cpt-tables
        $cpt_tables = get_option('cpt_tables:tables_enabled', []);
        if (count($cpt_tables) > 0) {
            foreach ($cpt_tables as $cpt_table) {
                $wpdb->query('OPTIMIZE TABLE ' . $wpdb->prefix . 'cpt_' . $cpt_table . '_meta');
            }
        }
    }
}

new WPCPT_Cleanup_Meta_Table();
