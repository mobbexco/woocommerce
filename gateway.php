<?php

class WC_Gateway_Mobbex extends WC_Payment_Gateway
{

    function __construct()
    {

        $this->id = 'mobbex';
        $this->method_title = __('Mobbex', 'mobbex-for-woocommerce');
        $this->method_description = __('Mobbex Payment Gateway redirects customers to Mobbex to enter their payment information.', 'mobbex-for-woocommerce');

        $this->init_form_fields();
        $this->init_settings();

        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->api_key = $this->get_option('api-key');
        $this->access_token = $this->get_option('access-token');

        if (empty($this->api_key) || empty($this->access_token)) {

            $this->error = true;
            Mobbex::notice('error', __('You need to specify an API Key and an Access Token.', 'mobbex-for-woocommerce'));

        }

        if (!$this->error) {
    
            add_action('woocommerce_api_mobbex_webhook', [$this, 'mobbex_webhook']);
            add_action('woocommerce_api_mobbex_return_url', [$this, 'mobbex_return_url']);
            
        }
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

    }

    function init_form_fields()
    {

        $this->form_fields = [

            'enabled' => [

                'title' => __('Enable/Disable', 'mobbex-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable checking out with Mobbex.', 'mobbex-for-woocommerce'),
                'default' => 'yes'

            ],

            'title' => [

                'title' => __('Title', 'mobbex-for-woocommerce'),
                'type' => 'text',
                'description' => __('This title will be shown on user checkout.', 'mobbex-for-woocommerce'),
                'default' => __('Pay with Mobbex', 'mobbex-for-woocommerce'),
                'desc_tip' => true,

            ],

            'description' => [

                'title' => __('Description', 'mobbex-for-woocommerce'),
                'description' => __('This description will be shown on user checkout.', 'mobbex-for-woocommerce'),
                'type' => 'textarea',
                'default' => ''

            ],

            'api-key' => [

                'title' => __('API Key', 'mobbex-for-woocommerce'),
                'description' => __('Your Mobbex API key.', 'mobbex-for-woocommerce'),
                'type' => 'text'

            ],

            'access-token' => [

                'title' => __('Access Token', 'mobbex-for-woocommerce'),
                'description' => __('Your Mobbex access token.', 'mobbex-for-woocommerce'),
                'type' => 'text'

            ]

        ];

    }

    function process_payment($order_id)
    {

        if ($this->error)
            return ['result' => 'error'];

        global $woocommerce;
        $order = new WC_Order($order_id);

        $order->reduce_order_stock();
        $woocommerce->cart->empty_cart();

        $order->update_status('pending', __('Awaiting Mobbex Webhook', 'mobbex-for-woocommerce'));

        $return_url = $this->get_return_url($order);

        if (empty($return_url))
            return ['result' => 'error'];

        return [

            'result' => 'success',
            'redirect' => $this->get_return_url($order)

        ];

    }

    function get_return_url($order = null)
    {

        if (empty($order)) die('No order was found');

        $response = wp_remote_post(MOBBEX_CHECKOUT, [

            'headers' => [

                'cache-control' => 'no-cache',
                'content-type' => 'application/json',
                'x-api-key' => $this->api_key,
                'x-access-token' => $this->access_token
            ],

            'body' => json_encode([

                'total' => $order->get_total(),
                'reference' => $order->get_id(),
                'description' => 'No description',
                'items' => $this->get_items($order),
                'webhook' => $this->get_api_endpoint('mobbex_webhook', $order),
                'return_url' => $this->get_api_endpoint('mobbex_return_url', $order),

            ]),

            'data_format' => 'body'

        ]);

        if (!is_wp_error($response)) {

            $response = json_decode($response['body'], true);
            $mobbex_checkout_url = $response['data']['url'];
            if ($mobbex_checkout_url)
                return $mobbex_checkout_url;

        }

        return null;

    }

    function get_items($order)
    {

        $items = [];

        foreach ($order->get_items() as $item) {

            $image = get_the_post_thumbnail_url($item->get_id(), 'thumbnail');
            $default_image = wc_placeholder_img_src('thumbnail');
            $image = $image ? $image : $default_image;

            $items[] = [

                'image' => $image,
                'quantity' => $item->get_quantity(),
                'description' => $item->get_name(),
                'total' => $item->get_total()

            ];

        }

        return $items;

    }

    function get_api_endpoint($endpoint, $order)
    {

        $query = ['wc-api' => $endpoint];

        if ($order) {

            $query['mobbex_order_id'] = $order->get_id();
            $query['mobbex_token'] = $this->generate_token();

        }

        return add_query_arg($query, home_url('/'));

    }

    function mobbex_webhook()
    {

        if (!$_POST['data']) die('Mobbex sent an invalid request body.');

        $status = $_POST['data']['payment']['status']['code'];
        $id = $_REQUEST['mobbex_order_id'];
        $token = $_REQUEST['mobbex_token'];

        if (empty($status) || empty($id) || empty($token)) die('Missing status, id, or token.');
        if (!$this->valid_mobbex_token($token)) die('Invalid mobbex token.');

        $order = new WC_Order($id);

        switch ($status) {

            case 200:
            case 300:
                $order->payment_complete();
                break;

            case 0:
            case 1:
            case 2:
            case 3:
                $order->update_status('on-hold', __('Awaiting payment', 'mobbex-for-woocommerce'));
                break;

            default:
                $order->update_status('failed', __('Order failed', 'mobbex-for-woocommerce'));
                break;


        }

        die();

    }

    function mobbex_return_url()
    {

        $status = $_REQUEST['status'];
        $id = $_REQUEST['mobbex_order_id'];
        $token = $_REQUEST['mobbex_token'];
    
    // What to do on error?
        if (empty($status) || empty($id) || empty($token)) die('Missing status, id, or token.');

    // What to do on error?
        if (!$this->valid_mobbex_token($token)) die('Invalid mobbex token.');

        $order = new WC_Order($id);

        if ($status >= 400)
            $redirect = $order->get_cancel_order_url();
        else
            $redirect = $order->get_checkout_order_received_url();

        wp_safe_redirect($redirect);
        die();

    }

    function valid_mobbex_token($token)
    {

        return $token == $this->generate_token();

    }

    function generate_token()
    {

        return md5($this->api_key . '|' . $this->access_token);

    }

}