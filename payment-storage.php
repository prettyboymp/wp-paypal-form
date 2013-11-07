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
			'edit_post' => 'edit_post',
			'read_post' => 'read_post',
			'delete_post' => 'delete_post',
			'edit_posts' => 'edit_posts',
			'delete_posts' => 'delete_posts',
			'delete_private_posts' => 'delete_private_posts',
			'delete_published_posts' => 'delete_published_posts',
			'delete_others_posts' => 'delete_others_posts',
			'edit_private_posts' => 'edit_private_posts',
			'edit_published_posts' => 'edit_published_posts',
			//blocked capabilities
			'create_posts' => 'create_payments',
			'publish_posts' => 'publish_payments',
			) );


		$args = array(
			'label' => __( 'paypal_transaction' ),
			'description' => __( 'Payments submitted to PayPal' ),
			'labels' => $labels,
			'supports' => array( 'payment_info' ),
			'hierarchical' => false,
			'public' => false,
			'show_ui' => true,
			'show_in_menu' => false,
			'show_in_nav_menus' => false,
			'show_in_admin_bar' => false,
			'menu_position' => 80,
			'menu_icon' => '',
			'can_export' => false,
			'has_archive' => false,
			'exclude_from_search' => true,
			'publicly_queryable' => false,
			'rewrite' => false,
			'map_meta_cap' => true,
			'capabilities' => $capabilities,
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
			add_action( 'admin_menu', array( __CLASS__, '_register_menu_pages' ), 9 );
			add_filter( 'manage_toplevel_page_paypal-transactions_columns', array( __CLASS__, '_manage_screen_columns' ) );
			add_action('load-toplevel_page_paypal-transactions', array(__CLASS__, '_handle_manage_transactions_postbacks'));
		}
	}

	public static function _manage_screen_columns( $columns ) {
		return array( 'cb' => '<input type="checkbox" />', 'title' => 'Title', 'date' => 'Date', );
	}

	public static function _register_menu_pages() {
		add_menu_page( 'PayPal Payments', 'PayPal', 'manage_options', 'paypal-transactions', array( __CLASS__, '_edit_transactions_page' ), null, 81 );
	}
	
	public static function _handle_manage_transactions_postbacks() {
		global $wpdb;
		$post_type = 'paypal_transaction';
		require_once dirname( __FILE__ ) . '/class-payment-list-table.php';
		$args = array( 'screen' => get_current_screen() );
		$args['screen']->post_type = 'paypal_transaction';

		$wp_list_table = new PayPal_Payment_List_Table( $args );

		$pagenum = $wp_list_table->get_pagenum();


		$parent_file = "admin.php?page=paypal-transactions";
		$submenu_file = "admin.php?page=paypal-transactions";

		$doaction = $wp_list_table->current_action();

		if ( $doaction ) {
			check_admin_referer( 'bulk-posts' );

			$sendback = remove_query_arg( array( 'trashed', 'untrashed', 'deleted', 'locked', 'ids' ), wp_get_referer() );
			if ( !$sendback )
				$sendback = admin_url( $parent_file );
			$sendback = add_query_arg( 'paged', $pagenum, $sendback );

			if ( 'delete_all' == $doaction ) {
				$post_status = preg_replace( '/[^a-z0-9_-]+/i', '', $_REQUEST['post_status'] );
				if ( get_post_status_object( $post_status ) ) // Check the post status exists first
					$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type=%s AND post_status = %s", $post_type, $post_status ) );
				$doaction = 'delete';
			} elseif ( isset( $_REQUEST['media'] ) ) {
				$post_ids = $_REQUEST['media'];
			} elseif ( isset( $_REQUEST['ids'] ) ) {
				$post_ids = explode( ',', $_REQUEST['ids'] );
			} elseif ( !empty( $_REQUEST['post'] ) ) {
				$post_ids = array_map( 'intval', $_REQUEST['post'] );
			}

			if ( !isset( $post_ids ) ) {
				wp_redirect( $sendback );
				exit;
			}

			switch ( $doaction ) {
				case 'trash':
					$trashed = $locked = 0;

					foreach ( ( array ) $post_ids as $post_id ) {
						if ( !current_user_can( 'delete_post', $post_id ) )
							wp_die( __( 'You are not allowed to move this item to the Trash.' ) );

						if ( wp_check_post_lock( $post_id ) ) {
							$locked++;
							continue;
						}

						if ( !wp_trash_post( $post_id ) )
							wp_die( __( 'Error in moving to Trash.' ) );

						$trashed++;
					}

					$sendback = add_query_arg( array( 'trashed' => $trashed, 'ids' => join( ',', $post_ids ), 'locked' => $locked ), $sendback );
					break;
				case 'untrash':
					$untrashed = 0;
					foreach ( ( array ) $post_ids as $post_id ) {
						if ( !current_user_can( 'delete_post', $post_id ) )
							wp_die( __( 'You are not allowed to restore this item from the Trash.' ) );

						if ( !wp_untrash_post( $post_id ) )
							wp_die( __( 'Error in restoring from Trash.' ) );

						$untrashed++;
					}
					$sendback = add_query_arg( 'untrashed', $untrashed, $sendback );
					break;
				case 'delete':
					$deleted = 0;
					foreach ( ( array ) $post_ids as $post_id ) {
						$post_del = get_post( $post_id );

						if ( !current_user_can( 'delete_post', $post_id ) )
							wp_die( __( 'You are not allowed to delete this item.' ) );

						if ( $post_del->post_type == 'attachment' ) {
							if ( !wp_delete_attachment( $post_id ) )
								wp_die( __( 'Error in deleting.' ) );
						} else {
							if ( !wp_delete_post( $post_id ) )
								wp_die( __( 'Error in deleting.' ) );
						}
						$deleted++;
					}
					$sendback = add_query_arg( 'deleted', $deleted, $sendback );
					break;
				case 'edit':
					if ( isset( $_REQUEST['bulk_edit'] ) ) {
						$done = bulk_edit_posts( $_REQUEST );

						if ( is_array( $done ) ) {
							$done['updated'] = count( $done['updated'] );
							$done['skipped'] = count( $done['skipped'] );
							$done['locked'] = count( $done['locked'] );
							$sendback = add_query_arg( $done, $sendback );
						}
					}
					break;
			}

			$sendback = remove_query_arg( array( 'action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status', 'post', 'bulk_edit', 'post_view' ), $sendback );

			wp_redirect( $sendback );
			die();
		} elseif ( !empty( $_REQUEST['_wp_http_referer'] ) ) {
			wp_redirect( remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
			die();
		}
	}

	/**
	 * A duplication of 3.7's edit.php since the current version of WordPress
	 * doesn't properly pay attention to capabilities or post statii.
	 */
	public static function _edit_transactions_page() {
		$post_type = 'paypal_transaction';
		$post_type_object = get_post_type_object( $post_type );
		require_once dirname( __FILE__ ) . '/class-payment-list-table.php';
		$args = array( 'screen' => get_current_screen() );
		$args['screen']->post_type = 'paypal_transaction';

		$wp_list_table = new PayPal_Payment_List_Table( $args );

		$wp_list_table->prepare_items();

		$title = $post_type_object->labels->name;

		add_screen_option( 'per_page', array( 'label' => $title, 'default' => 20, 'option' => 'edit_' . $post_type . '_per_page' ) );

		$bulk_counts = array(
			'updated' => isset( $_REQUEST['updated'] ) ? absint( $_REQUEST['updated'] ) : 0,
			'locked' => isset( $_REQUEST['locked'] ) ? absint( $_REQUEST['locked'] ) : 0,
			'deleted' => isset( $_REQUEST['deleted'] ) ? absint( $_REQUEST['deleted'] ) : 0,
			'trashed' => isset( $_REQUEST['trashed'] ) ? absint( $_REQUEST['trashed'] ) : 0,
			'untrashed' => isset( $_REQUEST['untrashed'] ) ? absint( $_REQUEST['untrashed'] ) : 0,
		);

		$bulk_messages = array( );
		$bulk_messages['post'] = array(
			'updated' => _n( '%s post updated.', '%s posts updated.', $bulk_counts['updated'] ),
			'locked' => _n( '%s post not updated, somebody is editing it.', '%s posts not updated, somebody is editing them.', $bulk_counts['locked'] ),
			'deleted' => _n( '%s post permanently deleted.', '%s posts permanently deleted.', $bulk_counts['deleted'] ),
			'trashed' => _n( '%s post moved to the Trash.', '%s posts moved to the Trash.', $bulk_counts['trashed'] ),
			'untrashed' => _n( '%s post restored from the Trash.', '%s posts restored from the Trash.', $bulk_counts['untrashed'] ),
		);
		$bulk_messages['page'] = array(
			'updated' => _n( '%s page updated.', '%s pages updated.', $bulk_counts['updated'] ),
			'locked' => _n( '%s page not updated, somebody is editing it.', '%s pages not updated, somebody is editing them.', $bulk_counts['locked'] ),
			'deleted' => _n( '%s page permanently deleted.', '%s pages permanently deleted.', $bulk_counts['deleted'] ),
			'trashed' => _n( '%s page moved to the Trash.', '%s pages moved to the Trash.', $bulk_counts['trashed'] ),
			'untrashed' => _n( '%s page restored from the Trash.', '%s pages restored from the Trash.', $bulk_counts['untrashed'] ),
		);

		/**
		 * Filter the bulk action updated messages.
		 *
		 * By default, custom post types use the messages for the 'post' post type.
		 *
		 * @since 3.7.0
		 *
		 * @param array $bulk_messages Arrays of messages, each keyed by the corresponding post type. Messages are
		 *                             keyed with 'updated', 'locked', 'deleted', 'trashed', and 'untrashed'.
		 * @param array $bulk_counts   Array of item counts for each message, used to build internationalized strings.
		 */
		$bulk_messages = apply_filters( 'bulk_post_updated_messages', $bulk_messages, $bulk_counts );
		$bulk_counts = array_filter( $bulk_counts );

		require_once( ABSPATH . 'wp-admin/admin-header.php' );
		?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2><?php
				echo esc_html( $post_type_object->labels->name );
				if ( current_user_can( $post_type_object->cap->create_posts ) )
					echo ' <a href="' . esc_url( admin_url( $post_new_file ) ) . '" class="add-new-h2">' . esc_html( $post_type_object->labels->add_new ) . '</a>';
				if ( !empty( $_REQUEST['s'] ) )
					printf( ' <span class="subtitle">' . __( 'Search results for &#8220;%s&#8221;' ) . '</span>', get_search_query() );
				?></h2>

			<?php
// If we have a bulk message to issue:
			$messages = array( );
			foreach ( $bulk_counts as $message => $count ) {
				if ( isset( $bulk_messages[$post_type][$message] ) )
					$messages[] = sprintf( $bulk_messages[$post_type][$message], number_format_i18n( $count ) );
				elseif ( isset( $bulk_messages['post'][$message] ) )
					$messages[] = sprintf( $bulk_messages['post'][$message], number_format_i18n( $count ) );

				if ( $message == 'trashed' && isset( $_REQUEST['ids'] ) ) {
					$ids = preg_replace( '/[^0-9,]/', '', $_REQUEST['ids'] );
					$messages[] = '<a href="' . esc_url( wp_nonce_url( "admin.php?page=paypal-transactions&post_type=$post_type&doaction=undo&action=untrash&ids=$ids", "bulk-posts" ) ) . '">' . __( 'Undo' ) . '</a>';
				}
			}

			if ( $messages )
				echo '<div id="message" class="updated"><p>' . join( ' ', $messages ) . '</p></div>';
			unset( $messages );

			$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'locked', 'skipped', 'updated', 'deleted', 'trashed', 'untrashed' ), $_SERVER['REQUEST_URI'] );
			?>

			<?php $wp_list_table->views(); ?>

			<form id="posts-filter" action="admin.php?page=paypal-transactions" method="get">
				<input type="hidden" name="page" value="paypal-transactions" />
				<?php $wp_list_table->search_box( $post_type_object->labels->search_items, 'post' ); ?>

				<input type="hidden" name="post_status" class="post_status_page" value="<?php echo!empty( $_REQUEST['post_status'] ) ? esc_attr( $_REQUEST['post_status'] ) : 'all'; ?>" />
				<input type="hidden" name="post_type" class="post_type_page" value="<?php echo $post_type; ?>" />
				<?php if ( !empty( $_REQUEST['show_sticky'] ) ) { ?>
					<input type="hidden" name="show_sticky" value="1" />
				<?php } ?>

				<?php $wp_list_table->display(); ?>

			</form>

			<?php
			if ( $wp_list_table->has_items() )
				$wp_list_table->inline_edit();
			?>

			<div id="ajax-response"></div>
			<br class="clear" />
		</div>

		<?php
		include( ABSPATH . 'wp-admin/admin-footer.php' );
	}

	public static function _add_meta_boxes( $post_type ) {
		if ( post_type_supports( $post_type, 'payment_info' ) ) {
			add_meta_box( 'payment_info', 'Payment Information', array( __CLASS__, '_payment_info_metabox' ), null, 'normal', 'high' );
			add_meta_box( 'payment_manage', 'Manage', array( __CLASS__, '_payment_return_metabox' ), null, 'side', 'high' );
			remove_meta_box( 'submitdiv', null, 'side' );
		}
	}

	public static function _payment_return_metabox( $post ) {
		?>
		<div class="submitbox" id="submitpost">

			<div id="major-publishing-actions">
				<div id="delete-action">
					<?php
					if ( current_user_can( "delete_post", $post->ID ) ) {
						if ( !EMPTY_TRASH_DAYS )
							$delete_text = __( 'Delete Permanently' );
						else
							$delete_text = __( 'Move to Trash' );
						?>
						<a class="submitdelete deletion" href="<?php echo get_delete_post_link( $post->ID ); ?>"><?php echo $delete_text; ?></a><?php }
					?>
				</div>

				<div id="publishing-action">
					<span class="spinner"></span>
					<a href="admin.php?page=paypal-transactions" class="button button-primary button-large">Return</a>
				</div>
				<div class="clear"></div>
			</div>
		</div>

		<?php
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