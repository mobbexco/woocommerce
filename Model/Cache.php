<?php

namespace Mobbex\WP\Checkout\Model;

/**
 * Model to manage mobbex_cache table.
 */
class Cache
{
    /**
     * Get data from mobbex_cache table with key.
     * 
     * @param string $key Identifier to obtain the data.
     * @param int $interval Interval to check if data is expired - set in seconds
     * 
     * @return bool|array Return the searched data or false in case there isnt.
     */
    public function get($key, $interval = 300)
    {
        global $wpdb;

        //Delete expired cache
        $wpdb->query("DELETE FROM ".$wpdb->prefix."mobbex_cache WHERE `date` < DATE_SUB(NOW(), INTERVAL " . $interval . " SECOND);");
        //Try to get results
        $result = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."mobbex_cache WHERE `cache_key`='$key';");

        return !empty($result[0]) ? json_decode($result[0]->data, true) : [];
    }

    /**
     * Save data in mobbex_cache table.
     * @param string $key Identifier to save the data.
     * @param string $data Data to be stored.
     */
    public function store($key, $data)
    {
        global $wpdb;

        // Saves or replaces data depending on the existence of the same key in the table
        $wpdb->query(
            "REPLACE INTO " . $wpdb->prefix . "mobbex_cache (`cache_key`, `data`) VALUES ('{$key}', '{$data}');"
        );
    }
}