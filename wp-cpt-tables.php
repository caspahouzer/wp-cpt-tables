<?php

/*
 * Plugin Name:         CPT Tables
 * Plugin URI:          https://wordpress.org/plugins/cpt-tables/
 * Description:         Allow storing custom post types in their own tables in order to make querying large datasets more efficient
 * Version:             1.2.6
 * Requires at least:   5.9
 * Requires PHP:        7.1
 * Author:              Sebastian Klaus
 * Author URI:          https://github.com/caspahouzer/wp-cpt-tables/
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         cpt-tables
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!version_compare(PHP_VERSION, '7.1', '>=')) {
    return;
}

if (!class_exists('WPCPT_Tables_Core')) {
    require_once __DIR__ . '/vendor/Connector.php';

    require_once __DIR__ . '/core.php';

    $includes = glob(dirname(__FILE__) . '/lib/*.php');
    foreach ($includes as $include) {
        require_once($include);
    }

    $core = new WPCPT_Tables_Core(new WPCPT_Tables_Db());
    $core->initConfig();

    register_activation_hook(__FILE__, [$core, 'activatePlugin']);
    register_deactivation_hook(__FILE__, [$core, 'deactivatePlugin']);

    add_action('admin_enqueue_scripts', [$core, 'enqueueScriptsStyles']);

    // Load the plugin filter and setup the classes
    add_action('plugins_loaded', [$core, 'load']);

    // Clear enabled post types if they don't exist
    add_action('wp_loaded', [$core, 'clearEnabledPostTypes']);
}
