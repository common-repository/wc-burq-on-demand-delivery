<?php
   /*
   Plugin Name: Burq: On-Demand Delivery for WooCommerce
   Description: Offer on-demand delivery instantly with Burq
   Version: 1.0
   Author: Burq
   WC requires at least: 2.2
   WC tested up to: 2.2
   Requires PHP: 5.6

 */
   

//load plugin
   add_action('plugins_loaded', 'init_burq_shipping_integration', 1);
   if (!function_exists('init_burq_shipping_integration')) {
   	function init_burq_shipping_integration() {
   		if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
   			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
   			deactivate_plugins( '/burq-on-demand-delivery-for-woocommerce/burq-on-demand-delivery-for-woocommerce.php', true );
   		}

   		define('BURQ_PLUGIN_DIR', PLUGIN_DIR_path(__FILE__));
   		define('BURQ_PLUGIN_URL', PLUGIN_DIR_url(__FILE__));
   		require_once PLUGIN_DIR_path(__FILE__) . 'includes/loader.php';
   		require_once PLUGIN_DIR_path(__FILE__) . 'includes/shipping.php';

   	}
   }	

//activation hook for checking if woocommerce is installed
   if (!function_exists('install_burq_shipping_integration')) {
   	function install_burq_shipping_integration() {
   		if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
   			die('Please install WooCommerce before activating this plugin.');

   		}
   	}
   }
   register_activation_hook(__FILE__, 'install_burq_shipping_integration');

//adding setting link in plugin section
   function BURQ_apd_settings_link( array $links ) {
   	$settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=burq') . '">' . __('Settings', 'textdomain') . '</a>';
   	$links[] = $settings_link;
   	return $links;
   }
   add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'BURQ_apd_settings_link' );

//For decimals
   add_filter( 'wc_get_price_decimals', 'BURQ_change_prices_decimals', 20, 1 );
   function BURQ_change_prices_decimals( $decimals ) {
   	if ( is_cart() || is_checkout() ) {
   		$decimals = 2;
   	}
   	return $decimals;
   }







