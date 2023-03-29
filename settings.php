<div class="wrap">
    <div id="poststuff" style="display:block;">
        <h1>Custom Post Type Tables</h1><br />

        <?php
        $show_multisite_warning = false;

        if (is_multisite()) {
            if (defined('SITE_ID_CURRENT_SITE') && get_current_blog_id() === SITE_ID_CURRENT_SITE) {
                $show_multisite_warning = true;
            }
        }
        ?>

        <?php if ($show_multisite_warning) : ?>
            <div class="notice notice-warning">
                <p>Warning: You are in a multisite environment. The settings will only be visible to a site.</p>
            </div>
        <?php else : ?>

            <div id="post-body" class="metabox-holder columns-2">

                <div id="postbox-container-1" class="postbox-container">
                    <div id="side-sortables" class="meta-box-sortables ui-sortable">
                        <?php if (empty($postTypes)) : ?>

                            <div id="postimagediv" class="postbox ">
                                <div class="postbox-header">
                                    <h2 class="hndle ui-sortable-handle">No CPT found</h2>
                                </div>
                                <div class="inside">
                                    <?php if ($this->helper->checkPluginInstalled('custom-post-type-ui/custom-post-type-ui.php')) : ?>
                                        <?php if ($this->helper->checkPluginActive('custom-post-type-ui/custom-post-type-ui.php')) : ?>
                                            <p><i>Custom Post Type UI</i> is installed and active but no custom post types found.</p>
                                            <p><a href="<?php echo admin_url('admin.php?page=cptui_manage_post_types', false); ?>">Start creating your first CPT</a></p>
                                        <?php else : ?>
                                            <p><i>Custom Post Type UI</i> is installed but not active.</p>
                                            <p>Go to your <a href="<?php echo admin_url('plugins.php'); ?>">installed plugins</a> and activate it to create your first CPT.</p>
                                        <?php endif; ?>
                                    <?php elseif ($this->helper->checkPluginInstalled('advanced-custom-fields-pro/acf.php') || $this->helper->checkPluginInstalled('advanced-custom-fields/acf.php')) : ?>
                                        <?php if ($this->helper->checkPluginActive('advanced-custom-fields-pro/acf.php') || $this->helper->checkPluginActive('advanced-custom-fields/acf.php')) : ?>
                                            <p><i>Advanced Custom Fields</i> is installed and active but no custom post types found.</p>
                                            <p><a href="<?php echo admin_url('edit.php?post_type=acf-field-group'); ?>">Start creating your first CPT</a></p>
                                        <?php else : ?>
                                            <p><i>Advanced Custom Fields</i> is installed but not active.</p>
                                            <p>Go to your <a href="<?php echo admin_url('plugins.php'); ?>">installed plugins</a> and activate it to create your first CPT.</p>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if (!$this->helper->checkPluginInstalled('advanced-custom-fields-pro/acf.php') && !$this->helper->checkPluginInstalled('advanced-custom-fields/acf.php') && !$this->helper->checkPluginInstalled('custom-post-type-ui/custom-post-type-ui.php')) : ?>
                                        <p>You don't have installed any custom post types. Please install <a href="https://wordpress.org/plugins/custom-post-type-ui/" target="_blank">Custom Post Type UI</a> or <a href="https://wordpress.org/plugins/advanced-custom-fields/" target="_blank">Advanced Custom Fields</a>.</p>
                                        <p>Or search for any other <a href="https://wordpress.org/plugins/tags/cpt/" target="_blank">CPT plugin</a></p>
                                        <p>Or create Custom Post Types <a href="https://developer.wordpress.org/plugins/post-types/registering-custom-post-types/" target="_blank">programmatically</a></p>
                                    <?php endif; ?>

                                </div>
                            </div>

                        <?php else : ?>

                            <div class="postbox ">
                                <div class="postbox-header">
                                    <h2 class="hndle ui-sortable-handle">How it works</h2>
                                </div>
                                <div class="inside">
                                    <p>As soon as you select a Custom Post Type, a new table is created in the database. This newly created table will be used for all entries of this custom post type.</p>
                                    <p>When you migrate the existing entries, the data from the old table is moved to the new table. </p>
                                    <p><strong>Backup</strong><br />This plugin modifies your WordPress database. Backup your database before migrating in case something doesn't work as you expect.</p>
                                </div>
                            </div>

                            <div class="postbox ">
                                <div class="postbox-header">
                                    <h2 class="hndle ui-sortable-handle">Not usable CPT</h2>
                                </div>
                                <div class="inside">
                                    <p>Maybe they are not public or they are reserved by the core system</p>
                                    <p><?php echo implode(', ', array_map(function ($e) {
                                            return $e['name'];
                                        }, $unpublicPostTypes)); ?></p>
                                </div>
                            </div>

                        <?php endif; ?>
                    </div>
                </div>

                <?php $unmigrated = $settingsClass->getUnmigrated(); ?>

                <div id="postbox-container-2" class="postbox-container">
                    <div id="normal-sortables" class="meta-box-sortables ui-sortable">
                        <div id="cpt-stats" class="postbox cpt-stats">
                            <div class="postbox-header">
                                <h2 class="hndle ui-sortable-handle">Custom Post Types</h2>
                            </div>
                            <div class="inside">
                                <?php if (count($unmigrated) == 0) : ?>
                                    <p>All custom post types are already using custom tables.</p>
                                <?php else : ?>

                                    <table cellspacing="0" cellpadding="10">
                                        <thead>
                                            <tr>
                                                <th>Custom Post Type</th>
                                                <th>New Table Name</th>
                                                <th width="10%" class="center">Entries</th>
                                                <th width="10%" class="center">Meta</th>
                                                <th width="10%"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($unmigrated as $key => $migrate) : ?>
                                                <?php if ($key & 1) : $bgcolor = '#F6F7F7';
                                                else : $bgcolor = 'white';
                                                endif; ?>
                                                <tr style="background-color:<?php echo esc_attr($bgcolor); ?>">
                                                    <td><?php echo esc_attr($migrate['name']); ?><br /><span class="slug">Name: <?php echo esc_attr($migrate['slug']); ?></span></td>
                                                    <td><?php echo esc_attr($migrate['table']); ?></td>
                                                    <td class="center"><?php echo esc_attr($migrate['count']); ?></td>
                                                    <td class="center"><?php echo esc_attr($migrate['count_meta']); ?></td>
                                                    <td style="text-align:right"><a href="#" data-url="<?php echo admin_url('options-general.php?page=' . esc_attr($this->config['plugin_slug']) . '&action=migrate&type=' . esc_attr($migrate['slug']), false); ?>" class="button button-small migrate-button">Migrate</a></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>



                <?php $migrated = $settingsClass->getMigrated(); ?>

                <div id="postbox-container-2" class="postbox-container">
                    <div id="normal-sortables" class="meta-box-sortables ui-sortable">
                        <div id="cpt-stats" class="postbox cpt-stats">
                            <div class="postbox-header">
                                <h2 class="hndle ui-sortable-handle">Migrated Custom Post Types</h2>
                            </div>
                            <div class="inside">
                                <?php if (count($migrated) == 0) : ?>
                                    <p>No custom post types have been migrated yet.</p>
                                <?php else : ?>
                                    <table cellspacing="0" cellpadding="10">
                                        <thead>
                                            <tr>
                                                <th>Custom Post Type</th>
                                                <th>Table Name</th>
                                                <th width="10%" class="center">Entries</th>
                                                <th width="10%" class="center">Meta</th>
                                                <th width="10%"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($migrated as $key => $migrate) : ?>
                                                <?php if ($key & 1) : $bgcolor = '#F6F7F7';
                                                else : $bgcolor = 'white';
                                                endif; ?>
                                                <tr style="background-color:<?php echo $bgcolor; ?>">
                                                    <td><?php echo esc_attr($migrate['name']); ?><br /><span class="slug">Name: <?php echo esc_attr($migrate['slug']); ?></span></td>
                                                    <td><?php echo esc_attr($migrate['table']); ?></td>
                                                    <td class="center"><?php echo esc_attr($migrate['count']); ?></td>
                                                    <td class="center"><?php echo esc_attr($migrate['count_meta']); ?></td>
                                                    <td style="text-align:right"><a href="#" data-url="<?php echo admin_url('options-general.php?page=' . esc_attr($this->config['plugin_slug']) . '&action=revert&type=' . esc_attr($migrate['slug']), false); ?>" class="button button-small revert-button">Revert</a></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        <?php endif; ?>
    </div>
</div>
