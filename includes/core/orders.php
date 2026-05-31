<?php
/**
 * Core Orders Handler
 * Manages order display, admin functionality, and priority order handling
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.0.0
 */

declare(strict_types=1);

/**
 * Core Orders Class
 *
 * @since 1.0.0
 */
class Core_Orders {

	public function __construct() {
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'display_priority_in_admin' ] );
		add_action( 'admin_head', [ $this, 'orders_list_styles' ] );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'modify_order_number_display' ], 10, 2 );
		add_action( 'woocommerce_shop_order_list_table_custom_column', [ $this, 'modify_order_number_display_hpos' ], 10, 2 );

		add_action( 'add_meta_boxes', [ $this, 'add_order_meta_box' ] );
		add_action( 'wp_ajax_wpp_toggle_order_priority', [ $this, 'ajax_toggle_order_priority' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'order_admin_scripts' ] );

		add_action( 'woocommerce_thankyou', [ $this, 'display_priority_on_thank_you' ], 10, 1 );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', [ $this, 'display_priority_in_shipping_section' ], 10, 1 );
	}

	/**
	 * Display priority badge in individual order billing section
	 *
	 * @param WC_Order $order
	 */
	public function display_priority_in_admin( WC_Order $order ): void {
		if ( $order->get_meta( '_priority_processing' ) !== 'yes' ) {
			return;
		}
		?>
		<p style="margin-top: 10px;">
			<strong style="color: #d63638;">⚡ <?php esc_html_e( 'Priority Processing', 'woo-priority' ); ?></strong><br>
			<?php esc_html_e( 'This order has priority processing and express shipping', 'woo-priority' ); ?>
		</p>
		<?php
	}

	/**
	 * Inline styles + JS for the orders list page
	 */
	public function orders_list_styles(): void {
		if ( ! $this->is_orders_page() ) {
			return;
		}
		?>
		<style>
			.wpp-priority-flash { color: #d63638; margin-right: 4px; animation: flash 2s infinite; }
			.wpp-priority-order-number { color: #d63638 !important; font-weight: bold !important; }
			@keyframes flash { 0%, 50% { opacity: 1; } 25%, 75% { opacity: 0.3; } }
		</style>
		<script>
		jQuery(document).ready(function($) {
			$('.wpp-priority-marker').each(function() {
				var $marker = $(this);
				var $orderCell = $marker.closest('tr').find('.order_number');
				if ($orderCell.find('a').length) {
					$orderCell.find('a').addClass('wpp-priority-order-number').prepend('<span class="wpp-priority-flash">⚡</span>');
				} else {
					$orderCell.find('strong').addClass('wpp-priority-order-number').prepend('<span class="wpp-priority-flash">⚡</span>');
				}
				$marker.remove();
			});
		});
		</script>
		<?php
	}

	/**
	 * Priority marker for traditional post-based orders list
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function modify_order_number_display( string $column, int $post_id ): void {
		if ( $column !== 'order_number' ) {
			return;
		}
		$order = wc_get_order( $post_id );
		if ( $order && $order->get_meta( '_priority_processing' ) === 'yes' ) {
			echo '<span class="wpp-priority-marker" data-order="' . esc_attr( $order->get_order_number() ) . '" style="display:none;"></span>';
		}
	}

	/**
	 * Priority marker for HPOS orders list
	 *
	 * @param string    $column Column name.
	 * @param WC_Order  $order  Order object.
	 */
	public function modify_order_number_display_hpos( string $column, WC_Order $order ): void {
		if ( $column !== 'order_number' ) {
			return;
		}
		if ( $order->get_meta( '_priority_processing' ) === 'yes' ) {
			echo '<span class="wpp-priority-marker" data-order="' . esc_attr( $order->get_order_number() ) . '" style="display:none;"></span>';
		}
	}

	/**
	 * Check if current screen is an orders list page
	 */
	private function is_orders_page(): bool {
		global $pagenow, $typenow;

		if ( $pagenow === 'edit.php' && $typenow === 'shop_order' ) {
			return true;
		}
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'wc-orders' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}
		return false;
	}

	/**
	 * Register priority meta box on order edit pages
	 */
	public function add_order_meta_box(): void {
		add_meta_box(
			'wpp_order_priority',
			__( '⚡ Priority Processing', 'woo-priority' ),
			[ $this, 'order_priority_meta_box' ],
			'shop_order',
			'side',
			'high'
		);

		if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
			add_meta_box(
				'wpp_order_priority',
				__( '⚡ Priority Processing', 'woo-priority' ),
				[ $this, 'order_priority_meta_box' ],
				wc_get_page_screen_id( 'shop-order' ),
				'side',
				'high'
			);
		}
	}

	/**
	 * Render priority meta box
	 *
	 * @param WP_Post|WC_Order $post_or_order
	 */
	public function order_priority_meta_box( $post_or_order ): void {
		$order = ( $post_or_order instanceof WP_Post ) ? wc_get_order( $post_or_order->ID ) : $post_or_order;

		if ( ! $order ) {
			return;
		}

		wp_nonce_field( 'wpp_order_priority_nonce', 'wpp_order_priority_nonce' );

		echo '<div id="wpp-order-priority-container">';
		echo $this->get_priority_meta_box_inner_html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}

	/**
	 * Build the inner HTML for the priority meta box.
	 * Called both on page render and in AJAX response so the DOM can be
	 * updated without a full page reload.
	 *
	 * @param WC_Order $order
	 * @return string
	 */
	private function get_priority_meta_box_inner_html( WC_Order $order ): string {
		$order_id    = $order->get_id();
		$has_priority = $order->get_meta( '_priority_processing' ) === 'yes';
		$fee_amount  = get_option( 'wpp_fee_amount', '5.00' );
		$fee_label   = get_option( 'wpp_fee_label', 'Priority Processing & Express Shipping' );

		$existing_fee = null;
		foreach ( $order->get_fees() as $fee ) {
			if ( str_contains( $fee->get_name(), 'Priority' ) || $fee->get_name() === $fee_label ) {
				$existing_fee = $fee;
				break;
			}
		}

		$can_modify = ! in_array( $order->get_status(), [ 'completed', 'refunded', 'cancelled' ], true );

		ob_start();
		if ( $has_priority ) : ?>
			<div class="wpp-priority-active">
				<p><strong style="color: #0f5132;">✅ <?php esc_html_e( 'Priority Processing Active', 'woo-priority' ); ?></strong></p>
				<?php if ( $existing_fee ) : ?>
					<p><?php
						/* translators: %s: formatted fee price */
						printf( esc_html__( 'Fee: %s', 'woo-priority' ), wc_price( $existing_fee->get_total() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?></p>
				<?php endif; ?>
				<?php if ( $can_modify ) : ?>
					<button type="button" id="wpp-remove-priority" class="button button-secondary"
						data-order-id="<?php echo esc_attr( (string) $order_id ); ?>"
						style="width: 100%; margin-top: 10px;">
						<?php esc_html_e( 'Remove Priority Processing', 'woo-priority' ); ?>
					</button>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<div class="wpp-priority-inactive">
				<p><strong><?php esc_html_e( 'Standard Processing', 'woo-priority' ); ?></strong></p>
				<?php if ( $can_modify ) : ?>
					<button type="button" id="wpp-add-priority" class="button button-primary"
						data-order-id="<?php echo esc_attr( (string) $order_id ); ?>"
						style="width: 100%; margin-top: 10px;">
						<?php
							/* translators: %s: formatted fee price */
							printf( esc_html__( 'Add Priority Processing (+%s)', 'woo-priority' ), wc_price( $fee_amount ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</button>
					<p style="font-size: 12px; color: #666; margin-top: 8px;">
						<?php
							/* translators: %s: formatted new total */
							printf( esc_html__( 'New total: %s', 'woo-priority' ), wc_price( $order->get_total() + floatval( $fee_amount ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</p>
				<?php else : ?>
					<p style="font-size: 12px; color: #666;">
						<?php esc_html_e( 'Cannot modify completed/cancelled orders', 'woo-priority' ); ?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<div id="wpp-loading" style="display: none; text-align: center; padding: 10px;">
			<span class="spinner is-active" style="float: none;"></span>
			<div style="margin-top: 5px; font-size: 12px;"><?php esc_html_e( 'Processing...', 'woo-priority' ); ?></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX: toggle priority processing on an order
	 */
	public function ajax_toggle_order_priority(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Permission denied', 'woo-priority' ) );
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpp_order_priority_nonce' ) ) {
			wp_send_json_error( __( 'Invalid nonce', 'woo-priority' ) );
		}

		$order_id = intval( $_POST['order_id'] ?? 0 );
		$action   = sanitize_text_field( $_POST['priority_action'] ?? '' );

		if ( ! $order_id || ! in_array( $action, [ 'add', 'remove' ], true ) ) {
			wp_send_json_error( __( 'Invalid parameters', 'woo-priority' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( __( 'Order not found', 'woo-priority' ) );
		}

		if ( in_array( $order->get_status(), [ 'completed', 'refunded', 'cancelled' ], true ) ) {
			wp_send_json_error( __( 'Cannot modify this order status', 'woo-priority' ) );
		}

		try {
			$fee_amount   = floatval( get_option( 'wpp_fee_amount', '5.00' ) );
			$fee_label    = get_option( 'wpp_fee_label', 'Priority Processing & Express Shipping' );
			$current_user = wp_get_current_user();

			if ( $action === 'add' ) {
				if ( $order->get_meta( '_priority_processing' ) === 'yes' ) {
					wp_send_json_error( __( 'Order already has priority processing', 'woo-priority' ) );
				}

				$existing_fee = null;
				foreach ( $order->get_fees() as $fee ) {
					if ( str_contains( $fee->get_name(), 'Priority' ) || $fee->get_name() === $fee_label ) {
						$existing_fee = $fee;
						break;
					}
				}

				if ( $existing_fee ) {
					wp_send_json_error( __( 'Order already has a priority processing fee', 'woo-priority' ) );
				}

				$order->update_meta_data( '_priority_processing', 'yes' );

				if ( $fee_amount > 0 ) {
					$fee = new WC_Order_Item_Fee();
					$fee->set_name( $fee_label );
					$fee->set_amount( $fee_amount );
					$fee->set_total( $fee_amount );
					$fee->set_order_id( $order_id );
					$order->add_item( $fee );
					wpp_log( "Added priority fee of {$fee_amount} to order #{$order_id}" );
				}

				$order->add_order_note(
					sprintf(
						/* translators: 1: user display name, 2: formatted fee */
						__( '⚡ Priority processing added by %1$s. Fee: %2$s', 'woo-priority' ),
						$current_user->display_name,
						wc_price( $fee_amount )
					),
					false
				);

				$message = __( 'Priority processing added successfully!', 'woo-priority' );

			} else {
				$order->delete_meta_data( '_priority_processing' );

				$fees_to_remove = [];
				foreach ( $order->get_fees() as $fee_id => $fee ) {
					if ( str_contains( $fee->get_name(), 'Priority' ) || $fee->get_name() === $fee_label ) {
						$fees_to_remove[] = $fee_id;
					}
				}
				foreach ( $fees_to_remove as $fee_id ) {
					$order->remove_item( $fee_id );
				}

				$order->add_order_note(
					sprintf(
						/* translators: %s: user display name */
						__( '❌ Priority processing removed by %s', 'woo-priority' ),
						$current_user->display_name
					),
					false
				);

				$message = __( 'Priority processing removed successfully!', 'woo-priority' );
			}

			$order->calculate_totals();
			$order->save();

			if ( function_exists( 'wc_delete_shop_order_transients' ) ) {
				wc_delete_shop_order_transients( $order_id );
			}

			do_action( 'wpp_order_priority_toggled', $order_id, $action, $current_user->ID );

			$wpp = WooCommerce_Priority_Processing::instance();
			if ( $wpp->core_statistics ) {
				$wpp->core_statistics->clear_cache();
			}

			wpp_log( "Priority processing {$action}ed for order #{$order_id} by {$current_user->display_name}" );

			wp_send_json_success( [
				'message'       => $message,
				'order_id'      => $order_id,
				'action'        => $action,
				'new_total'     => $order->get_formatted_order_total(),
				'has_priority'  => ( $action === 'add' ),
				'meta_box_html' => $this->get_priority_meta_box_inner_html( $order ),
			] );

		} catch ( Exception $e ) {
			wpp_log( 'Order Priority Toggle Error: ' . $e->getMessage() );
			wp_send_json_error( __( 'An error occurred while updating the order', 'woo-priority' ) );
		}
	}

	/**
	 * Enqueue scripts and styles for order admin pages
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function order_admin_scripts( string $hook ): void {
		global $post_type;
		$screen = get_current_screen();

		if (
			$post_type !== 'shop_order' &&
			! ( $screen && $screen->id === wc_get_page_screen_id( 'shop-order' ) ) &&
			! ( $screen && str_contains( $screen->id, 'shop_order' ) )
		) {
			return;
		}

		wp_enqueue_style( 'wpp-order-admin', WPP_PLUGIN_URL . 'assets/css/order-admin.css', [], WPP_VERSION );
		wp_enqueue_script( 'wpp-order-admin', WPP_PLUGIN_URL . 'assets/js/order-admin.js', [ 'jquery' ], WPP_VERSION, true );

		wp_localize_script( 'wpp-order-admin', 'wpp_order_admin', [
			'ajax_url'         => admin_url( 'admin-ajax.php' ),
			'error_title'      => __( 'Error:', 'woo-priority' ),
			'connection_error' => __( 'Connection error. Please try again.', 'woo-priority' ),
		] );
	}

	/**
	 * Display priority notice on the thank-you page
	 *
	 * @param int $order_id
	 */
	public function display_priority_on_thank_you( int $order_id ): void {
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_priority_processing' ) !== 'yes' ) {
			return;
		}

		$fee_label = get_option( 'wpp_fee_label', 'Priority Processing & Express Shipping' );
		?>
		<div class="woocommerce-message wpp-priority-thank-you"
			style="background:#dcfce7;border-color:#22c55e;color:#166534;padding:15px;margin-bottom:20px;border-radius:4px;">
			<strong style="font-size:16px;display:flex;align-items:center;gap:8px;">
				<span style="font-size:24px;">⚡</span>
				<?php esc_html_e( 'Priority Processing Activated!', 'woo-priority' ); ?>
			</strong>
			<p style="margin:10px 0 0 32px;font-size:14px;">
				<?php
				printf(
					/* translators: %s: fee label */
					esc_html__( 'Your order includes %s. We\'ll process and ship your order as quickly as possible!', 'woo-priority' ),
					'<strong>' . esc_html( $fee_label ) . '</strong>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Display priority badge in order shipping section (admin)
	 *
	 * @param WC_Order $order
	 */
	public function display_priority_in_shipping_section( WC_Order $order ): void {
		if ( $order->get_meta( '_priority_processing' ) !== 'yes' ) {
			return;
		}
		?>
		<p style="margin-top: 10px;">
			⚡ <strong><?php esc_html_e( 'Priority Processing Active', 'woo-priority' ); ?></strong>
		</p>
		<?php
	}
}
