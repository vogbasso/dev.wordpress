<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_AF_Rule_Ip_Location extends WC_AF_Rule {
	private $is_enabled  = false;
	private $rule_weight = 0;
	/**
	 * The constructor
	 */
	public function __construct() {
		$this->is_enabled  =  get_option('wc_af_ip_geolocation_order');
		$this->rule_weight = get_option('wc_settings_anti_fraud_ip_geolocation_order_weight');
		
		parent::__construct( 'ip_location', 'Customer IP address did not match given billing country.', $this->rule_weight );
	}

	/**
	 * Do the required check in this method. The method must return a boolean.
	 *
	 * @param WC_Order $order
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return bool
	 */
	public function is_risk( WC_Order $order ) {
		Af_Logger::debug('Checking ip address rule');
		global $wpdb;
		// Default risk is false
		$risk = false;

		// Set IP address in var
		$ip_address = $order->get_customer_ip_address();
		$billing_country = $order->get_billing_country();
		$contents = @file_get_contents('http://www.geoplugin.net/json.gp?ip=' . $ip_address);
			 
		if ( $contents !== false ) {

			$ipdat = @json_decode($contents);
			
			if (json_last_error() === JSON_ERROR_NONE) {
				
				// We can only do this check if there is an IP address
				if ( empty( $ip_address ) ) {
					
					Af_Logger::debug('ip address is empty');
					return false;
				}

				$objectTostring = json_decode(json_encode($ipdat), true);

				if (array_key_exists( 'geoplugin_countryCode', $objectTostring )) {

					$risk = ( $objectTostring['geoplugin_countryCode'] == $billing_country ) ? false : true;
					$debugMsg = ( $objectTostring['geoplugin_countryCode'] == $billing_country ) ? $objectTostring['geoplugin_countryCode']. ' is not at risk' : $objectTostring['geoplugin_countryCode']. ' is at risk';
					Af_Logger::debug('ip address '.$debugMsg);
				} else {

					$risk = false;
				}
				
				// Here we can create a log entry in future, whenever required. We can write the complete $res object in that log.
				Af_Logger::debug(print_r($objectTostring,true));
			}			    
			
		}
		Af_Logger::debug('ip address rule risk : ' . ( $risk===true ? 'true' : 'false' ));
		return $risk;
	}
	
	//Enable rule check
	public function is_enabled() {
		if ('yes' == $this->is_enabled) {
			return true;
		}
		return false;
	}
}
