<?php

namespace Mobbex\WP\Checkout\Models;

/**
 * Model to manage mobbex_cache table.
 */
class Cache extends \Mobbex\Model\AbstractCache
{
    /**
     * Get data from mobbex_cache table with key.
     * @param string $key Identifier to obtain the data.
     * @return bool|array Return the searched data or false in case there isnt.
     */
    public static function getCacheData($key)
    {
        global $wpdb;

        //Delete expired cache
        $wpdb->query("DELETE FROM ".$wpdb->prefix."mobbex_cache WHERE `date` < DATE_SUB(NOW(), INTERVAL 5 MINUTE);");
        //Try to get results
        $result = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."mobbex_cache WHERE `key`='$key';");

        return !empty($result[0]) ? json_decode($result[0]->data, true) : [];
    }

    /**
     * Save data in mobbex_cache table.
     * @param string $key Identifier to save the data.
     * @param string $data Data to be stored.
     */
    public static function saveCacheData($key, $data)
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix.'mobbex_cache', ['key' => $key, 'data' => $data]);
    }
}
