<?php

/**
 * This class handles all BURQ API operations 
 * API Ref : https://burq.readme.io/reference#api
 * Author : BURQ
 */

class BURQ_API { 
	/**
	* BURQ API 
	**/
	public function BURQ_call_api( $url, $api_key, $post_data, $obj) {

		$response = wp_remote_post($url, array('headers'   => array('Content-Type' => 'application/json; charset=utf-8','x-api-key'=> $api_key),'timeout' => 30, 'method' => 'POST', 'body' => $post_data));


		$logger = new WC_Logger();
		$logger->add('burq_woo_shipping_api', $post_data);
		if (is_wp_error($request)) {
			foreach ($request->get_error_messages() as $err) {
				//Write API error into log file
				$this->BURQ_writeLog($url, $err);
			} 
			
		} else {
			$responseCode = $response['response']['code'];
			if (304 == $responseCode ||400   == $responseCode|| 401  == $responseCode|| 404 == $responseCode || 429 ==  $responseCode || 500 == $responseCode ) {				
				//Write API error into log file
				$this->BURQ_writeLog($url, $response['body']);
			} else if (200 == $responseCode) {
				$options = get_option( 'woocommerce_burq_settings' );
				$debug_mode = $options['burq_debug_mode'];
				if (isset($debug_mode) && 'yes' == $debug_mode) {

					$logger = new WC_Logger();
					//Write request log file
					$request_msg = 'cURL Request For ' . $url . ':' . $post_data;
					$logger->add('burq_woo_shipping_api', $request_msg);

					//write response log file
					$msg = 'cURL Response For ' . $url . ':' . $response['body'];
					$logger->add('burq_woo_shipping_api', $msg);
				}
				return $response['body'];
			}

		}
	}

	public function BURQ_writeLog( $url, $response) {
		$err_msg = 'cURL Error For ' . $url . ':' . $response;
		$logger = new WC_Logger();
		$logger->add('burq_woo_shipping_api', $err_msg);
	}
}






