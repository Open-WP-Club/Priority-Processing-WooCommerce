<?php
/**
 * Frontend Account Handler
 * Shows a persistent Priority Processing badge on the customer's order view page
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.8.0
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend Account Class
 *
 * @since 1.8.0
 */
class Frontend_Account {

	/**
	 * Constructor
	 *
	 * @since 1.8.0
	 */
	public function __construct() {
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_badge' ) );
	}

	/**
	 * Render the priority badge on the customer order details view
	 *
	 * Skipped on the thank-you page, which already shows its own priority
	 * confirmation message (see Core_Orders::display_priority_on_thank_you).
	 * This badge is for return visits via My Account > Orders.
	 *
	 * @since 1.8.0
	 * @param \WC_Order $order Order object.
	 * @return void
	 */
	public function render_badge( \WC_Order $order ): void {
		if ( ! $this->should_display( $order ) ) {
			return;
		}

		$label   = (string) get_option( 'wpp_account_badge_label', self::get_default_label() );
		$message = (string) get_option( 'wpp_account_badge_message', self::get_default_message() );
		?>
		<div class="wpp-account-badge" style="background:#dcfce7;border:1px solid #22c55e;border-radius:4px;padding:12px 16px;margin:15px 0;color:#166534;">
			<strong style="display:flex;align-items:center;gap:8px;font-size:15px;">
				<span style="font-size:18px;">⚡</span>
				<?php echo esc_html( $label ); ?>
			</strong>
			<?php if ( '' !== trim( $message ) ) : ?>
				<p style="margin:8px 0 0 26px;font-size:13px;"><?php echo esc_html( $message ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Determine whether the badge should be displayed for this order/request
	 *
	 * @since 1.8.0
	 * @param \WC_Order $order Order object.
	 * @return bool
	 */
	private function should_display( \WC_Order $order ): bool {
		if ( get_option( 'wpp_account_badge_enabled' ) !== '1' ) {
			return false;
		}

		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return false;
		}

		return $order->get_meta( '_priority_processing' ) === 'yes';
	}

	/**
	 * Default badge label (English source string; translatable via the plugin textdomain)
	 *
	 * @since 1.8.0
	 * @return string
	 */
	public static function get_default_label(): string {
		return __( '⚡ Priority Processing Active', 'woo-priority' );
	}

	/**
	 * Default badge message (English source string; translatable via the plugin textdomain)
	 *
	 * @since 1.8.0
	 * @return string
	 */
	public static function get_default_message(): string {
		return __( 'This order is being processed and shipped with priority.', 'woo-priority' );
	}
}
