<?php

namespace Mobbex\WP\Checkout\Models;

class Table
{

    public function create_table($check_sql, $install_sql)
    {
        global $wpdb;

        $check_sql   = str_replace('DB_PREFIX_', $wpdb->prefix, $check_sql);
        $install_sql = str_replace(['DB_PREFIX_', 'ENGINE_TYPE'], [$wpdb->prefix, $wpdb->get_var("SHOW ENGINES;")], $install_sql);
        error_log('resultado: ' . "\n" . json_encode($wpdb->query($check_sql), JSON_PRETTY_PRINT) . "\n", 3, 'log.log');
        if(!$wpdb->query($check_sql))
            $wpdb->get_results($install_sql);
    }

}
