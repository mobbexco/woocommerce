<?php

namespace Mobbex\WP\Checkout\Controller;

class Sources
{
    /** @var \Mobbex\WP\Checkout\Model\Config */
    public $config;
    
    /** @var \Mobbex\WP\Checkout\Model\Logger */
    public $logger;

    public function __construct()
    {
        $this->config = new \Mobbex\WP\Checkout\Model\Config();
        $this->logger = new \Mobbex\WP\Checkout\Model\Logger();

        //Add Mobbex Sources hook 
        add_action('rest_api_init', function () {
            register_rest_route(
                'mobbex/v1', 
                '/sources', 
                [
                    'permission_callback' => '__return_true',
                    'callback'            => [$this, 'get_sources'],
                    'methods'             => \WP_REST_Server::READABLE,
                ]);
            }
        );
    }

    public function get_sources($request)
    {
        $products_ids = explode(',', $request->get_param('mbbx_products_ids'));
        $total        = (float) $request->get_param('mbbx_products_price');

        // Filters out non-numeric values
        $products_ids = array_filter($products_ids, function ($id) {
            return is_numeric($id);
        });
        
        // Get product plans
        extract($this->config->get_products_plans($products_ids));

        $installments = \Mobbex\Repository::getInstallments(
            $products_ids, 
            $common_plans, 
            $advanced_plans
        );

        try {
            // Get sources from Mobbex API
            $sources = \Mobbex\Repository::getSources(
                $total,
                $installments
            );

            // Return json with sources
            return rest_ensure_response([
                'success' => true,
                'sources' => $sources,
            ]);
        } catch (\Exception $e) {
            $this->logger->log('error', 'Sources > getSources', $e->getMessage());
            return rest_ensure_response([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
    }
}