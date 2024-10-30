<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Omnipay\Omnipay;

/**
 * Gateway class
 */
class WC_Braintree_Gateway_Lite extends \WC_Payment_Gateway {

	/** @var bool Whether or not logging is enabled */
	public $debug_active = false;

	/** @var WC_Logger Logger instance */
	public $log = false;

	/** @var string WC_API for the gateway - being use as return URL */
	public $returnUrl;

	function __construct() {

		// The global ID for this Payment method
		$this->id = W3GUY_BRAINTREE_LITE_WOOCOMMERCE_ID;

		// The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
		$this->method_title = __( "Braintree", 'better-braintree-for-woocommerce' );

		// The description for this Payment Gateway, shown on the actual Payment options page on the backend
		$this->method_description = __( "Braintree Payment Gateway for WooCommerce",
			'better-braintree-for-woocommerce' );

		// The title to be used for the vertical tabs that can be ordered top to bottom
		$this->title = __( "Braintree", 'better-braintree-for-woocommerce' );

		// If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
		$this->icon = apply_filters( 'omnipay_braintree_icon', W3GUY_BRAINTREE_LITE_ASSETS_URL . 'cards.png' );

		$this->supports = array();

		// This basically defines your settings which are then loaded with init_settings()
		$this->init_form_fields();

		$this->init_settings();

		$this->debug_active = true;
		$this->has_fields   = true;

		$this->description = $this->get_option( 'description' );

		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		// Save settings
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id,
				array( $this, 'process_admin_options' ) );
		}
	}

	/**
	 * Gateway settings page.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable / Disable', 'better-braintree-for-woocommerce' ),
				'label'   => __( 'Enable this payment gateway', 'better-braintree-for-woocommerce' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'environment' => array(
				'title'       => __( 'Braintree Test Mode', 'better-braintree-for-woocommerce' ),
				'label'       => __( 'Enable Test Mode', 'better-braintree-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => sprintf( __( 'Braintree sandbox can be used to test payments. Sign up for an account <a href="%s">here</a>',
					'better-braintree-for-woocommerce' ),
					'https://sandbox.braintreegateway.com' ),
				'default'     => 'no',
			),
			'title'       => array(
				'title'   => __( 'Title', 'better-braintree-for-woocommerce' ),
				'type'    => 'text',
				'default' => __( 'Credit Card', 'better-braintree-for-woocommerce' ),
			),
			'description' => array(
				'title'   => __( 'Description', 'better-braintree-for-woocommerce' ),
				'type'    => 'textarea',
				'default' => __( 'Pay securely using your credit card.', 'better-braintree-for-woocommerce' ),
				'css'     => 'max-width:350px;',
			),
			'merchant_id' => array(
				'title'       => __( 'Merchant ID', 'better-braintree-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Enter your merchant ID.', 'better-braintree-for-woocommerce' ),
			),
			'public_key'  => array(
				'title'       => __( 'Public Key', 'better-braintree-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Enter your public key.', 'better-braintree-for-woocommerce' ),
			),
			'private_key' => array(
				'title'       => __( 'Private Key', 'better-braintree-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Enter your private key.', 'better-braintree-for-woocommerce' ),
			),
		);
	}


	public function admin_options() { ?>

		<h3><?php echo ( ! empty( $this->method_title ) ) ? $this->method_title : __( 'Settings', 'woocommerce' ); ?></h3>

		<?php echo ( ! empty( $this->method_description ) ) ? wpautop( $this->method_description ) : ''; ?>

		<div id="message" class="error notice"><p>
				<?php printf(
					__(
						'Be PCI compliant with "Dropin-UI" & "Custom UI" checkout style and access to support from WooCommerce experts. <strong><a target="_blank" href="%s">Upgrade to PRO Now</a></strong>.',
						'better-braintree-for-woocommerce'
					),
					'https://omnipay.io/downloads/better-braintree-payment-gateway-for-woocommerce/'
				); ?>
			</p></div>
		<table class="form-table">
		<?php $this->generate_settings_html(); ?>
		</table><?php
	}

	/**
	 * Is gateway in test mode?
	 *
	 * @return bool
	 */
	public function is_test_mode() {
		return $this->environment == "yes";
	}

	/**
	 * @inheritdoc
	 */
	public function payment_fields() {

		if ( $description = $this->get_description() ) {
			echo wpautop( wptexturize( $description ) );
		}
		$this->credit_card_form();
	}

	/**
	 * WooCommerce payment processing function/method.
	 *
	 * @inheritdoc
	 *
	 * @param int $order_id
	 *
	 * @return mixed
	 */
	public function process_payment( $order_id ) {
		$order = new WC_Order( $order_id );

		do_action( 'omnipay_braintree_lite_before_process_payment' );

		$payment_data = array(
			'amount'   => $order->order_total,
			'options'  => array( 'submitForSettlement' => apply_filters( 'omnipay_braintree_lite_submit_for_settlement', true ) ),
			'customer' => array(
				'firstName' => $order->billing_first_name,
				'lastName'  => $order->billing_last_name,
				'email'     => $order->billing_email,
			),
			'billing'  => array(
				'firstName'         => $order->billing_first_name,
				'lastName'          => $order->billing_last_name,
				'streetAddress'     => $order->billing_address_1,
				'extendedAddress'   => $order->billing_address_2,
				'locality'          => $order->billing_city,
				'region'            => $order->billing_state,
				'postalCode'        => $order->billing_postcode,
				'countryCodeAlpha2' => $order->billing_country,
			),
		);

		$cc_data = $this->sanitize_cc_data();

		$payment_data['creditCard']['number']         = $cc_data['card-number'];
		$payment_data['creditCard']['cvv']            = $cc_data['card-cvc'];
		$payment_data['creditCard']['expirationDate'] = $cc_data['card-expiry'];

		try {
			Braintree_Configuration::environment( $this->is_test_mode() ? 'sandbox' : 'production' );
			Braintree_Configuration::merchantId( $this->merchant_id );
			Braintree_Configuration::publicKey( $this->public_key );
			Braintree_Configuration::privateKey( $this->private_key );

			$result = Braintree_Transaction::sale( $payment_data );

			if ( $result->success ) {
				$transaction_ref = $result->transaction->id;

				$order->payment_complete( $transaction_ref );

				// Add order note
				$order->add_order_note(
					sprintf( __( 'Credit card payment via Branintree completed. (Charge ID: %s)', 'better-braintree-for-woocommerce' ),
						$transaction_ref
					)
				);

				// Return thank you page redirect
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);

			} else if ( $result->transaction ) {
				$error = sprintf( __( 'Transaction Failed. %s (%)', 'better-braintree-for-woocommerce' ), $result->transaction->processorResponseText, $result->transaction->processorResponseCode );
				$order->add_order_note( sprintf( "%s Payments Failed: '%s'", $this->method_title, $error ) );
				wc_add_notice( $error, 'error' );
				$this->log( $result );

				return array(
					'result'   => 'fail',
					'redirect' => '',
				);

			} else {

				$exclude = array( 81725 ); //Credit card must include number, paymentMethodNonce, or venmoSdkPaymentMethodCode.
				foreach ( ( $result->errors->deepAll() ) as $error ) {
					if ( ! in_array( $error->code, $exclude ) ) {
						wc_add_notice( $error->message, 'error' );
					}
				}

				return array(
					'result'   => 'fail',
					'redirect' => '',
				);
			}
		} catch ( Exception $e ) {
			wc_add_notice( __( 'Unexpected error. Please try again.', 'better-braintree-for-woocommerce' ), 'error' );

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}

	}

	/**
	 * Validate and sanitize credit card information.
	 *
	 * @return array
	 */
	public function sanitize_cc_data() {

		$card_info = array(
			'card-number' => $_POST['better-braintree-for-woocommerce-card-number'],
			'card-expiry' => $_POST['better-braintree-for-woocommerce-card-expiry'],
			'card-cvc'    => $_POST['better-braintree-for-woocommerce-card-cvc'],

		);

		if ( ! empty( $card_info['card-expiry'] ) ) {
			// remove white space from card expiration date such that '11 / 16' becomes '11/16'
			$card_info['card-expiry'] = str_replace( ' ', '', $card_info['card-expiry'] );
		}

		return array_map( 'sanitize_text_field', $card_info );
	}


	/**
	 * Logger helper function.
	 *
	 * @param $message
	 */
	public function log( $message ) {
		if ( $this->debug_active ) {
			if ( ! ( $this->log ) ) {
				$this->log = new WC_Logger();
			}
			$this->log->add( 'woocommerce_braintree_lite', $message );
		}
	}
}
