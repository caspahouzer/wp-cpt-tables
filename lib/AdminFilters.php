<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPCPT_Tables_AdminFilters
{
    /**
     * Bind the method that updates the admin redirect url to the admin_url
     * filter
     */
    public function __construct()
    {
        add_filter('admin_url', [$this, 'updateAdminUrl']);
    }

    /**
     * Adds post type from GET/POST request to the url if it is an admin page
     * 
     * @param  string $url
     * @return string
     */
    public function updateAdminUrl(string $url): string
    {
        $post_type = '';

        if (isset($_POST['post_type'])) {
            $post_type = esc_attr($_POST['post_type']);
        } elseif (isset($_GET['post_type'])) {
            $post_type = esc_attr($_GET['post_type']);
        }

        if ($this->isAdminPage($url)) {
            $url .= sprintf(
                '&post_type=%s',
                $post_type
            );
        }

        return $url;
    }

    /**
     * Returns true is the current page is in the Wordpress admin
     * @param  string  $url
     * @return boolean
     */
    public function isAdminPage(string $url): bool
    {
        $match = get_site_url(null, 'wp-admin/', 'admin') . 'post.php?';

        return strpos($url, $match) !== false;
    }
}
