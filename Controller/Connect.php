<?php

namespace Mobbex\WP\Checkout\Controller;

class Connect
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
        $this->logger = new \Mobbex\WP\Checkout\Model\Logger();
        $this->helper = new \Mobbex\WP\Checkout\Model\Helper();

        add_action('rest_api_init', function () {
            register_rest_route(
                'mobbex/v1',
                '/connect_start',
                [
                    'permission_callback' => '__return_true',
                    'callback'            => [$this, 'start_connect'],
                    'methods'             => \WP_REST_Server::CREATABLE,
                ]
            );

            register_rest_route(
                'mobbex/v1',
                '/connect_redirect',
                [
                    'permission_callback' => '__return_true',
                    'callback'            => [$this, 'process_connect'],
                    'methods'             => \WP_REST_Server::READABLE,
                ]
            );
        });
    }

    /**
     * Starts Mobbex dev connect flow
     *
     * @param \WP_REST_Request $request
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function start_connect($request)
    {
        $params     = $request->get_json_params();
        $api_key    = isset($params['api_key']) ? trim((string) $params['api_key']) : '';
        $return_url = isset($params['return_url']) ? esc_url_raw((string) $params['return_url']) : '';
        if (!$api_key || !$return_url)
            return new \WP_Error(
                'mbbx_connect_missing_values', 
                'Missing api key or return url.', 
                ['status' => 400]
            );

        try {
            $res = wp_remote_post('https://api.mobbex.com/p/developer/connect', [
                'timeout' => 30,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-api-key'    => $api_key,
                ],
                'body'    => wp_json_encode([
                    'return_url' => $return_url,
                ]),
            ]);
        } catch (\Exception $e) {
            return new \WP_Error(
                'mbbx_connect_request_error', 
                $e->getMessage(), 
                ['status' => 502]
            );
        }

        if (is_wp_error($res))
            return new \WP_Error(
                'mbbx_connect_request_error', 
                $res->get_error_message(), 
                ['status' => 502]
            );
        
        $status = wp_remote_retrieve_response_code($res);
        if ($status >= 400)
            return new \WP_Error(
                'mbbx_connect_request_error', 
                'Could not start connect flow.', 
                ['status' => $status ?: 502]
            );

        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (!is_array($body))
            return new \WP_Error(
                'mbbx_connect_invalid_response', 
                'Invalid connect response.', 
                ['status' => 502]
            );

        $connect_id   = $body['data']['id'];
        $redirect_url = $body['data']['url'];

        if (empty($connect_id) || empty($redirect_url))
            return new \WP_Error(
                'mbbx_connect_missing_data', 
                'Missing data in connect response.', 
                ['status' => 502]
            );

        // Temporarily cache connect_id with five minutes expiration time
        // transient api: https://developer.wordpress.org/apis/transients/
        set_transient("mbbx_connect_api_key_$connect_id", $api_key, 5 * MINUTE_IN_SECONDS);

        //garantee clear response
        ob_clean();

        return new \WP_REST_Response([
            'success'      => true,
            'connect_id'   => $connect_id,
            'redirect_url' => $redirect_url,
        ], 200);
    }

    /**
     * Handles Mobbex dev connect callback and stores access token as setting in wp_options table.
     *
     * @param \WP_REST_Request $request
     */
    public function process_connect($request)
    {
        $c_id = sanitize_text_field($request->get_param('connectId'));
        if (empty($c_id))
            return new \WP_Error(
                'mbbx_connect_missing_id', 
                'Missing connect id.', 
                ['status' => 400]
            );

        // get api key value from transient api cache
        $api_key = get_transient("mbbx_connect_api_key_$c_id");
        if (empty($api_key))
            return new \WP_Error(
                'mbbx_connect_api_key', 
                'Transient api key not found.', 
                ['status' => 400]
            );

        $connect_url = "https://api.mobbex.com/p/developer/connect/$c_id/credentials";
        try {
            $res = wp_remote_get($connect_url, [
                'timeout' => 30,
                'headers' => [
                    'x-api-key' => $api_key,
                ],
            ]);
        } catch (\Exception $e) {
            return new \WP_Error(
                'mbbx_connect_request_error', 
                $e->getMessage(), 
                ['status' => 502]
            );
        }

        $status = wp_remote_retrieve_response_code($res);
        $body   = json_decode(wp_remote_retrieve_body($res), true);

        if ($status >= 400 || !is_array($body))
            return new \WP_Error(
                'mbbx_connect_invalid_response', 
                '[Mobbex] Response body is invalid.', 
                ['status' => $status ?: 502]
            );

        $access_token = $body['data']['access_token'];
        if (!$access_token)
            return new \WP_Error(
                'mbbx_connect_missing_access_token', 
                '[Mobbex] Missing access token in response.', 
                ['status' => 502]
            );

        $settings = get_option('woocommerce_' . MOBBEX_WC_GATEWAY_ID . '_settings', []);
        if (!is_array($settings))
            $settings = [];
        
        $settings['access-token'] = $access_token;

        update_option('woocommerce_' . MOBBEX_WC_GATEWAY_ID . '_settings', $settings);
        delete_transient("mbbx_connect_api_key_$c_id");

        wp_safe_redirect(add_query_arg([
            'page'    => 'wc-settings',
            'tab'     => 'checkout',
            'section' => 'mobbex',
        ], admin_url('admin.php')));
        exit;
    }
}
