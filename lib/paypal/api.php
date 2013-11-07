<?php

class WP_PayPal_API {

	/**
	 *
	 * @var WP_PayPal_Client_Credential
	 */
	private $credentialHandler;

	/**
	 * 
	 * @param WP_PayPal_Client_Credential $credentialHandler
	 */
	public function __construct( $credentialHandler ) {
		$this->credentialHandler = $credentialHandler;
	}

	public function sendJSON( $path, $payload, $headers = array( ), $method = 'POST' ) {
		$headers = wp_parse_args( $headers, array(
			'Content-Type' => 'application/json',
			) );
		return $this->send( $path, json_encode( $payload ), $headers, $method );
	}

	public function send( $path, $payload, $headers = array( ), $method = 'POST' ) {
		$rest = new WP_PayPal_Rest_Interface();
		$access_token = $this->credentialHandler->getAccessToken();
		
		if(  is_wp_error( $access_token )) {
			return $access_token;
		}
		$headers = wp_parse_args( $headers, array(
			'Accept' => 'application/json',
			'Accept-Language' => 'en_US',
			'Authorization' => 'Bearer ' .$this->credentialHandler->getAccessToken()
			) );
		return $rest->makeRequest( $path, $payload, $headers, $method );
	}

	public function sendPayment( $paymentObj ) {
		return $this->sendJSON( '/v1/payments/payment', $paymentObj );
	}

}