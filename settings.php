<div class="wrap">
    <div id="poststuff" style="display:block;">
        <h1>Custom Post Type Tables</h1><br />

        <?php
        $show_multisite_warning = false;
        if (is_multisite() && defined('BLOG_ID_CURRENT_SITE')) {
            if (defined('BLOG_ID_CURRENT_SITE') && get_current_blog_id() === BLOG_ID_CURRENT_SITE) {
                $show_multisite_warning = true;
            }
        }
        ?>

        <?php if ($show_multisite_warning) : ?>
            <div class="notice notice-warning">
                <p><?php echo __('Warning: You are in a multisite environment. The settings will only be visible to a site.', 'cpt-tables') ?></p>
            </div>
        <?php else : ?>

            <?php $unmigrated = $settingsClass->getUnmigrated(); ?>
            <?php $migrated = $settingsClass->getMigrated(); ?>

            <div id="post-body" class="metabox-holder columns-2">

                <div id="postbox-container-1" class="postbox-container">
                    <div id="side-sortables" class="meta-box-sortables ui-sortable">
                        <?php if (empty($postTypes)) : ?>

                            <div id="postimagediv" class="postbox ">
                                <div class="postbox-header">
                                    <h2><?php echo __('No CPT found', 'cpt-tables') ?></h2>
                                </div>
                                <div class="inside">
                                    <?php if ($this->helper->checkPluginInstalled('custom-post-type-ui/custom-post-type-ui.php')) : ?>
                                        <?php if ($this->helper->checkPluginActive('custom-post-type-ui/custom-post-type-ui.php')) : ?>
                                            <p><?php echo sprintf(__('%s is installed and active but no custom post types found.', 'cpt-tables'), '<i>Custom Post Type UI</i>'); ?></p>
                                            <p><a href="<?php echo admin_url('admin.php?page=cptui_manage_post_types', false); ?>"><?php echo __('Start creating your first CPT', 'cpt-tables'); ?></a></p>
                                        <?php else : ?>
                                            <p><?php echo sprintf(__('%s is installed but not active.', 'cpt-tables'), '<i>Custom Post Type UI</i>'); ?></p>
                                            <p>Go to your <a href="<?php echo admin_url('plugins.php'); ?>">installed plugins</a> and activate it to create your first CPT.</p>
                                        <?php endif; ?>
                                    <?php elseif ($this->helper->checkPluginInstalled('advanced-custom-fields-pro/acf.php') || $this->helper->checkPluginInstalled('advanced-custom-fields/acf.php')) : ?>
                                        <?php if ($this->helper->checkPluginActive('advanced-custom-fields-pro/acf.php') || $this->helper->checkPluginActive('advanced-custom-fields/acf.php')) : ?>
                                            <p><?php echo sprintf(__('%s is installed and active but no custom post types found.', 'cpt-tables'), '<i>Advanced Custom Fields</i>'); ?></p>
                                            <p><a href="<?php echo admin_url('edit.php?post_type=acf-field-group'); ?>"><?php echo __('Start creating your first CPT', 'cpt-tables') ?></a></p>
                                        <?php else : ?>
                                            <p><?php echo sprintf(__('%s is installed but not active.', 'cpt-tables'), '<i>Advanced Custom Fields</i>'); ?></p>
                                            <p><?php echo sprintf(__('Go to your <a href="%s">installed plugins</a> and activate it to create your first CPT', 'cpt-tables'), admin_url('plugins.php')); ?></p>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if (!$this->helper->checkPluginInstalled('advanced-custom-fields-pro/acf.php') && !$this->helper->checkPluginInstalled('advanced-custom-fields/acf.php') && !$this->helper->checkPluginInstalled('custom-post-type-ui/custom-post-type-ui.php')) : ?>
                                        <p><?php echo vsprintf(__('You don\'t have installed any custom post types. Please install %s or %s.', 'cpt-tables'), [
                                                '<a href="https://wordpress.org/plugins/custom-post-type-ui/" target="_blank">Custom Post Type UI</a>',
                                                '<a href="https://wordpress.org/plugins/advanced-custom-fields/" target="_blank">Advanced Custom Fields</a>',
                                            ]); ?></p>
                                        <p><?php echo sprintf(
                                                __('Or search for any other %s', 'cpt-tables'),
                                                '<a href="https://wordpress.org/plugins/tags/cpt/" target="_blank">CPT plugin</a>',
                                            ); ?></p>
                                        <p><?php echo sprintf(
                                                __('Or create Custom Post Types %s', 'cpt-tables'),
                                                '<a href="https://developer.wordpress.org/plugins/post-types/registering-custom-post-types/" target="_blank">programmatically</a>',
                                            ); ?></p>
                                    <?php endif; ?>

                                </div>
                            </div>

                        <?php else : ?>

                            <?php if (count($migrated) > 0) : ?>
                                <?php $cron_installed = get_option('cpt_tables:optimize', false); ?>
                                <div class="postbox ">
                                    <div class="postbox-header">
                                        <h2><?php echo __('Tables to optimize', 'cpt-tables') ?></h2>
                                    </div>
                                    <div class="inside">
                                        <p><?php echo __('The use of meta tables often leaves residual data that is not needed and bloats the database.', 'cpt-tables') ?></p>
                                        <?php if ($cron_installed) : ?>
                                            <p><strong><?php echo __('Cronjob is installed', 'cpt-tables') ?></strong><br /><a href="<?php echo admin_url('options-general.php?page=' . esc_attr($this->config['plugin_slug']) . '&action=optimize&type=uncron'); ?>" class="button button-small cronjob-button"><?php echo __('Remove Cronjob', 'cpt-tables') ?></a></p>
                                        <?php else : ?>
                                            <p><?php echo __('You can install this cron job to optimize the tables at regular intervals.', 'cpt-tables') ?></p>
                                            <p><a href="<?php echo admin_url('options-general.php?page=' . esc_attr($this->config['plugin_slug']) . '&action=optimize&type=cron'); ?>" class="button button-small cronjob-button"><?php echo __('Install Cronjob', 'cpt-tables') ?></a></p>
                                        <?php endif; ?>
                                        <p><a href="<?php echo admin_url('options-general.php?page=' . esc_attr($this->config['plugin_slug']) . '&action=optimize&type=now'); ?>" class="button button-small cronjob-button"><?php echo __('Optimize now!', 'cpt-tables') ?></a></p>
                                        <p>
                                            <strong><?php echo __('Tables to optimize', 'cpt-tables') ?>:</strong><br />
                                            postmeta,
                                            <?php echo implode(', ', array_map(function ($e) {
                                                return $e['slug'] . '_meta';
                                            }, $migrated)); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="postbox ">
                                <div class="postbox-header">
                                    <h2><?php echo __('How it works', 'cpt-tables') ?></h2>
                                </div>
                                <div class="inside">
                                    <p><?php echo __('As soon as you select a Custom Post Type, a new table is created in the database. This newly created table will be used for all entries of this custom post type.', 'cpt-tables') ?></p>
                                    <p><?php echo __('When you migrate the existing entries, the data from the old table is moved to the new table.', 'cpt-tables') ?></p>
                                    <p><strong><?php echo __('Backup', 'cpt-tables') ?></strong><br /><?php echo __('This plugin modifies your WordPress database. Backup your database before migrating in case something doesn\'t work as you expect.', 'cpt-tables') ?></p>
                                </div>
                            </div>

                        <?php endif; ?>
                    </div>
                </div>

                <div id="postbox-container-2" class="postbox-container">
                    <div id="normal-sortables" class="meta-box-sortables ui-sortable">
                        <div id="cpt-stats" class="postbox cpt-stats">
                            <div class="postbox-header">
                                <h2 class="hndle ui-sortable-handle"><?php echo __('Custom Post Types', 'cpt-tables') ?></h2>
                            </div>
                            <div class="inside">
                                <?php if (count($unmigrated) == 0) : ?>
                                    <p><?php echo __('All custom post types are already using custom tables.', 'cpt-tables') ?></p>
                                <?php else : ?>

                                    <table cellspacing="0" cellpadding="10">
                                        <thead>
                                            <tr>
                                                <th><?php echo __('Custom Post Type', 'cpt-tables') ?></th>
                                                <th><?php echo __('New Table Name', 'cpt-tables') ?></th>
                                                <th width="10%" class="center"><?php echo __('Entries', 'cpt-tables') ?></th>
                                                <th width="10%" class="center"><?php echo __('Meta', 'cpt-tables') ?></th>
                                                <th width="10%"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($unmigrated as $key => $migrate) : ?>
                                                <?php if ($key & 1) : $bgcolor = '#F6F7F7';
                                                else : $bgcolor = 'white';
                                                endif; ?>
                                                <tr style="background-color:<?php echo esc_attr($bgcolor); ?>">
                                                    <td><?php echo esc_attr($migrate['name']); ?><br /><span class="slug"><?php echo __('Name', 'cpt-tables') ?>: <?php echo esc_attr($migrate['slug']); ?></span></td>
                                                    <td><?php echo esc_attr($migrate['table']); ?></td>
                                                    <td class="center"><?php echo esc_attr($migrate['count']); ?></td>
                                                    <td class="center"><?php echo esc_attr($migrate['count_meta']); ?></td>
                                                    <td style="text-align:right"><a href="#" data-url="<?php echo admin_url('options-general.php?page=' . esc_attr($this->config['plugin_slug']) . '&action=migrate&type=' . esc_attr($migrate['slug']), false); ?>" class="button button-small migrate-button"><?php echo __('Migrate', 'cpt-tables') ?></a></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>


                <div id="postbox-container-2" class="postbox-container">
                    <div id="normal-sortables" class="meta-box-sortables ui-sortable">
                        <div id="cpt-stats" class="postbox cpt-stats">
                            <div class="postbox-header">
                                <h2 class="hndle ui-sortable-handle"><?php echo __('Migrated Custom Post Types', 'cpt-tables') ?></h2>
                            </div>
                            <div class="inside">
                                <?php if (count($migrated) == 0) : ?>
                                    <p><?php echo __('No custom post types have been migrated yet.', 'cpt-tables') ?></p>
                                <?php else : ?>
                                    <table cellspacing="0" cellpadding="10">
                                        <thead>
                                            <tr>
                                                <th><?php echo __('Custom Post Type', 'cpt-tables') ?></th>
                                                <th><?php echo __('Table Name', 'cpt-tables') ?></th>
                                                <th width="10%" class="center"><?php echo __('Entries', 'cpt-tables') ?></th>
                                                <th width="10%" class="center"><?php echo __('Meta', 'cpt-tables') ?></th>
                                                <th width="10%"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($migrated as $key => $migrate) : ?>
                                                <?php if ($key & 1) : $bgcolor = '#F6F7F7';
                                                else : $bgcolor = 'white';
                                                endif; ?>
                                                <tr style="background-color:<?php echo $bgcolor; ?>">
                                                    <td><?php echo esc_attr($migrate['name']); ?><br /><span class="slug"><?php echo __('Name', 'cpt-tables') ?>: <?php echo esc_attr($migrate['slug']); ?></span></td>
                                                    <td><?php echo esc_attr($migrate['table']); ?></td>
                                                    <td class="center"><?php echo esc_attr($migrate['count']); ?></td>
                                                    <td class="center"><?php echo esc_attr($migrate['count_meta']); ?></td>
                                                    <td style="text-align:right"><a href="#" data-url="<?php echo admin_url('options-general.php?page=' . esc_attr($this->config['plugin_slug']) . '&action=revert&type=' . esc_attr($migrate['slug']), false); ?>" class="button button-small revert-button"><?php echo __('Revert', 'cpt-tables') ?></a></td>
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
