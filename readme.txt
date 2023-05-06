=== Custom Post Type Tables ===
Donate link: https://paypal.me/klausi4711
Tags: custom post types, CPT, CMS, post, types, post type, custom, content types, custom content types, post types
Requires at least: 5.9
Tested up to: 6.2
Stable tag: 1.2.6
Requires PHP: 7.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Allow storing custom post types in their own tables in order to make querying large datasets more efficient

== Description ==

Wordpress stores custom post types in the default posts table (typically `wp_posts`), which is fine for most setups. In the majority of use cases, Wordpress sites are not used to store in excess of thousands of posts, and so this sort of setup doesn't place much additional load on servers.

In cases where the site generates a significant amount of posts across multiple post types though, queries can become very expensive - especially where meta generating plugins such as Advanced Custom Fields are involved. Where a Wordpress site is expected to generate thousands of posts (and subsequently, many thousands of rows of post meta) queries can be sped up significantly by splitting out data into separate tables. This plugin splits out data by post type, creating additional tables for each custom post type used. A 'product' custom post type for example will have its posts stored in `product` and its meta in `product_meta`.

If you are using WordPress a little bit longer, you already know that it is an incredibly versatile platform. With its numerous features and extensions, however, it can sometimes be difficult to keep track of and find the right method for managing your content. This is where the use of WordPress custom post types in separate tables comes in!

By outsourcing your custom post types to separate tables, you can better organize and manage your content. It also allows you to have better control over your database and process queries more quickly and efficiently, which can positively impact the performance of your website.

Moreover, outsourcing custom post types to separate tables can also help improve the security of your website. By separating your content into separate tables, you can prevent malware or hackers from accessing your entire database.

Overall, outsourcing WordPress custom post types to separate tables offers a range of benefits for your website. It can improve the organization and management of your content, optimize the performance of your website, and increase your security. If you want to take your website to the next level, you should consider outsourcing your custom post types to separate tables!

= Works with =
* Multisite Installations
* Woocommerce
* YOAST etc.
* Advanced Custom Fields
* … and all other registered active custom post types

= Settings =

As soon as you select a Custom Post Type, a new table is created in the database. This table will be used for all entries of this custom post type.

When you migrate the existing entries, the data from the old table is copied to the new table. This process is not reversible.

Backup your database before migrating in case something doesn't work as you expect.

= Implementation =

Each new post and meta table is created to the same structure as the Wordpress default post and meta tables. This streamlines the storage process and means that Wordpress is capable of interpreting the data wherever it would normally use a `wp_posts` row, e.g. on the admin edit post pages, admin post listing pages, and in the `wp-posts` functions (e.g. `get_post()`).

When new posts are created, a row is inserted into the `wp_posts` table (as normal) and an automatic MySQL trigger is used to copy this data into the new custom table. Queries to the wp_posts and wp_postmeta table are then rewritten to use the custom table, so that all future lookups and updates made by Wordpress and its plugins are made to the new tables. The original `wp_posts` row is retained for lookup purposes, so that we can determine the post type (and therefore custom table) when there is only a post ID available to work with. Since these lookups are (usually) only necessary in the Wordpress admin and exclusively use the primary key, they do not significantly increase the load of the request. Additionally, each ID lookup is made a maximum of once per request and the result is cached on a per-request basis.

To minimise unecessary lookups when writing your own queries, specify the post type you are looking for whenever possible. This will allow the plugin to simply parse the table from the query without having to lookup the post type in the `wp_posts` table.

= Filter Hooks =

**cpt_tables:settings_capability:**

Customise what capability the settings page should be limited to. Default is 'manage_options'.

== Installation ==

The plugin can be found it the WordPress Plugin Directory. Search for "Custom Post Type Tables".

= Manual Installation =

1. Upload the entire `/cpt-tables` directory to the `/wp-content/plugins/` directory.
2. Activate "Custom Post Type Tables" through the 'Plugins' menu in WordPress.
3. Do a database backup manually or use a third party tool
4. Go to "Settings / CPT Tables"
4. Migrate the post types you want

== Contribute ==

Developed with ♥ by [Sebastian](https://lightapps.de).

This plugin was initially created for own usage to get a big database cleaner.

Check out the [GitHub repository](https://github.com/caspahouzer/wp-cpt-tables) and submit pull requests or open issues

== Changelog ==

=== 1.2.6 ===

* Find orphaned meta values and copy to new custom table
* Trigger optimization

=== 1.2.5.2 ===

* Hotfix: Altered correct auto_increment field for meta table

=== 1.2.5 ===

* Removed autoincrement from copied table as it is controles by the posts_table (Revert CPT Tables and migrate them again to get the changes working)

=== 1.2.4 ===

* Bugfix in cleanup-cronjob

=== 1.2.3 ===

* Revert tables on plugin deactivation

=== 1.2.2 ===

* German translation
* Add missing migrated post_types, when the custom table exists
* Fixed bug on deactivate the plugin

=== 1.2.1 ===

* Load plugin the right time when all post types are registered
* Refactoring and bugfixing
* Added language pot template

=== 1.1.0 ===

* Clear orphaned post_types from activated entries
* You can activate a cronjob for table optimization in the settings

=== 1.0.9 ===

* Automatically remove orphaned entries in the main post table under certain conditions

=== 1.0.8 ===

* Re-create triggers if they don't exist

=== 1.0.7 ===

* tested with WordPress 6.2

=== 1.0.6 ===

* Fixed debugger warnings
* Clean up code
* Escape vars
* Sanitize vars
* Refactoring

=== 1.0.1 ===

* Added _ to accepted chars of post type
