<?php

global $wpdb;

return [
    'post_types' => array_map(function ($postType) {
        return esc_html($postType);
    }, get_option('cpt_tables:tables_enabled', [])),
    'tables_enabled' => 'cpt_tables:tables_enabled',
    'prefix' => $wpdb->prefix . get_option('cpt_tables:tables_prefix', 'cpt_'),
    'plugin_slug' => 'wp-cpt-tables',
    'default_post_table' => $wpdb->prefix . 'posts',
    'default_meta_table' => $wpdb->prefix . 'postmeta',
];
