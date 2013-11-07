<?php
/*
  Plugin Name: WP PayPal Form
  Version 0.1
  Plugin URI: http://example.com/
  Description: Allows embedding of a payment submission form to collect payments via PayPal
  Author: Michael Pretty (prettyboymp)
  Author URI:  http://github.com/prettyboymp
 */

include dirname( __FILE__ ) . '/payment-storage.php';
include dirname( __FILE__ ) . '/recaptcha.php';
include dirname( __FILE__ ) . '/lib/voce-settings-api/voce-settings-api.php';
include dirname( __FILE__ ) . '/lib/paypal/auth.php';
include dirname( __FILE__ ) . '/lib/paypal/rest-interface.php';
include dirname( __FILE__ ) . '/lib/paypal/api.php';

class WP_PayPal_Form {

	/**
	 * Error stored after processing an invalid form submission.
	 * @var WP_Error
	 */
	private $submission_error;
	private $payment_id;

	public function __construct() {
		$this->payment_id = '';
	}

	public function init() {
		if ( is_admin() ) {
			require_once dirname( __FILE__ ) . '/admin-settings.php';
			WP_PayPal_Form_Admin::register_settings();
		}

		WP_PayPal_Payment_Post_Type::init();

		if ( isset( $_POST['submit_paypal'] ) ) {
			add_action( 'wp_loaded', array( $this, '_process_form_submission' ) );
		}

		add_shortcode( 'paypal_form', array( $this, '_shortcode_handler' ) );

		wp_register_script( 'parsley', plugins_url( 'js/parsley.min.js', __FILE__ ), array( 'jquery' ), '1.1.18', true );
		$settings_api = Voce_Settings_API::GetInstance();
		if ( ($recaptcha_public_key = $settings_api->get_setting( 'recaptcha_public_key', 'paypal-cc-form' )) && ($recaptcha_private_key = $settings_api->get_setting( 'recaptcha_private_key', 'paypal-cc-form' ) ) ) {
			new WP_PayPal_Recaptcha_Handler( $recaptcha_public_key, $recaptcha_private_key );
		}
	}

	public function _shortcode_handler( $atts, $content = null ) {
		ob_start();
		$requested_url = is_ssl() ? 'https://' : 'http://';
		$requested_url .= $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		if ( !is_ssl() ) {
			?>
			<p class="error">Warning, Credit Card information should only be sent across secure connections!</p>
			<?php
		}
		if ( is_wp_error( $this->submission_error ) && count( $this->submission_error->get_error_codes() ) ) {
			printf( '<ul class="error">' );
			foreach ( $this->submission_error->get_error_codes() as $code ) {
				printf( '<li><a href="#%1$s" onClick="document.getElementById(\'%1$s\').focus(); return false;">%2$s</a></li>', esc_attr( $code ), esc_html( $this->submission_error->get_error_message( $code ) ) );
			}
			print('</ul>' );
		}
		?>
		<form method="post" action="<?php echo esc_url( $requested_url ) ?>" class="paypal-form" novalidate>
			<fieldset>
				<legend>Contact Information</legend>

				<p>
					<label for="first_name">First Name * :</label>
					<input type="text" id="first_name" name="first_name" required="required" data-trigger="change" value="<?php echo esc_attr( $_POST['first_name'] ) ?>" />
				</p>

				<p>
					<label for="last_name">Last Name * :</label>
					<input type="text" id="last_name" name="last_name" required="required" data-trigger="change" value="<?php echo esc_attr( $_POST['last_name'] ) ?>" />
				</p>

				<p>
					<label for="email">Email Address * :</label>
					<input type="email" id="email" name="email" required="required" data-trigger="change" value="<?php echo esc_attr( $_POST['email'] ) ?>" />
				</p>

				<p>
					<label for="phonenumber">Phone Number * :</label>
					<input type="text" id="phonenumber" name="phonenumber"  required="required" data-type="phone" placeholder="(XXX) XXX XXXX" data-trigger="change" value="<?php echo esc_attr( $_POST['phonenumber'] ) ?>" />
				</p>
			</fieldset>

			<fieldset>
				<legend>Credit Card Information</legend>
				<p>
					<label for="cc_number">Card Number * :</label>
					<input type="text" id="cc_number" name="cc_number" required="required" data-luhn="true" data-trigger="blur" autocomplete="off" />
				</p>

				<p>
					<label for="cc_type">Card Type * :</label>
					<select id="cc_type" name="cc_type" required="required">
						<option value=""></option>
						<option value="visa">Visa</option>
						<option value="mastercard">MasterCard</option>
						<option value="discover">Discover</option>
						<option value="amex">American Express</option>
					</select>
				</p>
				<p>
					<label for="cc_expire_month">Expiration Date * :</label>
					<select id="cc_expire_month" name="cc_expire_month" required="required" data-trigger="change">
						<option value="">MM</option>
						<option value="01">01</option>
						<option value="02">02</option>
						<option value="03">03</option>
						<option value="04">04</option>
						<option value="05">05</option>
						<option value="06">06</option>
						<option value="07">07</option>
						<option value="08">08</option>
						<option value="09">09</option>
						<option value="10">10</option>
						<option value="11">11</option>
						<option value="12">12</option>
					</select>
					/
					<select id="cc_expire_year" name="cc_expire_year" required="required" data-trigger="change">
						<option value="">YYYY</option>
						<?php
						$year = date( 'Y' );
						for ( $i = $year; $i < $year + 15; $i++ ) {
							printf( '<option value="%1$d">%1$d</option>', $i );
						}
						?>
					</select>
				</p>

				<p>
					<label for="cc_cvv2">cvv2 * :</label>
					<input type="text" id="cc_cvv2" name="cc_cvv2" required="required" data-trigger="change" autocomplete="off" />
				</p>
			</fieldset>

			<fieldset>
				<legend>Billing Information</legend>
				<p>
					<label for="cc_first_name">First Name *:</label>
					<input type="text" id="cc_first_name" name="cc_first_name" required="required" data-trigger="change" value="<?php echo esc_attr( $_POST['cc_first_name'] ) ?>" />
				</p>

				<p>
					<label for="cc_last_name">Last Name *:</label>
					<input type="text" id="cc_last_name" name="cc_last_name" required="required" data-trigger="change" value="<?php echo esc_attr( $_POST['cc_last_name'] ) ?>" />
				</p>

				<p>
					<label for="address1">Address Line 1 * :</label>
					<input type="text" id="address1" name="address1" reuquired="required" data-trigger="change" value="<?php echo esc_attr( $_POST['address1'] ) ?>" />
				</p>

				<p>
					<label for="address2">Address Line 2 :</label>
					<input type="text" id="address2" name="address2" value="<?php echo esc_attr( $_POST['address2'] ) ?>" />
				</p>

				<p>
					<label for="city">City/State :</label>
					<input type="text" id="city" name="city" required="required" data-trigger="change" value="<?php echo esc_attr( $_POST['city'] ) ?>" />
					<select id="state" name="state" require="required" data-trigger="change">
						<option value=""></option>
						<option value="AA"<?php selected( $_POST['state'], 'AA' ) ?>>AA</option>
						<option value="AE"<?php selected( $_POST['state'], 'AE' ) ?>>AE</option>
						<option value="AK"<?php selected( $_POST['state'], 'AK' ) ?>>AK</option>
						<option value="AL"<?php selected( $_POST['state'], 'AL' ) ?>>AL</option>
						<option value="AP"<?php selected( $_POST['state'], 'AP' ) ?>>AP</option>
						<option value="AR"<?php selected( $_POST['state'], 'AR' ) ?>>AR</option>
						<option value="AS"<?php selected( $_POST['state'], 'AS' ) ?>>AS</option>
						<option value="AZ"<?php selected( $_POST['state'], 'AZ' ) ?>>AZ</option>
						<option value="CA"<?php selected( $_POST['state'], 'CA' ) ?>>CA</option>
						<option value="CO"<?php selected( $_POST['state'], 'CO' ) ?>>CO</option>
						<option value="CT"<?php selected( $_POST['state'], 'CT' ) ?>>CT</option>
						<option value="DC"<?php selected( $_POST['state'], 'DC' ) ?>>DC</option>
						<option value="DE"<?php selected( $_POST['state'], 'DE' ) ?>>DE</option>
						<option value="FL"<?php selected( $_POST['state'], 'FL' ) ?>>FL</option>
						<option value="FM"<?php selected( $_POST['state'], 'FM' ) ?>>FM</option>
						<option value="GA"<?php selected( $_POST['state'], 'GA' ) ?>>GA</option>
						<option value="GU"<?php selected( $_POST['state'], 'GU' ) ?>>GU</option>
						<option value="HI"<?php selected( $_POST['state'], 'HI' ) ?>>HI</option>
						<option value="IA"<?php selected( $_POST['state'], 'IA' ) ?>>IA</option>
						<option value="ID"<?php selected( $_POST['state'], 'ID' ) ?>>ID</option>
						<option value="IL"<?php selected( $_POST['state'], 'IL' ) ?>>IL</option>
						<option value="IN"<?php selected( $_POST['state'], 'IN' ) ?>>IN</option>
						<option value="KS"<?php selected( $_POST['state'], 'KS' ) ?>>KS</option>
						<option value="KY"<?php selected( $_POST['state'], 'KY' ) ?>>KY</option>
						<option value="LA"<?php selected( $_POST['state'], 'LA' ) ?>>LA</option>
						<option value="MA"<?php selected( $_POST['state'], 'MA' ) ?>>MA</option>
						<option value="MD"<?php selected( $_POST['state'], 'MD' ) ?>>MD</option>
						<option value="ME"<?php selected( $_POST['state'], 'ME' ) ?>>ME</option>
						<option value="MH"<?php selected( $_POST['state'], 'MH' ) ?>>MH</option>
						<option value="MI"<?php selected( $_POST['state'], 'MI' ) ?>>MI</option>
						<option value="MN"<?php selected( $_POST['state'], 'MN' ) ?>>MN</option>
						<option value="MO"<?php selected( $_POST['state'], 'MO' ) ?>>MO</option>
						<option value="MP"<?php selected( $_POST['state'], 'M' ) ?>>MP</option>
						<option value="MS"<?php selected( $_POST['state'], 'MS' ) ?>>MS</option>
						<option value="MT"<?php selected( $_POST['state'], 'MI' ) ?>>MT</option>
						<option value="NC"<?php selected( $_POST['state'], 'NC' ) ?>>NC</option>
						<option value="ND"<?php selected( $_POST['state'], 'ND' ) ?>>ND</option>
						<option value="NE"<?php selected( $_POST['state'], 'NE' ) ?>>NE</option>
						<option value="NH"<?php selected( $_POST['state'], 'NH' ) ?>>NH</option>
						<option value="NJ"<?php selected( $_POST['state'], 'NJ' ) ?>>NJ</option>
						<option value="NM"<?php selected( $_POST['state'], 'NM' ) ?>>NM</option>
						<option value="NV"<?php selected( $_POST['state'], 'NV' ) ?>>NV</option>
						<option value="NY"<?php selected( $_POST['state'], 'NY' ) ?>>NY</option>
						<option value="OH"<?php selected( $_POST['state'], 'OH' ) ?>>OH</option>
						<option value="OK"<?php selected( $_POST['state'], 'OK' ) ?>>OK</option>
						<option value="OR"<?php selected( $_POST['state'], 'OR' ) ?>>OR</option>
						<option value="PA"<?php selected( $_POST['state'], 'PA' ) ?>>PA</option>
						<option value="PR"<?php selected( $_POST['state'], 'PR' ) ?>>PR</option>
						<option value="PW"<?php selected( $_POST['state'], 'PW' ) ?>>PW</option>
						<option value="RI"<?php selected( $_POST['state'], 'RI' ) ?>>RI</option>
						<option value="SC"<?php selected( $_POST['state'], 'SC' ) ?>>SC</option>
						<option value="SD"<?php selected( $_POST['state'], 'SD' ) ?>>SD</option>
						<option value="TN"<?php selected( $_POST['state'], 'TN' ) ?>>TN</option>
						<option value="TX"<?php selected( $_POST['state'], 'TX' ) ?>>TX</option>
						<option value="UT"<?php selected( $_POST['state'], 'UT' ) ?>>UT</option>
						<option value="VA"<?php selected( $_POST['state'], 'VA' ) ?>>VA</option>
						<option value="VI"<?php selected( $_POST['state'], 'VI' ) ?>>VI</option>
						<option value="VT"<?php selected( $_POST['state'], 'VT' ) ?>>VT</option>
						<option value="WA"<?php selected( $_POST['state'], 'WA' ) ?>>WA</option>
						<option value="WI"<?php selected( $_POST['state'], 'WI' ) ?>>WI</option>
						<option value="WV"<?php selected( $_POST['state'], 'WV' ) ?>>WV</option>
						<option value="WY"<?php selected( $_POST['state'], 'WY' ) ?>>WY</option>
					</select>
				</p>

				<p>
					<label for="zip">Zip Code * :</label>
					<input type="text" id="zip" name="zip" pattern="(\d{5}([\-]\d{4})?)" data-trigger="change" value="<?php echo esc_attr( $_POST['zip'] ) ?>" />
				</p>

			</fieldset>

			<?php do_action( 'wp_paypal_form_bottom', $this ); ?>

			<input type="hidden" name="payment_id" value="<?php echo esc_attr( $this->payment_id ) ?>" />
			<input type="hidden" name="payment_nonce" value="<?php wp_create_nonce( "paypal_payment_{$this->payment_id}" ) ?>" />

			<p>Click Pay to complete your purchase. Please review your information to make sure that it is correct.</p>
			<input type="submit" id="submit" name="submit_paypal" value="Pay" />
		</form>
		<?php
		wp_enqueue_script( 'parsley' );
		add_action( 'wp_print_footer_scripts', array( $this, '_print_footer_js' ) );
		return ob_get_clean();
	}

	public function _print_footer_js() {
		?>
		<script type="text/javascript">
			(function($) {
				var formSubmitted = false;
				$(document).ready(function() {
					$('.paypal-form').parsley({
						listeners: {
							onFormSubmit: function(isValid, event) {
								if (formSubmitted) {
									event.preventDefault();
								} else if (isValid) {
									formSubmitted = true;
								}
							}
						}
					});
					$('#cc_number').on('blur', function(e) {
						var cc_num = $(this).val().replace(/[^0-9]/g, ''),
								$cc_type = $('#cc_type'),
								type = '',
								cc_sub;

						$(this).val(cc_num);
						if ($cc_type.val() == '' && cc_num.length > 13 && cc_num.length < 17) {
							cc_sub = parseInt(cc_num.substring(0, 2));
							if (cc_sub == 34 || cc_sub == 37) {
								type = 'amex';
							} else if (cc_sub >= 51 && cc_sub <= 55) {
								type = 'mastercard';
							} else if (cc_num.substring(0, 4) == '6011') {
								type = 'discover';
							} else if (cc_num.substring(0, 1) == '4') {
								type = 'visa';
							}
							$cc_type.val(type);
						}
					});
				});
			})(jQuery);
		</script>
		<?php
	}

	public function _process_form_submission() {
		if ( !isset( $_POST['submit_paypal'] ) ) {
			return;
		}

		//check/validate a payment id if this is a repeat submission
		if ( !empty( $_POST['payment_id'] ) && !empty( $_POST['payment_nonce'] ) && wp_verify_nonce( $_POST['payment_nonce'], "paypal_payment_{$_POST['payment_id']}" ) ) {
			$this->payment_id = $_POST['payment_id'];
		}

		$err = new WP_Error();

		//filtered $_POST extraction
		$form_vars = array(
			'first_name', 'last_name', 'email', 'phonenumber', 'cc_number', 'cc_type',
			'cc_expire_month', 'cc_expire_year', 'cc_cvv2', 'cc_first_name',
			'cc_last_name', 'address1', 'address2', 'city', 'state',
			'zip'
		);
		foreach ( $form_vars as $var ) {
			$$var = isset( $_POST[$var] ) ? trim( $_POST[$var] ) : '';
		}

		if ( empty( $first_name ) ) {
			$err->add( 'first_name', "'First Name' is required." );
		}

		if ( empty( $last_name ) ) {
			$err->add( 'last_name', "'Last Name' is required." );
		}

		if ( empty( $email ) ) {
			$err->add( 'email', "'Email Address' is required." );
		} elseif ( !is_email( $email ) ) {
			$err->add( 'email', "The given 'Email Address' is invalid." );
		}

		$phonePattern = "/^(?:(?:\((?=\d{3}\)))?(\d{3})(?:(?<=\(\d{3})\))
			?[\s.\/-]?)?(\d{3})[\s\.\/-]?(\d{4})\s?(?:(?:(?:(?:e|x|ex|ext)\.?|extension)\s?)(?=\d+)(\d+))?$/x";
		if ( empty( $phonenumber ) ) {
			$err->add( 'phonenumber', "'Phone Number' is required." );
		} elseif ( !preg_match( $phonePattern, $phonenumber ) ) {
			$err->add( 'phonenumber', "The given 'Phone Number' is invalid." );
		}


		$cc_number = preg_replace( '/[^0-9]/', '', $cc_number );
		if ( empty( $cc_number ) ) {
			$err->add( 'cc_number', "'Card Number' is required." );
		} elseif ( strlen( $cc_number ) < 13 || strlen( $cc_number ) > 16 || !passesLuhnTest( $cc_number ) ) {
			$err->add( 'cc_number', "The 'Card Number' is invalid." );
		}

		if ( !in_array( $cc_type, array( 'visa', 'mastercard', 'amex', 'discover' ) ) ) {
			$err->add( 'cc_type', "'Card Type' is required." );
		}


		if ( empty( $cc_expire_month ) ) {
			$err->add( 'cc_expire_month', "'Expiration Month' is required." );
		}

		if ( empty( $cc_expire_year ) ) {
			$err->add( 'cc_expire_year', "'Expiration Year' is required." );
		}

		if ( !empty( $cc_expire_month ) && !empty( $cc_expire_year ) && ($cc_expire_year . $cc_expire_month < date( 'Ym' )) ) {
			$err->add( 'cc_expire_month', "Please select a valid 'Expiration Date.'" );
		}

		if ( empty( $cc_cvv2 ) ) {
			$err->add( 'cc_cvv2', "'ccv2' is required." );
		}

		if ( empty( $cc_first_name ) ) {
			$err->add( 'cc_first_name', "'Billing First Name' is required." );
		}

		if ( empty( $cc_last_name ) ) {
			$err->add( 'cc_last_name', "'Billing Last Name' is required." );
		}

		if ( empty( $address1 ) ) {
			$err->add( 'address1', "'Address Line 1' is required." );
		}

		if ( empty( $city ) ) {
			$err->add( 'city', "'City' is required." );
		}

		if ( empty( $state ) ) {
			$err->add( 'state', "'State' is required." );
		}

		if ( empty( $zip ) ) {
			$err->add( 'zip', "'Zip Code' is required." );
		} elseif ( !preg_match( '/(\d{5}([\-]\d{4})?)/', $zip ) ) {
			$err->add( 'zip', "The 'Zip Code' is invalid." );
		}

		do_action_ref_array( 'validate_paypal_form', array( &$err ) );

		if ( 0 === count( $err->get_error_codes() ) ) {
			$settings_api = Voce_Settings_API::GetInstance();
			$tran_amount = round( floatval( $settings_api->get_setting( 'amount', 'paypal-cc-form' ) ), 2 );
			$tran_desc = $settings_api->get_setting( 'description', 'paypal-cc-form' );

			//build payment object
			$payment = ( object ) array(
					'intent' => 'sale',
					'payer' => array(
						'payment_method' => 'credit_card',
						'funding_instruments' => array(
							array(
								"credit_card" => array(
									'number' => $cc_number,
									'type' => $cc_type,
									'expire_month' => $cc_expire_month,
									'expire_year' => $cc_expire_year,
									'cvv2' => $cc_cvv2,
									'first_name' => $cc_first_name,
									'last_name' => $cc_last_name,
									'billing_address' => array(
										'line1' => $address1,
										'line2' => $address2,
										'city' => $city,
										'country_code' => 'US',
										'postal_code' => $zip,
										'state' => $state,
										'phone' => $phonenumber
									)
								)
							)
						)
					),
					'transactions' => array(
						array(
							'amount' => array(
								'total' => $tran_amount,
								'currency' => 'USD'
							),
							'description' => $tran_desc
						),
					)
			);

			//save a copy in the db;
			$userData = array(
				'first_name' => $first_name,
				'last_name' => $last_name,
				'email' => $email,
				'phonenumber' => $phonenumber
			);
			$process_error = null;
			$this->payment_id = WP_PayPal_Payment_Post_Type::insert_payment( $userData, $payment, $process_error, $this->payment_id );

			$client_id = $settings_api->get_setting( 'id', 'paypal-client-app' );
			$secret = $settings_api->get_setting( 'secret', 'paypal-client-app' );
			$credentialHandler = new WP_PayPal_Client_Credential( $client_id, $secret );
			$api = new WP_PayPal_API( $credentialHandler );

			$response = $api->sendPayment( $payment );
			if ( is_wp_error( $response ) ) {
				$err->add( 'first_name', 'There was an error while processing the request.  Please try again later.' );
				$process_error = 'Error during payment submission: ' . $response->get_error_message();
				$payment->state = 'failed';
			} else {
				$body = wp_remote_retrieve_body( $response );
				$responseObj = json_decode( $body );
				if ( !$responseObj ) {
					$err->add( 'first_name', 'There was an error while processing the request.  Please try again later.' );
					$process_error = 'Error during payment submission: Couldn\'t parse response';
					$payment->state = 'failed';
				} elseif ( !empty( $responseObj->message ) ) {
					$err->add( 'first_name', 'There was an error while processing the request.  Please try again later.' );
					$process_error = 'Error during payment submission: ' . $responseObj->message;
					$payment->state = 'failed';
				} elseif ( $responseObj->state !== 'approved' ) {
					$err->add( 'first_name', 'There was an error while processing the request.  Please try again later.' );
					$process_error = 'Error during payment submission.  Please try again later.';
				} else {
					//payment processed correctly, update the payment object with the response
					$payment = $responseObj;
				}
			}

			//update the stored state
			$this->payment_id = WP_PayPal_Payment_Post_Type::insert_payment( $userData, $payment, $process_error, $this->payment_id );

			if ( !$process_error ) {
				//send confirmation email
				$headers = array( );
				$bcc = $settings_api->get_setting( 'email_bcc', 'paypal-cc-form' );
				if ( !empty( $bcc ) ) {
					$headers['from'] = $bcc;
					$headers['bcc'] = $bcc;
				}

				$subject = $settings_api->get_setting( 'email_subject', 'paypal-cc-form' );
				$replace = array(
					'%firstname%' => $first_name,
					'%lastname%' => $last_name,
					'%paymentid%' => $payment->id,
					'%refnumber%' => $this->payment_id
				);
				$subject = str_replace( array_keys( $replace ), array_values( $replace ), $subject );

				$message = $settings_api->get_setting( 'email_text', 'paypal-cc-form' );
				$replace['%paymentinfo%'] = <<<PAYMENTINFO
Purchaser: {$first_name} {$last_name}
Payment ID: {$payment->id}
Reference #: {$this->payment_id}
Order Total: \${$tran_amount}

Billing Information:
$cc_first_name $cc_last_name
$address1
$address2
$city, $state  $zip
PAYMENTINFO;

				$message = str_replace( array_keys( $replace ), array_values( $replace ), $message );

				wp_mail( $email, $subject, $message, $headers );

				do_action( 'paypal_payment_approved', $payment, $this->payment_id );

				//redirect to confirmation
				$confirmation_page = $settings_api->get_setting( 'confirmation_page', 'paypal-cc-form' );
				wp_redirect( get_permalink( $confirmation_page ) );
				die();
			}
		}
		$this->submission_error = $err;
	}

}

add_action( 'init', array( new WP_PayPal_Form, 'init' ) );


if ( !function_exists( 'passesLuhnTest' ) ) {

	function passesLuhnTest( $number ) {
		$sum = 0;
		$odd = strlen( $number ) % 2;

		// Calculate sum of digits.
		for ( $i = 0; $i < strlen( $number ); $i++ ) {
			$sum += $odd ? $number[$i] : (($number[$i] * 2 > 9) ? $number[$i] * 2 - 9 : $number[$i] * 2);
			$odd = !$odd;
		}

		// Check validity.
		return ($sum % 10 == 0) ? true : false;
	}

}