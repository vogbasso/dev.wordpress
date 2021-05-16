<?php
/**
 * maroctelecommerce Payment Gateway
 *
 * Provides a maroctelecommerce Payment Gateway.
 *
 * @class 		woocommerce_maroctelecommerce
 * @package		WooCommerce
 * @category	Payment Gateways
 * @author		MarocTelecommerce - MTC
 *
 *
 * Table Of Contents
 *
 * __construct() 
 * init_form_fields()
 * setup_constants() 
 * plugin_url()
 * is_valid_for_use()
 * admin_options()
 * payment_fields()
 * generate_maroctelecommerce_form()
 * process_payment()
 * receipt_page()
 * check_MTC_request_is_valid()
 * check_MTC_response()
 * successful_request()
 * log()
 * check_amounts()
 */
 
 
 
 
 
 
 
 
 
 
 
 function utf8entities($source)
{
//    array used to figure what number to decrement from character order value 
//    according to number of characters used to map unicode to ascii by utf-8
   $decrement[4] = 240;
   $decrement[3] = 224;
   $decrement[2] = 192;
   $decrement[1] = 0;
   
//    the number of bits to shift each charNum by
   $shift[1][0] = 0;
   $shift[2][0] = 6;
   $shift[2][1] = 0;
   $shift[3][0] = 12;
   $shift[3][1] = 6;
   $shift[3][2] = 0;
   $shift[4][0] = 18;
   $shift[4][1] = 12;
   $shift[4][2] = 6;
   $shift[4][3] = 0;
   
   $pos = 0;
   $len = strlen($source);
   $encodedString = '';
   while ($pos < $len)
   {
      $charPos = substr($source, $pos, 1);
      $asciiPos = ord($charPos);
      if ($asciiPos < 128)
      {
         $encodedString .= htmlentities($charPos);
         $pos++;
         continue;
      }
      
      $i=1;
      if (($asciiPos >= 240) && ($asciiPos <= 255))  // 4 chars representing one unicode character
         $i=4;
      else if (($asciiPos >= 224) && ($asciiPos <= 239))  // 3 chars representing one unicode character
         $i=3;
      else if (($asciiPos >= 192) && ($asciiPos <= 223))  // 2 chars representing one unicode character
         $i=2;
      else  // 1 char (lower ascii)
         $i=1;
      $thisLetter = substr($source, $pos, $i);
      $pos += $i;
      
//       process the string representing the letter to a unicode entity
      $thisLen = strlen($thisLetter);
      $thisPos = 0;
      $decimalCode = 0;
      while ($thisPos < $thisLen)
      {
         $thisCharOrd = ord(substr($thisLetter, $thisPos, 1));
         if ($thisPos == 0)
         {
            $charNum = intval($thisCharOrd - $decrement[$thisLen]);
            $decimalCode += ($charNum << $shift[$thisLen][$thisPos]);
         }
         else
         {
            $charNum = intval($thisCharOrd - 128);
            $decimalCode += ($charNum << $shift[$thisLen][$thisPos]);
         }
         
         $thisPos++;
      }
      
      $encodedLetter = '&#'. str_pad($decimalCode, ($thisLen==1)?3:5, '0', STR_PAD_LEFT).';';
      $encodedString .= $encodedLetter;
   }
   
   return $encodedString;
}



class WC_Gateway_maroctelecommerce extends WC_Payment_Gateway {

	public $version = '1.0.0';

	public function __construct() {
        global $woocommerce;
        $this->id			= 'maroctelecommerce';
        $this->method_title = __( 'maroctelecommerce', 'MarocTelecommerce - MTC' );
        $this->icon 		= $this->plugin_url() . '/assets/images/icon.png';
        $this->has_fields 	= true;
        $this->debug_email 	= get_option( 'admin_email' );

		// Setup available countries.
		$this->available_countries = array( 'FR' );

		// Setup available currency codes.
		$this->available_currencies = array( 'MAD' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Setup constants.
		$this->setup_constants();

		// Setup default merchant data.
		$this->merchant_id = $this->settings['merchant_id'];
		$this->actionslk = $this->settings['actionslk'];
		$this->SLKSecretkey = $this->settings['SLKSecretkey'];
		$this->title = $this->settings['title'];
		$this->confirmation_mode = $this->settings['confirmation_mode'];
		if($this->confirmation_mode == "yes") $this->confirmation_mode = 1; else $this->confirmation_mode = 2 ;

		

		$this->response_url	= add_query_arg( 'wc-api', 'WC_Gateway_maroctelecommerce', home_url( '/' ) );

		add_action( 'woocommerce_api_wc_gateway_maroctelecommerce', array( $this, 'check_MTC_response' ) );
		add_action( 'valid-maroctelecommerce-standard-MTC-request', array( $this, 'successful_request' ) );
		/* 1.0.1 */
		add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
		/* 1.0 */
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_maroctelecommerce', array( $this, 'receipt_page' ) );

		// Check if the base currency supports this gateway.
		if ( ! $this->is_valid_for_use() )
			$this->enabled = false;
    }

	/**
     * Initialise Gateway Settings Form Fields
     *
     * @since 1.0.0
     */
    function init_form_fields () {

    	$this->form_fields = array(
    						'enabled' => array(
											'title' => __( 'Enable/Disable', 'woothemes' ),
											'label' => __( 'Enable maroctelecommerce', 'woothemes' ),
											'type' => 'checkbox',
											'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.', 'woothemes' ),
											'default' => 'yes'
										),
    						'title' => array(
    										'title' => __( 'Title', 'woothemes' ),
    										'type' => 'text',
    										'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
    										'default' => __( 'Maroctelecommerce', 'woothemes' )
    									),
							'description' => array(
											'title' => __( 'Description', 'woothemes' ),
											'type' => 'text',
											'description' => __( 'This controls the description which the user sees during checkout.', 'woothemes' ),
											'default' => ''
										),
							'merchant_id' => array(
											'title' => __( 'StoreID', 'woothemes' ),
											'type' => 'text',
											'description' => __( 'StoreID fourni par Maroctelecommerce.', 'woothemes' ),
											'default' => ''
										),
							'SLKSecretkey' => array(
											'title' => __( 'SLKSecretkey', 'woothemes' ),
											'type' => 'text',
											'description' => __( 'Clés secrètes fourni par Maroctelecommerce.', 'woothemes' ),
											'default' => ''
										),
							'actionslk' => array(
											'title' => __( 'actionslk', 'woothemes' ),
											'type' => 'text',
											'description' => __( 'URL de la passerelle fourni par Maroctelecommerce.', 'woothemes' ),
											'default' => ''
										),
							'confirmation_mode' => array(
											'title' => __( 'Mode de confirmation', 'woothemes' ),
											'type' => 'checkbox',
											'label' => __( 'Confirmation automatique des transactions Maroctelecommerce.', 'woothemes' ),
											'default' => 'yes'
										)
							);

    } // End init_form_fields()

    /**
	 * Get the plugin URL
	 *
	 * @since 1.0.0
	 */
	function plugin_url() {
		if( isset( $this->plugin_url ) )
			return $this->plugin_url;

		if ( is_ssl() ) {
			return $this->plugin_url = str_replace( 'http://', 'https://', WP_PLUGIN_URL ) . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
		} else {
			return $this->plugin_url = WP_PLUGIN_URL . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
		}
	} // End plugin_url()

    /**
     * is_valid_for_use()
     *
     * Check if this gateway is enabled and available in the base currency being traded with.
     *
     * @since 1.0.0
     */
	function is_valid_for_use() {
		global $woocommerce;

		$is_available = false;

        $user_currency = get_option( 'woocommerce_currency' );

        $is_available_currency = in_array( $user_currency, $this->available_currencies );

		if ($this->enabled == 'yes' && $this->settings['merchant_id'] != '' )
			$is_available = true;
        return $is_available;
	} // End is_valid_for_use()

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		// Make sure to empty the log file if not in test mode.
		if ( $this->settings['testmode'] != 'yes' ) {
			$this->log( '' );
			$this->log( '', true );
		}

    	?>
    	<h3><?php _e( 'Maroctelecommerce', 'MarocTelecommerce - MTC' ); ?></h3>

    	<?php
    		?><table class="form-table"><?php
			// Generate the HTML For the settings form.
    		$this->generate_settings_html();
    		?></table><!--/.form-table-->
    	<?php
    } // End admin_options()

    /**
	 * There are no payment fields for maroctelecommerce, but we want to show the description if set.
	 *
	 * @since 1.0.0
	 */
    function payment_fields() {
    	if ( isset( $this->settings['description'] ) && ( '' != $this->settings['description'] ) ) {
    		echo wpautop( wptexturize( $this->settings['description'] ) );
    	}
    } // End payment_fields()

	/**
	 * Generate the maroctelecommerce button link.
	 *
	 * @since 1.0.0
	 */
    public function generate_maroctelecommerce_form( $order_id ) {

		global $woocommerce;

		$order = new WC_Order( $order_id );

		$shipping_name = explode(' ', $order->shipping_method);
        $user_currency = get_option( 'woocommerce_currency' );

		$return_url = $this->get_return_url( $order );
		$cancel_url = $order->get_cancel_order_url();
		$notify_url = $this->response_url;




		$totalAmountTx = $order->order_total;
		//Verify if data come from maroctelecommerce
		$dataMD5=$this->settings['actionslk'] . $order->order_total  . $this->settings['merchant_id'] . $order->id. $order->billing_email . $this->settings['SLKSecretkey'];
		//echo $dataMD5;
		$checksum=MD5(utf8entities(rawurlencode($dataMD5)));
		// Construct variables for post
		$lang = get_locale();
		if($lang == "fr-FR")	
		{
			$lang = "fr";
		}else
		{
			$lang = "en";
		}
		
			    $data = array(
	'clientid' => $this->settings['merchant_id'],
	'lang' => $lang,
	'rnd' => microtime(),
	'storetype' => "3D PAY HOSTING",
	'hashAlgorithm' => "ver3",
	'tranType' => "PreAuth",
	'email' => $order->billing_email,
	'BillToName' => $order->billing_first_name." ".$order->billing_last_name,
	'BillToStreet1' => $order->billing_address_1,
	'BillToCity' => $order->billing_city,
	'BillToStateProv' => $order->billing_state,
	'BillToCountry' => $order->billing_country,
	'BillToPostalCode' => $order->billing_postcode,
	'BillToTelVoice' => $order->billing_phone,
	'oid' => $order->id,
	'refreshtime' => "5",
	'instalment' => "",
	'amount' => $totalAmountTx,
	'shopurl' => $cancel_url,
	'currency' => "504",
	'failUrl' => $cancel_url,
	'okUrl' =>$return_url,
	'callbackUrl' => $notify_url,
	'encoding' => "UTF-8"
	);
	
	  /*   $this->data_to_send = array(
	        // Merchant details
	        'storeId' => $this->settings['merchant_id'],
	        'updateURL' => $return_url,
	        'offerURL' => $cancel_url,
	        'bookURL' => $notify_url,
			'name' => $order->billing_first_name." ".$order->billing_last_name,
			'email' => $order->billing_email,
	        'totalAmountTx' => $order->order_total,
	    	'checksum' => $checksum,
	    	'cartId' => $order->id,
	    	'langue' => $lang,
			'address' => $order->billing_address_1,
			'city' => $order->billing_city,
			'state' => $order->billing_state,
			'country' => $order->billing_country,
			'postCode' => $order->billing_postcode,
			'tel' => $order->billing_phone,
	   	); */

		
		$postParams = array();
		foreach ($data as $key => $value){
			array_push($postParams	, $key);
		}

		natcasesort($postParams);

		$hashval = "";
		foreach ($postParams as $param){
			$paramValue = trim(html_entity_decode(html_entity_decode($data[$param])));
			$escapedParamValue = str_replace("|", "\\|", str_replace("\\", "\\\\", $paramValue));

			$lowerParam = strtolower($param);
			if($lowerParam != "hash" && $lowerParam != "encoding" )	{
				$hashval = $hashval . $escapedParamValue . "|";
			}
		}

		$escapedStoreKey = str_replace("|", "\\|", str_replace("\\", "\\\\", $this->settings['SLKSecretkey']));
		$hashval = $hashval . $escapedStoreKey;

		//echo $hashval;
		$calculatedHashValue = hash('sha512', $hashval);
		$hash = base64_encode (pack('H*',$calculatedHashValue));
		
		$data['hash'] = $hash;
	
		$this->data_to_send = $data;
		$maroctelecommerce_args_array = array();

		foreach ($this->data_to_send as $key => $value) {
			$maroctelecommerce_args_array[] = '<input type="hidden" name="'.$key.'" value="'.trim($value).'" />';
		}
		wc_enqueue_js( '
			$.blockUI({
					message: "' . esc_js( __( 'Merci pour votre commande. vous serez redirigé vers MarocTelecommerce pour effectuer le paiement.', 'woocommerce' ) ) . '",
					baseZ: 99999,
					overlayCSS:
					{
						background: "#fff",
						opacity: 0.6
					},
					css: {
						padding:        "20px",
						zindex:         "9999999",
						textAlign:      "center",
						color:          "#555",
						border:         "3px solid #aaa",
						backgroundColor:"#fff",
						cursor:         "wait",
						lineHeight:		"24px",
					}
				});
			jQuery("#submit_maroctelecommerce_payment_form").click();
		' );
		return '<form action="' .  $this->settings['actionslk']  . '" method="post" id="maroctelecommerce_payment_form" target="_top">
				' . implode( '', $maroctelecommerce_args_array ) . '
				<!-- Button Fallback -->
				<div class="payment_buttons">
					<input type="submit" class="button alt" id="submit_maroctelecommerce_payment_form" value="' . __( 'Payez via maroctelecommerce', 'woocommerce' ) . '" /> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Annuler la commande', 'woocommerce' ) . '</a>
				</div>
				<script type="text/javascript">
					jQuery(".payment_buttons").hide();
				</script>
			</form>';

	} // End generate_maroctelecommerce_form()

	/**
	 * Process the payment and return the result.
	 *
	 * @since 1.0.0
	 */
	function process_payment( $order_id ) {

		$order = new WC_Order( $order_id );

		return array(
			'result' 	=> 'success',
			'redirect'	=> $order->get_checkout_payment_url( true )
		);

	}

	/**
	 * Reciept page.
	 *
	 * Display text and a button to direct the user to maroctelecommerce.
	 *
	 * @since 1.0.0
	 */
	function receipt_page( $order ) {
		echo '<p>' . __( 'Merci pour votre commande, s\'il vous plaît cliquez sur le bouton ci-dessous pour payer avec Maroctelecommerce.', 'woothemes' ) . '</p>';

		echo $this->generate_maroctelecommerce_form( $order );
	} // End receipt_page()

	/**
	 * Check maroctelecommerce MTC validity.
	 *
	 * @param array $data
	 * @since 1.0.0
	 */
	function check_MTC_request_is_valid( $data ) {			
			
		global $woocommerce;

		$pfError = false;
		$pfDone = false;
		
        $vendor_name = get_option( 'blogname' );
        $vendor_url = home_url( '/' );

		$order_id = (int) $data['oid'];
		$order = new WC_Order( $order_id );
		$order_key = $order->order_key;
		
		
		
		
		$postParams = array();
		foreach ($_POST as $key => $value){
			array_push($postParams, $key);
			//echo "<tr><td>" . $key ."</td><td>" . $value . "</td></tr>";
		}

		natcasesort($postParams);

		$hashval = "";
		foreach ($postParams as $param){

			//$paramValue = html_entity_decode(preg_replace("#\n|\t|\r#","",$_POST[$param]), ENT_QUOTES, 'UTF-8');
			$paramValue = html_entity_decode(preg_replace("/\n$/","",$_POST[$param]), ENT_QUOTES, 'UTF-8'); 

			$escapedParamValue = str_replace("|", "\\|", str_replace("\\", "\\\\", $paramValue));

			$lowerParam = strtolower($param);
			if($lowerParam != "hash" && $lowerParam != "encoding" )	{
				$hashval = $hashval . $escapedParamValue . "|";
			}
			
		}

		$escapedStoreKey = str_replace("|", "\\|", str_replace("\\", "\\\\", $this->settings['SLKSecretkey']));
		$hashval = $hashval . $escapedStoreKey;

		$calculatedHashValue = hash('sha512', $hashval);
		$actualHash = base64_encode (pack('H*',$calculatedHashValue));
		
		
		

		$this->log( "\n" . '----------' . "\n" . 'maroctelecommerce call received' );

		

        // Get data sent by maroctelecommerce
        if ( ! $pfError && ! $pfDone ) {
        	$this->log( 'Get posted data' );

            $this->log( 'maroctelecommerce Data: '. print_r( $data, true ) );

            if ( $data === false ) {
                $pfError = true;
                $pfErrMsg = BPI_ERR_BAD_ACCESS;
            }
        }

		// Verify security checksum
        if( ! $pfError && ! $pfDone ) {
            $this->log( 'Verify security checksum' );

            // If checksum different, log for debugging
            if($actualHash != $data['HASH']) {
                $pfError = true;
                $pfErrMsg = BPI_ERR_INVALID_SIGNATURE;
				
				echo "APPROVED";
				exit;
            }
        }
		
		
		
        // Get internal order and verify it hasn't already been processed
        if( ! $pfError && ! $pfDone ) {

            $this->log( "Purchase:\n". print_r( $order, true )  );

            // Check if order has already been processed
            if( $order->status == 'completed' ) {
                $this->log( 'Order has already been processed' );
                $pfDone = true;
				echo "Approved";
				exit;
            }
        }

        

        // Check data against internal order
        if( ! $pfError && ! $pfDone ) {
            $this->log( 'Check data against internal order' );

            // Check order amount
            if( ! $this->check_amounts( $data['amount'], $order->order_total ) ) {
                $pfError = true;
                $pfErrMsg = BPI_ERR_AMOUNT_MISMATCH;
				echo "APPROVED";
				exit;
            }
        }
		
	

        // Check status and update order
        if( ! $pfError && ! $pfDone ) {
            $this->log( 'Check status and update order' );

		if ( $order->order_key !== $order_key ) { exit; }

						$this->log( '- Complete' );			
        }

        // If an error occurred
        if( $pfError ) {
            $this->log( 'Error occurred: '. $pfErrMsg );
        }

        // Close log
        $this->log( '', true );

    	return $pfError;
    } // End check_MTC_request_is_valid()
	
	/**
	 * Check maroctelecommerce MTC response.
	 *
	 * @since 1.0.0
	 */
	function check_MTC_response() {		
		//$_POST = stripslashes_deep( $_REQUEST );
		if ( !$this->check_MTC_request_is_valid( $_POST ) ) {
			do_action( 'valid-maroctelecommerce-standard-MTC-request', $_POST );
		}
	} // End check_MTC_response()

	/**
	 * Successful Payment!
	 *
	 * @since 1.0.0
	 */
	function successful_request( $posted ) {
		
		if($posted['ProcReturnCode'] != "00")
		{
        $order->update_status('Failed', __('Payment Error  - Error: Payment Gateway Declined order.', 'wptut'));
        $order->add_order_note('Payment failed<br/>Payment Gateway Message: '.$posted['ErrMsg']);

		return false; 
		exit;
		}else
		{

		$order = new WC_Order( $posted['oid'] );
		
		

		if ( $order->status !== 'completed' ) {
			// We are here so lets check status and do actions

				$msg = 'Paiement accepté par le CMI';

				// Payment completed
				$order->add_order_note( __( $msg , 'woothemes' ) );
				$order->payment_complete();
			
			//wp_redirect( $this->get_return_url( $order ) );
			echo "ACTION=POSTAUTH";
				exit;
		} // End IF Statement

		exit;
		
		}
	}

	/**
	 * Setup constants.
	 *
	 * Setup common values and messages used by the maroctelecommerce gateway.
	 *
	 * @since 1.0.0
	 */
	function setup_constants () {
		global $woocommerce;
		//// Create user agent string
		// User agent constituents (for cURL)
		define( 'BPI_SOFTWARE_NAME', 'WooCommerce' );
		define( 'BPI_SOFTWARE_VER', $woocommerce->version );
		define( 'BPI_MODULE_NAME', 'WooCommerce-maroctelecommerce-Free' );
		define( 'BPI_MODULE_VER', $this->version );

		// Features
		// - PHP
		$pfFeatures = 'PHP '. phpversion() .';';

		// - cURL
		if( in_array( 'curl', get_loaded_extensions() ) )
		{
		    define( 'BPI_CURL', '' );
		    $pfVersion = curl_version();
		    $pfFeatures .= ' curl '. $pfVersion['version'] .';';
		}
		else
		    $pfFeatures .= ' nocurl;';

		// Create user agrent
		define( 'BPI_USER_AGENT', BPI_SOFTWARE_NAME .'/'. BPI_SOFTWARE_VER .' ('. trim( $pfFeatures ) .') '. BPI_MODULE_NAME .'/'. BPI_MODULE_VER );

		// General Defines
		define( 'BPI_TIMEOUT', 15 );
		define( 'BPI_EPSILON', 0.01 );

		// Messages
		    // Error
		define( 'BPI_ERR_AMOUNT_MISMATCH', __( 'Amount mismatch', 'woothemes' ) );
		define( 'BPI_ERR_BAD_ACCESS', __( 'Bad access of page', 'woothemes' ) );
		define( 'BPI_ERR_INVALID_SIGNATURE', __( 'Security signature mismatch', 'woothemes' ) );

	} // End setup_constants()

	/**
	 * log()
	 *
	 * Log system processes.
	 *
	 * @since 1.0.0
	 */

	function log ( $message, $close = false ) {
		
		static $fh = 0;

		if( $close ) {
            @fclose( $fh );
        } else {
            // If file doesn't exist, create it
            if( !$fh ) {
                $pathinfo = pathinfo( __FILE__ );
                $dir = str_replace( '/classes', '/logs', $pathinfo['dirname'] );
                $fh = @fopen( $dir .'/maroctelecommerce.log', 'a+' );
            }

            // If file was successfully created
            if( $fh ) {
                $line = $message ."\n";

                fwrite( $fh, $line );
            }
        }
	} // End log()


	/**
	 * check_amounts()
	 *
	 * Checks if received amount is equal to sent amount
	 *
	 * @param $amount1 Float 1st amount for comparison
	 * @param $amount2 Float 2nd amount for comparison
	 * @since 1.0.0
	 */
	function check_amounts ( $amount1, $amount2 ) {
		if(number_format($amount1) !== number_format($amount2))return false;
		return true;
	} // End check_amounts()

} // End Class