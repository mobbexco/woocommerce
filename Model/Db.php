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

    /**
     * Insert data in specific table. Columns and values can be passed
     * 
     * @param string $table_name
     * @param array  $columns optional it can contain column name and its value as assosiative array
     * 
     */
    public function insert($table_name, $columns = [])
    {
        $this->db->insert($this->prefix . $table_name, $columns);
    }

    /**
     * Select data from specifict table. Filters, limit and offset can be passed
     * 
     * @param string $table_name
     * @param array  $filters
     * @param string|int  $limit
     * @param string|int  $offset
     * 
     * @return array table data
     * 
     */
    public function select($table_name, $filters = [], $limit = '', $offset = '', $order = '')
    {
        // Sets query restriction clauses
        $limit      = !empty($limit) ? "LIMIT $limit" : '';
        $offset     = !empty($offset) ? "OFFSET $offset" : '';
        $conditions = !empty(array_filter($filters)) ? "WHERE " . implode(' AND ', array_filter($filters)) : '';
        
        return $this->db->get_results("SELECT * FROM  $this->prefix$table_name $conditions $order $limit $offset;" , ARRAY_A);
    }
}