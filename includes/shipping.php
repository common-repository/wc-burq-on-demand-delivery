<?php
require_once 'BURQConfig.php';
if (!function_exists('BURQ_shipping_method_init')) {
	function BURQ_shipping_method_init() {
		if ( ! class_exists( 'BURQ_Shipping_Method' ) ) {
			class BURQ_Shipping_Method extends WC_Shipping_Method {
			/**
			* Construct Of Shipping Method
			**/
			public function __construct() {
					$this->id                 = 'burq'; // Id for Free Shipping. Should be uunique.
					$this->method_title       = __( 'Burq: On-Demand Delivery');  // Title shown in admin

					$this->enabled            = 'yes'; // This can be added as an setting but for this example its forced enabled
					$this->title              = 'Burq: On-Demand Delivery'; // This can be added as an setting but for this example its forced.

					$this->init();
				}

			/**
			* Init  Settings
			**/
			public function init() {
					// Load the settings API
				$this->init_form_fields(); 
				$this->init_settings(); 

					// Save settings in admin if you have any defined
				add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
			}

			/**
			* Shipping Method Form Settings 
			**/
			public function init_form_fields() {


				$this->form_fields = array(

					'burq_api_key' => array(
						'title'       => __( 'API Key'),
						'type'        => 'text',
						'description' => 'Obtain your BURQ API keys from <a href="' . esc_url(BURQ_DASHBOARD_URL) . '" target="_blank">' . esc_url(BURQ_DASHBOARD_URL),							
					),
					'burq_store_phone_no' => array(
						'title'       => __( 'Store Phone Number'),
						'type'        => 'tel',
						'description' => 'Please include country code, example +19999999999',							
					),

						// 'enviornment' => array(
						// 	'title'       => __( 'Enviornment'),
						// 	'type'        => 'Select',
						// 	'options' => array(
      // 									'test'        => __('Test'),
      // 									'live'       => __('Live'),
    		// 				),
						// 	'description' => 'Select enviornment test or live',
						// ),

					'burq_debug_mode' => array(
						'title'       => __( 'Debug Mode'),
						'type'        => 'checkbox',
						'default'     => 'yes',
						'description' => 'If debug mode is enabled, you can view all API requests/responses. <a href="' . admin_url('admin.php?page=wc-status&tab=logs') . '" target="_blank"> View Logs',
					),


				);

			}

			/**
			* Calculate Shipping Function
			**/
			public function calculate_shipping( $package = array() ) {

				require_once 'loader.php';
				$BURQ_Loader = new BURQ_Loader();
				$quote_data = $BURQ_Loader->BURQ_get_quote_api_data();
				$data = json_decode($quote_data);

					// if(is_array($data)){
				if (isset($data->id) && !empty($data->id)) {
					$cost = (float) ( $data->fee / 100 );
					$rate = array(
						'id' => $this->id,
						'label' => $this->title,
						'cost' => $cost,
					);
					
					set_transient('burq-quote-id', $data->id, 60 * 60 * 12);
					set_transient('burq-quote-delivery-time', $data->dropoff_at, 60 * 60 * 12);						
							// Register the rate
					$this->add_rate( $rate );
				}
					// }

			}
		}
	}
}
}
add_action('woocommerce_shipping_init', 'BURQ_shipping_method_init' );

//Register shipping method
function BURQ_add_shipping_method( $methods ) {
	$methods['burq_shipping_method'] = 'BURQ_Shipping_Method';
	return $methods;
}
add_filter( 'woocommerce_shipping_methods', 'BURQ_add_shipping_method' );



//Show estimate time 
add_action( 'woocommerce_after_shipping_rate', 'BURQ_action_after_shipping_rate_callback', 10, 2 );

function BURQ_action_after_shipping_rate_callback( $method, $index ) {
	$chosen_shipping_id = WC()->session->get( 'chosen_shipping_methods' )[$index];
	

	//Check if shipping method is Burq
	if ('burq' === $method->method_id && $method->id === $chosen_shipping_id) {
		$delivery_time = get_transient('burq-quote-delivery-time');
		if(!empty($delivery_time)){
			$delivery_time = gmdate('F j, Y, g:i a', strtotime($delivery_time));

			echo '<br><strong>' . esc_attr('Estimated Time', 'woocommerce') . '</strong>: ' . esc_html($delivery_time);
		}
	}
}