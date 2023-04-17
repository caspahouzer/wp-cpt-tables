<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clean and optimize meta tables
 */
class WPCPT_Tables_Optimize
{

    public $hook_cleanup = 'wp-cpt/meta_table/cleanup';
    public $hook_optimize = 'wp-cpt/meta_table/optimize';

    /** 
     * Register cronjob in wordpress system
     * 
     * @return void
     */
    public function __construct()
    {
        if (get_option('cpt_tables:optimize', false) == false) {
            return;
        }

        if (!wp_next_scheduled($this->hook_cleanup)) {
            wp_schedule_event(time(), 'daily', $this->hook_cleanup);
        }

        add_action($this->hook_cleanup, [$this, 'cleanup']);

        if (!wp_next_scheduled($this->hook_optimize)) {
            wp_schedule_event(time(), 'weekly', $this->hook_optimize);
        }

        add_action($this->hook_optimize, [$this, 'optimize']);
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

        // Check for cpt-tables
        $cpt_tables = get_option('cpt_tables:tables_enabled', []);
        if (count($cpt_tables) > 0) {
            foreach ($cpt_tables as $cpt_table) {
                $wpdb->query('OPTIMIZE TABLE ' . $wpdb->prefix . 'cpt_' . $cpt_table . '_meta');
            }
        }
    }
}
