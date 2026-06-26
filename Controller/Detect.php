<?php

namespace Mobbex\WP\Checkout\Controller;

/**
 *  Handles the HTTP request to detect payment card details, 
 *  such as the card brand and available installment plans, 
 *  based on the provided Bank Identification Number (BIN).
 */
final class Detect
{
    /** @var \Mobbex\WP\Checkout\Model\Config */
    public $config;

    /** @var \Mobbex\WP\Checkout\Model\Logger */
    public $logger;

    /** @var \Mobbex\WP\Checkout\Model\Helper */
    public $helper;

    public function __construct()
    {
        $this->config = new \Mobbex\WP\Checkout\Model\Config();
        $this->helper = new \Mobbex\WP\Checkout\Model\Helper();
        $this->logger = new \Mobbex\WP\Checkout\Model\Logger();

        //Add detect card hook 
        add_action('rest_api_init', function () {
            register_rest_route('mobbex/v1', '/detect', [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'execute'],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    /**
     * Execute detection of payment source based on BIN
     * 
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response object
     */
    public function execute($request)
    {
        try {
            $params = $request->get_json_params();
            
            if (empty($params['bin']) || empty($params['token'])) {
                $this->logger->log(
                    'error', 
                    'Transparent Detect | Missing required parameters', 
                    $params
                );
                
                throw new \Exception(
                    __('[Mobbex Transparent] Detect > Missing bin or token', 'mobbex-for-woocommerce')
                );
            }
            
            $bin   = sanitize_text_field($params['bin']);
            $token = sanitize_text_field($params['token']);
            
            $card = $this->detect_card($bin, $token);
            $this->logger->log('info', 'Transparent Detect | Source detected successfully');
            
            return new \WP_REST_Response($card, 200);
            
        } catch (\Exception $e) {
            $this->logger->log('error', 'Transparent Detect | Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new \WP_REST_Response([
                'result' => false,
                'error' => __('Error al detectar medio de pago', 'mobbex-for-woocommerce')
            ], 500);
        }
    }

    /**
     * Get installments and card brand from Mobbex API
     * 
     * @param string $bin Card BIN
     * @param string $token Mobbex Checkout intent token
     * 
     * @return array|false Installments data or false on error
     * 
     * @throws \Exception
     */
    private function detect_card($bin, $token)
    {
        $multivendor = $this->config->multivendor == "active" || $this->config->multivendor == "unified";

        $response = \Mobbex\Api::request([
            'method' => 'POST',
            'uri'    => "sources/detect/$token",
            'raw'    => true,
            'body'   => [
                'type' => 'card',
                'data' => ['bin' => $bin],
                'options' => [
                    'installments' => true,
                    'filter'       => null,
                    'brand'        => true,
                    'brands'       => true,
                    'multivendor'  => $multivendor ?: null,
                ],
            ],
        ]);

        if (empty($response['data']))
            throw new \Mobbex\Exception(sprintf(
                'Mobbex request error #%s: %s %s',
                isset($response['code']) ? $response['code'] : 'NOCODE',
                isset($response['error']) ? $response['error'] : 'NOERROR',
                isset($response['status_message']) ? $response['status_message'] : 'NOMESSAGE'
            ));

        return $response;
    }
}