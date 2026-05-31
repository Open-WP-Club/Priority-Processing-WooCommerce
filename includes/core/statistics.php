<?php
/**
 * Core Statistics Handler
 * Manages statistics calculation, caching, and display for priority orders
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.0.0
 */

declare(strict_types=1);

/**
 * Core Statistics Class
 *
 * @since 1.0.0
 */
class Core_Statistics {

	private readonly string $cache_key;

	public function __construct() {
		$this->cache_key = 'wpp_statistics_data';
		add_action( 'wp_ajax_wpp_refresh_stats', [ $this, 'ajax_refresh_stats' ] );
	}

	/**
	 * Get statistics data with caching
	 *
	 * @param bool $force_refresh Skip cache and recalculate.
	 * @return array<string, mixed>
	 */
	public function get_statistics( bool $force_refresh = false ): array {
		if ( ! $force_refresh ) {
			$cached = get_transient( $this->cache_key );
			if ( $cached !== false ) {
				wpp_log( 'Statistics loaded from cache' );
				return $cached;
			}
		}

		wpp_log( 'Calculating fresh statistics...' );
		$stats = $this->calculate_statistics();
		set_transient( $this->cache_key, $stats, DAY_IN_SECONDS );
		wpp_log( 'Statistics cached for 24 hours' );

		return $stats;
	}

	/**
	 * Count orders matching given args using a lightweight COUNT query.
	 * Uses wc_get_orders() with paginate:true so WC runs a COUNT(*) instead of
	 * fetching IDs — safe with both HPOS and legacy storage.
	 *
	 * @param array<string, mixed> $args
	 * @return int
	 */
	private function count_orders( array $args ): int {
		$result = wc_get_orders( array_merge( $args, [
			'paginate' => true,
			'limit'    => 1,
		] ) );

		return (int) ( $result->total ?? 0 );
	}

	/**
	 * Calculate all statistics in one pass using efficient COUNT queries.
	 *
	 * @return array<string, mixed>
	 */
	private function calculate_statistics(): array {
		$stats = [
			'total_priority_orders'      => 0,
			'total_priority_revenue'     => 0.0,
			'today_priority_orders'      => 0,
			'this_week_priority_orders'  => 0,
			'this_month_priority_orders' => 0,
			'priority_percentage'        => 0.0,
			'average_priority_fee'       => 0.0,
			'total_orders'               => 0,
			'last_updated'               => current_time( 'mysql' ),
		];

		$all_statuses = array_keys( wc_get_order_statuses() );

		$priority_base = [
			'meta_key'   => '_priority_processing',
			'meta_value' => 'yes',
			'status'     => $all_statuses,
		];

		$stats['total_priority_orders'] = $this->count_orders( $priority_base );

		$stats['today_priority_orders'] = $this->count_orders( array_merge( $priority_base, [
			'date_created' => '>=' . gmdate( 'Y-m-d' ),
		] ) );

		$stats['this_week_priority_orders'] = $this->count_orders( array_merge( $priority_base, [
			'date_created' => '>=' . gmdate( 'Y-m-d', strtotime( 'monday this week' ) ),
		] ) );

		$stats['this_month_priority_orders'] = $this->count_orders( array_merge( $priority_base, [
			'date_created' => '>=' . gmdate( 'Y-m-01' ),
		] ) );

		$stats['total_orders'] = $this->count_orders( [ 'status' => $all_statuses ] );

		$stats = $this->calculate_priority_revenue( $stats );

		wpp_log( 'Statistics calculation completed - Total Priority Orders: ' . $stats['total_priority_orders'] );

		return $stats;
	}

	/**
	 * Calculate revenue from priority fees in batches of 100 to limit memory usage.
	 *
	 * @param array<string, mixed> $stats Partial stats array.
	 * @return array<string, mixed>
	 */
	private function calculate_priority_revenue( array $stats ): array {
		$fee_label        = get_option( 'wpp_fee_label', 'Priority Processing & Express Shipping' );
		$priority_revenue = 0.0;
		$total_fee_amount = 0.0;
		$fee_count        = 0;

		if ( $stats['total_priority_orders'] === 0 ) {
			return $this->apply_derived_stats( $stats, 0.0, 0 );
		}

		try {
			$base = [
				'meta_key'   => '_priority_processing',
				'meta_value' => 'yes',
				'status'     => [ 'completed', 'processing', 'on-hold', 'pending' ],
				'limit'      => 100,
				'paginate'   => true,
			];

			$page = 1;
			do {
				$result = wc_get_orders( array_merge( $base, [ 'paged' => $page ] ) );

				foreach ( $result->orders as $order ) {
					foreach ( $order->get_fees() as $fee ) {
						if ( str_contains( $fee->get_name(), 'Priority' ) || $fee->get_name() === $fee_label ) {
							$amount           = floatval( $fee->get_total() );
							$priority_revenue += $amount;
							$total_fee_amount += $amount;
							$fee_count++;
						}
					}
				}

				$page++;
			} while ( $page <= $result->max_num_pages );

			wpp_log( 'Revenue calculation complete (' . $result->total . ' orders, ' . $fee_count . ' fees)' );

		} catch ( Exception $e ) {
			wpp_log( 'Error calculating priority revenue: ' . $e->getMessage() );
		}

		$stats['total_priority_revenue'] = $priority_revenue;

		return $this->apply_derived_stats( $stats, $total_fee_amount, $fee_count );
	}

	/**
	 * Calculate percentage and average fee from raw totals.
	 *
	 * @param array<string, mixed> $stats
	 * @param float                $total_fee_amount
	 * @param int                  $fee_count
	 * @return array<string, mixed>
	 */
	private function apply_derived_stats( array $stats, float $total_fee_amount, int $fee_count ): array {
		if ( $stats['total_orders'] > 0 ) {
			$stats['priority_percentage'] = round(
				( $stats['total_priority_orders'] / $stats['total_orders'] ) * 100,
				1
			);
		}

		if ( $fee_count > 0 ) {
			$stats['average_priority_fee'] = round( $total_fee_amount / $fee_count, 2 );
		}

		return $stats;
	}

	/**
	 * Clear statistics cache
	 */
	public function clear_cache(): void {
		delete_transient( $this->cache_key );
		wpp_log( 'Statistics cache cleared' );
	}

	/**
	 * Get formatted statistics for display
	 *
	 * @param array<string, mixed>|null $stats Raw stats (fetched if null).
	 * @return array<string, string>
	 */
	public function get_formatted_statistics( ?array $stats = null ): array {
		if ( $stats === null ) {
			$stats = $this->get_statistics();
		}

		return [
			'total_priority_orders'      => number_format( $stats['total_priority_orders'] ),
			'total_priority_revenue'     => wc_price( $stats['total_priority_revenue'] ),
			'today_priority_orders'      => number_format( $stats['today_priority_orders'] ),
			'this_week_priority_orders'  => number_format( $stats['this_week_priority_orders'] ),
			'this_month_priority_orders' => number_format( $stats['this_month_priority_orders'] ),
			'priority_percentage'        => $stats['priority_percentage'] . '%',
			'average_priority_fee'       => wc_price( $stats['average_priority_fee'] ),
			'last_updated'               => gmdate( 'Y-m-d H:i:s', strtotime( $stats['last_updated'] ) ),
		];
	}

	/**
	 * AJAX handler for refreshing statistics
	 */
	public function ajax_refresh_stats(): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpp_admin_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		try {
			$stats     = $this->get_statistics( true );
			$formatted = $this->get_formatted_statistics( $stats );

			$formatted['last_updated'] = gmdate( 'H:i' );

			wpp_log( 'Statistics refreshed via AJAX' );

			wp_send_json_success( [
				'stats'     => $stats,
				'formatted' => $formatted,
				'message'   => __( 'Statistics updated successfully', 'woo-priority' ),
			] );
		} catch ( Exception $e ) {
			wpp_log( 'Error refreshing statistics: ' . $e->getMessage() );
			wp_send_json_error( 'Error refreshing statistics: ' . $e->getMessage() );
		}
	}

	/**
	 * Get cache info for admin display
	 *
	 * @return array<string, mixed>
	 */
	public function get_cache_info(): array {
		return [
			'is_cached'            => get_transient( $this->cache_key ) !== false,
			'cache_duration_hours' => DAY_IN_SECONDS / HOUR_IN_SECONDS,
			'cache_key'            => $this->cache_key,
		];
	}

	/**
	 * Schedule daily cache refresh
	 */
	public function schedule_daily_refresh(): void {
		if ( ! wp_next_scheduled( 'wpp_daily_stats_refresh' ) ) {
			wp_schedule_event( time(), 'daily', 'wpp_daily_stats_refresh' );
		}
		add_action( 'wpp_daily_stats_refresh', [ $this, 'daily_cache_refresh' ] );
	}

	/** @internal */
	public function daily_cache_refresh(): void {
		wpp_log( 'Running daily statistics cache refresh' );
		$this->get_statistics( true );
	}

	/**
	 * Clean up scheduled events on deactivation
	 */
	public function cleanup_scheduled_events(): void {
		wp_clear_scheduled_hook( 'wpp_daily_stats_refresh' );
	}
}
