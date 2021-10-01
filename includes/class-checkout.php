<?php

class MobbexCheckout
{
    public $total = 0;

    public $reference = '';

    public $relation = 0;

    public $customer = [];

    public $items = [];

    public $installments = [];

    public $endpoints = [];

    /**  Module configuration settings */
    public $settings = [];

    /** @var MobbexApi */
    public $api;

    public function __construct($settings, $api)
    {
        $this->settings = $settings;
        $this->api      = $api;
    }

    /**
     * Create the checkout.
     * 
     * @return array Checkout response
     */
    public function create()
    {
        $data = [
            'uri'    => 'checkout',
            'method' => 'POST',
            'body'   => apply_filters('mobbex_checkout_custom_data', [
                'total'        => $this->total,
                'webhook'      => $this->endpoints['webhook'],
                'return_url'   => $this->endpoints['return'],
                'reference'    => $this->reference,
                'description'  => 'Pedido #' . $this->relation,
                'test'         => $this->settings['test_mode'] == 'yes',
                'multicard'    => $this->settings['multicard'] == 'yes',
                'wallet'       => $this->settings['use_wallet'] && wp_get_current_user()->ID,
                'intent'       => $this->settings['payment_mode'],
                'timeout'      => 5,
                'items'        => $this->items,
                'installments' => $this->installments,
                'customer'     => $this->customer,
                'options'      => [
                    'button'   => $this->settings['use_button'] == 'yes',
                    'domain'   => parse_url(home_url())['host'],
                    'theme'    => [
                        'type'       => $this->settings['checkout_theme'],
                        'background' => $this->settings['checkout_background_color'],
                        'header'     => [
                            'name' => $this->settings['checkout_title'] ?: get_bloginfo('name'),
                            'logo' => $this->settings['checkout_logo'],
                        ],
                        'colors'     => [
                            'primary' => $this->settings['checkout_primary_color'],
                        ]
                    ],
                    'platform' => [
                        'name'      => 'woocommerce',
                        'version'   => MOBBEX_VERSION,
                        'ecommerce' => [
                            'wordpress'   => get_bloginfo('version'),
                            'woocommerce' => WC_VERSION
                        ]
                    ],
                    'redirect' => [
                        'success' => true,
                        'failure' => false,
                    ],
                ],
            ], $this->relation)
        ];

        return $this->api->request($data);
    }

    /**
     * Set total to pay.
     * 
     * @param int|string $total
     */
    public function set_total($total)
    {
        $this->total = $total;
    }

    /**
     * Set the reference.
     * 
     * @param string|int $id Unique ID of the instance that will be related to the checkout.
     */
    public function set_reference($id)
    {
        // First, set the relation instance id
        $this->relation = $id;

        $reference = [
            'wc_id:' . $id,
            'time:' . time()
        ];

        // Add reseller id
        if (!empty($this->settings['reseller_id']))
            $reference[] = 'reseller:' . str_replace(' ', '-', trim($this->settings['reseller_id']));

        $this->reference = implode('_', $reference);
    }

    /**
     * Set customer data.
     * 
     * @param string $name
     * @param string $email
     * @param string $identification
     * @param string|null $phone
     * @param string|int|null $uid
     */
    public function set_customer($name, $email, $identification = '12123123', $phone = null, $uid = null)
    {
        $this->customer = compact('name', 'email', 'identification', 'phone', 'uid');
    }

    /**
     * Set notification endpoints.
     * 
     * @param mixed $return Post-payment redirect URL
     * @param mixed $webhook URL that recieve the Mobbex payment response
     */
    public function set_endpoints($return, $webhook)
    {
        $this->endpoints = compact('return', 'webhook');
    }

    /**
     * Add an item.
     * 
     * @param int|string $total
     * @param int $quantity
     * @param string|null $decription
     * @param string|null $image
     */
    public function add_item($total, $quantity = 1, $decription = null, $image = null)
    {
        $this->items[] = compact('total', 'quantity', 'description', 'image');
    }

    /**
     * Add an installment to show in checkout.
     * 
     * @param string $uid UID of a plan configured with advanced rules
     */
    public function add_installment($uid)
    {
        $this->installments[] = '+uid:' . $uid;
    }

    /**
     * Block an installment type in checkout.
     * 
     * @param string $reference Reference of the plans to hide
     */
    public function block_installment($reference)
    {
        $this->installments[] = '-' . $reference;
    }
}