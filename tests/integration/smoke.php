<?php
/**
 * Smoke tests executed inside the wp-env WordPress/WooCommerce container.
 *
 * Run with: pnpm run test:integration
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'This test must be run through WP-CLI.' );
}

/**
 * Fail the command when an integration assertion is false.
 */
function wpp_integration_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}

	WP_CLI::log( 'PASS: ' . $message );
}

$option_names = array(
	'wpp_enabled',
	'wpp_allow_guests',
	'wpp_min_order_amount',
	'wpp_fee_amount',
	'wpp_fee_label',
	'wpp_cart_message_enabled',
	'wpp_cart_messages',
	'wpp_cart_message_mode',
	'wpp_cart_message_threshold',
	'wpp_product_message_enabled',
	'wpp_product_messages',
);
$missing      = '__wpp_missing_option__';
$originals    = array();
$order        = null;
$product      = null;
$cart_item_key = null;

foreach ( $option_names as $option_name ) {
	$originals[ $option_name ] = get_option( $option_name, $missing );
}

try {
	wpp_integration_assert( class_exists( 'WooCommerce' ), 'WooCommerce is loaded.' );
	wpp_integration_assert( is_plugin_active( WPP_PLUGIN_BASENAME ), 'Priority Processing is active.' );

	$plugin = WooCommerce_Priority_Processing::instance();
	wpp_integration_assert( $plugin->frontend_fees instanceof Frontend_Fees, 'Plugin services are initialized.' );
	wpp_integration_assert( null !== wp_next_scheduled( 'wpp_daily_stats_refresh' ), 'Daily statistics cron is scheduled.' );

	update_option( 'wpp_enabled', '1' );
	update_option( 'wpp_allow_guests', '1' );
	update_option( 'wpp_min_order_amount', '0' );
	update_option( 'wpp_fee_amount', '7.50' );
	update_option( 'wpp_fee_label', 'Integration Priority Fee' );

	if ( ! WC()->session ) {
		WC()->initialize_session();
	}
	if ( ! WC()->cart ) {
		WC()->initialize_cart();
	}

	WC()->session->set( 'priority_processing', true );
	$plugin->frontend_fees->add_priority_fee_to_cart();
	$fees = WC()->cart->get_fees();
	wpp_integration_assert( count( $fees ) === 1, 'Priority fee is added to a real WooCommerce cart.' );
	$priority_fee = reset( $fees );
	wpp_integration_assert( abs( (float) $priority_fee->amount - 7.50 ) < 0.001, 'Priority fee amount is correct.' );

	$order = wc_create_order();
	wpp_integration_assert( $order instanceof WC_Order, 'A real WooCommerce order can be created.' );
	$plugin->frontend_fees->save_priority_to_order( $order, array() );

	$stored_order = wc_get_order( $order->get_id() );
	wpp_integration_assert( $stored_order instanceof WC_Order, 'The order is persisted through WooCommerce CRUD.' );
	wpp_integration_assert( 'yes' === $stored_order->get_meta( '_priority_processing' ), 'Priority metadata is persisted.' );
	wpp_integration_assert( 'express' === $stored_order->get_meta( '_priority_service_level' ), 'Express service metadata is persisted.' );

	$matches = wc_get_orders(
		array(
			'limit'      => -1,
			'return'     => 'ids',
			'meta_key'   => '_priority_processing',
			'meta_value' => 'yes',
		)
	);
	wpp_integration_assert( in_array( $order->get_id(), $matches, true ), 'Priority order query works with WooCommerce storage.' );

	update_option( 'wpp_cart_message_enabled', '1' );
	update_option( 'wpp_cart_messages', 'Integration cart message & priority' );
	update_option( 'wpp_cart_message_mode', 'threshold' );
	update_option( 'wpp_cart_message_threshold', '50' );
	$product = new WC_Product_Simple();
	$product->set_name( 'Priority Processing integration product' );
	$product->set_regular_price( '30' );
	$product->set_status( 'publish' );
	$product->save();
	$cart_item_key = WC()->cart->add_to_cart( $product->get_id(), 1 );
	WC()->cart->calculate_totals();
	wpp_integration_assert( '' === $plugin->frontend_messages->get_cart_message(), 'Cart message is hidden below the threshold.' );
	WC()->cart->set_quantity( $cart_item_key, 2 );
	wpp_integration_assert( 'Integration cart message & priority' === $plugin->frontend_messages->get_cart_message(), 'Cart message appears after a quantity change crosses the threshold.' );

	$rendered_cart = do_blocks( '<!-- wp:woocommerce/cart --><div>Integration cart</div><!-- /wp:woocommerce/cart -->' );
	wpp_integration_assert( str_contains( $rendered_cart, 'wpp-cart-block-message' ) && str_contains( $rendered_cart, 'Integration cart message &amp; priority' ), 'Real Cart Block rendering includes the escaped message.' );
	wpp_integration_assert( wp_script_is( 'wpp-cart-messages', 'enqueued' ) && wp_script_is( 'wc-blocks-data-store', 'registered' ), 'Cart message script and WooCommerce data store are available.' );

	$request = new WP_REST_Request( 'GET', '/wc/store/v1/cart' );
	$response = rest_do_request( $request );
	$data = json_decode( wp_json_encode( $response->get_data() ), true );
	wpp_integration_assert( 200 === $response->get_status() && 'Integration cart message & priority' === ( $data['extensions']['wpp-priority']['cart_message'] ?? null ), 'The real Store API includes the eligible cart message.' );
	update_option( 'wpp_allow_guests', '0' );
	$response = rest_do_request( $request );
	$data = json_decode( wp_json_encode( $response->get_data() ), true );
	wpp_integration_assert( '' === ( $data['extensions']['wpp-priority']['cart_message'] ?? null ), 'The Store API hides messages when guest access is disabled.' );
	update_option( 'wpp_allow_guests', '1' );

	update_option( 'wpp_product_message_enabled', '1' );
	update_option( 'wpp_product_messages', 'Integration product message' );
	$previous_query = $GLOBALS['wp_query'];
	$previous_product = $GLOBALS['product'] ?? null;
	try {
		$GLOBALS['wp_query'] = new WP_Query( array( 'p' => $product->get_id(), 'post_type' => 'product' ) );
		$GLOBALS['product'] = $product;
		$content = apply_filters( 'render_block_woocommerce/add-to-cart-with-options', '<button>Add to cart</button>' );
		wpp_integration_assert( str_contains( $content, 'Integration product message' ), 'Block product templates receive the fallback message.' );
		wpp_integration_assert( $content === apply_filters( 'render_block_woocommerce/add-to-cart-form', $content ), 'Nested and legacy product blocks do not duplicate the message.' );
	} finally {
		$GLOBALS['wp_query'] = $previous_query;
		$GLOBALS['product'] = $previous_product;
	}

	WP_CLI::success( 'WooCommerce Priority Processing integration smoke tests passed.' );
} finally {
	if ( $cart_item_key && WC()->cart ) {
		WC()->cart->remove_cart_item( $cart_item_key );
	}
	if ( $product instanceof WC_Product ) {
		$product->delete( true );
	}
	if ( $order instanceof WC_Order ) {
		$order->delete( true );
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
}
