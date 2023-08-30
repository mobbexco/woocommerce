<?php

namespace Mobbex\WP\Checkout\Model;

/**
 * Model to manage mobbex_cache table.
 */
class Cache
{
    /**
     * Get data from mobbex_cache table with key.
     * @param string $key Identifier to obtain the data.
     * @return bool|array Return the searched data or false in case there isnt.
     */
    public function get($key)
    {
        global $wpdb;

        //Delete expired cache
        $wpdb->query("DELETE FROM ".$wpdb->prefix."mobbex_cache WHERE `date` < DATE_SUB(NOW(), INTERVAL 5 MINUTE);");
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
    
        // Check that the key is not repeated before inserting the data in the table 
        $result = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix."mobbex_cache WHERE `cache_key`='$key';");

        if (empty($result))
            $wpdb->insert($wpdb->prefix.'mobbex_cache', ['cache_key' => $key, 'data' => $data]);
    }
}
