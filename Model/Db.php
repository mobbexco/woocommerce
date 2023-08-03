<?php

namespace Mobbex\WP\Checkout\Model;

/**
 * Db Class
 * 
 * This class alow the Mobbex php-plugins-sdk interact with platform database.
 */
class Db extends \Mobbex\Model\Db
{
    public $prefix;
    public $db;

    public function __construct() {
        $this->db     = $GLOBALS['wpdb'];
        $this->prefix = $this->db->prefix;
    }

    /**
     * Executes a sql query & return the results.
     * 
     * @param string $sql
     * 
     * @return bool|array
     */
    public function query($sql)
    {
        // Get wordpress table prefix
        $this->prefix = $this->db->prefix;

        $result = $this->db->query($sql);

        // If isn't a select type query return bool, otherwise returns array
        if (!preg_match('#^\s*\(?\s*(select|show|explain|describe|desc)\s#i', $sql))
            return (bool) $result;
        else
            return $this->db->get_results($sql, ARRAY_A);

    }
}