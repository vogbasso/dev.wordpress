<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'WC_AF_Hook_Manager' ) ) {

	class WC_AF_Hook_Manager {

		private static $instance = null;

		/**
		 * Private constructor, initiate class via ::setup()
		 */
		private function __construct() {

			// Meta Boxes
			add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

			// Order edit assets
			add_action( 'admin_print_scripts-post.php', array( $this, 'post_admin_scripts' ), 11 );

			// Order overview assets
			add_action( 'admin_print_scripts-edit.php', array( $this, 'edit_admin_scripts' ), 11 );


			//add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'payment_complete_order_status' ), 99, 2 );

			// Change the payment complete order
			add_action( 'woocommerce_order_status_changed', array( $this, 'change_order_status' ), 99, 3 );

			// Catch the Anti Fraud check request
			add_action( 'wc-af-check', array( $this, 'process_queue' ), 10, 1 );

			// Order columns
			add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_column' ), 11 );
			add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_column' ), 3 );

			//paypal cron job integrations
			add_action('wp', array($this,'cron_schedule_paypal_email') );

			//cron job peerform action
			add_action('wp_af_paypal_verification', array($this, 'paypal_email_task_hook_function') );

			//define custom time for cron job
			add_filter('cron_schedules', array($this, 'cron_schedule_paypal_email_schedule') );

			//define cron job every hour for check risk score
			add_action('wp', array($this, 'check_risk_score_seven_days_scheduled'));

			add_action('my_hourly_event', array($this, 'do_this_hourly'));

			add_action('valid-paypal-standard-ipn-request', array($this, 'preapproved_api_order'), 10, 1);
			add_action('woocommerce_paypal_express_checkout_valid_ipn_request', array( $this, 'get_buyer_paypal_express_email'), 10, 1);

			// For check Enable_whitelist_payment_method
			$enable_settings = get_option( 'wc_af_enable_whitelist_payment_method' );
			if ( $enable_settings == 'yes' ) {

				add_filter('manage_edit-shop_order_columns', array( $this, 'af_payment_method_list_columns_function')); // Extra column title
				add_action('manage_shop_order_posts_custom_column', array( $this,'af_payment_method_value_list'), 2); // Extra column value
			}

			// TODO check this event
			add_action('wp_af_my_hourly_event', array($this, 'do_this_hourly'));


		}



		/**
		 * The setup method // singleton initiator
		 *
		 * @static
		 * @since  1.0.0
		 * @access public
		 */
		public static function setup() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
		}

		/**
		 * Add the meta boxes
		 *
		 * @since  1.0.0
		 * @access public
		 */
		public function add_meta_boxes() {
			new WC_AF_Meta_Box();
		}

		/**
		 * Enqueue post admin scripts
		 *
		 * @since  1.0.0
		 * @access public
		 */
		public function post_admin_scripts() {
			global $post_type;

			// Check post type
			if ( 'shop_order' == $post_type ) {

				// Enqueue scripts
				wp_enqueue_script( 'knob' );
				wp_enqueue_script( 'edit' );

				/*wp_enqueue_script(
					'wc_af_edit_shop_order_js',
					plugins_url( '/assets/js/edit-shop-order' . ( ( ! SCRIPT_DEBUG ) ? '.min' : '' ) . '.js', WooCommerce_Anti_Fraud::get_plugin_file() ),
					array( 'jquery', 'wc_af_knob_js' )
				);*/

				// CSS
				wp_enqueue_style(
					'wc_af_post_shop_order_css',
					plugins_url( '/assets/css/post-shop-order.css', WooCommerce_Anti_Fraud::get_plugin_file() )
				);

			}

		}

		/**
		 * Enqueue edit admin scripts
		 *
		 * @since  1.0.0
		 * @access public
		 */
		public function edit_admin_scripts() {
			global $post_type;
			if ( 'shop_order' == $post_type ) {
				wp_enqueue_style(
					'wc_af_edit_shop_order_css',
					plugins_url( '/assets/css/edit-shop-order.css', WooCommerce_Anti_Fraud::get_plugin_file() )
				);
			}
		}

		/**
		 * Set the order status to 'Waiting for Fraud Check'
		 *
		 * @param $id
		 * @param $old_status
		 * @param $new_status
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 */
		public function change_order_status( $id, $old_status, $new_status ) {

			if ( 'completed' == $new_status || 'processing' == $new_status || 'on-hold' == $new_status ) {

				// Schedule fraud check
				$score_helper = new WC_AF_Score_Helper();
				$score_helper->schedule_fraud_check( $id );

			}

		}

		public function payment_complete_order_status( $new_status, $order_id ) {
			if (get_post_meta($order_id, '_payment_method', true) == 'cod') {
				$new_status = 'on-hold';
				update_post_meta( $order_id, '_wc_af_post_payment_status', $new_status );
			} else {
				// If the fraud check hasn't finished yet, don't advance to completed
				if ( ! WC_AF_Score_Helper::is_fraud_check_complete( $order_id ) ) {

					if ( in_array( $new_status, array( 'completed', 'processing' ) ) ) {
						//                  $new_status = "on-hold";
										$new_status = $new_status;  // Commented by Nisheet
					}

					// Save the payment recommended state so we can apply it when fraud check completes
					update_post_meta( $order_id, '_wc_af_post_payment_status', $new_status );

				} else {
					// if anti fraud has already recommended this order to be cancelled or held
					// don't allow the payment to override that state

					$af_recommended_status = get_post_meta( $order_id, '_wc_af_recommended_status', true );

					if ( ! empty( $af_recommended_status ) ) {
						$new_status = $af_recommended_status;
					}
				}
			}
			return $new_status;

		}

		/**
		 * Catch and do the anti fraud order check
		 *
		 * @param $order_id
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 */
		public function process_queue( $order_id = null ) {

			// Argument order_id must be set
			if ( null == $order_id ) {
				return;
			}

			// Do the fraud check
			$score_helper = new WC_AF_Score_Helper();
			$score_helper->do_check( $order_id );
		}

		/**
		 * Add the order overview column
		 *
		 * @param $columns
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @return mixed
		 */
		public function add_column( $columns ) {
			$columns = array_merge( array_slice( $columns, 0, 5 ), array( 'anti_fraud' => '&nbsp;' ), array_slice( $columns, 5 ) );

			return $columns;
		}

		public function render_column( $column ) {
			global $post;
			if ( 'anti_fraud' == $column ) {

				// Get the score points
				$score_points = get_post_meta( $post->ID, 'wc_af_score', true );

				// Get meta
				$meta = WC_AF_Score_Helper::get_score_meta( $score_points );

				// Display span
				echo "<span class='wc-af-score tips' style='color:" . $meta['color'] . "' data-tip='" . $meta['label'] . "'>&nbsp;</span>";

			}

		}

		/*Paypal verified addresses*/
		public function paypal_verified_addresses() {
			$paypal_verified_addresses = array();

			$verified_addresses = get_option('wc_settings_anti_fraud_paypal_verified_address');
			if ($verified_addresses && '' != $verified_addresses) {

				$paypal_address = explode( ',', $verified_addresses );
					// Check if is valid array
				if ( is_array( $paypal_address ) && count( $paypal_address ) > 0 ) {

					// Trim items to be sure
					foreach ( $paypal_address as $address ) {
						$paypal_verified_addresses[] = trim( $address );
					}

					// Set paypal_verified_addresses
					return $paypal_verified_addresses;
				}

			}
			return null;
		}

		/*
		* cron_schedules
		* check and execute cron job
		*/
		function cron_schedule_paypal_email() {
			if ( !wp_next_scheduled( 'wp_af_paypal_verification' ) ) {
				wp_schedule_event(time(), 'wc_af_further_attempt', 'wp_af_paypal_verification');
			}
		}

		/*
		* cron_schedules
		* set interval for function to execute as cron job
		*/
		function cron_schedule_paypal_email_schedule( $schedules ) {

			$schedules['wc_af_further_attempt'] = array(
					'interval'  => 86400*get_option('wc_settings_anti_fraud_time_paypal_attempts'),
					'display'   => __( 'Antifraud paypal verification', 'textdomain' )
			);
			$schedules['wp_af_every_hour'] = array(  // For fraud risk score check
				'interval'  => 60*60,
				'display'   => __( 'Check pending order fraud risk score', 'textdomain' )
			);
			return $schedules;
		}

		/*
		* cron_schedules
		* execute as cron job function
		*/
		public function paypal_email_task_hook_function() {
			if ('' != get_option('wc_af_paypal_verification')) {

				$score_helper = new WC_AF_Score_Helper();
				// Get orders payed by paypal.
				$args = array(
					'payment_method' => array('paypal','ppec_paypal'),
					'status'         => array('on-hold','pending'),
				);
				$orders = wc_get_orders( $args );

				foreach ($orders as $order) {
					$orderstatus = $order->get_status();
					if ('on-hold' == $orderstatus || 'processing' == $orderstatus || 'completed' == 'order_status') {
						$datetime1 = new DateTime();
						$datetime2 = new DateTime($order->get_date_created()->format('Y-m-d h:i:s'));
						$interval = $datetime1->diff($datetime2);
						$current_interval = $interval->format('%a');

						if (get_option('wc_settings_anti_fraud_time_paypal_attempts') > $current_interval ) {

							$score_helper->paypal_email_verification($order, '10');
						}

						if (get_option('wc_settings_anti_fraud_day_deleting_paypal_order') < $current_interval) {
							$order->update_status( 'cancelled', __( 'Fraud check done.', 'woocommerce-anti-fraud' ) );
						}
					}
				}
			}
		}


		/*
		* cron_schedules
		* execute as cron job and check if any order not check within 7 days
		*/
		public function check_risk_score_seven_days_scheduled() {
			if ( !wp_next_scheduled( 'wp_af_my_hourly_event' ) ) {
				wp_schedule_event( time(), 'wp_af_every_hour', 'wp_af_my_hourly_event');
			}
		}


		public function do_this_hourly() {

			global $wpdb;
			$date_range = strtotime ( '-7 day' );
			$orders = wc_get_orders(array(
				'limit'=>-1,
				'type'=> 'shop_order',
				'date_query'  => array(
						array(
							'after' => array(
								'year'  => date('Y', $date_range ),
								'month' => date('m', $date_range ),
								'day'   => date('d', $date_range ),
							),
						)
					),

				'id' =>'ids'
				)
			);
			if (!empty($orders)) {

				foreach ($orders as $value) {

					$id = $value->get_id();
					$score_points = get_post_meta( $id, 'wc_af_score', true );

					if ('' != $score_points) {

						return;
					}

					$risk_waiting = get_post_meta( $id, '_wc_af_waiting', true );

					if ('' == $score_points || '' != $risk_waiting) {

						$score_helper = new WC_AF_Score_Helper();
						$score_helper->schedule_fraud_check( $id );
					}
				}
			}
		}

		public function preapproved_api_order( $details ) {

			global $woocommerce;
			$payer_email = $details['payer_email'];
			//$order_id = $details['item_number1'];
			$data = json_decode($details['custom']);
			$score_helper = new WC_AF_Score_Helper();

			if (!empty($payer_email) && !empty($data)) {
				$tmp_data  = (object) $data;
				$order_id = $tmp_data ->order_id;
				$order = new WC_Order( $order_id );
				add_post_meta( $order_id, '_paypal_payer_email', $payer_email );
				$score_helper->paypal_email_verification($order, 10);
			}
		}

		public function get_buyer_paypal_express_email( $details ) {

			global $woocommerce;
			$payer_email = $details['payer_email'];
			$data = json_decode($details['custom']);
			$score_helper = new WC_AF_Score_Helper();

			if (!empty($payer_email) && !empty($data)) {
				$tmp_data  = (object) $data;
				$order_id = $tmp_data ->order_id;
				$order = new WC_Order( $order_id );
				add_post_meta( $order_id, '_paypal_express_payer_email', $payer_email );
				$score_helper->paypal_email_verification($order, 10);

			}
		}

		function af_payment_method_list_columns_function( $columns ) {

			$new_columns = ( is_array( $columns ) ) ? $columns : array();
			unset( $new_columns[ 'order_total' ] );
			// all of your columns will be added before the actions column
			$new_columns['wc_af_payment_method_list'] = 'Payment Method';
			//stop editing
			@$new_columns[ 'order_total' ] = @$columns[ 'order_total' ];
			return $new_columns;

		}

		// Change order of columns (working)

		function af_payment_method_value_list( $column ) {

			global $post;
			if ( 'wc_af_payment_method_list' === $column ) {
				$order = wc_get_order( $post->ID );
				$order_id = $order->get_id();
				$payment_method = get_post_meta( $order_id, '_payment_method', true );
				echo '<span class="wc_af_payment_method">' . $payment_method . ' </span><br>';
			}
		}

	}

}
