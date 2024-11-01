<?php
require_once 'BURQConfig.php';


class BURQ_Loader {

	private static $instance = null;

	/**
	* Create Instance of Loader Class 
	**/
	public static function getInstance() {
		if (null === self::$instance) {
			self::$instance = new BURQ_Loader();
		}
		return self::$instance;
	}

	public function __construct() {
		
		require_once BURQ_PLUGIN_DIR . 'includes/api.php';
		//Create delivery
		add_action('woocommerce_order_status_processing', array($this,'BURQ_create_delivery'));
		//Cancel delivery
		add_action('woocommerce_order_status_cancelled', array($this,'BURQ_cancel_delivery'));
		//Add tracking URL to customer account dashboard	
		add_action('woocommerce_account_orders_columns', array($this,'BURQ_add_column_tracking_order'), 5);
		//Add tracking URL to customer account dashboard
		add_action( 'woocommerce_my_account_my_orders_column_track-order-column', array($this,'BURQ_add_value_tracking_order_column'));
		//Add meta boxes
		add_action('add_meta_boxes', array($this, 'BURQ_add_meta_box'));
		//Add custom column order admin page
		add_filter( 'manage_edit-shop_order_columns', array($this,'BURQ_add_order_column'), 20);

		add_action( 'manage_shop_order_posts_custom_column', array($this,'BURQ_add_order_column_data' ));

	}

	/**
	* Add custom column in customer account order dashboard
	**/	

	public function BURQ_add_column_tracking_order( $columns) {
		$columns['track-order-column'] = __( 'Track Order');
		return $columns;
	}

	/**
	* Function For Order Tracking
	**/	
	public function BURQ_add_value_tracking_order_column( $order ) {
		$order_id = $order->get_id();
		$tracking_url = get_post_meta($order_id, 'burq_tracking_url', true);
		if (!empty($tracking_url)) {
			echo '<a href="' . esc_url($tracking_url) . '" target="_blank">Track Order';
		}	

	}

	/**
	* Function For Create Delivery  
	**/	
	public function BURQ_create_delivery( $order_id) {
		
		$order = new WC_Order($order_id);
		$shipping_method = $order->get_shipping_method();
		$quote_id = get_transient('burq-quote-id');

		if ('Burq: On-Demand Delivery' == $shipping_method && !empty($quote_id)) {
			$url = BURQ_API_URL . 'delivery_information';
			$api_key = $this->BURQ_get_api_key();

			$items_data = $this->BURQ_get_item_data($order_id, $order);
			$shipping_data = $this->BURQ_get_customer_shipping_address($order_id);
			$pickup_data = $this->BURQ_get_store_address();
			$options = get_option( 'woocommerce_burq_settings' );
			$store_phone_no = $options['burq_store_phone_no'];


			$data = array
			(
				'items' => $items_data['items_data'],
				'quote_id' => $quote_id,
				'dropoff_name' => $shipping_data['customer_name'],
				'dropoff_phone_number' => $shipping_data['customer_phone_number'],
				'pickup_name' => $pickup_data['pickup_name'], 
				'pickup_phone_number' => $store_phone_no,
				'items_description'=> 'items_description',
				// 'pickup_zip'=>$pickup_data['pickup_post_code'],
				// 'dropoff_zip'=>$shipping_data['postcode'],
				// 'pickup_state'=>$pickup_data['pickup_state'],
				// 'dropoff_state'=>$shipping_data['state'],
				'external_order_ref'=> 'order #'.$order_id,

				'dropoff_address' => $shipping_data['address_1'] . ' ' .$shipping_data['city'].' '. $shipping_data['state'] . ' ' . $shipping_data['postcode']. ', ' . $shipping_data['country'],
				'pickup_address' => $pickup_data['pickup_address_1'] . ' ' . $pickup_data['pickup_city'].' '. $pickup_data['pickup_state'] . ' ' . $pickup_data['pickup_post_code']. ', ' . $pickup_data['pickup_country'],

			);

			$post_data = json_encode($data);
			$BURQ_Client = new BURQ_API();
			$quote_data = $BURQ_Client->BURQ_call_api($url, $api_key, $post_data, 'Delivery_Create');
			$res_data = json_decode($quote_data);
			if (isset($res_data->id) && $res_data->tracking_url) {
				update_post_meta($order_id, 'burq_delivery_id', $res_data->id);
				update_post_meta($order_id, 'burq_tracking_url', $res_data->tracking_url);
			}

		}

	}

	/**
	* Cancel Delivery
	**/
	public function BURQ_cancel_delivery( $order_id) {
		$delivery_id = get_post_meta($order_id, 'burq_delivery_id', true);
		//$url = BURQ_API_URL . 'deliveries/' . $delivery_id . '/cancel';
		$url = BURQ_API_URL . 'delivery/' . $delivery_id . '/cancel';
		$api_key = $this->BURQ_get_api_key();
		$post_data = '';
		if (!empty($delivery_id)) {
			$BURQ_Client = new BURQ_API();
			$quote_data = $BURQ_Client->BURQ_call_api($url, $api_key, $post_data, 'Cancel_Delivery');
			delete_post_meta($order_id, 'burq_tracking_url');
		}		
	}

	/**
	* Create Quote And Get Quote Data
	**/
	public function BURQ_get_quote_api_data() {
		global $woocommerce;
		$shipping_address = $this->BURQ_get_customer_shipping_address();
		$ship_address_1 = $shipping_address['address_1'];
		$ship_state = $shipping_address['state'];
		$ship_city = $shipping_address['city'];
		$ship_postcode = $shipping_address['postcode'];
		$ship_country = $shipping_address['country'];

		
		//$url = BURQ_API_URL . 'delivery_quotes';
		$url = BURQ_API_URL . 'quote';
		$api_key = $this->BURQ_get_api_key();
		$pickup_data = $this->BURQ_get_store_address();
		$pick_address_1 =$pickup_data['pickup_address_1'];
		$pick_state =$pickup_data['pickup_state'];
		$pick_postcode = $pickup_data['pickup_post_code'];
		$pick_city = $pickup_data['pickup_city'];
		$pick_country = $pickup_data['pickup_country'];

		$data = array (
			'dropoff_address' => $ship_address_1 . ', ' . $ship_city .', '. $ship_state . ' ' . $ship_postcode. ', ' . $ship_country,
			'pickup_address' => $pick_address_1 . ', ' . $pick_city . ', '. $pick_state.' ' . $pick_postcode. ', ' . $pick_country,
		);
		$post_data = json_encode($data);

		$BURQ_Client = new BURQ_API();
		$quote_data = $BURQ_Client->BURQ_call_api($url, $api_key, $post_data, 'Quote_Create');
		return $quote_data;
	}

	/**
	* Get Customer Shipping Address
	**/
	public function BURQ_get_customer_shipping_address( $order_id = 0) {
		global $woocommerce;


		if (isset($order_id) && !empty($order_id)) {
			$order = new WC_Order($order_id);
			$shipping_address = array();
			$shipping_address['address_1'] = $order->get_shipping_address_1();
			$shipping_address['postcode'] = $order->get_shipping_postcode();
			$shipping_address['state'] = $order->get_shipping_state();
			$shipping_address['city'] = $order->get_shipping_city();

			$shipping_address['country'] = $order->get_shipping_country();
			$shipping_address['customer_name'] = $order->get_shipping_first_name();
			$shipping_address['customer_phone_number'] = $order->get_billing_phone();
		} else {
			$shipping_address = array();
			$shipping_address['address_1'] = $woocommerce->customer->get_shipping_address_1();
			$shipping_address['postcode'] = $woocommerce->customer->get_shipping_postcode();
			$shipping_address['state'] = $woocommerce->customer->get_shipping_state();
			$shipping_address['city'] = $woocommerce->customer->get_shipping_city();
			$shipping_address['country'] = $woocommerce->customer->get_shipping_country();
			$shipping_address['customer_name'] = $woocommerce->customer->get_shipping_first_name();
			$shipping_address['customer_phone_number'] = $woocommerce->customer->get_billing_phone();
		}
		

		
		return $shipping_address; 

	}

	/**
	* Get Store  Address
	**/
	public function BURQ_get_store_address() {
		$pickup_address['pickup_post_code'] = get_option( 'woocommerce_store_postcode');
		$pickup_address['pickup_address_1'] = get_option( 'woocommerce_store_address');
		$pickup_address['pickup_city'] = get_option( 'woocommerce_store_city');

		
		$country_state = get_option( 'woocommerce_default_country');
		$stateArray = explode(':', $country_state);
		$pickup_address['pickup_state'] = $stateArray[1];
		$pickup_address['pickup_name'] = get_option('blogname');
		$pickup_address['pickup_country'] = $stateArray[0];
		return $pickup_address;
	}

	/**
	* Get API Key
	**/
	public function BURQ_get_api_key() {
		$options = get_option( 'woocommerce_burq_settings' );
		$api_key = $options['burq_api_key'];
		return $api_key;
	}

	/**
	* Get Order Item Data
	**/
	public function BURQ_get_item_data( $order_id, $order) {
		$items = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product(); 
			$product_description = get_post($item['product_id'])->post_content;
			$product_description = $product->get_description();
			$product_size = 'small';
			$item_data =     array(
				'name' => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'size' => $product_size,
			);
			array_push($items, $item_data);
		}
		$items_data = array('items_data' => $items);

		return $items_data; 
	}


	public function BURQ_add_meta_box() {
		global $post;
		$bureq_delivery_id = get_post_meta($post->ID, 'burq_delivery_id');
		if (!empty($bureq_delivery_id)) {
			add_meta_box('burq_order_custom_meta', __('Burq: On-Demand Delivery	
', 'burq_order_custom_meta'), array($this, 'BURQ_meta_box_sales_order'), 'shop_order', 'side', 'high');
		}

	}

	/**
	 * Show the meta box for shipment info on the order page
	 *
	 * Access public
	 */
	public function BURQ_meta_box_sales_order( $post ) {
		?>
		 <input type=button 
		 value='Manage Delivery' onClick="parent.open('https://dashboard.burqup.com/')" style="color:#fff;background-color:#2271b1;border-color:#2271b1" >
		<?php
	}

	/**
	 * Add Custom Column
	 */
	public function BURQ_add_order_column( $columns ) {

		$new_columns = array();

		foreach ( $columns as $column_name => $column_info ) {

			$new_columns[ $column_name ] = $column_info;

			if ( 'order_status' === $column_name ) {
				$new_columns['burq_on_demand_delivery'] = __( 'Burq: On-Demand Delivery', 'my-textdomain' );
			}
		}

		return $new_columns;
	}

	public function BURQ_add_order_column_data( $column ) {
   
		global $post; 
		if ( 'burq_on_demand_delivery' === $column ) {
			$bureq_delivery_id = get_post_meta($post->ID, 'burq_delivery_id');
			if (!empty($bureq_delivery_id)) {
				echo '<a href="' . esc_url(BURQ_DASHBOARD_URL) . '" target="_blank">Manage Delivery';
			}
	  
		}
	}




}

/**
 * Get Loader Class Instance
**/
function BURQ_Loader() {
	return BURQ_Loader::getInstance();
}

BURQ_Loader();
