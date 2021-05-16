<?php
/*
	Plugin Name: WooCommerce Maroctelecommerce Gateway
	Plugin URI: http://www.maroctelecommerce.com/
	Description: Une plateforme de paiement sécurisée.
	Version: 1.0.0
	Author: maroctelecommerce - MTC
	Author URI: http://www.maroctelecommerce.com/
	Requires at least: 3.5
	Tested up to: 3.8
*/


/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) )
	require_once( 'woo-includes/woo-functions.php' );

/**
 * Plugin updates
 */

load_plugin_textdomain( 'wc_maroctelecommerce', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );

add_action( 'plugins_loaded', 'woocommerce_maroctelecommerce_init', 0 );

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */
function woocommerce_maroctelecommerce_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	require_once( plugin_basename( 'classes/maroctelecommerce.class.php' ) );

	add_filter('woocommerce_payment_gateways', 'woocommerce_maroctelecommerce_add_gateway' );

} // End woocommerce_maroctelecommerce_init()

/**
 * Add the gateway to WooCommerce
 *
 * @since 1.0.0
 */
function woocommerce_maroctelecommerce_add_gateway( $methods ) {
	$methods[] = 'WC_Gateway_maroctelecommerce';
	return $methods;
} // End woocommerce_maroctelecommerce_add_gateway()