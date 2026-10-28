<?php
/**
 * Feature integration tests (daily limit, cart button, order list, WooCommerce settings tab)
 * executed inside the wp-env WordPress/WooCommerce container.
 *
 * Run with: pnpm run test:integration
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'This test must be run through WP-CLI.' );
}

function wpp_feature_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	WP_CLI::log( 'PASS: ' . $message );
}

/** Create a persisted order, optionally flagged as priority. */
function wpp_feature_order( bool $priority, string $status = 'processing' ): WC_Order {
	$order = wc_create_order();
	if ( $priority ) {
		$order->update_meta_data( '_priority_processing', 'yes' );
	}
	$order->set_status( $status );
	$order->save();
	return $order;
}

$option_names = array(
	'wpp_enabled', 'wpp_allow_guests', 'wpp_min_order_amount', 'wpp_daily_limit', 'wpp_cart_button_enabled',
	'wpp_fee_amount', 'wpp_allowed_user_roles', 'wpp_cart_message_enabled', 'wpp_cart_messages',
);
$missing   = '__wpp_missing_option__';
$originals = array();
foreach ( $option_names as $option_name ) {
	$originals[ $option_name ] = get_option( $option_name, $missing );
}
$orders      = array();
$user_id     = 0;
$previous_user = get_current_user_id();
$get_backup  = $_GET;
$post_backup = $_POST;

try {
	$plugin = WooCommerce_Priority_Processing::instance();
	update_option( 'wpp_enabled', '1' );
	update_option( 'wpp_allow_guests', '1' );
	update_option( 'wpp_min_order_amount', '0' );
	update_option( 'wpp_daily_limit', '0' );
	wp_set_current_user( 0 );

	// -- Daily limit ---------------------------------------------------------
	Core_Limits::flush_cache();
	$baseline = Core_Limits::get_today_count();
	wpp_feature_assert( false === Core_Limits::is_limit_reached() && null === Core_Limits::get_remaining(), 'Limit 0 means unlimited.' );

	$orders[] = wpp_feature_order( true );
	$orders[] = wpp_feature_order( true, 'pending' );
	$orders[] = wpp_feature_order( true, 'cancelled' );
	$orders[] = wpp_feature_order( true, 'failed' );
	$orders[] = wpp_feature_order( false );
	Core_Limits::flush_cache();
	wpp_feature_assert( Core_Limits::get_today_count() === $baseline + 2, 'Only active priority orders created today are counted (cancelled, failed and regular orders are not).' );

	update_option( 'wpp_daily_limit', (string) ( $baseline + 3 ) );
	Core_Limits::flush_cache();
	wpp_feature_assert( ! Core_Limits::is_limit_reached() && 1 === Core_Limits::get_remaining(), 'One slot remains below the limit.' );
	wpp_feature_assert( Core_Permissions::can_enable_priority_processing(), 'Priority can be enabled below the limit.' );

	$orders[] = wpp_feature_order( true );
	Core_Limits::flush_cache();
	wpp_feature_assert( Core_Limits::is_limit_reached() && 0 === Core_Limits::get_remaining(), 'The limit is reached once the count equals it.' );
	wpp_feature_assert( ! Core_Permissions::can_enable_priority_processing(), 'The real permission check blocks activation when sold out.' );
	wpp_feature_assert( false === $plugin->frontend_blocks->extend_checkout_data()['enabled'] && true === $plugin->frontend_blocks->extend_checkout_data()['limit_reached'], 'Checkout Block data reports sold out.' );
	wpp_feature_assert( '' !== $plugin->frontend_blocks->extend_checkout_data()['limit_message'], 'Checkout Block data carries the sold-out message.' );

	// Fee is not applied to a stale session once sold out.
	if ( ! WC()->session ) {
		WC()->initialize_session();
	}
	if ( ! WC()->cart ) {
		WC()->initialize_cart();
	}
	WC()->session->set( 'priority_processing', true );
	WC()->cart->empty_cart();
	$plugin->frontend_fees->add_priority_fee_to_cart();
	wpp_feature_assert( 0 === count( WC()->cart->get_fees() ), 'A stale session cannot add the priority fee when sold out.' );

	$orders[3]->set_status( 'completed' );
	$orders[3]->save();
	update_option( 'wpp_daily_limit', '0' );
	Core_Limits::flush_cache();
	wpp_feature_assert( Core_Permissions::can_enable_priority_processing(), 'Removing the limit re-enables priority.' );

	// -- Cart button -----------------------------------------------------------
	$button = new Frontend_Cart_Button( false );
	update_option( 'wpp_cart_button_enabled', '0' );
	wpp_feature_assert( ! $button->is_eligible(), 'Cart button is off by default.' );
	update_option( 'wpp_cart_button_enabled', '1' );
	wpp_feature_assert( $button->is_eligible(), 'Cart button is eligible when enabled.' );

	ob_start();
	$button->render_classic_button();
	$html = (string) ob_get_clean();
	wpp_feature_assert( str_contains( $html, 'wpp-cart-button' ), 'The classic cart renders the button markup.' );

	$block = $button->render_cart_block_button( '<div>cart</div>' );
	wpp_feature_assert( str_contains( $block, 'wpp-cart-button-block' ) && str_contains( $block, '<div>cart</div>' ), 'The Cart Block gets the button container.' );

	$request = new WP_REST_Request( 'GET', '/wc/store/v1/cart' );
	$data    = json_decode( wp_json_encode( rest_do_request( $request )->get_data() ), true );
	wpp_feature_assert( true === ( $data['extensions']['wpp-cart-button']['eligible'] ?? null ), 'The real Store API exposes the cart button data.' );

	update_option( 'wpp_daily_limit', '1' );
	Core_Limits::flush_cache();
	wpp_feature_assert( ! $button->is_eligible(), 'The cart button disappears when sold out.' );
	update_option( 'wpp_daily_limit', '0' );
	Core_Limits::flush_cache();

	update_option( 'wpp_min_order_amount', '999999' );
	wpp_feature_assert( ! $button->is_eligible(), 'The cart button respects the minimum order amount.' );
	update_option( 'wpp_min_order_amount', '0' );

	// -- Order list filter ---------------------------------------------------------
	$priority_order = wpp_feature_order( true );
	$regular_order  = wpp_feature_order( false );
	$orders[]       = $priority_order;
	$orders[]       = $regular_order;
	$list           = new Admin_Order_List( $plugin->core_orders );

	// HPOS stores support meta_query in wc_get_orders(); legacy post storage is filtered through WP_Query.
	$hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	$ids  = static function ( string $filter ) use ( $list, $hpos ) {
		$_GET['wpp_priority_filter'] = $filter;
		if ( $hpos ) {
			return wc_get_orders( $list->filter_hpos_query_args( array( 'limit' => -1, 'return' => 'ids' ) ) );
		}
		$query = new WP_Query(
			array(
				'post_type'   => 'shop_order',
				'post_status' => 'any',
				'fields'      => 'ids',
				'nopaging'    => true,
				'meta_query'  => $list->build_meta_query( array(), $filter ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		return array_map( 'intval', $query->posts );
	};
	WP_CLI::log( 'Order storage: ' . ( $hpos ? 'HPOS' : 'legacy posts' ) );
	$found = $ids( 'priority' );
	wpp_feature_assert( in_array( $priority_order->get_id(), $found, true ) && ! in_array( $regular_order->get_id(), $found, true ), 'Filter "priority only" returns only priority orders.' );
	$found = $ids( 'not_priority' );
	wpp_feature_assert( ! in_array( $priority_order->get_id(), $found, true ) && in_array( $regular_order->get_id(), $found, true ), 'Filter "not priority" returns only regular orders (orders without the meta included).' );
	$_GET['wpp_priority_filter'] = 'bogus';
	wpp_feature_assert( array() === $list->filter_hpos_query_args( array() ), 'An unknown filter value does not change the query.' );
	unset( $_GET['wpp_priority_filter'] );

	// -- Bulk actions ---------------------------------------------------------------
	wp_set_current_user( $user_id = wp_insert_user( array( 'user_login' => 'wpp_customer_' . wp_generate_password( 6, false ), 'user_pass' => wp_generate_password(), 'role' => 'customer' ) ) );
	$untouched = $list->handle_bulk_actions( 'http://example.test/', Admin_Order_List::ACTION_MARK, array( $regular_order->get_id() ) );
	wpp_feature_assert( 'http://example.test/' === $untouched && 'yes' !== wc_get_order( $regular_order->get_id() )->get_meta( '_priority_processing' ), 'Bulk actions are denied without the edit_shop_orders capability.' );

	wp_set_current_user( 1 );
	$redirect = $list->handle_bulk_actions( 'http://example.test/', Admin_Order_List::ACTION_MARK, array( $regular_order->get_id(), $priority_order->get_id(), 999999999 ) );
	wpp_feature_assert( str_contains( $redirect, 'wpp_bulk_marked=1' ), 'Bulk mark changes only orders that were not priority (no-ops and unknown ids skipped).' );
	$marked = wc_get_order( $regular_order->get_id() );
	wpp_feature_assert( 'yes' === $marked->get_meta( '_priority_processing' ), 'Bulk mark persists the priority meta.' );
	$notes = wc_get_order_notes( array( 'order_id' => $regular_order->get_id() ) );
	wpp_feature_assert( (bool) array_filter( $notes, static fn( $n ) => str_contains( $n->content, 'bulk action' ) ), 'Bulk mark adds an order note.' );

	$redirect = $list->handle_bulk_actions( 'http://example.test/?wpp_bulk_marked=1', Admin_Order_List::ACTION_REMOVE, array( $regular_order->get_id() ) );
	wpp_feature_assert( str_contains( $redirect, 'wpp_bulk_removed=1' ) && ! str_contains( $redirect, 'wpp_bulk_marked' ), 'Bulk remove reports its count and drops the previous result args.' );
	wpp_feature_assert( 'yes' !== wc_get_order( $regular_order->get_id() )->get_meta( '_priority_processing' ), 'Bulk remove clears the priority meta.' );

	$actions = $list->register_bulk_actions( array() );
	wpp_feature_assert( isset( $actions[ Admin_Order_List::ACTION_MARK ], $actions[ Admin_Order_List::ACTION_REMOVE ] ), 'Both bulk actions are registered in the dropdown.' );
	wpp_feature_assert( has_filter( 'bulk_actions-woocommerce_page_wc-orders' ) && has_filter( 'bulk_actions-edit-shop_order' ), 'Bulk hooks are registered for HPOS and legacy storage.' );

	// -- WooCommerce settings tab ----------------------------------------------------------
	$pages = apply_filters( 'woocommerce_get_settings_pages', array() );
	$page  = null;
	foreach ( $pages as $candidate ) {
		if ( 'priority-processing' === $candidate->get_id() ) {
			$page = $candidate;
		}
	}
	wpp_feature_assert( null !== $page, 'The Priority Processing tab is registered in WooCommerce settings.' );
	wpp_feature_assert( array( '', 'access', 'display', 'messages', 'badge', 'statistics' ) === array_keys( $page->get_sections() ), 'The tab exposes all sections.' );

	$_POST = array(
		'wpp_enabled'              => '1',
		'wpp_daily_limit'          => '-7',
		'wpp_cart_button_enabled'  => '1',
		'wpp_fee_amount'           => '6.5',
	);
	WC_Admin_Settings::save_fields( $page->get_settings_for_section( '' ) );
	wpp_feature_assert( '0' === (string) get_option( 'wpp_daily_limit' ), 'Saving a negative daily limit stores 0.' );
	$_POST['wpp_daily_limit'] = '12abc';
	WC_Admin_Settings::save_fields( $page->get_settings_for_section( '' ) );
	wpp_feature_assert( '12' === (string) get_option( 'wpp_daily_limit' ), 'The daily limit is saved as an integer through the WooCommerce save flow.' );

	$_POST = array( 'wpp_cart_button_enabled' => '1' );
	WC_Admin_Settings::save_fields( $page->get_settings_for_section( 'display' ) );
	wpp_feature_assert( '1' === (string) get_option( 'wpp_cart_button_enabled' ), 'The cart button toggle saves checked.' );
	$_POST = array( 'wpp_cart_button_enabled' => '0' );
	WC_Admin_Settings::save_fields( $page->get_settings_for_section( 'display' ) );
	wpp_feature_assert( '0' === (string) get_option( 'wpp_cart_button_enabled' ), 'The cart button toggle saves unchecked.' );

	$legacy = Admin_WC_Settings_Tab::get_legacy_redirect_url();
	wpp_feature_assert( null === $legacy, 'No redirect happens outside the legacy page URL.' );
	$_GET['page'] = 'woo-priority-processing';
	wpp_feature_assert( str_contains( (string) Admin_WC_Settings_Tab::get_legacy_redirect_url(), 'tab=priority-processing' ), 'The old settings page URL redirects to the WooCommerce tab.' );

	WP_CLI::success( 'WooCommerce Priority Processing feature integration tests passed.' );
} finally {
	$_GET  = $get_backup;
	$_POST = $post_backup;
	wp_set_current_user( $previous_user );
	foreach ( $orders as $order ) {
		if ( $order instanceof WC_Order ) {
			$order->delete( true );
		}
	}
	if ( $user_id ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );
	}
	if ( WC()->session ) {
		WC()->session->set( 'priority_processing', false );
	}
	foreach ( $originals as $option_name => $original_value ) {
		if ( $missing === $original_value ) {
			delete_option( $option_name );
		} else {
			update_option( $option_name, $original_value );
		}
	}
	Core_Limits::flush_cache();
}
