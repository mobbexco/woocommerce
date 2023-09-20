<?php

namespace Mobbex\WP\Checkout\Controller;

/**
 * LogTable Class controller.
 */
class LogTable
{
    /**
     * Exports data as txt or csv
     *  
     **/ 
    public function mobbex_export_data()
    {
        $logs       = (new \Mobbex\WP\Checkout\Model\LogTable($_POST));
        $table_data = $logs->get_export_data();

        // Clean buffer
        ob_clean();
        // Open file
        $file = fopen('php://memory', 'w');

        // Write the file with the corresponding format according to the extension
        if ($_POST['filter_extension'] == 'csv'){
            // Get column names
            fputcsv($file, array_keys($table_data[0]));
            // Get rows
            foreach ($table_data as $log)
                fputcsv($file, $log);
        }
        else {
            // Get column names
            fwrite($file, implode(' // ' , array_keys($table_data[0])));
            fwrite($file, "\r\n\r\n");
            // Get rows
            foreach ($table_data as $log){
                fwrite($file, implode(' // ', $log));
                fwrite($file, "\r\n\r\n");
                }
        }
        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename=exported_logs.{$_POST['filter_extension']}");

        // Reset file pointer, output file contents, and close the file.
        fseek($file, 0); fpassthru($file); fclose($file);
        die;
    }
}