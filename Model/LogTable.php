<?php

namespace Mobbex\WP\Checkout\Model;

/**
 * LogTable Class model
 * 
 * This class
 */
class LogTable
{
    /** Limit value for query */
    public $limit;

    /** Offset value for query */
    public $offset;

    /** Query Filters */
    public $filters = [];

    /** Logs data */
    public $logs;

    /** Paging data */
    public $page_data;

    public function __construct($post)
    {
        $this->limit       = !empty($post['filter_limit']) ? (int) $post['filter_limit'] : 25;
        $this->offset      = isset($post['log-page']) ? ((int) $post['log-page'] * (int) $this->limit) : 0;
        $this->filters     = [
            'date'     => !empty($post['filter_date']) ? "DATE(creation_date) = '{$post['filter_date']}'" : null,
            'type'     => isset($post['filter_type']) && $post['filter_type'] != 'all'  ? "type = '{$post['filter_type']}'" : null,
            'keywords' => !empty($post['filter_text']) ? "(message LIKE '%{$post['filter_text']}%' OR data LIKE '%{$post['filter_text']}%')" : null,
        ];

        $this->logs      = (new \Mobbex\WP\Checkout\Model\Db)->select(
            'mobbex_log', $this->filters, $this->limit, $this->offset, 'ORDER BY `creation_date` DESC'
            );
        $this->page_data = $this->get_page_data();
    }
    
    /**
     * Gets needed data for pagination
     * 
     * @return array $data pagination data
     */
    public function get_page_data()
    {   
        // Paging calculations
        $total_logs   = count((new \Mobbex\WP\Checkout\Model\Db)->select('mobbex_log', $this->filters)); 
        $total_pages  = ceil($total_logs / $this->limit);
        $actual_page  = $this->offset / $this->limit; 
        
        // Shows logs with filters applied
        if (isset($post['filter-submit']))
            $this->logs = (new \Mobbex\WP\Checkout\Model\Db)->select('mobbex_log', $this->filters, $this->limit, $this->offset);

        // Sets pagination data
        return $data = [
            'logs'        => $this->logs,
            'actualPage'  => $actual_page, 
            'total_pages' => $total_pages
        ];
    }

    /**
     * Get data to export from database
     * 
     * @return array $logs array resulted from query
     */
    public function get_export_data()
    {
        if (!isset($_POST['download']))
            return;

        // Gets the filter and queries the database with appropriate ones
        if ($_POST['download'] == 'page')
            $this->logs = (new \Mobbex\WP\Checkout\Model\Db)->select('mobbex_log', $this->filters, $this->limit, $this->offset);

        if ($_POST['download'] == 'query')
            $this->logs = (new \Mobbex\WP\Checkout\Model\Db)->select('mobbex_log', $this->filters);

        return $this->logs;
    }
}