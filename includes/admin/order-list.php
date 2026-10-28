<?php
/**
 * Order list filter and bulk actions
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.9.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Order_List {

	public const FILTER_PARAM = 'wpp_priority_filter';
	public const FILTER_YES   = 'priority';
	public const FILTER_NO    = 'not_priority';

	public const ACTION_MARK   = 'wpp_mark_priority';
	public const ACTION_REMOVE = 'wpp_remove_priority';

	private const META_KEY = '_priority_processing';

	private ?Core_Orders $orders;

	/**
	 * @param Core_Orders|null $orders Orders handler; falls back to the plugin instance.
	 */
	public function __construct( ?Core_Orders $orders = null ) {
		$this->orders = $orders;

		// HPOS orders list.
		add_action( 'woocommerce_order_list_table_restrict_manage_orders', [ $this, 'render_filter' ] );
		add_filter( 'woocommerce_order_list_table_prepare_items_query_args', [ $this, 'filter_hpos_query_args' ] );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this, 'register_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', [ $this, 'handle_bulk_actions' ], 10, 3 );

		// Legacy post-based orders list.
		add_action( 'restrict_manage_posts', [ $this, 'render_filter_legacy' ], 10, 1 );
		add_action( 'pre_get_posts', [ $this, 'filter_legacy_query' ] );
		add_filter( 'bulk_actions-edit-shop_order', [ $this, 'register_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-shop_order', [ $this, 'handle_bulk_actions' ], 10, 3 );

		add_action( 'admin_notices', [ $this, 'render_bulk_notice' ] );
	}

	/**
	 * Allow-list a raw filter value
	 *
	 * @param mixed $value Raw value.
	 * @return string One of the FILTER_* constants, or '' for no filtering.
	 */
	public static function sanitize_filter( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = sanitize_text_field( $value );
		return in_array( $value, [ self::FILTER_YES, self::FILTER_NO ], true ) ? $value : '';
	}

	/**
	 * Currently requested filter, sanitized
	 */
	private function get_requested_filter(): string {
		return self::sanitize_filter( wp_unslash( $_GET[ self::FILTER_PARAM ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Echo the filter dropdown
	 */
	public function render_filter(): void {
		$current = $this->get_requested_filter();
		$options = [
			''               => __( 'Priority: All', 'woo-priority' ),
			self::FILTER_YES => __( 'Priority only', 'woo-priority' ),
			self::FILTER_NO  => __( 'Not priority', 'woo-priority' ),
		];

		echo '<select name="' . esc_attr( self::FILTER_PARAM ) . '" id="wpp-priority-filter">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( (string) $value ),
				(string) $value === $current ? ' selected="selected"' : '',
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Filter dropdown for the legacy list (restrict_manage_posts fires for every post type)
	 *
	 * @param string $post_type Current post type.
	 */
	public function render_filter_legacy( string $post_type = '' ): void {
		if ( $post_type !== 'shop_order' ) {
			return;
		}
		$this->render_filter();
	}

	/**
	 * Add the priority clause to a meta_query, keeping whatever is already there
	 *
	 * @param mixed  $meta_query Existing meta_query (any shape).
	 * @param string $filter     Sanitized filter value.
	 */
	public function build_meta_query( mixed $meta_query, string $filter ): array {
		$meta_query = is_array( $meta_query ) ? $meta_query : [];

		if ( $filter === self::FILTER_YES ) {
			$clause = [
				'key'     => self::META_KEY,
				'value'   => 'yes',
				'compare' => '=',
			];
		} elseif ( $filter === self::FILTER_NO ) {
			// Removing priority deletes the meta, but tolerate any non-'yes' value too.
			$clause = [
				'relation' => 'OR',
				[
					'key'     => self::META_KEY,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => self::META_KEY,
					'value'   => 'yes',
					'compare' => '!=',
				],
			];
		} else {
			return $meta_query;
		}

		if ( ! $meta_query ) {
			return [ $clause ];
		}

		return [
			'relation' => 'AND',
			$meta_query,
			$clause,
		];
	}

	/**
	 * HPOS: apply the filter to the list table query args
	 *
	 * @param array $args wc_get_orders() style args.
	 */
	public function filter_hpos_query_args( array $args ): array {
		$filter = $this->get_requested_filter();
		if ( $filter === '' ) {
			return $args;
		}
		$args['meta_query'] = $this->build_meta_query( $args['meta_query'] ?? [], $filter ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		return $args;
	}

	/**
	 * Legacy: apply the filter to the main orders query
	 *
	 * @param WP_Query $query Query being prepared.
	 */
	public function filter_legacy_query( $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'shop_order' ) {
			return;
		}
		$filter = $this->get_requested_filter();
		if ( $filter === '' ) {
			return;
		}
		$query->set( 'meta_query', $this->build_meta_query( $query->get( 'meta_query' ), $filter ) );
	}

	/**
	 * Add the bulk actions to the dropdown
	 *
	 * @param array $actions Existing bulk actions.
	 */
	public function register_bulk_actions( $actions ): array {
		$actions   = is_array( $actions ) ? $actions : [];
		$actions[ self::ACTION_MARK ]   = __( 'Mark as priority', 'woo-priority' );
		$actions[ self::ACTION_REMOVE ] = __( 'Remove priority', 'woo-priority' );
		return $actions;
	}

	/**
	 * Run the bulk actions. WordPress/WooCommerce verify the bulk nonce before this fires.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $action      Selected bulk action.
	 * @param array  $ids         Selected order IDs.
	 * @return string Redirect URL carrying the result counts.
	 */
	public function handle_bulk_actions( $redirect_to, $action, $ids ): string {
		$redirect_to = (string) $redirect_to;
		if ( ! in_array( $action, [ self::ACTION_MARK, self::ACTION_REMOVE ], true ) ) {
			return $redirect_to;
		}
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return $redirect_to;
		}

		$orders_handler = $this->orders ?? WooCommerce_Priority_Processing::instance()->core_orders ?? null;
		if ( ! $orders_handler ) {
			return $redirect_to;
		}

		$add     = ( $action === self::ACTION_MARK );
		$changed = 0;

		foreach ( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			// Skip orders that are already in the requested state.
			$has_priority = $order->get_meta( self::META_KEY ) === 'yes';
			if ( $add === $has_priority ) {
				continue;
			}

			try {
				if ( $orders_handler->apply_priority_change( $order, $add ? 'add' : 'remove', true ) ) {
					++$changed;
				}
			} catch ( Exception $e ) {
				wpp_log( "Bulk priority change failed for order #{$order_id}: " . $e->getMessage() );
			}
		}

		$redirect_to = remove_query_arg( [ 'wpp_bulk_marked', 'wpp_bulk_removed' ], $redirect_to );
		return add_query_arg( $add ? 'wpp_bulk_marked' : 'wpp_bulk_removed', $changed, $redirect_to );
	}

	/**
	 * Build the success notice text for the bulk result in the query string
	 *
	 * @param array $query Query args (normally $_GET).
	 * @return string Empty when there is nothing to report.
	 */
	public function get_notice_message( array $query ): string {
		if ( isset( $query['wpp_bulk_marked'] ) ) {
			$count = absint( $query['wpp_bulk_marked'] );
			if ( $count === 0 ) {
				return __( 'No orders were changed. The selected orders already have priority or cannot be modified.', 'woo-priority' );
			}
			return sprintf(
				/* translators: %d: number of orders */
				_n( '%d order marked as priority.', '%d orders marked as priority.', $count, 'woo-priority' ),
				$count
			);
		}

		if ( isset( $query['wpp_bulk_removed'] ) ) {
			$count = absint( $query['wpp_bulk_removed'] );
			if ( $count === 0 ) {
				return __( 'No orders were changed. The selected orders do not have priority or cannot be modified.', 'woo-priority' );
			}
			return sprintf(
				/* translators: %d: number of orders */
				_n( 'Priority removed from %d order.', 'Priority removed from %d orders.', $count, 'woo-priority' ),
				$count
			);
		}

		return '';
	}

	/**
	 * Show the bulk result notice on the orders list
	 */
	public function render_bulk_notice(): void {
		if ( ! $this->is_orders_page() ) {
			return;
		}
		$message = $this->get_notice_message( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $message === '' ) {
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Check if current screen is an orders list page
	 */
	private function is_orders_page(): bool {
		global $pagenow, $typenow;

		if ( $pagenow === 'edit.php' && $typenow === 'shop_order' ) {
			return true;
		}
		return isset( $_GET['page'] ) && $_GET['page'] === 'wc-orders' && ! isset( $_GET['action'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}
