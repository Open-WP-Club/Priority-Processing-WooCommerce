<?php
/**
 * Smoke tests executed inside the wp-env WordPress/WooCommerce container.
 *
 * Run with: npm run test:integration
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
);
$missing      = '__wpp_missing_option__';
$originals    = array();
$order        = null;

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

	WP_CLI::success( 'WooCommerce Priority Processing integration smoke tests passed.' );
} finally {
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
