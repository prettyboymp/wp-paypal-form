<?php

/**
 * HTTP wrapper for PayPal Rest API
 * 
 */
class WP_PayPal_Rest_Interface {

	private $endpoint;

	public function __construct() {
		if ( defined( 'PAYPAL_REST_ENDPOINT' ) ) {
			$this->endpoint = untrailingslashit(PAYPAL_REST_ENDPOINT);
		} else {
			$this->endpoint = 'https://api.sandbox.paypal.com';
		}
	}

	public function makeRequest( $path, $data = '', $headers = array( ), $method = 'POST' ) {
		$url = $this->endpoint . $path;
		
		$args = array(
			'method' => strtoupper( $method ),
			'headers' => $headers,
			'timeout' => 60,
			'body' => $data,
		);
		return wp_remote_request( $url, $args );
	}

	/**
	 * Sends a GET request to the api endpoint
	 * @param string $path The endpoint path
	 * @param array|string $data The data payload to send
	 * @param array $headers Request headers
	 * @return WP_Error|array in format of WP_HTTP response
	 */
	public function get( $path, $data, $headers = array( ) ) {
		return $this->makeRequest( $path, $data, $headers, 'GET' );
	}

	/**
	 * Sends a POST request to the api endpoint
	 * @param string $path The endpoint path
	 * @param array|string $data The data payload to send
	 * @param array $headers Request headers
	 * @return WP_Error|array in format of WP_HTTP response
	 */
	public function post( $path, $data, $headers = array( ) ) {
		return $this->makeRequest( $path, $data, $headers, 'POST' );
	}

}