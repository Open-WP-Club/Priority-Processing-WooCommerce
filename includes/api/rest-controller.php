<?php
/**
 * REST API Controller
 * Exposes priority processing statistics via WP REST API
 *
 * Endpoints:
 *   GET  /wp-json/wpp/v1/stats         — cached statistics
 *   POST /wp-json/wpp/v1/stats/refresh — force refresh + return fresh data
 *
 * Both endpoints require the manage_woocommerce capability.
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.5.0
 */

declare(strict_types=1);

/**
 * WPP REST Controller
 *
 * @since 1.5.0
 */
class WPP_REST_Controller {

	private const NAMESPACE = 'wpp/v1';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST routes
	 */
	public function register_routes(): void {
		register_rest_route( self::NAMESPACE, '/stats', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_stats' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );

		register_rest_route( self::NAMESPACE, '/stats/refresh', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'refresh_stats' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );
	}

	/**
	 * Require manage_woocommerce capability
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * GET /wpp/v1/stats — return cached statistics
	 *
	 * @return WP_REST_Response
	 */
	public function get_stats(): WP_REST_Response {
		$statistics = $this->get_statistics_instance();
		if ( ! $statistics ) {
			return new WP_REST_Response( [ 'error' => 'Statistics service not available' ], 503 );
		}

		$stats     = $statistics->get_statistics();
		$formatted = $statistics->get_formatted_statistics( $stats );

		return new WP_REST_Response( [
			'stats'     => $stats,
			'formatted' => $formatted,
		] );
	}

	/**
	 * POST /wpp/v1/stats/refresh — force cache refresh
	 *
	 * @return WP_REST_Response
	 */
	public function refresh_stats(): WP_REST_Response {
		$statistics = $this->get_statistics_instance();
		if ( ! $statistics ) {
			return new WP_REST_Response( [ 'error' => 'Statistics service not available' ], 503 );
		}

		$stats     = $statistics->get_statistics( true );
		$formatted = $statistics->get_formatted_statistics( $stats );

		return new WP_REST_Response( [
			'stats'     => $stats,
			'formatted' => $formatted,
			'message'   => __( 'Statistics refreshed successfully', 'woo-priority' ),
		] );
	}

	/**
	 * @return Core_Statistics|null
	 */
	private function get_statistics_instance(): ?Core_Statistics {
		return WooCommerce_Priority_Processing::instance()->core_statistics;
	}
}
