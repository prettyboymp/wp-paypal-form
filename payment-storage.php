<?php

/**
 * Wrapper class/pseudo-namespace for storing transaction data.
 */
class WP_PayPal_Payment_Post_Type {

	public static function init() {
		$labels = array(
			'name' => _x( 'PayPal Payments', 'Post Type General Name' ),
			'singular_name' => _x( 'PayPal Payment', 'Post Type Singular Name' ),
			'menu_name' => __( 'PayPal' ),
			'parent_item_colon' => __( '' ),
			'all_items' => __( 'All Payments' ),
			'view_item' => __( 'View Payment' ),
			'add_new_item' => __( 'Add New Payment' ),
			'add_new' => __( 'New Payment' ),
			'edit_item' => __( 'Edit Payment' ),
			'update_item' => __( 'Update Payment' ),
			'search_items' => __( 'Search payments' ),
			'not_found' => __( 'No payments found' ),
			'not_found_in_trash' => __( 'No payments found in Trash' ),
		);
		$capabilities = apply_filters( 'paypal_form_payment_capabilities', array(
			'edit_post' => 'edit_payment',
			'read_post' => 'read_payment',
			'delete_post' => 'delete_payments',
			'edit_posts' => 'edit_payments',
			'edit_others_posts' => 'edit_others_payments',
			'publish_posts' => 'publish_payments',
			'read_private_posts' => 'read_private_payments',
			) );
		$args = array(
			'label' => __( 'paypal_transaction' ),
			'description' => __( 'Payments submitted to PayPal' ),
			'labels' => $labels,
			'supports' => array( 'payment_info' ),
			'hierarchical' => false,
			'public' => false,
			'show_ui' => true,
			'show_in_menu' => true,
			'show_in_nav_menus' => false,
			'show_in_admin_bar' => false,
			'menu_position' => 80,
			'menu_icon' => '',
			'can_export' => false,
			'has_archive' => false,
			'exclude_from_search' => true,
			'publicly_queryable' => false,
			'rewrite' => false,
			//'capabilities' => $capabilities,
		);
		register_post_type( 'paypal_transaction', $args );

		$args = array(
			'label' => 'Unsent',
			'label_count' => _n_noop( 'Unsent (%s)', 'Unsent (%s)', 'paypal-form' ),
			'public' => true,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
			'exclude_from_search' => true,
		);
		register_post_status( 'unsent', $args );

		$args = array(
			'label' => 'Created',
			'label_count' => _n_noop( 'Created (%s)', 'Created (%s)', 'paypal-form' ),
			'public' => true,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
			'exclude_from_search' => true,
		);
		register_post_status( 'created', $args );

		$args = array(
			'label' => 'Failed',
			'label_count' => _n_noop( 'Failed (%s)', 'Failed (%s)', 'paypal-form' ),
			'public' => true,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
			'exclude_from_search' => true,
		);
		register_post_status( 'failed', $args );

		$args = array(
			'label' => 'Expired',
			'label_count' => _n_noop( 'Expired (%s)', 'Expired (%s)', 'paypal-form' ),
			'public' => true,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
			'exclude_from_search' => true,
		);
		register_post_status( 'expired', $args );

		$args = array(
			'label' => 'Approved',
			'label_count' => _n_noop( 'Approved (%s)', 'Approved (%s)', 'paypal-form' ),
			'public' => true,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
			'exclude_from_search' => true,
		);
		register_post_status( 'approved', $args );


		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( __CLASS__, '_add_meta_boxes' ) );
		}
	}

	public static function _add_meta_boxes( $post_type ) {
		if ( post_type_supports( $post_type, 'payment_info' ) ) {
			add_meta_box( 'payment_info', 'Payment Information', array( __CLASS__, '_payment_info_metabox' ), null, 'normal', 'high' );
		}
	}

	public static function _payment_info_metabox( $post ) {
		$data = json_decode( $post->post_content );
		if ( $data ) {
			$cc = $data->payment->payer->funding_instruments[0]->credit_card;
			?>
			<table style="width: 100%">
				<tr>
					<th colspan="2">User Information</th>
				</tr>
				<tr>
					<td style="width: 30%;"><strong>First Name</strong></td>
					<td style="width: 70%;"><?php echo esc_html( $data->userData->first_name ) ?></td>
				</tr>
				<tr>
					<td><strong>Last Name</strong></td>
					<td><?php echo esc_html( $data->userData->last_name ) ?></td>
				</tr>
				<tr>
					<td><strong>Email</strong></td>
					<td><?php echo esc_html( $data->userData->email ) ?></td>
				</tr>
				<tr>
					<td><strong>Phone</strong></td>
					<td><?php echo esc_html( $data->userData->phonenumber ) ?></td>
				</tr>

				<tr><th>&nbsp;</th></tr>

				<tr>
					<th colspan="2">Billing / CC Information</th>
				</tr>
				<tr>
					<td><strong>First Name</strong></td>
					<td><?php echo esc_html( $cc->first_name ) ?></td>
				</tr>
				<tr>
					<td><strong>Last Name</strong></td>
					<td><?php echo esc_html( $cc->last_name ) ?></td>
				</tr>
				<tr>
					<td><strong>Card Number</strong></td>
					<td><?php echo esc_html( $cc->number ) ?></td>
				</tr>
				<tr>
					<td><strong>Card Type</strong></td>
					<td><?php echo esc_html( $cc->type ) ?></td>
				</tr><tr>
					<td><strong>Card Expiration</strong></td>
					<td><?php echo esc_html( str_pad( $cc->expire_month, 2, '0', STR_PAD_LEFT ) . '/' . str_pad( $cc->expire_year, 3, '0', STR_PAD_LEFT ) ) ?></td>
				</tr>
				<tr>
					<td><strong>Address Line 1</strong></td>
					<td><?php echo esc_html( $cc->billing_address->line1 ) ?></td>
				</tr>
				<?php if ( !empty( $cc->billing_address->line2 ) ) : ?>
					<tr>
						<td><strong>Address Line 2</strong></td>
						<td><?php echo esc_html( $cc->billing_address->line2 ) ?></td>
					</tr>
				<?php endif; ?>
				<tr>
					<td><strong>City</strong></td>
					<td><?php echo esc_html( $cc->billing_address->city ) ?></td>
				</tr>
				<tr>
					<td><strong>State</strong></td>
					<td><?php echo esc_html( $cc->billing_address->state ) ?></td>
				</tr>
				<tr>
					<td><strong>Zip Code</strong></td>
					<td><?php echo esc_html( $cc->billing_address->postal_code ) ?></td>
				</tr>

				<tr><th>&nbsp;</th></tr>

				<tr>
					<th colspan="2">Transaction Information</th>
				</tr>
				<tr>
					<td><strong>State</strong></td>
					<td><?php echo esc_html( $data->payment->state ) ?></td>
				</tr>
				<?php if ( !empty( $data->payment->id ) ) : ?>
					<tr>
						<td><strong>Payment ID</strong></td>
						<td><?php echo esc_html( $data->payment->id ) ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( !empty( $data->error ) ) : ?>
					<tr>
						<td><strong>Error</strong></td>
						<td><?php echo esc_html( $data->error ) ?></td>
					</tr>
				<?php endif; ?>
				<tr>
					<td><strong>Amount</strong></td>
					<td><?php echo esc_html( $data->payment->transactions[0]->amount->total ) ?></td>
				</tr>
			</table>
			<?php
		} else {
			?>
			<p class="error">Unable to read data</p>
			<?php
		}
	}

	public static function insert_payment( $userData, $payment, $error = null, $payment_id = 0 ) {
		if ( $payment_id ) {
			$post = get_post( $payment_id );
			if ( get_post_type( $post ) !== 'paypal_transaction' ) {
				unset( $post );
			}
		}
		if ( !$post ) {
			$post = new stdClass();
		}

		//make sure we don't modify the original
		$payment = json_decode( json_encode( $payment ) );
		$userData = ( object ) $userData;

		//filter out sensitive payment data
		$cc = $payment->payer->funding_instruments[0]->credit_card;
		$len_cc_number = strlen( $cc->number );
		$cc->number = str_pad( substr( $cc->number, $len_cc_number - 4 ), $len_cc_number, 'x', STR_PAD_LEFT );
		if ( empty( $payment->state ) ) {
			if ( $error ) {
				$payment->state = 'error';
			} else {
				$payment->state = 'unsent';
			}
		}
		unset( $cc->cvv2 );
		$post->post_title = $userData->first_name . ' ' . $userData->last_name;
		$post->post_content = json_encode( array(
			'userData' => $userData,
			'payment' => $payment,
			'error' => $error
			) );
		$post->post_status = $payment->state;
		$post->post_type = 'paypal_transaction';

		return wp_insert_post( ( array ) $post );
	}

}