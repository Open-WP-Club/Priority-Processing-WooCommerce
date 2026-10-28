<?php
/**
 * Daily priority order limit
 *
 * Caps how many priority orders can be placed per store-timezone day. Only the
 * customer-facing "enable" paths are limited (via
 * Core_Permissions::can_enable_priority_processing()). Orders that already
 * contain priority are never touched, and manual changes by administrators or
 * shop managers on the order screen do not go through that check, so they are
 * intentionally not blocked by the limit.
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.9.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Core_Limits {

	/**
	 * Transient holding today's count (keyed by day so a stale value never crosses midnight).
	 */
	private const CACHE_KEY = 'wpp_today_priority_count';

	/**
	 * Cache lifetime in seconds. Kept short; also flushed on order events.
	 */
	private const CACHE_TTL = 30;

	/**
	 * Order statuses that do not use up a priority slot.
	 *
	 * @var string[]
	 */
	private const EXCLUDED_STATUSES = array( 'wc-cancelled', 'wc-failed', 'wc-refunded', 'wc-checkout-draft', 'trash' );

	/**
	 * Register cache invalidation hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		foreach ( array(
			'woocommerce_new_order',
			'woocommerce_order_status_changed',
			'woocommerce_checkout_create_order',
			'woocommerce_checkout_order_processed',
			'woocommerce_store_api_checkout_order_processed',
		) as $hook ) {
			add_action( $hook, array( self::class, 'flush_cache' ) );
		}
	}

	/**
	 * Configured daily limit (0 = unlimited).
	 *
	 * @return int
	 */
	public static function get_limit(): int {
		return max( 0, (int) get_option( 'wpp_daily_limit', '0' ) );
	}

	/**
	 * Number of priority orders placed today (store timezone).
	 *
	 * @return int
	 */
	public static function get_today_count(): int {
		$timezone = wp_timezone();
		$start    = ( new DateTimeImmutable( 'today', $timezone ) )->getTimestamp();
		$end      = ( new DateTimeImmutable( 'tomorrow', $timezone ) )->getTimestamp() - 1;
		$cache    = self::CACHE_KEY . '_' . gmdate( 'Ymd', $start );

		$cached = get_transient( $cache );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$statuses = function_exists( 'wc_get_order_statuses' )
			? array_values( array_diff( array_keys( wc_get_order_statuses() ), self::EXCLUDED_STATUSES ) )
			: array( 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed' );

		// wc_get_orders works on both HPOS and legacy post storage.
		$result = wc_get_orders(
			array(
				'status'       => $statuses,
				'meta_key'     => '_priority_processing',
				'meta_value'   => 'yes',
				'date_created' => $start . '...' . $end,
				'limit'        => 1,
				'paginate'     => true,
				'return'       => 'ids',
			)
		);

		$count = (int) ( $result->total ?? 0 );
		set_transient( $cache, $count, self::CACHE_TTL );

		return $count;
	}

	/**
	 * Whether today's limit has been used up.
	 *
	 * @return bool
	 */
	public static function is_limit_reached(): bool {
		$limit = self::get_limit();
		return $limit > 0 && self::get_today_count() >= $limit;
	}

	/**
	 * Priority slots left today, or null when unlimited.
	 *
	 * @return int|null
	 */
	public static function get_remaining(): ?int {
		$limit = self::get_limit();
		return $limit > 0 ? max( 0, $limit - self::get_today_count() ) : null;
	}

	/**
	 * Customer-facing notice shown instead of the priority option.
	 *
	 * @return string
	 */
	public static function get_limit_message(): string {
		return __( 'Priority slots for today are sold out', 'woo-priority' );
	}

	/**
	 * Drop the cached count.
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		$start = ( new DateTimeImmutable( 'today', wp_timezone() ) )->getTimestamp();
		delete_transient( self::CACHE_KEY . '_' . gmdate( 'Ymd', $start ) );
	}
}

Core_Limits::init();
