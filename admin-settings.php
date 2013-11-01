<?php

/**
 * Wrapper class (fake namespace) for admin functions.
 */
class WP_PayPal_Form_Admin {

	public static function register_settings() {
		// register settings
		Voce_Settings_API::GetInstance()
			->add_page( 'PayPal Form', 'Settings', 'wp-paypal-form', 'manage_options', '', 'edit.php?post_type=paypal_transaction' )
				->add_group( 'PayPal Application Settings', 'paypal-client-app', null, 'In order to connect to PayPal to process payments, you will need to create a PayPal application.  ' .
					'Once created, PayPal will give you a Client ID and Secret for the application that WordPress can use ' .
					'to authenticate with PayPal.  To learn more, reade about ' .
					'<a href="https://developer.paypal.com/webapps/developer/docs/integration/admin/manage-apps/">managing your applications</a>.' )
					->add_setting( 'Client ID', 'id', array(
						'display_callback' => array( __CLASS__, '_display_large_text' ),
						'description' => '<br />Example: AWh_7Njg4UUf8472s9Wt_wRmXEDUDzWa4E9P3Kah2sBxCqpZb284tWV36Pzt'
						) )
				->group
					->add_setting( 'Secret', 'secret', array(
					'display_callback' => array( __CLASS__, '_display_large_text' ),
					'sanitize_callbacks' => array( array( __CLASS__, '_validate_application_secret' ) ),
					'description' => '<br />Example: BvQLuCJD76YAVcbVFr6xPn99vFvr88rjKYfFzsDgBDt3EHAFV4PBNq9sVnbD'
					) )
				->group
			->page
				->add_group( 'Payment Form', 'paypal-cc-form', null, 'Setup the handling for the form generated by the PayPal Form Shortcode.' )
					->add_setting( 'Transaction Amount', 'amount', array(
						'display_callback' => array( __CLASS__, '_display_dollar' ),
						'sanitize_callbacks' => array(
							array( __CLASS__, '_sanitize_dollar' )
						) ) )
				->group
					->add_setting( 'Transaction Description', 'description', array(
						'description' => '<br />The description used on the PayPal transaction.'
					) )
				->group
					->add_setting( 'Confirmation Page', 'confirmation_page', array(
						'display_callback' => array( __CLASS__, '_display_page' ),
						'sanitize_callbacks' => array( array( __CLASS__, '_sanitize_page' ) ),
						'description' => '<br />Choose a page to redirect the user to after the transaction has processed. '
					) )
				->group
					->add_setting( 'Confirmation Email Text', 'email_text', array(
						'display_callback' => 'vs_display_textarea',
						'description' => '<br />Enter the confirmation text for the email that should be sent to the user after their payment ' .
						'has been processed.'
					) )
				->group
					->add_setting('ReCaptcha Public Key', 'recaptcha_public_key', array(
						'description' => '<br />Enter a <a href="https://www.google.com/recaptcha/admin/create">ReCaptcha Keys</a> to enable ReCaptcha on the PayPal form.'
					))
				->group
					->add_setting('ReCaptcha Private Key', 'recaptcha_private_key', array(
					));
	}

	public static function _display_large_text( $value, $setting, $args ) {
		?>
		<input name="<?php echo $setting->get_field_name() ?>" id="<?php echo $setting->get_field_id() ?>" value="<?php echo esc_attr( $value ) ?>" class="large-text" type="text">
		<?php if ( !empty( $args['description'] ) ) : ?>
			<span class="description"><?php echo $args['description'] ?></span>
			<?php
		endif;
	}

	public static function _validate_application_secret( $value, $setting, $args ) {
		$secret = $value;
		$client_id = Voce_Settings_API::GetInstance()->get_setting( 'id', 'paypal-client-app' );
		$auth = new WP_PayPal_Client_Credential( $client_id, $secret );
		$token = $auth->getAccessToken();
		if ( is_wp_error( $token ) ) {
			$setting->add_error( sprintf( 'Unable to validate the application credentials: ' . $token->get_error_message() ) );
		}
		return $value;
	}

	public static function _display_dollar( $value, $setting, $args ) {
		if ( !$value )
			$value = 0.00;
		?>
		$<input name="<?php echo $setting->get_field_name() ?>" id="<?php echo $setting->get_field_id() ?>" value="<?php echo number_format( floatval( $value ), 2 ) ?>" class="small-text" type="text">
		<?php if ( !empty( $args['description'] ) ) : ?>
			<span class="description"><?php echo $args['description'] ?></span>
			<?php
		endif;
	}

	public static function _sanitize_dollar( $value, $setting, $args ) {
		$value = ltrim( $value, '$' );
		$value = round( floatval( $value ), 2 );
		if ( $value <= 0.0 ) {
			$setting->add_error( sprintf( 'The %s must be a positive dollar amount.', $setting->title ) );
			return null;
		}
		return $value;
	}

	public static function _display_page( $value, $setting, $args ) {
		wp_dropdown_pages( array(
			'name' => $setting->get_field_name(),
			'id' => $setting->get_field_id(),
			'selected' => $value
		) );
		if ( !empty( $args['description'] ) ) {
			?>
			<span class="description"><?php echo $args['description'] ?></span>
			<?php
		}
	}

	public static function _sanitize_page( $value, $setting, $args ) {
		return intval( $value );
	}

}