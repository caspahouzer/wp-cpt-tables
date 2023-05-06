<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPCPT_Tables_Triggers
{
    /**
     * @var WPCPT_Tables_Db
     */
    private $db;

    /**
     * @var array
     */
    private $config;

    /**
     * @var string
     */
    private $insertPostTrigger = 'custom_post_insert';

    /**
     * @var string
     */
    private $deletePostTrigger = 'custom_post_delete_parent';

    /**
     * @var string
     */
    private $insertMetaTrigger = 'custom_meta_insert';

    /**
     * @var string
     */
    private $deleteMetaTrigger = 'custom_meta_delete';

    /**
     * Triggers the methods that create the post and post meta triggers
     *
     * @param WPCPT_Tables_Db    $db
     * @param array $config
     */
    public function __construct(WPCPT_Tables_Db $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Executes the methods required to set up all necessary triggers
     * @param  array  $tables
     * @return void
     */
    public function create(array $tables)
    {
        // Delete all triggers first to avoid syntax errors
        $this->deleteAllTrigger($tables);

        $this->insertPostTrigger($tables);
        $this->insertMetaTrigger($tables);

        // Wait for triggers to be created
        sleep(1);
    }

    /**
     * Executes the methods required to delete all triggers
     * 
     * @return void
     */
    public function deleteAllTrigger(array $tables)
    {
        $this->db->value("DROP TRIGGER IF EXISTS " . $this->db->escape($this->config['prefix'] . $this->insertPostTrigger));
        $this->db->value("DROP TRIGGER IF EXISTS " . $this->db->escape($this->config['prefix'] . $this->insertMetaTrigger));

        foreach ($tables as $i => $postType) {
            // if post_type does not exist, skip
            // if (!post_type_exists($postType)) {
            //     continue;
            // }
            $table = $this->config['prefix'] . str_replace('-', '_', $postType);

            $this->db->value("DROP TRIGGER IF EXISTS " . $this->db->escape($table . '_' . $this->deletePostTrigger));
            $this->db->value("DROP TRIGGER IF EXISTS " . $this->db->escape($table . '_' . $this->deleteMetaTrigger));
        }
    }

    /**
     * Creates a trigger on the new custom post type table that copies each new
     * row of data from the posts table to the custom table
     *
     * @param  array  $tables
     * @return void
     */
    private function insertPostTrigger(array $tables)
    {
        if (!$tables) {
            return;
        }

        if (!is_array($tables)) {
            return;
        }

        if (count($tables) === 0) {
            return;
        }

        array_values($tables);

        // Create the copy from wp_posts to custom table trigger
        $params = [];
        $query = sprintf(
            "CREATE TRIGGER %s AFTER INSERT ON %s FOR EACH ROW BEGIN IF @disable_triggers IS NULL THEN ",
            $this->db->escape($this->config['prefix'] . $this->insertPostTrigger),
            $this->db->escape($this->config['default_post_table'])
        );

        foreach ($tables as $i => $postType) {
            $table = $this->config['prefix'] . str_replace('-', '_', $postType);

            if ($i) {
                $query .= 'ELSE';
            }

            $query .= sprintf(
                "IF (? = NEW.post_type) THEN
                    REPLACE INTO %s (ID, post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count)
                    VALUES (NEW.ID, NEW.post_author, NEW.post_date, NEW.post_date_gmt, NEW.post_content, NEW.post_title, NEW.post_excerpt, NEW.post_status, NEW.comment_status, NEW.ping_status, NEW.post_password, NEW.post_name, NEW.to_ping, NEW.pinged, NEW.post_modified, NEW.post_modified_gmt, NEW.post_content_filtered, NEW.post_parent, NEW.guid, NEW.menu_order, NEW.post_type, NEW.post_mime_type, NEW.comment_count);
                ",
                $this->db->escape($table),
                $this->db->escape($this->config['default_post_table'])
            );

            $params[] = $postType;
        }

        $query .= "END IF; END IF; END";

        $this->db->query($query, ...$params);
    }


    /**
     * Creates a trigger on the new custom post type meta table that copies each
     * new row of data from the post meta table to the custom meta table
     *
     * @param  array  $tables
     * @return void
     */
    private function insertMetaTrigger(array $tables)
    {
        if (!$tables) {
            return;
        }

        if (!is_array($tables)) {
            return;
        }

        if (count($tables) === 0) {
            return;
        }

        // Create the post meta copy trigger
        $params = [];
        $query = sprintf(
            "CREATE TRIGGER %s AFTER INSERT ON %s FOR EACH ROW BEGIN IF @disable_triggers IS NULL THEN ",
            $this->db->escape($this->config['prefix'] . $this->insertMetaTrigger),
            $this->db->escape($this->config['default_meta_table'])
        );

        foreach ($tables as $i => $postType) {
            $table = $this->config['prefix'] . str_replace('-', '_', $postType);

            if ($i) {
                $query .= 'ELSE';
            }

            $query .= sprintf(
                "IF ((SELECT post_type FROM %s WHERE ID = NEW.post_id) = ?) THEN
                    REPLACE INTO %s (meta_id, post_id, meta_key, meta_value)
                    VALUES (NEW.meta_id, NEW.post_id, NEW.meta_key, NEW.meta_value);
                ",
                $this->db->escape($this->config['default_post_table']),
                $this->db->escape($table . '_meta')
            );

            $params[] = $postType;
        }

        $query .= "END IF; END IF; END";

        $this->db->query($query, ...$params);

        // create trigger to delete from custom meta table after delete from custom post table
        foreach ($tables as $i => $postType) {
            $table = $this->config['prefix'] . str_replace('-', '_', $postType);
            $params = [];
            $query = sprintf(
                "CREATE TRIGGER %s
                AFTER DELETE ON %s FOR EACH ROW DELETE FROM %s WHERE post_id = OLD.ID;",
                $this->db->escape($table . '_' . $this->deleteMetaTrigger),
                $this->db->escape($table),
                $this->db->escape($table . '_meta')
            );

            $this->db->query($query, ...$params);
        }
    }
}
