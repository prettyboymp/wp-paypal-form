<?php

/**
 * Interface to generate a PayPal Rest API Authentication Token
 */
class WP_PayPal_Client_Credential {

	private static $expireTimeBuffer = 120;
	private $id;
	private $secret;
	private $token;
	private $expiresAt;

	/**
	 * 
	 * @param string $id
	 * @param string $secret
	 */
	public function __construct( $id, $secret ) {
		$this->id = $id;
		$this->secret = $secret;
		$token_data = false; //get_transient( 'paypal_form_access_token' );
		if ( is_array( $token_data ) && isset( $token_data['token'] ) &&
			isset( $token_data['id'] ) && $token_data['id'] === $this->id &&
			isset( $token_data['secret'] ) && $token_data['secret'] === $this->secret &&
			isset( $token_data['expiresAt'] )
		) {
			$this->token = $token_data['token'];
			$this->expiresAt = $token_data['expiresAt'];
		}
	}

	/**
	 * Returns an access token for the set credentials
	 * @return string|\WP_Error on error
	 */
	public function getAccessToken() {
		if ( !$this->token || $this->expiresAt < time() ) {
			$headers = array(
				'Accept' => 'application/json',
				'Accept-Language' => 'en_US',
				'Authorization' => 'Basic ' . base64_encode( $this->id . ':' . $this->secret ),
				'Content-Type' => 'application/x-www-form-urlencoded'
			);
			$data = 'grant_type=client_credentials';
			$path = '/v1/oauth2/token';
			$rest = new WP_PayPal_Rest_Interface();
			$response = $rest->post( $path, $data, $headers );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$jsonResponse = json_decode( wp_remote_retrieve_body( $response ) );
			if ( is_object( $jsonResponse ) && isset( $jsonResponse->access_token ) && isset( $jsonResponse->expires_in ) ) {
				$this->token = $jsonResponse->access_token;
				$this->expiresAt = time() + $jsonResponse->expires_in - self::$expireTimeBuffer;
				//store token as transient
				$token_data = array(
					'id' => $this->id,
					'secret' => $this->secret,
					'token' => $this->token,
					'expiresAt' => $this->expiresAt
				);
				set_transient( paypal_form_access_token, $token_data, $jsonResponse->expires_in - self::$expireTimeBuffer );
			} else {
				$this->token = null;
				$this->expiresAt = null;
				if ( isset( $jsonResponse->error ) && isset( $jsonResponse->error_description ) ) {
					return new WP_Error( $jsonResponse->error, $jsonResponse->error_description );
				} else {
					return new WP_Error( 'invalid_response', 'Invalid response from server while generating access token.' );
				}
			}
		}
		return $this->token;
	}
}