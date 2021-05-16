<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_AF_Paypal_Email extends WC_Email {

	/**
	 * Array containing all Report Rows
	 *
	 * @var array<WC_SRE_Report_Row>
	 */
	private $rows = array();

	public function __construct( $order, $score ) {
		// WC_Email basic properties
		$this->id          = 'anti_fraud_paypal_verification';
		$this->title       = __( 'Anti Fraud - PayPal verification', 'woocommerce-anti-fraud' );
		$this->description = __( 'Notice about PayPal verification.', 'woocommerce-anti-fraud' );

		$this->order = $order;
		$this->order_id = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
		
		$this->score = $score;
		$this->email_template = get_option('wc_settings_anti_fraud_paypal_email_format');
		$this->verification_url = site_url() . '/?order_id=' . base64_encode($this->order_id) . '&paypal_verification=true';
		parent::__construct();

	}

	/**
	 * Initialize the class via this init method instead of the constructor to enhance performance.
	 *
	 * @access private
	 * @since  1.0.0
	 */
	private function init() {

		// Subject & heading
		$this->subject = get_option('wc_settings_anti_fraud_paypal_email_subject');
		$this->heading = __( 'PayPal Verification notification of order #{order_id}', 'woocommerce-anti-fraud' );

		// Set the template base path
		$this->template_base = plugin_dir_path( WooCommerce_Anti_Fraud::get_plugin_file() ) . 'templates/';

		// Set the templates
		$this->template_html  = 'af-paypal-notice.php';
		$this->template_plain = 'plain/af-paypal-notice.php';

		// Find & Replace vars
		$this->find['order-id']    = '{order_id}';
		$this->replace['order-id'] = $this->order->get_order_number();

	}

	/**
	 * This method is triggered on WP Cron.
	 *
	 * @access public
	 * @since  1.0.0
	 */
	public function send_notification() {

		// All checks are done, initialize the object
		$this->init();

		// for paypal email
		$order_ids = $this->order->get_order_number();
		$payment_method = get_post_meta( $order_ids, '_payment_method', true );
		// Set recipients
		if ($payment_method == 'ppec_paypal') {

			$this->recipient = get_post_meta( $order_ids, '_paypal_express_payer_email', true);

		} else {

			$this->recipient = get_post_meta( $order_ids, '_paypal_payer_email', true);
		}

		// Add the 'woocommerce_locate_template' filter so we can load our plugin template file
		add_filter( 'woocommerce_locate_template', array( $this, 'load_plugin_template' ), 10, 3 );

		// Add email header and footer
		if ( ! has_action( 'woocommerce_email_header' ) ) {
			add_action( 'woocommerce_email_header', array( $this, 'email_header' ) );
			add_action( 'woocommerce_email_footer', array( $this, 'email_footer' ) );
		}

		// Send the emails
		if (get_option('wc_af_paypal_verification') == 'yes') {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		// Remove the woocommerce_locate_template filter
		remove_filter( 'woocommerce_locate_template', array( $this, 'load_plugin_templates' ), 10 );

		// Remove the header and footer actions
		remove_action( 'woocommerce_email_header', array( $this, 'email_header' ) );
		remove_action( 'woocommerce_email_footer', array( $this, 'email_footer' ) );

	}

	/**
	 * Load template files of this plugin
	 *
	 * @param String $template
	 * @param String $template_name
	 * @param String $template_path
	 *
	 * @access public
	 * @since  1.0.0
	 *
	 * @return String
	 */
	public function load_plugin_template( $template, $template_name, $template_path ) {
		
		if ( 'af-paypal-notice.php' == $template_name || 'plain/af-paypal-notice.php' == $template_name ) {
			$template = $template_path . $template_name;
		}
		
		return $template;
	}

	/**
	 * Get the email header.
	 *
	 * @access public
	 * @since  1.0.0
	 *
	 * @param mixed $email_heading heading for the email
	 *
	 * @return void
	 */
	public function email_header( $email_heading ) {
		wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
	}

	/**
	 * Get the email footer.
	 *
	 * @access public
	 * @since  1.0.0
	 *
	 * @return void
	 */
	public function email_footer() {
		wc_get_template( 'emails/email-footer.php' );
	}

	/**
	 * get_content_html function.
	 *
	 * @access public
	 * @since  1.0.0
	 *
	 * @return string
	 */
	public function get_content_html() {
		ob_start();
		$template_name = $this->template_html;
		$plain_text    = false;
		if ('text' == $this->email_template) {
			$template_name = $this->template_plain;
			$plain_text	   = true;		
		}
		wc_get_template( $template_name, array(
			'email_heading' => $this->get_heading(),
			'order_id'      => $this->order->get_order_number(),
			'score'         => $this->score,
			'url'     		=> $this->verification_url,
			'plain_text'    => $plain_text
		), $this->template_base );

		return ob_get_clean();
	}

	/**
	 * get_content_plain function.
	 *
	 * @access public
	 * @since  1.0.0
	 *
	 * @return string
	 */
	public function get_content_plain() {
		ob_start();
		wc_get_template( $this->template_plain, array(
			'email_heading' => $this->get_heading(),
			'order_id'      => $this->order->get_order_number(),
			'score'         => $this->score,
			'url'    		=> $this->verification_url,
			'plain_text'    => true
		), $this->template_base );

		return ob_get_clean();
	}

}
