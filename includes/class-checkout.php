<?php

class MobbexCheckout
{
    public $total = 0;

    public $reference = '';

    public $relation = 0;

    public $customer = [];

    public $addresses = [];

    public $items = [];

    public $merchants = [];

    public $installments = [];

    public $endpoints = [];

    /** Module configured options */
    public $settings = [];

    /** @var \Mobbex\WP\Checkout\Includes\Config */
    public $config;

    /** @var MobbexApi */
    public $api;

    /** Name of hook to execute when body is filtered */
    public $filter = '';

    /**
     * Constructor.
     * 
     * @param array $settings Module configured options.
     * @param MobbexApi $api API conector.
     * @param string $filter Name of hook to execute when body is filtered.
     */
    public function __construct($api, $filter = 'mobbex_checkout_custom_data')
    {
        $this->config = new \Mobbex\WP\Checkout\Includes\Config;
        $this->api    = $api;
        $this->filter = $filter;
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
            'body'   => apply_filters($this->filter, [
                'total'        => $this->total,
                'webhook'      => $this->endpoints['webhook'],
                'return_url'   => $this->endpoints['return'],
                'reference'    => $this->reference,
                'description'  => 'Pedido #' . $this->relation,
                'test'         => $this->config->test_mode == 'yes',
                'multicard'    => $this->config->multicard == 'yes',
                'multivendor'  => $this->config->multivendor != 'no' ? $this->config->multivendor : false,
                'wallet'       => $this->config->wallet == 'yes' && wp_get_current_user()->ID,
                'intent'       => $this->config->payment_mode,
                'timeout'      => $this->config->timeout,
                'items'        => $this->items,
                'merchants'    => $this->merchants,
                'installments' => $this->installments,
                'customer'     => array_merge($this->customer),
                'addresses'    => $this->addresses,
                'options'      => [
                    'embed'    => $this->config->button == 'yes',
                    'domain'   => str_replace('www.', '', parse_url(home_url(), PHP_URL_HOST)),
                    'theme'    => [
                        'type'       => $this->config->visual_theme,
                        'background' => $this->config->checkout_background_color,
                        'header'     => [
                            'name' => $this->config->checkout_title ?: get_bloginfo('name'),
                            'logo' => $this->config->checkout_logo,
                        ],
                        'colors'     => [
                            'primary' => $this->config->checkout_primary_color,
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
        ];
        // Add site id
        if (!empty($this->settings['site_id']))
            $reference[] = 'site_id:' . str_replace(' ', '-', trim($this->settings['site_id']));

        // Add reseller id
        if (!empty($this->config->reseller_id))
            $reference[] = 'reseller:' . str_replace(' ', '-', trim($this->config->reseller_id));

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
     * Set address data.
     * 
     * @param Class $object Order or Customer class.
     * 
     */
    public function set_addresses($object)
    {
        foreach (['billing', 'shipping'] as $type) {
            
            foreach (['address_1', 'address_2', 'city', 'state', 'postcode', 'country'] as $method)
                ${$method} = "get_".$type."_".$method;

            // Force address 1 type to string and trim spaces
            $street = trim((string) $object->$address_1());

            $this->addresses[] = [
                'type'         => $type,
                'country'      => $this->convert_country_code($object->$country()),
                'state'        => $object->$state(),
                'city'         => $object->$city(),
                'zipCode'      => $object->$postcode(),
                'street'       => trim(preg_replace('/(\D{0})+(\d*)+$/', '', $street)),
                'streetNumber' => str_replace(preg_replace('/(\D{0})+(\d*)+$/', '', $street), '', $street),
                'streetNotes'  => $object->$address_2()
            ];

        }
    }

    /**
     * Converts the WooCommerce country codes to 3-letter ISO codes.
     * 
     * @param string $code 2-Letter ISO code.
     * 
     * @return string|null
     */
    public function convert_country_code($code)
    {
        $countries = include ('iso-3166.php') ?: [];

        return isset($countries[$code]) ? $countries[$code] : null;
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
     * @param string|null $description
     * @param string|null $image
     * @param string|null $entity
     */
    public function add_item($total, $quantity = 1, $description = null, $image = null, $entity = null, $subscription = null)
    {
        // Try to add entity to merchants
        if ($entity)
            $this->merchants[] = ['uid' => $entity];

        if($subscription) {
            $this->items[] = [
                'type'      => 'subscription',
                'reference' => $subscription
            ];
        } else {
            $this->items[] = compact('total', 'quantity', 'description', 'image', 'entity');
        }
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