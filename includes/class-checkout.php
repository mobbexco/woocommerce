<?php

class MobbexCheckout
{
    public $total = 0;

    public $reference = '';

    public $relation = 0;

    public $customer = [];

    public $address = [];

    public $items = [];

    public $merchants = [];

    public $installments = [];

    public $endpoints = [];

    /** Module configured options */
    public $settings = [];

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
    public function __construct($settings, $api, $filter = 'mobbex_checkout_custom_data')
    {
        $this->settings = $settings;
        $this->api      = $api;
        $this->filter   = $filter;
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
                'test'         => $this->settings['test_mode'] == 'yes',
                'multicard'    => $this->settings['multicard'] == 'yes',
                'multivendor'  => $this->settings['multivendor'] != 'no' ? $this->settings['multivendor'] : false,
                'wallet'       => $this->settings['wallet'] == 'yes' && wp_get_current_user()->ID,
                'intent'       => $this->settings['payment_mode'],
                'timeout'      => 5,
                'items'        => $this->items,
                'merchants'    => $this->merchants,
                'installments' => $this->installments,
                'customer'     => array_merge($this->customer, $this->address),
                'options'      => [
                    'embed'    => $this->settings['button'] == 'yes',
                    'domain'   => str_replace('www.', '', parse_url(home_url(), PHP_URL_HOST)),
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
     * Set address data.
     * 
     * @param string|null $street Street name with house number.
     * @param string|int|null $postcode Postal|ZIP code.
     * @param string|null $state
     * @param string|null $country Country ISO 3166-1 alpha-3 code.
     * @param string|null $note
     * @param string|null $agent User agent.
     */
    public function set_address($street = null, $postcode = null, $state = null, $country = null, $note = null, $agent = null)
    {
        $this->address = [
            'address'       => trim(preg_replace('/[0-9]/', '', (string) $street)),
            'addressNumber' => trim(preg_replace('/[^0-9]/', '', (string) $street)),
            'zipCode'       => $postcode,
            'state'         => $state,
            'country'       => $country,
            'addressNotes'  => $note,
            'userAgent'     => $agent,
        ];
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
    public function add_item($total, $quantity = 1, $description = null, $image = null, $entity = null, $suscription = null)
    {
        // Try to add entity to merchants
        if ($entity)
            $this->merchants[] = ['uid' => $entity];

        if($suscription) {
            $this->items[] = [
                'type'      => 'suscription',
                'reference' => $suscription
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