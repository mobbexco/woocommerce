<?php

class MobbexApi
{
    public $ready = false;

    /** Mobbex API base URL */
    public $api_url = 'https://api.mobbex.com/p/';

    /** Commerce API Key */
    private $api_key;

    /** Commerce Access Token */
    private $access_token;

    /**
     * Constructor.
     * 
     * Set Mobbex credentails.
     * 
     * @param string $api_key Commerce API Key.
     * @param string $access_token Commerce Access Token.
     */
    public function __construct($api_key, $access_token)
    {
        // TODO: Maybe this could recieve a mobbex store object
        $this->api_key      = $api_key;
        $this->access_token = $access_token;
        $this->ready        = !empty($api_key) && !empty($access_token);
    }

    /**
     * Make a request to Mobbex API.
     * 
     * @param array $data 
     * 
     * @return mixed Result status or data if exists.
     * 
     * @throws \Mobbex\Exception
     */
    public function request($data)
    {
        error_log('api: ' . "\n" . json_encode(43, JSON_PRETTY_PRINT) . "\n", 3, 'log.log');
        if (!$this->ready)
        return false;
        
        if (empty($data['method']) || empty($data['uri']))
            throw new \Mobbex\Exception('Mobbex request error: Missing arguments', 0, $data);
        
        error_log('api: ' . "\n" . json_encode(50, JSON_PRETTY_PRINT) . "\n", 3, 'log.log');
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL            => $this->api_url . $data['uri'] . (!empty($data['params']) ? '?' . http_build_query($data['params']) : null),
            CURLOPT_HTTPHEADER     => $this->get_headers(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => $data['method'],
            CURLOPT_POSTFIELDS     => !empty($data['body']) ? json_encode($data['body']) : null,
        ]);
        error_log('api: ' . "\n" . json_encode(63, JSON_PRETTY_PRINT) . "\n", 3, 'log.log');
        
        $response = curl_exec($curl);
        $error    = curl_error($curl);
        
        curl_close($curl);
        
        error_log('api: ' . "\n" . json_encode(70, JSON_PRETTY_PRINT) . "\n", 3, 'log.log');
        // Throw curl errors
        if ($error)
         throw new \Mobbex\Exception('Curl error in Mobbex request #:' . $error, curl_errno($curl), $data);
        error_log('api: ' . "\n" . json_encode(74, JSON_PRETTY_PRINT) . "\n", 3, 'log.log');
        
        $result = json_decode($response, true);

        error_log('Log Message: ' . "\n" . json_encode($result, JSON_PRETTY_PRINT) . "\n", 3, 'log.log');

        // Throw request errors
        if (!$result)
            throw new \Mobbex\Exception('Mobbex request error: Invalid response format', 0, $data);

        if (!$result['result'])
            throw new \Mobbex\Exception(
                sprintf(
                    'Mobbex request error #%s: %s',
                    isset($result['code']) ? $result['code'] : '',
                    isset($result['status_message']) ? $result['status_message'] : ''
                ),
                599,
                $data
            );

        return isset($result['data']) ? $result['data'] : $result['result'];
    }

    /**
     * Get headers to connect with Mobbex API.
     * 
     * @return string[] 
     */
    private function get_headers()
    {
        return [
            'cache-control: no-cache',
            'content-type: application/json',
            'x-api-key: ' . $this->api_key,
            'x-access-token: ' . $this->access_token,
            'x-ecommerce-agent: WordPress/' . get_bloginfo('version') . ' WooCommerce/' . WC_VERSION . ' Plugin/' . MOBBEX_VERSION,
        ];
    }
}