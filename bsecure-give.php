<?php

/*
 * Plugin Name: bSecure Payment Gateway for GiveWP
 * Plugin URI: https://bsecure.pk
 * Description: Multiple Payment Gateways in one place.
 * Author: bSecure
 * Author URI: https://bsecure.pk/
 * Version: 1.0.0
 */

require plugin_dir_path( __FILE__ ) . 'includes/class-bsecure-give.php';


function bsecure_give_run() {		

	$give_bsecure = new Bsecure_Give;
	$give_bsecure->init();
	

}

// from version 1.5.8 
add_action( 'plugins_loaded',  'check_bsecure_give'  );

function check_bsecure_give(){

	// check GiveWp is activate and installed	
	bsecure_give_run();
	
}

/**
 * bSecure does not need a CC form, so remove it.
 */

add_action( 'give_bsecure_cc_form', '__return_false' );
//add_action( 'give_load_gateway', 'detectect_selection' );


/**
 * Register payment method.
 *
 * @since 1.0.0
 *
 * @param array $gateways List of registered gateways.
 *
 * @return array
 */


function give_bsecure_register_payment_method( $gateways ) {
  
  // Duplicate this section to add support for multiple payment method from a custom payment gateway.
  $gateways['bsecure'] = array(
    'admin_label'    => __( 'bSecure - Payment Gateway', 'bsecure-give' ), // This label will be displayed under Give settings in admin.
    'checkout_label' => __( 'bSecure Payment Gateway', 'bsecure-give' ), // This label will be displayed on donation form in frontend.
  );
  
  return $gateways;
}

add_filter( 'give_payment_gateways', 'give_bsecure_register_payment_method' );


/**
 * Register Section for Payment Gateway Settings.
 *
 * @param array $sections List of payment gateway sections.
 *
 * @since 1.0.0
 *
 * @return array
 */

// change the insta_for_give prefix to avoid collisions with other functions.
function give_bsecure_register_payment_gateway_sections( $sections ) {
	
	// `instamojo-settings` is the name/slug of the payment gateway section.
	$sections['bsecure-settings'] = __( 'bSecure Payment Gateway', 'bsecure-give' );

	return $sections;
}

add_filter( 'give_get_sections_gateways', 'give_bsecure_register_payment_gateway_sections' );


/**
 * Register Admin Settings.
 *
 * @param array $settings List of admin settings.
 *
 * @since 1.0.0
 *
 * @return array
 */
// change the insta_for_give prefix to avoid collisions with other functions.
function give_bsecure_register_payment_gateway_setting_fields( $settings ) {

	$defualtBaseUrl = 'https://api.bsecure.pk/v1';
    $bSecurePortalUrl = 'https://partner.bsecure.pk/integration-live'; 

	switch ( give_get_current_setting_section() ) {

		case 'bsecure-settings':
			$settings = array(
				array(
					'id'   => 'give_title_bsecure',
					'type' => 'title',
				),
			);			

			// ----- Save Live Credentials Start ------ //
		    $settings[] = array(
				'name' => __( 'bSecure Base URL', 'bsecure-give' ),
				'desc' => __( 'Enter bSecure Transactionpost URL, found in your bSecure Dashboard.', 'bsecure-give' ),
				'id'   => 'bsecure_base_url',
				'type' => 'text',
				'default'   => $defualtBaseUrl
		    );

            $settings[] = array(
				'name' => __( 'bSecure Store ID', 'bsecure-give' ),
				'desc' => __( 'Enter bSecure Store ID, found in your bSecure Dashboard.', 'bsecure-give' ),
				'id'   => 'bsecure_store_id',
				'type' => 'text',
		    );

            $settings[] = array(
				'name' => __( 'bSecure Client ID', 'bsecure-give' ),
				'desc' => __( 'Enter bSecure Client ID, found in your bSecure Dashboard.', 'bsecure-give' ),
				'id'   => 'bsecure_client_id',
				'type' => 'text',
		    );



		    $settings[] = array(
				'name' => __( 'bSecure Client Secret', 'bsecure-give' ),
				'desc' => __( 'You can find this client secret from bSecure portal. <br> <a href="'.$bSecurePortalUrl.'" target="_blank">'.$bSecurePortalUrl.'</a>', 'bsecure-give' ),
				'id'   => 'bsecure_client_secret',
				'type' => 'password',
		    );

		    
		     $settings[] = array(

                        'name' => __( 'Append country code with Contact Number field.', 'bsecure-give' ),
                        'type' => 'checkbox',
                        'desc' => __( '', 'wc-bsecure' ),
                        'id'   => 'give_auto_append_country_code',
                        'default'   => 'no',
                        'value' => get_option('give_auto_append_country_code', 'no')
                    );
		   
			$settings[] = array(
				'id'   => 'give_title_bsecure',
				'type' => 'sectionend',
			);

			break;

	} // End switch().

	return $settings;
}

// change the insta_for_give prefix to avoid collisions with other functions.
add_filter( 'give_get_settings_gateways', 'give_bsecure_register_payment_gateway_setting_fields' );


/**
 * Process Square checkout submission.
 *
 * @param array $posted_data List of posted data.
 *
 * @since  1.0.0
 * @access public
 *
 * @return void
 */

// change the insta_for_give prefix to avoid collisions with other functions.
function give_bsecure_process_donation( $purchase_data ) {

	$give_options = give_get_settings();
	$give_bsecure = new Bsecure_Give;	
	$bsecure_give_functions = new Bsecure_Give_Functions;	

	$form = new Give_Donate_Form( $purchase_data['post_data']['give-form-id'] );


	// check there is a gateway name.
	if ( ! isset( $purchase_data['post_data']['give-gateway'] ) ) {
		return;
	}
	// Make sure we don't have any left over errors present.
	give_clear_errors();

	// Any errors?
	$errors = give_get_errors();

	// No errors, proceed.
	if ( ! $errors ) {
		
		//Insert Contact Number in User Info
		$purchase_data['user_info']['give_donor_contact'] = $purchase_data['post_data']['give_donor_contact'];
		$auto_append_country_code = $give_options['give_auto_append_country_code'];
		$purchase_data['user_info']['give_country_calling_code'] = ($auto_append_country_code == 'yes') ? $purchase_data['post_data']['give_country_calling_code'] : '92';

		$payment_data = array(
			'price'           => $purchase_data['price'],
			'give_form_title' => $purchase_data['post_data']['give-form-title'],
			'give_form_id'    => intval( $purchase_data['post_data']['give-form-id'] ),
			'give_price_id'   => isset($purchase_data['post_data']['give-price-id']) ? $purchase_data['post_data']['give-price-id'] : '',
			'date'            => $purchase_data['date'],
			'user_email'      => $purchase_data['user_email'],
			'purchase_key'    => $purchase_data['purchase_key'],
			'currency'        => give_get_currency( $purchase_data['post_data']['give-form-id'], $purchase_data ),
			'user_info'       => $purchase_data['user_info'],
			'status'          => 'pending',			
						
			'give_donor_contact' => $purchase_data['post_data']['give_donor_contact'],
			'give_country_calling_code' => (!empty($purchase_data['post_data']['give_country_calling_code'])) ? $purchase_data['post_data']['give_country_calling_code'] : '92',
		);
		
		
		
		
		if( isset($purchase_data['post_data']['give-cs-currency']) && !empty($purchase_data['post_data']['give-cs-currency']) )
		{
			$payment_data['currency'] = $purchase_data['post_data']['give-cs-currency'];
		}		
		$auto_append_country_code = $give_options['give_auto_append_country_code'];
		
		if(!empty($payment_data['give_donor_contact']) && $auto_append_country_code == 'on'){			
		
			if(!$bsecure_give_functions->isPhoneNumberValid($payment_data['give_donor_contact'])){
				$errorMsg = __( 'Please enter a valid phone number <strong>03XXXXXXXXX</strong> in this format.', 'bsecure-give' );

				$give_bsecure->displayGiveError($errorMsg);			

			}
		}

		// Record the pending payment
		$paymentId = give_insert_payment( $payment_data );	

		// Record the pending donation.

		if ( ! $paymentId ) {

			// Record Gateway Error as Pending Donation in Give is not created.
			$errorMsg = __( 'Unable to create a pending donation with Give.', 'bsecure-give' );
			$give_bsecure->displayGiveError($errorMsg);	
		}

		$payment_data['order_id'] = $paymentId;
		$order_pay_load = prepareBsecurePayLoad($give_options,$payment_data);	


		$response = saveOrderAtBsecure($order_pay_load);
	
		$validateResponse = $give_bsecure->validateResponse($response);



		if( $validateResponse['error'] ){
				
			$give_bsecure->displayGiveError($validateResponse['msg']);					

		}else{

			if(!empty($response->body->order_reference)){		

				add_post_meta($paymentId,'_bsecure_order_ref', sanitize_text_field($response->body->order_reference));		
				update_post_meta($paymentId,'_bsecure_order_ref', sanitize_text_field($response->body->order_reference));		

				$redirect = !empty($response->body->checkout_url) ? $response->body->checkout_url : "";

				wp_redirect($redirect); 
				exit;
				
				
			}else{

				$complete_response =  __("No response from bSecure server, order_reference field not found.",'wc-bsecure');				

				$errorMsg = !empty($response->message) ? implode(',', $response->message) : $complete_response;
		
				$give_bsecure->displayGiveError($errorMsg);

			}

		}
		/* Record the payment as Pending */

		/* Hosted Checkout - bSecure */
	

	} else {

		// Send user back to checkout.
		give_send_back_to_checkout( '?payment-mode=bsecure' );
	} // End if().


}

// change the insta_for_give prefix to avoid collisions with other functions.
add_action( 'give_gateway_bsecure', 'give_bsecure_process_donation' );

add_action( 'give_donation_form_user_info', 'giv_bsecure_add_phone_number_field' );

function giv_bsecure_add_phone_number_field($form_id){

	$give_options = give_get_settings();
	$give_bsecure = new Bsecure_Give;
	$selected_gateway = give_get_chosen_gateway( $form_id );
	$donor_contact = '';
	$country_calling_code = '+92';
	$country_calling_codes = $give_bsecure->get_country_calling_code();
	$auto_append_country_code = $give_options['give_auto_append_country_code'];

	if ( is_user_logged_in() ) {
		$donor_data    = get_userdata( get_current_user_id() );
		$donor_contact = !empty($donor_data->donor_contact) ? $donor_data->donor_contact : "";
		$country_calling_code = !empty($donor_data->country_calling_code) ? $donor_data->country_calling_code : $country_calling_code;	
	
	}
	
	if('bsecure' == $selected_gateway){
		$class = 'form-row form-row-wide';
		if($auto_append_country_code == 'on'){
			$class = 'form-row form-row-last form-row-responsive';
		?>


			<p id="give-give_donor_country_calling_code-wrap" class="form-row form-row-first form-row-responsive">
		        <label class="give-label" for="give-give_mobile_phone">
		            <?php
		            esc_attr_e('Contact Number', 'bsecure-give'); ?>
		            
		            
		                <span class="give-required-indicator">*</span>
		                
		            <?php
		            echo Give()->tooltips->render_help(__('We can contact you to this Contact Number.', 'bsecure-give')); ?>
		        </label>
		        <select name="give_country_calling_code">
		        	<?php
		        		if(!empty($country_calling_codes)){
		        			foreach ($country_calling_codes as $key => $value) {
		        				if(is_array($value)){
		        					$value = $value[0];
		        				}
		        				if(!empty($value)){
			        				?>
			        				<option value="<?php esc_attr_e($value); ?>" 
			        					<?php echo ($country_calling_code == $value ? 'selected' :  ''); ?>
			        					><?php esc_attr_e($value); ?></option>
			        				<?php
			        			}
		        			}
		        		}
		        	?>
		        	
		        </select>
		    </p>

		<?php } ?>

		    <p id="give-give_donor_contact-wrap" class="form-row <?php echo esc_attr($class); ?>">
		    	
			     	<label class="give-label" for="give-give_mobile_phone">&nbsp;
			            <?php
			            if($auto_append_country_code != 'on'){
				            esc_attr_e('Contact Number', 'bsecure-give'); ?>
				            
				            
				                <span class="give-required-indicator">*</span>
				                
				            <?php
				            echo Give()->tooltips->render_help(__('We can contact you to this Contact Number.', 'bsecure-give'));
			            } ?>
			        </label>
			    
		        <input
		            class="give-input required"
		            type="text"
		            name="give_donor_contact"
		            autocomplete=""
		            placeholder="<?php esc_attr_e('Contact Number', 'bsecure-give'); ?>"
		            id="give_donor_contact"
		            value="<?php echo esc_html($donor_contact); ?>"
		            <?php  echo ' required aria-required="true" '; ?>
		        >

		    </p>

		<?php
	}
}



function prepareBsecurePayLoad($give_options, $payment_data){

	$user_info = $payment_data['user_info'];
	$first_name = $user_info['first_name']??$user_info['first_name'];
	$last_name = $user_info['last_name']??$user_info['last_name'];
	$email = $user_info['email']??$user_info['email'];
	$billing_country = !empty($payment_data['billing_country']) ? $payment_data['billing_country'] : 'PK';
	$phone_number = $user_info['give_donor_contact']??$user_info['give_donor_contact'];
	$country_calling_code = $user_info['give_country_calling_code']??$user_info['give_country_calling_code'];

	$bsecure_give_functions = new Bsecure_Give_Functions;
	$phone_number = $bsecure_give_functions->phoneWithoutCountryCode($phone_number, $country_calling_code, $billing_country);
	
	$full_name = $first_name.' '.$last_name;

	$pay_load['customer'] = [ 
								"name" => $full_name,
							    "email" => $email,
							    "country_code" => $country_calling_code,
							    "phone_number" => $phone_number
							];

	$pay_load['products'][] = [

								"id" => $payment_data['give_form_id'],
								"name" => $payment_data['give_form_title'],
								"sku" => $bsecure_give_functions->createUrlSlug($payment_data['give_form_title']),
								"quantity" => 1,
								"price" => $payment_data['price'],
								"discount" => 0,
								"sale_price" => $payment_data['price'],
								"sub_total" => $payment_data['price'],
								"image" => "",
								"short_description" => "",
								"description" => "",
								"line_total" => $payment_data['price'],
								"product_options" => []

							];
	
	
	$pay_load["order_id"] = $payment_data['order_id'];
	$pay_load["currency"] = "PKR";
	$pay_load["total_amount"] = $payment_data['price'];
	$pay_load["sub_total_amount"] = $payment_data['price'];
	$pay_load["discount_amount"] = 0;	

	return $pay_load;
  
}


function saveOrderAtBsecure($request_data){
	
	$give_bsecure = new Bsecure_Give;
	
	$config = $give_bsecure->getBsecureConfig();
  	$order_create_endpoint = !empty($config->createPaymentGatewayOrder) ? $config->createPaymentGatewayOrder : "";
	
	$headers =   $give_bsecure->getApiHeaders('No Token Needed',false);		   			

	$params = 	[

					'method' => 'POST',
					'body' => $request_data,
					'headers' => $headers,					

				];
				

	$response = $give_bsecure->bsecureSendCurlRequest($order_create_endpoint,$params);

	return $response;
}




// Save Custom Donor Info //
function give_bsecure_add_user_info($donor, $payment_id, $payment_data, $args){

	$give_options = give_get_settings();
	$give_bsecure = new Bsecure_Give;
	$bsecure_give_functions = new Bsecure_Give_Functions;
	$auto_append_country_code = $give_options['give_auto_append_country_code'];
	$give_donor_contact = !empty($_REQUEST['give_donor_contact']) ? sanitize_text_field($_REQUEST['give_donor_contact']) : '';
	$give_country_calling_code = !empty($_REQUEST['give_country_calling_code']) ? sanitize_text_field($_REQUEST['give_country_calling_code']) : '';
	$billing_country = !empty($_REQUEST['billing_country']) ? sanitize_text_field($_REQUEST['billing_country']) : 'PK';
	$phone_number = $bsecure_give_functions->phoneWithoutCountryCode($give_donor_contact, $give_country_calling_code, $billing_country);

	if ( ! empty( $donor ) ) {
		$donor->update_meta( '_give_donor_contact', $phone_number );
		$donor->update_meta( '_give_country_calling_code', $give_country_calling_code );
		update_user_meta($donor->id,'donor_contact',$phone_number);
		update_user_meta($donor->id,'country_calling_code',$give_country_calling_code);
	}

	return $donor;
}	

add_filter("give_update_donor_information","give_bsecure_add_user_info",10,10);

// Add custom post meta // 

function give_bsecure_insert_payment_custom_data($payment_id, $payment_data){	

	update_post_meta( $payment_id, '_give_donor_contact_number', $payment_data['give_donor_contact'] );	
}

add_action( 'give_insert_payment', 'give_bsecure_insert_payment_custom_data',10,10 );

/**
* Add setting lin at plugin page
*/
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'give_bsecure_add_plugin_page_settings_link');

function give_bsecure_add_plugin_page_settings_link( $links ) {

	$links = array_merge( array(
		'<a href="' . esc_url( admin_url( 'edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=bsecure-settings' ) ) . '">' . __( 'Settings', 'bsecure-give' ) . '</a>'
	), $links );
	
	return $links;
}


// Display contact number etc in Done Info  at payment detail page // 
function give_bsecure_show_custom_donor_data_admin($payment_id){
	$give_bsecure = new Bsecure_Give;
	$bSecureOrderViewUrl = $give_bsecure->getBsecureEnvPartnerUrl();
	?>
	<div class="column-container donor-info">
		<div class="column">
			<p>
				<strong><?php esc_html_e( 'Donor Contact:', 'bsecure-give' ); ?></strong><br>

				<?php $donor_contact = give_get_payment_meta( $payment_id, '_give_donor_contact_number', true ); 
				echo !empty($donor_contact) ? esc_html($donor_contact) : 'None'; ?>
			</p>
			
		</div>		
		<div class="column">
			<p>
				<strong><?php esc_html_e( 'bSecure Reference:', 'bsecure-give' ); ?></strong><br>

				<?php 

					$bsecure_order_ref = give_get_payment_meta( $payment_id, '_bsecure_order_ref', true ); 
					if(!empty($bsecure_order_ref)) { ?>
				
					<a href="<?php echo esc_url($bSecureOrderViewUrl .  $bsecure_order_ref); ?>" target="_blank">
						<?php	echo __( 'View order at bSecure', 'bsecure-give' ); ?>
					</a>

				<?php } ?>
			</p>
			
		</div>
	</div>
	<?php
}

add_action('give_payment_view_details','give_bsecure_show_custom_donor_data_admin',10,10);



/**
 * Check give bSecure plugin requirements before activating //
 */
function give_bsecure_superess_activate() {

    //check givewp plugin version
    $give_ext = 'give/give.php';

    // last version tested
    $version_to_check = '2.0.0'; 

    $give_error = false;

    if(file_exists(WP_PLUGIN_DIR.'/'.$give_ext)){
        $give_ext_data = get_plugin_data( WP_PLUGIN_DIR.'/'.$give_ext);
        $give_error = !version_compare ( $give_ext_data['Version'], $version_to_check, '>=') ? true : false;
    }   

    $give_bsecure = new Bsecure_Give;	    

    if ( ! $give_bsecure::wc_bsecure_check_ssl() ) {
       echo '<div class="notice notice-error"><p><strong>bSecure Give</strong> '.__('plugin require ssl enabled to activate it at your domain.', 'bsecure-give').' </p></div>'; 	 

      // @trigger_error(__('Please enable ssl to continue using this plugin.', 'bsecure-give'), E_USER_ERROR);     
    }


    if ( $give_error ) {
       echo '<div class="notice notice-error"><p><strong>Give bSecure</strong> '.__('plugin require the minimum Give plugin version of', 'bsecure-give').' '.esc_html($version_to_check).' </p></div>'; 	 

       @trigger_error(__('Please update Give plugin to continue using this plugin.', 'bsecure-give'), E_USER_ERROR);     
    }

    
    if ( ! class_exists( 'Give' ) ) {

    	echo '<div class="notice notice-error"><p>';
    	echo __('The bSecure Give plugin requires the <a href="http://wordpress.org/plugins/give/">Give</a> plugin to be active!', 'bsecure-give');
    	echo ' </p></div>';
    	
       @trigger_error(__('bSecure Give plugin required Give plugin to be installed and activate.', 'bsecure-give'), E_USER_ERROR); 
    }

    $give_bsecure_activated = get_option('give_bsecure_activated', 0);

	if ($give_bsecure_activated < 1){

	    if (class_exists('Bsecure_Give_Admin')){

		    $give_bsecure_admin = new Bsecure_Give_Admin;
		    $give_bsecure_admin->plugin_activate_deactivate();
		}
	}

}


function give_bsecure_superess_decactivate(){

	$give_bsecure_activated = get_option('give_bsecure_activated');

	if ($give_bsecure_activated == 1){

		if (class_exists('Bsecure_Give_Admin')){

		    $give_bsecure_admin = new Bsecure_Give_Admin;
		    $give_bsecure_admin->plugin_activate_deactivate('deactivate');
		}
	}

	
}

register_activation_hook(__FILE__, 'give_bsecure_superess_activate');
register_deactivation_hook(__FILE__, 'give_bsecure_superess_decactivate');