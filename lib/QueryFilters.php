<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPCPT_Tables_QueryFilters
{
    /**
     * @var WPCPT_Tables_Db
     */
    private $db;

    /**
     * @var array
     */
    private $config;

    private $custom_table = [];

    public static $active = true;

    /**
     * Binds the method that changes tables in the query to the query filter
     *
     * @param Db    $db
     * @param array $config
     */
    public function __construct(WPCPT_Tables_Db $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;

        // Add the filter that changes the tables in the query
        add_filter('query', [$this, 'updateQueryTables']);
    }

    public static function deactivate()
    {
        global $wpdb;
        $wpdb->query("SET @disable_triggers = 1;");
        self::$active = false;
    }

    public static function activate()
    {
        global $wpdb;
        $wpdb->query("SET @disable_triggers = NULL;");
        self::$active = true;
    }

    /**
     * If the query is for a post type that has custom tables set up, replace
     * the post and meta tables with the custom ones
     *
     * @param  string $query
     * @return string
     */
    public function updateQueryTables(string $query): string
    {
        // Check if it has been deactivated temporarily to make changes in the main posts or postmeta table
        if (self::$active === false) {
            return $query;
        }

        $table = $this->determineTable($query);
        $this->custom_table = $table;

        if ($table && in_array($table, $this->config['post_types'])) {
            $table = $this->config['prefix'] . str_replace('-', '_', $table);

            $query = str_replace($this->config['default_post_table'], $table, $query);
            $query = str_replace($this->config['default_meta_table'], $table . '_meta', $query);
        }

        return $query;
    }

    /**
     * Tries to parse the post type from the query. If not possible, parses the
     * ID and uses it to look up the post type in the wp_posts table
     *
     * @param  string $query
     * @return string
     */
    private function determineTable(string $query)
    {
        if ($table = $this->getPostTypeFromRequest($query)) {
            return $table;
        }

        if ($table = $this->getPostTypeFromQuery($query)) {
            return $table;
        }

        if ($table = $this->lookupPostTypeInDatabase($query)) {
            return $table;
        }
    }

    public function getPostTypeFromRequest(string $query)
    {
        preg_match("/`?post_type`?\s*=\s*'([a-zA-Z_]*)'/", $query, $postType);

        if ($postType = array_pop($postType)) {
            if (isset($_GET['post_type']) && sanitize_key($_GET['post_type']) == $postType) {
                return $postType;
            }
        }
    }

    /**
     * Tries to parse the post type from the query
     *
     * @param  string $query
     * @return bool|string
     */
    public function getPostTypeFromQuery(string $query)
    {
        preg_match("/`?post_type`?\s*=\s*'([a-zA-Z_]*)'/", $query, $postType);

        if ($postType = array_pop($postType)) {
            return $postType;
        }
    }

    /**
     * Grabs the post id from the query and looks up the post type for this id
     * in the wp_posts table
     *
     * @param  string $query
     * @return string|null
     */
    public function lookupPostTypeInDatabase(string $query)
    {
        if ($ids = $this->getPostIdsFromQuery($query)) {
            return $this->getPostTypeById($ids);
        }
    }

    /**
     * Tries to parse the post id(s) from the query
     *
     * @param  string $query
     * @return bool|string
     */
    public function getPostIdsFromQuery(string $query): ?string
    {
        preg_match(sprintf(
            "/(?:SELECT.*FROM\s(?:%s|%s)\s*WHERE.*(?:ID|post_id)+\s*IN\s\(+\s*'?([\d\s,]*)'?\)"
                .  "|SELECT.*FROM\s(?:%s|%s)\s*WHERE.*(?:ID|post_id)+\s*=+\s*'?(\d*)'?"
                .  "|DELETE FROM\s`?(?:%s|%s)`?\s*WHERE.*`?(?:ID|post_id)`?+\s*=+\s*'?(\d*)'?"
                .  "|UPDATE.*(?:%s|%s).*WHERE.*`?(?:ID|post_id)+`?\s*=+\s*'?(\d*)'?"
                .  "|INSERT INTO.*\s`?%s`?\s\(`post_id`, `meta_key`, `meta_value`\){1,1}\s*VALUES\s*\((\d*),"
                . ")/",
            $this->config['default_post_table'],
            $this->config['default_meta_table'],
            $this->config['default_post_table'],
            $this->config['default_meta_table'],
            $this->config['default_post_table'],
            $this->config['default_meta_table'],
            $this->config['default_post_table'],
            $this->config['default_meta_table'],
            $this->config['default_meta_table']
        ), $query, $ids);

        if (!isset($ids)) {
            return false;
        }

        return array_pop($ids);
    }

    /**
     * Looks up post type in wp_posts. Caches the response, and if more than one id
     * is provided, also cache the result against each individual ID.
     *
     * @param  string $ids
     * @return string
     */
    public function getPostTypeById(string $ids): ?string
    {
        $key = __METHOD__ . $ids;

        if (!$cached = wp_cache_get($key)) {
            // $ids = explode(',', $ids);

            $cached = $this->getCachedPostType($this->config['default_post_table'], $ids, $key);
            foreach ($this->config['post_types'] as $table) {
                $table = $this->config['prefix'] . str_replace('-', '_', $table);
                $cached = $this->getCachedPostType($table, $ids, $key);
                if ($cached) {
                    break;
                }
            }
        }

        return $cached;
    }

    /**
     * Looks up post type in wp_posts. Caches the response, and if more than one id
     * is provided, also cache the result against each individual ID.
     *
     * @param  string $ids
     * @return string
     */
    private function getCachedPostType(string $table, string $ids, string $key)
    {
        $ids = explode(',', $ids);

        $cached = $this->db->value(
            sprintf(
                "SELECT post_type, ID as identifier FROM %s HAVING identifier IN (%s) LIMIT 1",
                $this->db->escape($table),
                implode(',', array_fill(0, count($ids), '?'))
            ),
            ...$ids
        );

        wp_cache_set($key, $cached);

        if (count($ids)) {
            foreach ((array) $ids as $id) {
                wp_cache_set(__METHOD__ . $id, $cached);
            }
        }
        return $cached;
    }
}
