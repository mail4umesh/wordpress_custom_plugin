<?php
/*
Plugin Name: WooCommerce Dinpay WeChat QR Code Payment Gateway
Plugin URI: http://www.umesh-jangir.com/
Description: Dinpay WeChat QR Code Payment Gateway for WooCommerce.
Version: 1.0.0
Author: ChinaSalesCo.com
Author URI: http://www.umesh-jangir.com/
License: GPLv2
*/

add_action( 'plugins_loaded', 'init_ddbillwqc_gateway_class' );

require_once(dirname(__FILE__).'/lib/ddbillwqc.php');
require_once(dirname(__FILE__).'/lib/phpqrcode.php');

function init_ddbillwqc_gateway_class() {
	
class WC_Gateway_DDBillWQC extends WC_Payment_Gateway {

	var $notify_url;

	/**
	 * Constructor
	 */
	public function __construct() {

		$this->id                = 'ddbillwqc';
		$this->icon              = apply_filters( 'woocommerce_ddbillwqc_icon', plugins_url( 'images/ddbillwqc.png', __FILE__ ) );
		$this->has_fields        = false;
		$this->order_button_text = __( 'Proceed to Dinpay WeChat QR Code', 'woocommerce-ddbillwqc' );
		$this->method_title      = __( 'Dinpay WeChat QR Code', 'woocommerce-ddbillwqc' );
		$this->notify_url        = WC()->api_request_url( 'WC_Gateway_DDBillWQC' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->enabled          = $this->get_option( 'enabled' );
		$this->title 			= $this->get_option( 'title' );
		$this->description 		= $this->get_option( 'description' );
		$this->debug			= $this->get_option( 'debug' );
		$this->sign_type		= $this->get_option( 'sign_type' );
		$this->merchant_code	= $this->get_option( 'merchant_code' );
		$this->merchant_name	= $this->get_option( 'merchant_name' );
		$this->public_key		= $this->get_option( 'public_key' );
		$this->private_key		= $this->get_option( 'private_key' );
		$this->enabled_cust_id 	= $this->get_option( 'enabled_cust_id' );

		@session_start();
		
		$this->cardFields = array('_customer_id', '_customer_name');
		foreach( $this->cardFields as $field )
		{
			$key = $this->id.$field;
			if( isset($_REQUEST[$key]) )
			{
				$this->$key = $_REQUEST[$key];
				$_SESSION[$key] = $_REQUEST[$key];
			}
			else if( isset($_SESSION[$key]) )
			{
				$this->$key = $_SESSION[$key];
			}
		}

		// Logs
		if ( 'yes' == $this->debug ) {
			$this->log = new WC_Logger();
		}

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_api_wc_gateway_ddbillwqc', array( $this, 'check_ipn_response' ) );
	}

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @access public
	 * @return void
	 */
	public function admin_options() {
		$this->checks();
		?>
		<h3><?php __( 'Dinpay WeChat QR Code', 'woocommerce-ddbillwqc' ); ?></h3>
		<p><?php __( 'Dinpay WeChat QR Code works by sending the user to Dinpay Account to enter their payment information.', 'woocommerce-ddbillwqc' ); ?></p>

        <table class="form-table">
        <?php
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
        ?>
        </table>
		<?php 
	}

	/**
	 * Check if SSL is enabled and notify the user
	 */
	public function checks() {
		if ( 'no' == $this->enabled ) {
			return;
		}

		// PHP Version
		if ( version_compare( phpversion(), '5.3', '<' ) ) {
			echo '<div class="error"><p>' . sprintf( __( 'Dinpay WeChat QR Code Payment Error: Dinpay WeChat QR Code Payment requires PHP 5.3 and above. You are using version %s.', 'woocommerce-ddbillwqc' ), phpversion() ) . '</p></div>';
		}

		// Check required fields
		elseif ( ! $this->merchant_code ) {
			echo '<div class="error"><p>' . __( 'Dinpay WeChat QR Code Payment Error: Please enter your Dinpay Account Merchant Code', 'woocommerce-ddbillwqc' ) . '</p></div>';
		}
		elseif ( ! $this->public_key ) {
			echo '<div class="error"><p>' . __( 'Dinpay WeChat QR Code Payment Error: Please enter your Dinpay Account Public Key', 'woocommerce-ddbillwqc' ) . '</p></div>';
		}
		elseif ( ! $this->private_key ) {
			echo '<div class="error"><p>' . __( 'Dinpay WeChat QR Code Payment Error: Please enter your Mechant Private Key', 'woocommerce-ddbillwqc' ) . '</p></div>';
		}
		elseif ( ! function_exists('curl_init') ) {
			echo '<div class="error"><p>' . __( 'Dinpay WeChat QR Code Payment Error: CURL is required for Dinpay WeChat QR Code Payment', 'woocommerce-ddbillwqc' ) . '</p></div>';
		}
		elseif ( ! function_exists('openssl_get_privatekey') ) {
			echo '<div class="error"><p>' . __( 'Dinpay WeChat QR Code Payment Error: OpenSSL is required for Dinpay WeChat QR Code Payment', 'woocommerce-ddbillwqc' ) . '</p></div>';
		}

		// Show message when using live mode and no SSL on the checkout page
		elseif ( ! class_exists( 'WordPressHTTPS' ) ) {
			echo '<div class="error"><p>' . sprintf( __( 'Dinpay WeChat QR Code Payment is enabled, but the <a href=\"%s\">force SSL option</a> is disabled; your checkout may not be secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'woocommerce-ddbillwqc'), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '</p></div>';
		}
	}

	/**
	 * Check if this gateway is enabled
	 */
	public function is_available() {
		if ( 'yes' != $this->enabled ) {
			return false;
		}

		if ( ! $this->merchant_code ) {
			return false;
		}

		if ( ! $this->public_key ) {
			return false;
		}

		if ( ! $this->private_key ) {
			return false;
		}

		if ( ! function_exists('curl_init') ) {
			return false;
		}

		if ( ! function_exists('openssl_get_privatekey') ) {
			return false;
		}


		return true;
	}
	/**
	 * Initialise Gateway Settings Form Fields
	 */
	function init_form_fields() {

		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-ddbillwqc' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Dinpay WeChat QR Code', 'woocommerce-ddbillwqc' ),
				'default' => 'no'
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce-ddbillwqc' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-ddbillwqc' ),
				'default'     => __( 'Dinpay WeChat QR Code', 'woocommerce-ddbillwqc' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-ddbillwqc' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-ddbillwqc' ),
				'default'     => __( 'Pay via Dinpay WeChat QR Code', 'woocommerce-ddbillwqc' )
			),
			'sign_type' => array(
				'title'       => __( 'Sign Type', 'woocommerce-ddbillwqc' ),
				'type'        => 'select',
				'description' => __( 'Sign Type: For Dinpay Account.', 'woocommerce-ddbillwqc' ),
				'default'     => 'RSA-S',
				'desc_tip'    => true,
				'options'	  => array('MD5' => 'MD5', 'RSA-S' => 'RSA-S', 'RSA' => 'RSA'),
			),
			'merchant_name' => array(
				'title'       => __( 'Payee Name', 'woocommerce-ddbillwqc' ),
				'type'        => 'text',
				'description' => __( 'NOTE: This should come from the DinPay assigned WeChat Payment channel for your account, and is NOT your store name or business name.', 'woocommerce-ddbillwqc' ),
				'default'     => '深圳速汇宝支付科技有限公司',
			),
			'merchant_code' => array(
				'title'       => __( 'Dinpay Merchant Code', 'woocommerce-ddbillwqc' ),
				'type'        => 'text',
				'description' => __( 'Dinpay Merchant Code: From Dinpay Account.', 'woocommerce-ddbillwqc' ),
				'default'     => '',
				'desc_tip'    => true
			),
			'public_key' => array(
				'title'       => __( 'Dinpay Public Key', 'woocommerce-ddbillwqc' ),
				'type'        => 'textarea',
				'description' => __( 'Dinpay Public Key: From Dinpay Account.', 'woocommerce-ddbillwqc' ),
				'default'     => '',
				'desc_tip'    => true
			),
			'private_key' => array(
				'title'       => __( 'Merchant Private Key', 'woocommerce-ddbillwqc' ),
				'type'        => 'textarea',
				'description' => __( 'Merchant Private Key: For Dinpay Account and make sure added Merchant Public Key at Dinpay Account.', 'woocommerce-ddbillwqc' ),
				'default'     => '',
				'desc_tip'    => true
			),
			'enabled_cust_id' => array(
				'title'   => __( 'Customer ID', 'woocommerce-ddbillb2b' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Customer ID', 'woocommerce-ddbillb2b' ),
				'default' => 'no'
			),
			'debug' => array(
				'title'       => __( 'Debug Log', 'woocommerce-ddbillwqc' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'woocommerce-ddbillwqc' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log Dinpay WeChat QR Code events, such as IPN requests, inside', 'woocommerce-ddbillwqc' ).'<code>'.WC_LOG_DIR.'ddbillwqc-%s.log</code>', sanitize_file_name( wp_hash( 'ddbillwqc' ) ) ),
			)
		);
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		$this->credit_card_form( array( 'fields_have_names' => true ));
	}

	/**
	 * Core credit card form which gateways can used if needed.
	 *
	 * @param  array $args
	 */
	public function credit_card_form( $args = array(), $fields = array() ) {

		wp_enqueue_script( 'wc-credit-card-form' );

		$default_args = array(
			'fields_have_names' => true, // Some gateways like stripe don't need names as the form is tokenized
		);

		$args = wp_parse_args( $args, apply_filters( 'woocommerce_credit_card_form_args', $default_args, $this->id ) );

		$default_fields = array();
		
		if( 'yes' == $this->enabled_cust_id ) {
			$default_fields['customer_id_field'] = '<p class="form-row form-row-wide">
				<label for="' . esc_attr( $this->id ) . '_customer_id">' . __( 'Customer ID', 'woocommerce-ddbillwqc' ) . ' <span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '_customer_id" class="input-text wc-credit-card-form-customer-id" name="' . $this->id . '_customer_id" style="width:100%;" /></p>';

			$default_fields['customer_name_field'] = '<p class="form-row form-row-wide">
				<label for="' . esc_attr( $this->id ) . '_customer_name">' . __( 'Customer Name', 'woocommerce-ddbillwqc' ) . ' <span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '_customer_name" class="input-text wc-credit-card-form-customer-name" name="' . $this->id . '_customer_name" style="width:100%;" /></p>';
		}

		$fields = $default_fields;
		?>
		<fieldset id="<?php echo $this->id; ?>-cc-form">
			<?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
			<?php
				foreach ( $fields as $field ) {
					echo $field;
				}
			?>
			<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
			<div class="clear"></div>
		</fieldset>
		<?php
	}

	/**
	 * Process the payment
	 *
	 * @param integer $order_id
	 */
	function process_payment( $order_id ) {
		global $woocommerce;
		$order = new WC_Order( $order_id );
		
		foreach( $this->cardFields as $field )
		{
			if( 'no' == $this->enabled_cust_id && in_array($field, array('_customer_id', '_customer_name')) )
				continue;

			if( $field == '_bank_code' )
				continue;

			$key = $this->id.$field;
			if( empty($this->$key) )
			{
				wc_add_notice( __('Please make sure your card details have been entered correctly', 'woocommerce-ddbillwqc'), 'error' );
				return array(
					'result'   => 'fail',
					'redirect' => ''
				);
			}
		}
		
		if( 'no' != $this->enabled_cust_id )
		{
			$customer_id = $this->id.'_customer_id';
			$customer_name = $this->id.'_customer_name';
			
			if( !preg_match('/^\d{18}$/', $this->$customer_id) )
			{
				wc_add_notice( __('Customer ID should be an 18-digit number', 'woocommerce-ddbillwqc'), 'error' );
				return array(
					'result'   => 'fail',
					'redirect' => ''
				);
			}

			$customer_data = array(
						'public_key' => $this->public_key,
						'private_key' => $this->private_key,
						'sign_type' => $this->sign_type,
						'merchant_code' => $this->merchant_code,
						'customer_id' => $this->$customer_id,
						'customer_name' => $this->$customer_name,
					);
			$verify = DDBillWQCCustomerIDCheck( $customer_data );
			if( $verify !== true )
			{
				wc_add_notice( $verify, 'error' );
				return array(
					'result'   => 'fail',
					'redirect' => ''
				);
			}
		} 
		
		if ( 'yes' == $this->debug ) {
			$this->log->add( 'ddbillwqc', 'Process Payment order #' . $order_id );
		}
		
		$this->ddbillwqc_amount = number_format($order->order_total, 2, '.', '');
		$this->ddbillwqc_currency = strtoupper( get_woocommerce_currency() );
		
		$this->ddbillwqc_amount = $this->convertCurrency($this->ddbillwqc_amount, $this->ddbillwqc_currency, 'CNY');
		
		$customer_id = ''; $customer_name = '';
		if( 'yes' == $this->enabled_cust_id ) {
			$customer_id = $this->processInput($this->ddbillwqc_customer_id);
			$customer_name = $this->processInput($this->ddbillwqc_customer_name);
		}
		$client_ip = $_SERVER['REMOTE_ADDR'];
		
		$item_names = array();
		foreach ( $order->get_items() as $item ) {
			$item_names[] = $item['name'] . ' x ' . $item['qty'];
		}
		$product_desc = implode( ', ', $item_names );
		
        $primaryPayload = array(
            "sign_type" => $this->sign_type,
            "merchant_name" => $this->merchant_name,
            "merchant_code" => $this->merchant_code,
            "public_key" => $this->public_key,
            "private_key" => $this->private_key,
            "notify_url" => $this->notify_url,
            "currency" => 'CNY',
            "customer_idNumber" => $customer_id,
            "customer_name" => $customer_name,
            "order_amount" => $this->ddbillwqc_amount,
            "order_no" => $order_id,
            "order_time" => date('Y-m-d H:i:s', time()),
            "product_code" => "",
            "product_desc" => $product_desc,
            "product_name" => "Products of Order No: ".$order_id,
            "product_num" => "",
            "return_url" => $this->get_return_url( $order ),
            "show_url" => "",
            "customer_first_name" => $order->billing_first_name,
            "customer_last_name" => $order->billing_last_name,
            "customer_email" => $order->billing_email,
            "customer_phone" => $order->billing_phone,
            "customer_country" => $order->billing_country,
            "customer_state" => $order->billing_state,
            "customer_city" => $order->billing_city,
            "customer_street" => $order->billing_address_1.' '.$order->billing_address_2,
            "customer_zip" => $order->billing_postcode,
            "ship_to_firstname" => $order->shipping_first_name,
            "ship_to_lastname" => $order->shipping_last_name,
            "ship_to_email" => $order->billing_email,
            "ship_to_phone" => $order->billing_phone,
            "ship_to_country" => $order->shipping_country,
            "ship_to_state" => $order->shipping_state,
            "ship_to_city" => $order->shipping_city,
            "ship_to_street" => $order->shipping_address_1.' '.$order->shipping_address_2,
            "ship_to_zip" => $order->shipping_postcode,
            "extra_return_param" => "",
            "client_ip" => $client_ip,
			"order_button_text" => $this->order_button_text,
			"qrcode_path" => plugins_url('lib/qrcodes', __FILE__ ),
        );
		
		$_SESSION['WCDDBillWQCPrimaryPayload'] = $primaryPayload;
		
		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Output for the order received page.
	 *
	 * @access public
	 * @param object $order
	 * @return void
	 */
	public function receipt_page( $order ){
		
		if( 'yes' == $this->debug ){
			$this->log->add( 'ddbillwqc', 'Process Payment order form in POST method.');
		}

		$this->generate_ddbillwqc_form( $order );
	}

	public function generate_ddbillwqc_form( $order ){

		if( isset($_POST['process_ddbillwqc']) && $_POST['process_ddbillwqc'] == 1 )
		{
			if( isset($_SESSION['WCDDBillWQCPrimaryPayload']) && !empty($_SESSION['WCDDBillWQCPrimaryPayload']) )
			{
				$primaryPayload = $_SESSION['WCDDBillWQCPrimaryPayload'];
				$return = DDBillWQCPay($primaryPayload);
				if( $return['status'] == true )
				{
					echo $return['html'];
					unset($_SESSION['WCDDBillWQCPrimaryPayload']);
					WC()->cart->empty_cart();
				}
				else
				{
					echo $return['html'];
				}
			}
			return true;
		}
		
		if( isset($_SESSION['WCDDBillWQCPrimaryPayload']) && !empty($_SESSION['WCDDBillWQCPrimaryPayload']) )
		{
			$primaryPayload = $_SESSION['WCDDBillWQCPrimaryPayload'];
			echo '<form name="dinpayForm" id="dinpayForm" method="post" action=""><input type="hidden" name="process_ddbillwqc" value="1" /><input type="submit" name="processpayment" value="'.$primaryPayload['order_button_text'].'" /></form>';
			return true;
		}
    }
	
	function processInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return strval($data);
    }

	function check_ipn_response() {
		
		if ( 'yes' == $this->debug ) {
			$this->log->add( 'ddbillwqc', 'IPN Post Data: '.date('Y-m-d H:i:s', time()).' :' . json_encode($_POST) );
		}
		
		$_POST['public_key'] = $this->public_key;
		$_POST['private_key'] = $this->private_key;
		
		$return = DDBillWQCPayNotify( $_POST );
		if( $return )
		{
			$order_id = $_POST['order_no'];
			$order = new WC_Order( $order_id );
			$order->add_order_note('Payment has been completed via IPN.');
			$order->reduce_order_stock();
			$order->payment_complete();

			echo "SUCCESS";
			exit;
		}
		else
		{
			echo "FAIL";
			exit;
		}
	}

	function convertCurrency($amount, $from, $to){
		
		$yql_url = "http://query.yahooapis.com/v1/public/yql";
		$yql_qry = 'select * from yahoo.finance.xchange where pair in ("'.$from.$to.'")';
		$yql_url = $yql_url . "?q=" . urlencode($yql_qry);
		$yql_url .= "&format=json&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys";
		$yql_ses = @file_get_contents($yql_url);
		$yql_jsn =  @json_decode($yql_ses, true);

		$converted = 0;
		if( isset($yql_jsn['query']['results']['rate']['Rate']) )
			$converted = (float)$amount*$yql_jsn['query']['results']['rate']['Rate'];
		$converted = round($converted, 2);
		
		if( $converted == 0 ) return $amount;
		else return $converted;
	}
			
} //End of Clsss...
} //End of function...

function add_ddbillwqc_gateway_class( $methods ){
	$methods[] = 'WC_Gateway_DDBillWQC'; 
	return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'add_ddbillwqc_gateway_class' );

function add_ddbillwqc_woocommerce_thankyou_order_id( $absint ){ 
	if( isset($_SESSION['WCDDBillWQCPrimaryPayload']) )
		unset($_SESSION['WCDDBillWQCPrimaryPayload']);
		
    return $absint; 
}; 
         
add_filter( 'woocommerce_thankyou_order_id', 'add_ddbillwqc_woocommerce_thankyou_order_id', 10, 1 );
?>