<?php

namespace Mobbex\WP\Checkout\Model;

/**
 * LogTable Class model
 *
 * This class manages mobbex logs table data
 */
class LogTable
{
    /** Allowed values for type filter */
    const TYPES = ['all', 'debug', 'error', 'fatal', 'critical'];

    /** Allowed values for limit filter */
    const LIMITS = [5, 10, 25];

    /** Limit value for query */
    public $limit;

    /** Offset value for query */
    public $offset;

    /** Sanitized request params */
    public $params = [];

    /** Query Filters */
    public $filters = [];

    /** Logs data */
    public $logs;

    /** Paging data */
    public $page_data;

    /** Mobbex\WP\Checkout\Model\Db */
    public $db;

    public function __construct($post)
    {
        $this->db     = new \Mobbex\WP\Checkout\Model\Db;
        $this->params = $this->sanitize_params($post);
        $this->limit  = $this->params['filter_limit'];
        $this->offset = $this->params['log-page'] * $this->limit;

        global $wpdb;

        $date     = $this->params['filter_date'];
        $type     = $this->params['filter_type'];
        $keywords = '%' . $wpdb->esc_like($this->params['filter_text']) . '%';

        $this->filters = [
            'date'     => $date ? $wpdb->prepare('DATE(creation_date) = %s', $date) : null,
            'type'     => $type != 'all' ? $wpdb->prepare('type = %s', $type) : null,
            'keywords' => $this->params['filter_text'] !== '' ? $wpdb->prepare('(message LIKE %s OR data LIKE %s)', $keywords, $keywords) : null,
        ];

        $this->logs      = $this->db->select(
            'mobbex_log', $this->filters, $this->limit, $this->offset, 'ORDER BY `creation_date` DESC'
            );
        $this->page_data = $this->get_page_data();
    }

    /**
     * Normalizes the request params against allowed values
     *
     * @param array $post
     *
     * @return array
     */
    public function sanitize_params($post)
    {
        $date  = isset($post['filter_date']) ? (string) $post['filter_date'] : '';
        $type  = isset($post['filter_type']) ? (string) $post['filter_type'] : 'all';
        $limit = isset($post['filter_limit']) ? (int) $post['filter_limit'] : 25;

        return [
            'filter_date'      => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '',
            'filter_type'      => in_array($type, self::TYPES, true) ? $type : 'all',
            'filter_text'      => isset($post['filter_text']) ? sanitize_text_field($post['filter_text']) : '',
            'filter_limit'     => in_array($limit, self::LIMITS, true) ? $limit : 25,
            'filter_extension' => isset($post['filter_extension']) && $post['filter_extension'] == 'csv' ? 'csv' : 'txt',
            'log-page'         => isset($post['log-page']) ? max(0, (int) $post['log-page']) : 0,
            'download'         => isset($post['download']) && in_array($post['download'], ['page', 'query'], true) ? $post['download'] : null,
        ];
    }

    /**
     * Gets needed data for pagination
     *
     * @return array $data pagination data
     */
    public function get_page_data()
    {
        // Paging calculations
        $total_logs   = count($this->db->select('mobbex_log', $this->filters));
        $total_pages  = ceil($total_logs / $this->limit);
        $actual_page  = $this->offset / $this->limit;

        // Sets pagination data
        return [
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
        // Gets the filter and queries the database with appropriate ones
        if ($this->params['download'] == 'page')
            return $this->db->select('mobbex_log', $this->filters, $this->limit, $this->offset);

        if ($this->params['download'] == 'query')
            return $this->db->select('mobbex_log', $this->filters);

        return [];
    }
}
