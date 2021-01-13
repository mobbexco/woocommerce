<?php
require_once 'utils.php';

class MobbexHelper extends WC_Settings_API
{

    public function __construct()
    {
        $this->id = MOBBEX_WC_GATEWAY_ID;
        $this->enabled = $this->get_option('enabled');
        $this->api_key = $this->get_option('api-key');
        $this->access_token = $this->get_option('access-token');
    }

    public function isReady()
    {
        if ($this->enabled !== 'yes') {
            return false;
        }

        if (empty($this->api_key) || empty($this->access_token)) {
            return false;
        }

        return true;
    }

    /**
     * Get sources with common plans from mobbex.
     * @param integer|null $total
     */
    public function get_sources($total = null)
    {
        // If plugin is not ready
        if (!$this->isReady()) {
            return [];
        }

        $data = $total ? '?total=' . $total : null;

        $response = wp_remote_get(MOBBEX_SOURCES . $data, [

            'headers' => [
                'cache-control' => 'no-cache',
                'content-type' => 'application/json',
                'x-api-key' => $this->api_key,
                'x-access-token' => $this->access_token,
            ],

        ]);

        if (!is_wp_error($response)) {
            $response = json_decode($response['body'], true);
            $data = $response['data'];

            if ($data) {
                return $data;
            }
        }

        return [];
    }

    /**
     * Get sources with advanced rule plans from mobbex.
     * @param string $rule
     */
    public function get_sources_advanced($rule = 'externalMatch')
    {
        if (!$this->isReady()) {
            return [];
        }

        $response = wp_remote_get(str_replace('{rule}', $rule, MOBBEX_ADVANCED_PLANS), [

            'headers' => [
                'cache-control' => 'no-cache',
                'content-type' => 'application/json',
                'x-api-key' => $this->api_key,
                'x-access-token' => $this->access_token,
            ],

        ]);

        if (!is_wp_error($response)) {
            $response = json_decode($response['body'], true);
            $data = $response['data'];

            if ($data) {
                return $data;
            }
        }

        return [];
    }
}
