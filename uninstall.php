<?php
/**
 * Plugin uninstall handler
 * Runs when the plugin is deleted from WP Admin → Plugins.
 * Removes all plugin options, transients, and scheduled events.
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.5.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove all plugin options.
$options = [
	'wpp_enabled',
	'wpp_fee_amount',
	'wpp_checkbox_label',
	'wpp_description',
	'wpp_fee_label',
	'wpp_section_title',
	'wpp_allowed_user_roles',
	'wpp_allow_guests',
];

foreach ( $options as $option ) {
	delete_option( $option );
}

// Remove cached statistics.
delete_transient( 'wpp_statistics_data' );

// Remove scheduled cron event.
wp_clear_scheduled_hook( 'wpp_daily_stats_refresh' );
