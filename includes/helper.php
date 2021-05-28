<?php
require_once 'utils.php';

class MobbexHelper
{

    public function __construct()
    {
        // Init settings (Full List in WC_Gateway_Mobbex::init_form_fields)
        $option_key = 'woocommerce_' . MOBBEX_WC_GATEWAY_ID . '_settings';
		$settings = get_option($option_key, null);
        foreach ($settings as $key => $value)
            $this->$key = $value;
    }

    public function isReady()
    {
        return ($this->enabled === 'yes' && !empty($this->api_key) && !empty($this->access_token));
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
            error_log("Llega!  ".print_r($data), 3, "/var/www/html/wp-content/plugins/mwoocommerce/planesAvamzados.log");
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

        //error_log("Llega!  ".$response, 3, "/var/www/html/wp-content/plugins/mwoocommerce/planesAvamzados.log");

        if (!is_wp_error($response)) {
            $response = json_decode($response['body'], true);
            $data = $response['data'];
            if ($data) {
                return $data;
            }
        }

        return [];
    }

    private function get_payment_methods($tax_id,$total){
        
        $url = str_replace('{total}', $total, MOBBEX_LIST_PLANS);
        $response = wp_remote_get(str_replace('{tax_id}', $tax_id, $url), [

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

    private function get_payment_image($reference){

        $response = wp_remote_get(str_replace('{reference}', $reference, MOBBEX_PAYMENT_IMAGE), [

            'headers' => [
                'cache-control' => 'no-cache',
                'content-type' => 'application/json',
                'x-api-key' => $this->api_key,
                'x-access-token' => $this->access_token,
            ],

        ]);
        if (!is_wp_error($response)) {
            return $response['body'];
        }

        return [];
    }


    /**
     * 
     */
    public function get_list_source($tax_id,$total,$product_id)
    {
        
        //error_log("Llega!  ".print_r(var_dump(MOBBEX_LIST_PLANS)), 3, "/var/www/html/wp-content/plugins/mwoocommerce/planesAvamzados.log");
        $payment_methods_mobbex = $this->get_payment_methods($tax_id,$total);
        error_log("Llega!  ".print_r($payment_methods_mobbex,true), 3, "/var/www/html/wp-content/plugins/mwoocommerce/planesAvamzados.log");
        $payment_methods = array();
        if (!empty($payment_methods_mobbex)) {
            // installments view source
            
            $no_active_plans = $this->get_no_active_plans($product_id);
               
            
            foreach($payment_methods_mobbex as $payment_method){
                //only add if payment is enabled
                if($payment_method['installments']['enabled'])
                {
                    $included_plans= array();
                    foreach($payment_method['installments']['list'] as $installment)
                    {
                        $plan = array();
                        //if it is a 'ahora' plan then use the reference 
                        if(strpos($installment['reference'],'ahora')!== false){
                            if(!in_array($installment['reference'],$no_active_plans)){
                                $plan['name'] = $installment['name'];
                                $plan['amount'] = $installment['totals']['total'];    
                            }
                        }else{
                            $plan['name'] = $installment['name'];
                            $plan['amount'] = $installment['totals']['total'];
                        }
                        if(!empty($plan)){
                            $included_plans[] = $plan;
                        }
                    }       
                    if(!empty($included_plans)){
                        $method = array();
                        $method['id'] = $payment_method['source']['id'];
                        $method['reference'] = $payment_method['source']['reference'];
                        $method['name'] = $payment_method['source']['name'];
                        $method['installments'] = $included_plans;
                        $method['image'] = $this->get_payment_image($payment_method['source']['reference']);
                        $payment_methods[] = $method;
                    }
                }

            }
            error_log("[".print_r($payment_methods,true)."]", 3, "/var/www/html/wp-content/plugins/mwoocommerce/get_list_source.log"); 
        }else{
            error_log("Error!  ".print_r(var_dump($response)), 3, "/var/www/html/wp-content/plugins/mwoocommerce/planes.log");
        }

        return $payment_methods;

    }


    private function get_no_active_plans($product_id){
        
        $ahora = array(
            'ahora_3'  => 'Ahora 3',
            'ahora_6'  => 'Ahora 6',
            'ahora_12' => 'Ahora 12',
            'ahora_18' => 'Ahora 18',
        );
        $no_active_plans = array();
        
        // Check "Ahora" custom fields in categories
        $categories_ids = array();
        $categories = get_the_terms( $product_id, 'product_cat' );//retrieve categories
        foreach($categories as $category){
            array_push($categories_ids, $category->term_id);
        }
        
        foreach ($ahora as $key => $value) {
            //check if any of the product's categories have the plan selected
            //Have one or more categories
            foreach($categories_ids as $cat_id){
                if (get_term_meta($cat_id, $key, true) === 'yes') {
                    //Plan is checked in the category
                    $no_active_plans[] = $key;
                    unset($ahora[$key]);
                    break;
                }
            }
        }

        foreach ($ahora as $key => $value) 
        {
            //error_log("[".print_r($key."-".get_post_meta($product_id, $key, true),true)."]", 3, "/var/www/html/wp-content/plugins/mwoocommerce/get_no_active_plans.log");  
            if (get_post_meta($product_id, $key, true) === 'yes') {
                //the product have $ahora[$key] plan selected
                $no_active_plans[] = $key;
                unset($ahora[$key]);
            }
        }

        $checked_common_plans = unserialize(get_post_meta($product_id, 'common_plans', true));
        $checked_advanced_plans = unserialize(get_post_meta($product_id, 'advanced_plans', true));

        if (!empty($checked_common_plans)) 
        {
            foreach ($checked_common_plans as $key => $common_plan) {
                $no_active_plans[] = $common_plan;
                unset($checked_common_plans[$key]);
            }
        }

        if (!empty($checked_advanced_plans)) 
        {
            foreach ($checked_advanced_plans as $key => $advanced_plan) {
                $no_active_plans[] =$advanced_plan;
                unset($checked_advanced_plans[$key]);
            }
        }
        

        return $no_active_plans;
    }

    


    
}
