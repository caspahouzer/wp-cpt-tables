<?php


class Table
{
    /**
     * @var Db
     */
    private $db;

    /**
     * @var array
     */
    private $config;

    /**
     * @var string
     */
    private $table;

    /**
     * The notices class
     * @var Notices
     */
    private $notices;

    /**
     * Triggers the create methods for tables
     * @param Db     $db
     * @param array  $config
     * @param string $table
     */
    public function __construct(Db $db, array $config)
    {
        $this->notices = new Notices();

        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Returns the table name with the prefix
     * @param  string $table
     * @return string
     */
    public function getTableName(string $table)
    {
        return $this->config['prefix'] . str_replace('-', '_', $table);
    }

    /**
     * Executes the methods required to add the necessary tables
     * @param  string $table
     * @return void
     */
    public function create(string $table)
    {
        global $wpdb;
        $table = $this->config['prefix'] . str_replace('-', '_', $table);

        $this->createTables($table);
    }

    /**
     * Creates the new post table for the custom post type, basing the structure
     * on wp_posts
     *
     * @param  array  $table
     * @return void
     */
    private function createTables(string $table)
    {
        $this->db->query(
            sprintf(
                "CREATE TABLE IF NOT EXISTS %s LIKE %s",
                $this->db->escape($table),
                $this->db->escape($this->config['default_post_table'])
            )
        );

        $this->db->query(
            sprintf(
                "CREATE TABLE IF NOT EXISTS %s LIKE %s",
                $this->db->escape($table . '_meta'),
                $this->db->escape($this->config['default_meta_table'])
            )
        );
    }

    /**
     * Executes the methods required to remove the tables
     * 
     * @param  string $table
     * @return void
     */
    public function revert(string $table)
    {
        global $wpdb;

        update_option('cpt_tables:migrate_running', true);

        $custom_post_type = $table;
        $table = $this->config['prefix'] . str_replace('-', '_', $table);

        $message = 'Reverting <strong>' . $custom_post_type . '</strong> back to posts table:<br/>';
        $successMessage = $message;
        $errorMessage = $message;

        $params = [];

        $hasErrors = false;

        // copy the data from custom table to wp_posts
        $query = sprintf(
            'REPLACE INTO %s SELECT * FROM %s',
            $wpdb->posts,
            $table
        );
        $this->db->query($query, ...$params);

        // check for errors
        if ($wpdb->last_error || $hasErrors) {
            $errorMessage .= 'Error copying ' . $custom_post_type . ' rows: ' . $wpdb->last_error . '<br/>';
            $hasErrors = true;
        } else {
            $successMessage .= $custom_post_type . ' rows copied: ' . $wpdb->rows_affected . '<br/>';
        }

        // copy the data from the custom meta table to wp_postmeta
        $query = sprintf(
            'REPLACE INTO %s SELECT * FROM %s',
            $wpdb->postmeta,
            $table . '_meta',
        );
        $this->db->query($query, ...$params);

        // check for errors
        if ($wpdb->last_error || $hasErrors) {
            $errorMessage .= 'Error copying ' . $custom_post_type . ' rows: ' . $wpdb->last_error . '<br/>';
            $hasErrors = true;
        } else {
            $successMessage .= 'New ' . $custom_post_type . ' meta rows copied: ' . $wpdb->rows_affected . '<br/>';
        }

        // delete the custom tables
        $query = sprintf(
            'DROP TABLE IF EXISTS %s, %s',
            $table,
            $table . '_meta'
        );
        $this->db->query($query, ...$params);

        // check for errors
        if ($wpdb->last_error || $hasErrors) {
            $errorMessage .= 'Error deleting ' . $custom_post_type . ' tables: ' . $wpdb->last_error . '<br/>';
            $hasErrors = true;
        } else {
            $successMessage .= 'Tables deleted: ' . $table . ', ' . $table . '_meta<br/>';
        }

        delete_option('cpt_tables:migrate_running');
    }

    /**
     * Migrate existing custom post types to the new tables
     * 
     * @param  string $table
     * @return void
     */
    public function migrate(string $table)
    {
        global $wpdb;

        update_option('cpt_tables:migrate_running', true);

        $custom_post_type = $table;
        $table = $this->config['prefix'] . str_replace('-', '_', $table);

        // be sure the target tables exists
        $this->createTables($table);

        $message = 'Migration of <strong>' . $custom_post_type . '</strong>:<br/>';
        $successMessage = $message;
        $errorMessage = $message;

        $params = [];

        $hasErrors = false;

        // clean up auto-drafts from this post_type in wp_posts
        $query = sprintf(
            'DELETE FROM %s WHERE %s = "%s" AND %s = "auto-draft"',
            $wpdb->posts,
            $wpdb->posts . '.post_type',
            $custom_post_type,
            $wpdb->posts . '.post_status'
        );
        $this->db->query($query, ...$params);

        if ($wpdb->last_error || $hasErrors) {
            $errorMessage .= 'Error cleaning up the posts table before migrate<br/>';
            $hasErrors = true;
        }

        // copy the data from wp_posts to the new table
        $query = sprintf(
            'REPLACE INTO %s SELECT * FROM %s WHERE %s = "%s"',
            $table,
            $wpdb->posts,
            $wpdb->posts . '.post_type',
            $custom_post_type
        );
        $this->db->query($query, ...$params);

        // check for errors
        if ($wpdb->last_error || $hasErrors) {
            $errorMessage .= 'Error copying ' . $custom_post_type . ' rows: ' . $wpdb->last_error . '<br/>';
            $hasErrors = true;
        } else {
            $successMessage .= 'New ' . $custom_post_type . ' rows copied: ' . $wpdb->rows_affected . '<br/>';
        }

        // copy the data from wp_postmeta to the new table
        $query = sprintf(
            'REPLACE INTO %s SELECT * FROM %s WHERE %s IN (SELECT %s FROM %s WHERE %s = "%s")',
            $table . '_meta',
            $wpdb->postmeta,
            $wpdb->postmeta . '.post_id',
            $wpdb->posts . '.ID',
            $wpdb->posts,
            $wpdb->posts . '.post_type',
            $custom_post_type
        );
        $this->db->query($query, ...$params);

        // check for errors
        if ($wpdb->last_error || $hasErrors) {
            $errorMessage .= 'Error copying ' . $custom_post_type . ' rows: ' . $wpdb->last_error . '<br/>';
            $hasErrors = true;
        } else {
            $successMessage .= 'New ' . $custom_post_type . ' meta rows copied: ' . $wpdb->rows_affected . '<br/>';
        }

        // delete the data from tables if no errors
        if (!$hasErrors) {
            // delete the data from wp_posts
            $query = sprintf(
                'DELETE FROM %s WHERE %s IN (SELECT %s FROM %s WHERE %s = "%s")',
                $wpdb->postmeta,
                $wpdb->postmeta . '.post_id',
                $wpdb->posts . '.ID',
                $wpdb->posts,
                $wpdb->posts . '.post_type',
                $custom_post_type
            );
            $this->db->query($query, ...$params);

            // delete the data from wp_postmeta (except auto-drafts)
            $query = sprintf(
                'DELETE FROM %s WHERE %s = "%s"',
                $wpdb->posts,
                $wpdb->posts . '.post_type',
                $custom_post_type
            );
            $this->db->query($query, ...$params);
        }

        delete_option('cpt_tables:migrate_running');

        if ($hasErrors) {
            $this->notices->add($errorMessage, 'error');
        } else {
            // $this->notices->add($successMessage, 'success');
        }

        flush_rewrite_rules();
    }
}
