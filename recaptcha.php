<?php

class WP_PayPal_Recaptcha_Handler {

	private $public_key;
	private $private_key;

	public function __construct( $public_key, $private_key ) {
		$this->public_key = $public_key;
		$this->private_key = $private_key;
		require_once(__DIR__ . '/lib/recaptcha/recaptcha.php');
		add_action( 'wp_paypal_form_bottom', array( $this, 'print_recaptcha' ) );
		add_action( 'validate_paypal_form', array( $this, 'check_recaptcha' ) );
	}

	public function print_recaptcha( $paypalForm ) {
		echo recaptcha_get_html( $this->public_key, null, is_ssl() );
	}

	/**
	 * 
	 * @param WP_Error $err
	 */
	public function check_recaptcha( &$err ) {
		$resp = recaptcha_check_answer( $this->private_key, $_SERVER["REMOTE_ADDR"], $_POST["recaptcha_challenge_field"], $_POST["recaptcha_response_field"] );
		if(!$resp->is_valid) {
			$err->add('recaptcha_response_field', "Missing or Invalid 'ReCaptcha' input.");
		}
	}

}
