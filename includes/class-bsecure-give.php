<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.bsecure.pk
 * @since      1.0.1
 *
 * @package    Bsecure_Give
 * @subpackage Bsecure_Give/includes
 */
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.1
 * @package    Bsecure_Give
 * @subpackage Bsecure_Give/includes
 * @author     bSecure <info@bsecure.pk>
 */

class Bsecure_Give {  

    const PLUGIN_NAME = 'Custom';
    const PLUGIN_VERSION = '1.0.0';
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELED = 'cancelled';
    const STATUS_ONHOLD = 'on-hold';
    const STATUS_FAILED = 'failed';
    const STATUS_DRAFT = 'bsecure_draft';
    const ORDER_TYPE_APP = 'app';
    const ORDER_TYPE_MANUAL = 'manual'; 
    const ORDER_TYPE_PAYMENT_GATEWAY = 'payment_gateway';
    const BSECURE_CREATED_STATUS = 1;
    const BSECURE_INITIATED_STATUS = 2;
    const BSECURE_PROCESSING_STATUS = 3;
    const BSECURE_EXPIRED_STATUS = 6;
    const BSECURE_FAILED_STATUS = 7;
    const BSECURE_DEV_VIEW_ORDER_URL = 'https://partners-dev.bsecure.app/view-order/';
    const BSECURE_STAGE_VIEW_ORDER_URL = 'https://partners-stage.bsecure.app/view-order/';
    const BSECURE_LIVE_VIEW_ORDER_URL = 'https://partner.bsecure.pk/view-order/';

    public $base_url = "";

    public function __construct(){      

        $this->base_url = '';

        if(function_exists('give_get_settings')){
            $give_options = give_get_settings();

            $this->base_url = !empty($give_options['bsecure_base_url']) ? $give_options['bsecure_base_url'] : '';
        }

        if(isset($_GET['order_ref']) && isset($_GET['integration'])){

            if($_GET['integration'] == 'givewp' )
            add_action( 'wp_loaded', array($this, 'manageGiveWpOrder' ), 5);

        }
        
    }
    
    public function init() {       


        add_action( 'wp_enqueue_scripts', array($this, 'give_bsecure_frontend_scripts' ), 99 );   

        add_action( 'admin_enqueue_scripts', array($this, 'give_bsecure_admin_scripts' ), 99 );

        if ( file_exists(plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bsecure-give-functions.php') ) {

            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bsecure-give-functions.php';

            new Bsecure_Give_Functions;

        }

    }


    /**
     * Load the required dependencies for this plugin.
     *
     *
     * @since    1.0.1
     * @access   private
     */
    private function load_dependencies() {

        
    }

    /* Load Script file in front end */
	public function give_bsecure_frontend_scripts() {
        
	    wp_enqueue_script( 'give_bsecure_frontend_scripts', plugin_dir_url( __FILE__ ) . '../assets/js/give-bsecure-front.js',null, mt_rand(), true );

        wp_enqueue_style( 'give_bsecure_frontend_style', plugin_dir_url( __FILE__ ) . '../assets/css/bsecure-style.css',null, mt_rand(), false ); 

        //$wc_is_hosted_checkout = get_option('wc_is_hosted_checkout','yes');
        
	     wp_localize_script( 'give_bsecure_frontend_scripts', 'give_bsecure_js_object',

	            array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 
                    'ajax_loader' => plugin_dir_url( __FILE__ ) . '../assets/images/ajax-loader.gif',
                    'nonce' => wp_create_nonce('bsecure-ajax-nonce'),                                     
                    //'bsecureWindow' => '',                    
                    //'wc_is_hosted_checkout' => $wc_is_hosted_checkout,                   
                    'site_url' => site_url() 
                ) 
            );

	}

    public function give_bsecure_admin_scripts() {        

        wp_enqueue_style( 'give_bsecure_admin_style', plugin_dir_url( __FILE__ ) . '../assets/css/bsecure-style-admin.css',null, mt_rand(), false );

    }

	/**
     * Send curl request using wp core function WP_Http for curl request
     *
     * @return array server response .
     */
	public function bsecureSendCurlRequest($url, $params = '', $retry = 0, $isJson = false){

		$wp_http = new WP_Http;

        $params['timeout'] = 20; // How long the connection should stay open in seconds.

        $pluginInfo = ['x-plugin-name' => Bsecure_Give::PLUGIN_NAME, 'x-plugin-version' => Bsecure_Give::PLUGIN_VERSION];

        $params['headers'] = !empty($params['headers']) ? array_merge($pluginInfo,  $params['headers']) : $pluginInfo;

		$response = $wp_http->request( $url,  ($params));	
        
		if( is_wp_error( $response ) ) {    

            if($retry < 3){

                $retry++;

                $res = $this->bsecureSendCurlRequest($url, $params, $retry);

            }else{

                status_header( 422 );

                $msg = __("An error occurred while sending request: ",'bsecure-give') . $response->get_error_message();

                if($isJson){

                    echo json_encode(['status' => false, 'msg' => esc_html($msg)]); die;

                }else{

                    echo esc_html($msg); die;

                }

            }             

		}else if(!empty($response['body'])){			

			return json_decode($response['body']);						

		}

        // Retry request 3 times if failed
        if($retry < 3){

            $retry++;

            error_log('retries:'.$retry.' response blank');

            $this->bsecureSendCurlRequest($url, $params, $retry);          

        }else{

            status_header( 422 );

            $msg = __("An error occurred while sending request!",'bsecure-give') ; 

            if($isJson){

                echo json_encode(['status' => false, 'msg' => esc_html($msg)]); die;

            }else{

                echo esc_html($msg); die;

            }

            die;

        }

	}

    /*
     * Validate bSecure response 
     */
    public function validateResponse($response, $type = ''){

        $errorMessage = ["error" => false, "msg" => __("No response from bSecure server!")];

        if(empty($response)){

            return ["error" => true, "msg" => __("No response from bSecure server!")];

        }

        if(empty($response->status) && !empty($response->message)){         

            return $errorMessage;

        }else if((!empty($response->status) && $response->status != 200)){            

            $msg = (is_array($response->message)) ? implode(",", $response->message) : $response->message;

            $errorMessage = ["error" => true, "msg" => $msg];

        } else if(!empty($response->message) && !is_array($response->message) && !empty($response->status)){            
            if($response->status != 200){

                $errorMessage = ["error" => true, "msg" => $response->message];

            }            

        }else if(!empty($response->message) && is_array($response->message) && !empty($response->status)){      

            if($response->status != 200){

                $errorMessage = ["error" => true, "msg" => implode(",", $response->message)];

            }           

        }     

        return $errorMessage;

    }

    /*
     * Validate bSecure response order data 
     */
    public function validateOrderData($order_data){

        $defaultMessage = [
                            'status' => false, 
                            'msg' => __('Order data validated successfully.','bsecure-give'),
                            'is_error' => false
                        ];

        if (strtolower($order_data->order_type) == 'payment_gateway') {

            return $defaultMessage;

        }        

        if (empty($order_data->items) ){

            return  [
                        'status' => true, 
                        'msg' => __("No cart items returned from bSecure server. Please resubmit your order.", 'bsecure-give'),
                        'is_error' => true
                    ];

        }

        return $defaultMessage;

    }

    /**
     * Get oauth token from server
     *
     * @return array server response .
     */
    public function bsecureGetOauthToken(){  

        $give_options = give_get_settings(); 

        $grant_type = 'client_credentials';
        $client_id = $give_options['bsecure_client_id'];
        $client_secret = $give_options['bsecure_client_secret'];
        $store_id = $give_options['bsecure_store_id'];
        $client_id = !empty($store_id) ? $client_id.':'.sanitize_text_field($store_id) : $client_id;
        $config = $this->getBsecureConfig();

        if(!empty($config->token)){

            $oauth_url = $config->token;

        }else{

            return false;

        }        

        $params =   [
                        'sslverify' => false,
                        'method' => 'POST',
                        'body' => 
                            [
                                'grant_type' => $grant_type, 
                                'client_id' => $client_id, 
                                'client_secret' => $client_secret,
                            ],
                    ];

        $response = $this->bsecureSendCurlRequest($oauth_url,$params);

        if($response->status !== 200){

           return  $response;

        }

        if(!empty($response->body)){  

            return $response->body;
        }       

        return $response;
    }

    /**
     * Get Configuration
     *
     * @return array server response .
     */
    public function getBsecureConfig(){

        if(!empty($this->base_url)){            

            $url = $this->base_url."/plugin/configuration";
            $params = ['method' => 'GET'];
            $response = $this->bsecureSendCurlRequest( $url,  $params);            

            if(!empty($response->body->api_end_points)){

                return $response->body->api_end_points;

            }

        }

        return false;

    }

    /*
     * Checkk ssl is enabled
     */
    public static function wc_bsecure_check_ssl(){

        //allow localhost environment to activate without ssl //
        $whitelist = array(

                            '127.0.0.1', 
                            '::1'

                        );

        if(!in_array($_SERVER['REMOTE_ADDR'], $whitelist)){            

            if(!is_ssl()){

                return false;

            }

        }

        return true;

    }

    public function getApiHeaders($accessToken, $isToken = true){

        $give_options = give_get_settings(); 

        $grant_type = 'client_credentials';
        $client_id = $give_options['bsecure_client_id'];
        $client_secret = $give_options['bsecure_client_secret'];
        $store_id = $give_options['bsecure_store_id'];
        $client_id = !empty($store_id) ? $client_id.':'.sanitize_text_field($store_id) : $client_id;      

        $headers =  ['Authorization' => 'Bearer '.$accessToken];        

        if(!$isToken){

            $headers =  [
                            'x-client-id' => base64_encode($client_id),
                            'x-client-token' => base64_encode($client_secret),
                        ];
        }
        
        return $headers;
    }


    /**
    * Manage order at givewp
    * if order found in givewp against bsecure order_ref then update status else create in givewp
    */
    public function manageGiveWpOrder(){      

        $bsecure_order_ref = sanitize_text_field($_GET['order_ref']);

        $order_data = [];

        $order = $this->getGiveWpOrderByBsecureRefId($bsecure_order_ref);

        if(!empty($order)){  
            
            $payment = new Give_Payment( $order->ID ); 

            $order_data = $this->getBsecureOrder($bsecure_order_ref);
            $this->updateOrderPaymentGateway($payment,$order_data);
            
            // if order not success

            if($payment->status == Bsecure_Give::STATUS_CANCELED ){ 

                $this->displayGiveError("Sorry! Your order has been ".$payment->status); 

            }  else if ($payment->status != Bsecure_Give::STATUS_FAILED) {

                give_send_to_success_page();
                exit;

            }

        }

        $this->displayGiveError("Sorry! Your order has been failed.");

        die;

    }

    /**
    * Map bScure statuses with givwwp default statuses
    */
    public function givewpStatus($placement_status, $payment_status){

        /*"order_status": {

        'created'       => 1,

        'initiated'     => 2,

        'placed'        => 3,

        'awaiting-confirmation' => 4,

        'canceled' => 5,

        'expired' => 6,

        'failed' => 7

        'awaiting-payment' => 8

        }*/

        $order_status = Bsecure_Give::STATUS_PROCESSING;
        $placement_status = (int) $placement_status;

        switch ($placement_status) {

            case 1:
            case 2:

                $order_status = Bsecure_Give::STATUS_DRAFT;

            break;

            case 3:

                $order_status = Bsecure_Give::STATUS_PROCESSING;
                // payment_status 1 is Paid
                if($payment_status == 1) {
                     $order_status = Bsecure_Give::STATUS_COMPLETED;
                }
                

            break;          

            case 4:

                $order_status = Bsecure_Give::STATUS_PENDING;

            break;

            case 5: 
            //case 6:
                $order_status = Bsecure_Give::STATUS_CANCELED;

            break;

            //case 5:
            case 6:
            case 7:

                $order_status = Bsecure_Give::STATUS_FAILED;

            break;

            case 8: // Pending Payment at bSecure

                $order_status = Bsecure_Give::STATUS_PENDING;

            break;                          

            default:

                $order_status = Bsecure_Give::STATUS_PROCESSING;

            break;

        }       

        return $order_status;

    }


    public function getGiveWpOrderByBsecureRefId($bsecure_order_ref){       

        $args = array(

            //'posts_per_page'   => 1,
            //'post_type'        => 'give_payment', 
            //'post_status'      =>  array_keys( give_get_payment_statuses() ),

            'meta_query' => array(

                array(

                    'key' => '_bsecure_order_ref',
                    'value' => $bsecure_order_ref,
                    'compare' => '=',
                )

            )
        );      

        //$order = get_posts( $args );
        $order = give_get_payments( $args );

        return !empty($order[0]) ? $order[0] : [];

    }


    /**
     * Get calling code for a country code.
     *
     * @since 1.0.0
     * @param string $cc Country code.
     * @return string|array Some countries have multiple. The code will be stripped of - and spaces and always be prefixed with +.
     */
    public function get_country_calling_code( $cc = '' ) {
        $codes = wp_cache_get( 'calling-codes', 'countries' );

        if ( ! $codes ) {
            $codes = include plugin_dir_path( __FILE__ ) . 'custom/country-phone-codes.php';
            
            wp_cache_set( 'calling-codes', $codes, 'countries' );
        }

        if(empty($cc)){

            return $codes;
        }

        $calling_code = isset( $codes[ $cc ] ) ? $codes[ $cc ] : '';

        if ( is_array( $calling_code ) ) {
            $calling_code = $calling_code[0];
        }

        return $calling_code;
    }


    /**
     * Use this function if payment gateway order type used
     */
    public function updateOrderPaymentGateway($payment, $order_data){      

        if(empty($payment) && empty($order_data)){
            return false;
        }

        $donor_id = $payment->donor_id;
        $donor = new Give_Donor($donor_id);

        if($order_data->customer){

            $donor->update_meta( '_give_donor_contact_number', $order_data->customer->phone_number);
            $donor->update_meta( '_give_donor_country_code', $order_data->customer->country_code);
            $donor->update_meta( '_give_payment_donor_email', $order_data->customer->email);

        }

        if($order_data->delivery_address){

            $delivery_address = $order_data->delivery_address;
            $country_code = $this->getCountryCodeByCountryName($delivery_address->country);
            $state_code = $this->getStateCode($delivery_address->country,$delivery_address->province);
            give_update_meta( $payment->ID, '_give_donor_billing_address1',$delivery_address->area );
            give_update_meta( $payment->ID, '_give_donor_billing_address2',$delivery_address->address );
            give_update_meta( $payment->ID, '_give_donor_billing_city',$delivery_address->city );
            give_update_meta( $payment->ID, '_give_donor_billing_country',$country_code );
            give_update_meta( $payment->ID, '_give_donor_billing_state',$state_code );            
            give_update_meta( $payment->ID, '_give_donor_contact_number',$order_data->customer->phone_number );            
        }       

        $placement_status   = $order_data->placement_status;             
        $payment_status   = $order_data->payment_status;             

        if(!empty($order_data->payment_method->name)){

            $orderNotes = "Payment Method: ".$order_data->payment_method->name.'<br>';

            if(!empty($order_data->card_details->card_name) || !empty($order_data->card_details->card_type)){

                //"card_type":"Mastercard","card_number":2449,"card_expire":"12\/25","card_name":"Khan WC1"
                $orderNotes .= " Card Type: ".$order_data->card_details->card_type.'<br>';
                $orderNotes .= " Card Holder Name: ".$order_data->card_details->card_name.'<br>';
                $orderNotes .= " Card Number: ".$order_data->card_details->card_number.'<br>';
                $orderNotes .= " Card Expire: ".$order_data->card_details->card_expire.'<br>';

            }    

            if(!empty($order_data->payment_method->transaction_id)) {
               $orderNotes .= " Transaction ID: ".$order_data->payment_method->transaction_id.'<br>'; 
            }      

            //give_update_payment_status( $payment->ID, 'failed' );                       
            give_insert_payment_note($payment->ID, $orderNotes);
        }        
               
        give_update_payment_status($payment->ID,$this->givewpStatus($placement_status,$payment_status));       

        update_post_meta($payment->ID,'_bsecure_order_ref', sanitize_text_field($order_data->order_ref));
        update_post_meta($payment->ID,'_bsecure_order_type',strtolower($order_data->order_type));
        
        return $payment->ID;

    }


    public function getBsecureOrder($bsecure_order_ref){

        // Get Order //
        $order_data = '';
        $access_token = __('No token needed','bsecure-give');           

        $headers =   $this->getApiHeaders($access_token, false);

        $request_data['order_ref'] = $bsecure_order_ref;                                    

        $params =   [

                        'method' => 'POST',

                        'body' => $request_data,

                        'headers' => $headers,                  

                    ];  

        $config = $this->getBsecureConfig();            

        $this->order_status_endpoint = !empty($config->orderStatus) ? $config->orderStatus : "";         

        $response = $this->bsecureSendCurlRequest( $this->order_status_endpoint,$params);           

        $validateResponse = $this->validateResponse($response); 

        if($validateResponse['error']){ 

            $this->displayGiveError('Response Error: '.$validateResponse['msg']);              

        }else{

            $order_data = $response->body;

        }

        $validateOrderData =  $this->validateOrderData($order_data);

        if(!empty($validateOrderData['status'])){

            $this->displayGiveError('Response Error: '.$validateResponse['msg']);           

        }else if (!empty($order_data->placement_status)) {

            return $order_data;
        }      

        return false;
    }


    public function displayGiveError($msg){

        give_set_error( 'givewp_error', $msg );
        // Record Gateway Error as Pending Donation in Give is not created.
        give_record_gateway_error(
            __( 'bSecure Error', 'bsecure-give' ),
            sprintf(
            /* translators: %s Exception error message. */
                __( $msg, 'bsecure-give' )
            )
        );
        // Send user back to checkout.
        give_send_back_to_checkout( '?payment-mode=bsecure' );
        return; 
    }

    public function getBsecureEnvPartnerUrl(){

        switch ($this->base_url) {

            case 'https://api-dev.bsecure.app/v1':
            case 'https://api-dev-v2.bsecure.app/v1':

                $bSecureOrderViewUrl = Bsecure_Give::BSECURE_DEV_VIEW_ORDER_URL;

            break;

            case 'https://api-stage.bsecure.app/v1':

                $bSecureOrderViewUrl = Bsecure_Give::BSECURE_STAGE_VIEW_ORDER_URL;

            break;

            default :                   

                $bSecureOrderViewUrl = Bsecure_Give::BSECURE_LIVE_VIEW_ORDER_URL;

            break;          

        }

        return $bSecureOrderViewUrl;
    }



}