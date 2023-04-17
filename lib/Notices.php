<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPCPT_Tables_Notices
{

    /**
     * Add the required action to display the notices
     * 
     * @return void
     */
    public function __construct()
    {
        add_action('admin_notices', [$this, 'displayFlashNotices'], 12);
    }

    /**
     * Add a new notice to the database
     * 
     * @param string $notice
     * @param string $type
     * @param bool $dismissible
     * @return void
     */
    public function add(string $notice, string $type = 'success', bool $dismissible = true)
    {
        $notices = get_transient('cpt_tables_notices');

        if (empty($notices)) {
            $notices = [];
        }

        // If notice already exists, then return
        if (in_array(md5($notice), $notices)) {
            return;
        }

        $dismissible_text = ($dismissible) ? 'is-dismissible' : '';

        // Add our new notice.
        $notices[md5($notice)] = [
            'notice' => $notice,
            'type' => $type,
            'dismissible' => $dismissible_text
        ];

        set_transient('cpt_tables_notices', $notices);
    }

    /**
     * Function executed when the 'admin_notices' action is called, here we check if there are notices on
     * our database and display them, after that, we remove the option to prevent notices being displayed forever.
     * 
     * @return void
     */
    public function displayFlashNotices()
    {
        $notices = get_transient('cpt_tables_notices');

        if (empty($notices)) {
            return;
        }

        // Iterate through our notices to be displayed and print them.
        foreach ($notices as $notice) {
            printf(
                '<div class="notice notice-%1$s %2$s"><p>%3$s</p></div>',
                esc_attr($notice['type']),
                esc_attr($notice['dismissible']),
                wp_kses_post($notice['notice'])
            );
        }

        delete_transient('cpt_tables_notices');
    }
}
