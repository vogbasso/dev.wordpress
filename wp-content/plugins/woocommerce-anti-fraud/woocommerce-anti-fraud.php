<?php
/**
 * Plugin Name: WooCommerce Anti Fraud
 * Plugin URI: https://woocommerce.com/products/woocommerce-anti-fraud/
 * Description: Score each of your transactions, checking for possible fraud, using a set of advanced scoring rules.
 * Version: 3.6
 * Author: OPMC Australia Pty Ltd
 * Author URI: https://opmc.biz/
 * License: GPL v3
 * WC tested up to: 5.2
 * WC requires at least: 2.6
 * Woo: 500217:955da0ce83ea5a44fc268eb185e46c41
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Copyright (c) 2017 OPMC Australia Pty Ltd.
*/

/**
 * Required functions
 */
 
function add_the_theme_page(){
    add_menu_page('Anti-Fraud', 'Anti-Fraud', 'manage_options', 'theme-options', 'page_content', 'dashicons-book-alt');
}
add_action('admin_menu', 'add_the_theme_page');
function page_content(){
    require_once( plugin_dir_path( __FILE__ ) . '/templates/dashboard.php' );
} 

if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . '/woo-includes/woo-functions.php' );
}
	/**
	 * Plugin updates
	 */
	woothemes_queue_update( plugin_basename( __FILE__ ), '955da0ce83ea5a44fc268eb185e46c41', '500217' );


if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Plugin page links
 */
function wc_antifraud_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=wc_af' ) . '">' . __( 'Settings', 'woocommerce-anti-fraud' ) . '</a>',
		'<a href="https://docs.woocommerce.com/document/woocommerce-anti-fraud/">' . __( 'Docs', 'woocommerce-anti-fraud' ) . '</a>',
	);

	return array_merge( $plugin_links, $links );
}

	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_antifraud_plugin_links' );


	define( 'WOOCOMMERCE_ANTI_FRAUD_VERSION', '2.7.5' );

class WooCommerce_Anti_Fraud {

	/**
	 * Get the plugin file
	 *
	 * @static
	 * @since  1.0.0
	 * @access public
	 *
	 * @return String
	 */
	public static function get_plugin_file() {
		return __FILE__;
	}

	/**
	 * A static method that will setup the autoloader
	 *
	 * @static
	 * @since  1.0.0
	 * @access private
	 */
	private static function setup_autoloader() {
		require_once( plugin_dir_path( self::get_plugin_file() ) . '/includes/class-wc-af-privacy.php' );
		require_once( plugin_dir_path( self::get_plugin_file() ) . '/includes/class-wc-af-autoloader.php' );

		// Core loader
		$core_autoloader = new WC_AF_Autoloader( plugin_dir_path( self::get_plugin_file() ) . 'anti-fraud-core/' );
		spl_autoload_register( array( $core_autoloader, 'load' ) );

		// Rule loader

		$rule_autoloader = new WC_AF_Autoloader( plugin_dir_path( self::get_plugin_file() ) . 'rules/' );
		spl_autoload_register( array( $rule_autoloader, 'load' ) );
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		// Check if WC is activated
		if ( $this->is_wc_active() ) {
			$this->init();
		}
		register_activation_hook( __FILE__, array($this,'save_default_settings' ) );

		register_activation_hook( __FILE__, array($this,'deactivate_events_on_active_plugin' ) );

		register_deactivation_hook( __FILE__, array($this,'deactivate_events' ) );
		add_action( 'admin_init', array( $this, 'admin_scripts' ) );
		add_action('admin_enqueue_scripts', array( $this, 'switch_onoff') );

		add_action( 'wp_ajax_my_action', array($this, 'my_action' ));
		add_action( 'wp_ajax_nopriv_my_action', array($this, 'my_action' ) );
		add_action('init', array( $this,'paypal_verification' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'kia_display_order_data_in_admin' ) );

		// Ajax For whitlist email check
		add_action( 'wp_ajax_check_blacklist_whitelist', array($this,'check_blacklist_whitelist' ) );
		add_action( 'wp_ajax_nopriv_check_blacklist_whitelist', array($this,'check_blacklist_whitelist' ) );

		// For MaxMind Device Tracking Script
		add_action('admin_head', array( $this, 'get_device_tracking_script'), 100, 100);
		add_action('wp_head', array( $this, 'get_device_tracking_script'), 100, 100);

	}

	public function switch_onoff( $hookget) {
		
		wp_enqueue_script('antifraud-chart-js', plugins_url('assets/js/chart.js', __FILE__ ));
		
		if ( 'woocommerce_page_wc-settings' != $hookget ) {
			return;
		}

		if (!isset($_REQUEST['section']) || $_REQUEST['section'] != 'minfraud_settings') {
			return;
		}

		wp_enqueue_style('on-off-switch', plugins_url('assets/css/on-off-switch.css', __FILE__ ));
		wp_enqueue_script('on-off-jqueryadd', plugins_url('assets/js/jquery-1.11.2.min.js', __FILE__ ));
		wp_enqueue_script('on-off-switch', plugins_url('assets/js/on-off-switch.js', __FILE__ ));
		wp_enqueue_script('on-off-switch-onload', plugins_url('assets/js/on-off-switch-onload.js', __FILE__ ));

	}

	public function deactivate_events_on_active_plugin( $hook) {

		$crons = _get_cron_array();
		if ( empty( $crons ) ) {
			return;
		}

		foreach ( $crons as $timestamp => $cron ) {

			if ( ! empty( $cron['my_hourly_event'] ) ) {
				unset( $crons[$timestamp]['my_hourly_event'] );
			}
		}
		_set_cron_array( $crons );
	}

	public function deactivate_events( $hook) {

		$crons = _get_cron_array();
		if ( empty( $crons ) ) {
			return;
		}

		foreach ( $crons as $timestamp => $cron ) {

			if ( ! empty( $cron['wc-af-check'] ) ) {
				unset( $crons[$timestamp]['wc-af-check'] );
			}
			if ( ! empty( $cron['wp_af_paypal_verification'] ) ) {
				unset( $crons[$timestamp]['wp_af_paypal_verification'] );
			}
			if ( ! empty( $cron['wp_af_my_hourly_event'] ) ) {
				unset( $crons[$timestamp]['wp_af_my_hourly_event'] );
			}
		}
		_set_cron_array( $crons );
	}

	/**
	* Check if Device tracking is active
	*
	* @since  1.0.0
	*
	* Call on header
	*/

	public function get_device_tracking_script() {

		$device_trackin_settings = get_option( 'wc_af_maxmind_device_tracking' );
		// Get Device Tracking enable/disable
		if ( $device_trackin_settings == 'yes' ) {
			$maxmind_user = get_option( 'wc_af_maxmind_user' );

			if ( !empty( $maxmind_user ) ) {
				?>
				<script type="text/javascript">
					maxmind_user_id = "<?php echo $maxmind_user; ?>";
					(function() {
						var loadDeviceJs = function() {
						var element = document.createElement('script');
						element.src = 'https://device.maxmind.com/js/device.js';
						document.body.appendChild(element);
					};
					if (window.addEventListener) {
						window.addEventListener('load', loadDeviceJs, false);
					} else if (window.attachEvent) {
						window.attachEvent('onload', loadDeviceJs);
					}
				  })();
				</script>
				<?php
			}
		}
	}

	function check_blacklist_whitelist() {
		$blocked_email = get_option('wc_settings_anti_fraudblacklist_emails');
		$array_mail = explode(',', $blocked_email);
		$whitelistarray = $_POST['whitelist'];
		$expwhitearray = explode("\n", $whitelistarray);
		$result = array_diff($array_mail, $expwhitearray);
		$finalblocklist = implode(',', $result);

		update_option('wc_settings_anti_fraudblacklist_emails', $finalblocklist);

		echo $finalblocklist;
		wp_die();

	}

	// display the extra data in the order admin panel
	public function kia_display_order_data_in_admin( $order ) {
		$blocked_email = get_option('wc_settings_anti_fraudblacklist_emails');
		$array_mail = explode(',', $blocked_email);
		$orderemail = $order->get_billing_email();
		foreach ($array_mail as $single) {
			if ($orderemail == $single) {
				?>
				<p class="form-field form-field-wide">
					<?php echo '<h3 style="color:red;"><strong>' . __( 'This email id is blocked' ) . '</strong></h3>'; ?>
				</p>
				<?php
			}
		}
	}


	/**
	 * Check if WooCommerce is active
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return bool
	 */
	private function is_wc_active() {

		$is_active = WC_Dependencies::woocommerce_active_check();


		// Do the WC active check
		if ( false === $is_active ) {
			add_action( 'admin_notices', array( $this, 'notice_activate_wc' ) );
		}

		return $is_active;
	}

	/**
	 * Display the notice
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 */
	public function notice_activate_wc() {
		?>
		<div class="error">
			<p><?php printf( __( 'Please install and activate %1$sWooCommerce%2$s in order for the WooCommerce Anti Fraud extension to work!', 'woocommerce-anti-fraud' ), '<a href="' . admin_url( 'plugin-install.php?tab=search&s=WooCommerce&plugin-search-input=Search+Plugins' ) . '">', '</a>' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Init the plugin
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 */
	private function init() {
		require_once( dirname( __FILE__ ) . '/includes/class-wc-af-logger.php' );
		// Load plugin textdomain
		load_plugin_textdomain( 'woocommerce-anti-fraud', false, plugin_dir_path( self::get_plugin_file() ) . 'languages/' );

		// Setup the autoloader
		self::setup_autoloader();

		// Setup the required WooCommerce hooks
		WC_AF_Hook_Manager::setup();

		// Add base rules
		$maxmind_settings = get_option( 'wc_af_maxmind_type' ); // Get MaxMind enable/disable
                $wc_af_maxmind_insights_setting = get_option( 'wc_af_maxmind_insights' ); // Get MaxMind insights enable/disable
                $wc_af_maxmind_factors_setting = get_option( 'wc_af_maxmind_factors' ); // Get MaxMind factors enable/disable
		if ( $maxmind_settings == 'yes' ) {
			WC_AF_Rules::get()->add_rule( new WC_AF_Rule_MinFraud() );
		}
                if ( $wc_af_maxmind_insights_setting == 'yes' ) {
                        WC_AF_Rules::get()->add_rule( new WC_AF_Rule_MinFraud_Insights() );
		}
                if ( $wc_af_maxmind_factors_setting == 'yes' ) {
                        WC_AF_Rules::get()->add_rule( new WC_AF_Rule_MinFraud_Factors() );
		}
		WC_AF_Rules::get()->add_rule( new WC_AF_Rule_Country() );
		WC_AF_Rules::get()->add_rule( new WC_AF_Rule_Billing_Matches_Shipping() );
		WC_AF_Rules::get()->add_rule( new WC_AF_Rule_Detect_Proxy() );
		WC_AF_Rules::get()->add_rule( new WC_AF_Rule_Temporary_Email() );
		WC_AF_Rules::get()->add_rule( new WC_AF_Rule_Free_Email() );
		WC_AF_Rules::get()->add_rule( new WC_AF_Rule_International_Order() );
		WC_AF_Rules::get()->add_rule( new WC_AF_Rule_High_Value() );
		WC_AF_Rules::get()->add_rule( new WC_AF_Rule_High_Amount() );
		WC_AF_Rules::get()->add_rule( new WC_AF_Rule_Ip_Location() );
		WC_AF_Rules::get()->add_rule( new WC_AF_Rule_First_Order() );
		WC_AF_Rules::get()->add_rule( new WC_AF_Rule_First_Order_Processing() );
		WC_AF_Rules::get()->add_rule( new WC_AF_Rule_Ip_Multiple_Order_Details() );
		WC_AF_Rules::get()->add_rule( new WC_AF_Rule_Velocities() );
		WC_AF_Rules::get()->add_rule( new WC_AF_Rule_Billing_Phone_Matches_Billing_Country() );

		// Check if admin
		if ( is_admin() ) {
			require_once(dirname( __FILE__ ) . '/anti-fraud-core/class-wc-af-settings.php');
		}
	}

	//Update order on paypal verification
	public function paypal_verification() {
		if (isset($_REQUEST['order_id']) && isset($_REQUEST['paypal_verification'])) {
			$order_id = base64_decode($_REQUEST['order_id']);
			update_post_meta($order_id, 'wc_af_paypal_verification', true);
			$order = new WC_Order($order_id);
			echo "<script type='text/javascript'>
			alert('Your Paypal Email verified Successfully')</script>";
			if( 'completed' === $order->get_status() || 'processing' === $order->get_status() || 'cancelled' === $order->get_status() ) {
				return;
			} else {
				$order->add_order_note( __( 'PayPal Verification Done.', 'woocommerce-anti-fraud' ) );
				$status = $order->update_status('processing');
			}
		}
	}

	//TO Do Test
	public function my_action() {
		$help_class = new WC_AF_Score_Helper();
		$help_class->do_check($_POST['order_id']);
		wp_die();
	}

	//TO DO
	public function admin_scripts() {
		wp_enqueue_script('cal', plugins_url( 'assets/js/cal.js', __FILE__ ) );
		wp_enqueue_script('tags_input', plugins_url( 'assets/js/tags-input.js', __FILE__ ) );
		wp_enqueue_style('cal', plugins_url( 'assets/css/tags-input.css', __FILE__ ) );
		wp_register_script('knob', plugins_url( '/assets/js/jquery.knob.min.js', WooCommerce_Anti_Fraud::get_plugin_file() ), array( 'jquery' ));
		wp_register_script('edit', plugins_url( '/assets/js/edit-shop-order.js', __FILE__ ) );
	}

	public function save_default_settings() {
		// For Minfraud
		update_option('wc_af_fraud_check_before_payment', 'no');
		update_option('wc_af_enable_whitelist_payment_method', 'no');
		update_option('wc_settings_anti_fraud_minfraud_order_weight', 30);
		update_option('wc_settings_anti_fraud_minfraud_risk_score', 30);

		update_option('wc_af_email_notification', 'yes');
		update_option('wc_settings_anti_fraud_cancel_score', 90);
		update_option('wc_settings_anti_fraud_hold_score', 70);
		update_option('wc_settings_anti_fraud_email_score', 50);
		update_option('wc_settings_anti_fraud_email_score1', 51);
		update_option('wc_settings_anti_fraud_low_risk_threshold', 25);
		update_option('wc_settings_anti_fraud_higher_risk_threshold', 75);
		update_option('wc_af_first_order', 'yes');
		update_option('wc_settings_anti_fraud_first_order_weight', 5);
		update_option('wc_af_international_order', 'yes');
		update_option('wc_settings_anti_fraud_international_order_weight', 10);
		update_option('wc_af_ip_geolocation_order', 'yes');
		update_option('wc_af_billing_phone_number_order', 'no');
		update_option('wc_settings_anti_fraud_billing_phone_number_order_weight', 15);
		update_option('wc_settings_anti_fraud_ip_geolocation_order_weight', 50);
		update_option('wc_af_bca_order', 'yes');
		update_option('wc_settings_anti_fraud_bca_order_weight', 20);
		update_option('wc_af_proxy_order', 'yes');
		update_option('wc_settings_anti_fraud_proxy_order_weight', 50);
		update_option('wc_af_suspecius_email', 'yes');
		update_option('wc_settings_anti_fraud_suspecious_email_weight', 5);
		update_option('wc_settings_anti_fraud_suspecious_email_domains', $this->suspicious_domains());
		update_option('wc_af_unsafe_countries', 'yes');
		update_option('wc_settings_anti_fraud_unsafe_countries_weight', 25);
		update_option('wc_af_order_avg_amount_check', 'yes');
		update_option('wc_settings_anti_fraud_order_avg_amount_weight', 15);
		update_option('wc_settings_anti_fraud_avg_amount_multiplier', 2);
		update_option('wc_af_order_amount_check', 'yes');
		update_option('wc_settings_anti_fraud_order_amount_weight', 5);
		update_option('wc_settings_anti_fraud_amount_limit', 10000);
		update_option('wc_af_attempt_count_check', 'yes');
		update_option('wc_settings_anti_fraud_order_attempt_weight', 25);
		update_option('wc_settings_anti_fraud_attempt_time_span', 24);
		update_option('wc_settings_anti_fraud_max_order_attempt_time_span', 1);
		update_option('wc_af_ip_multiple_check', 'yes');
		update_option('wc_settings_anti_fraud_ip_multiple_weight', 25);
		update_option('wc_settings_anti_fraud_ip_multiple_time_span', 30);
		update_option('wc_settings_anti_fraudenable_automatic_email_blacklist', 'yes');
		update_option('wc_settings_anti_fraudenable_automatic_blacklist', 'yes');
		update_option('wc_af_paypal_verification', 'yes');
		update_option('wc_af_paypal_prevent_downloads', 'yes');
		update_option('wc_settings_anti_fraud_time_paypal_attempts', 2);
		update_option('wc_settings_anti_fraud_paypal_email_format', 'html');
		update_option('wc_settings_anti_fraud_paypal_email_subject', $this->paypal_email_subject());
		update_option('wc_settings_anti_fraud_email_body', $this->paypal_email_body());
	}

	public function suspicious_domains() {
		$email_domains = array('hotmail',
		'live',
		'gmail',
		'yahoo',
		'mail',
		'123vn',
		'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijk',
		'aaemail.com',
		'webmail.aol',
		'postmaster.info.aol',
		'personal',
		'atgratis',
		'aventuremail',
		'byke',
		'lycos',
		'computermail',
		'dodgeit',
		'thedoghousemail',
		'doramail',
		'e-mailanywhere',
		'eo.yifan',
		'earthlink',
		'emailaccount',
		'zzn',
		'everymail',
		'excite',
		'expatmail',
		'fastmail',
		'flashmail',
		'fuzzmail',
		'galacmail',
		'godmail',
		'gurlmail',
		'howlermonkey',
		'hushmail',
		'icqmail',
		'indiatimes',
		'juno',
		'katchup',
		'kukamail',
		'mail',
		'mail2web',
		'mail2world',
		'mailandnews',
		'mailinator',
		'mauimail',
		'meowmail',
		'merawalaemail',
		'muchomail',
		'MyPersonalEmail',
		'myrealbox',
		'nameplanet',
		'netaddress',
		'nz11',
		'orgoo',
		'phat.co',
		'probemail',
		'prontomail',
		'rediff',
		'returnreceipt',
		'synacor',
		'walkerware',
		'walla',
		'wongfaye',
		'xasamail',
		'zapak',
		'zappo');
		return implode(',', $email_domains);
	}

	public function paypal_email_body() {
		return 'Hi! We have received your order on ' . get_site_url() . ", but to complete it, we have to verify your PayPal email address. If you haven't made or authorized any purchase, please, contact PayPal support immediately, and email us at " . get_option('admin_email') . '.';
	}

	public function paypal_email_subject() {
			return get_bloginfo( 'name' ) . ' Confirm your PayPal email address';
	}
}
	add_action('profile_update', 'sync_woocommerce_email', 10, 2) ;

function sync_woocommerce_email( $user_id, $old_user_data ) {
	$current_user = wp_get_current_user();

	if ($current_user->user_email != $old_user_data->user_email) {
		wp_update_user( array ( 'ID' => $current_user->ID, 'billing_email' => $current_user->user_email ) ) ;
	}
}
	//custom code for block order if email in blacklist.
	add_action( 'woocommerce_after_checkout_validation', 'misha_validate_fname_lname', 10, 2);
function misha_validate_fname_lname( $fields, $errors ) {
	$blocked_email = get_option('wc_settings_anti_fraudblacklist_emails');
	$blocked_ipaddress = get_option('wc_settings_anti_fraudblacklist_ipaddress');
	$array_mail = explode(',', $blocked_email);
	if ('' != $blocked_email) {
		foreach ($array_mail as $single) {
			if ($_POST[ 'billing_email' ] == $single) {
				$errors->add( 'validation', 'This email id is blocked.' );
			}
		}
	} else if ('' != $blocked_ipaddress) {

		$userip = WC_Geolocation::get_ip_address();
		$array_ipaddress = explode(',', $blocked_ipaddress);
		foreach ($array_ipaddress as $singles) {
			if ($userip == $singles) {
				$errors->add( 'validation', 'This IP Address is blocked.' );
			}
		}
	}
}


	// wc_af_fraud_check_before_payment
add_action( 'woocommerce_checkout_order_processed', 'wh_pre_paymentcall', 10, 2);
function wh_pre_paymentcall( $order_id, $errors ) {

	if( !is_numeric($order_id) ){
		return;
	}

	$check_before_payment = get_option('wc_af_fraud_check_before_payment');
	// echo $check_before_payment;

	if ($check_before_payment == 'yes') {

		if ( null !== get_option('wc_af_pre_payment_message') ){
			$pre_payment_block_message = get_option('wc_af_pre_payment_message');
		}else{
			$pre_payment_block_message = 'Website Administrator does not allow you to place this order. Please contact our support team. Sorry for any inconvenience.';
		}

		$high_risk = get_option('wc_settings_anti_fraud_higher_risk_threshold');
		$score_helper = new WC_AF_Score_Helper();
		$score_helper->schedule_fraud_check( $order_id, true );

		$score_points = get_post_meta( $order_id, 'wc_af_score', true );
		$circle_points = WC_AF_Score_Helper::invert_score( $score_points );

		if ($high_risk <= $circle_points) {

			$order = wc_get_order( $order_id );
			$order->update_status( 'failed', 'Pre Payment Fraud Check: Calculated risk score is above High Risk Threshold.', true );

			$return = array('result' => 'failure', 'messages' => "<ul class='woocommerce-error' role='alert'><li>".$pre_payment_block_message."</li></ul>");

			wp_send_json($return);
			wp_die();
		}
	}
}

//echo get_option('wc_af_paypal_verification');die;
new WooCommerce_Anti_Fraud();
