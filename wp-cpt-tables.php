<?php

/*
 * Plugin Name:         Custom Post Type Tables
 * Plugin URI:          https://wordpress.org/plugins/wp-cpt-tables/
 * Description:         Allow storing custom post types in their own tables in order to make querying large datasets more efficient
 * Version:             1.0.1
 * Requires at least:   5.9
 * Requires PHP:        7.1
 * Author:              Sebastian Klaus
 * Author URI:          https://lightapps.de
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         wp-cpt-tables
 */

if (!defined('WPINC')) {
    die;
}

if (!defined('WPCPTTABLES_BASENAME')) {
    define('WPCPTTABLES_BASENAME', plugin_basename(__FILE__));
}

require_once __DIR__ . '/core.php';

$includes = glob(dirname(__FILE__) . '/lib/*.php');
foreach ($includes as $include) {
    require_once($include);
}

$core = new Core(new Db());

register_activation_hook(__FILE__, [$core, 'activate']);
register_deactivation_hook(__FILE__, [$core, 'deactivate']);

add_action('admin_enqueue_scripts', [$core, 'enqueue_scripts_styles']);
add_action('plugins_loaded', [$core, 'load']);
