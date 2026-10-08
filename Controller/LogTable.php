<?php

namespace Mobbex\WP\Checkout\Controller;

/**
 * LogTable Class controller.
 */
class LogTable
{
    /** Request params */
    public $params;

    public function __construct($params = [])
    {
        $this->params = $params;
    }

    /**
     * Exports data as txt or csv
     *
     **/
    public function mobbex_export_data()
    {
        $logs       = new \Mobbex\WP\Checkout\Model\LogTable($this->params);
        $table_data = $logs->get_export_data() ?: [];
        $extension  = $logs->params['filter_extension'];

        // Clean buffer
        if (ob_get_length())
            ob_clean();
        // Open file
        $file = fopen('php://memory', 'w');

        // Write the file with the corresponding format according to the extension
        if ($table_data && $extension == 'csv'){
            // Get column names
            fputcsv($file, array_keys($table_data[0]));
            // Get rows
            foreach ($table_data as $log)
                fputcsv($file, $log);
        }
        else if ($table_data) {
            // Get column names
            fwrite($file, implode(' // ' , array_keys($table_data[0])));
            fwrite($file, "\r\n\r\n");
            // Get rows
            foreach ($table_data as $log){
                fwrite($file, implode(' // ', $log));
                fwrite($file, "\r\n\r\n");
                }
        }
        header($extension == 'csv' ? 'Content-Type: text/csv' : 'Content-Type: text/plain');
        header("Content-Disposition: attachment; filename=exported_logs.$extension");

        // Reset file pointer, output file contents, and close the file.
        fseek($file, 0); fpassthru($file); fclose($file);
        die;
    }
}
