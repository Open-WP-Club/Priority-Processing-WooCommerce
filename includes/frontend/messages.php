<?php
/**
 * Frontend Messages Handler
 * Displays motivational upsell messages for Priority Processing on the cart and product pages
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
 * Frontend Messages Class
 *
 * @since 1.8.0
 */
class Frontend_Messages {

	/**
	 * Constructor
	 *
	 * @since 1.8.0
	 */
	public function __construct( bool $register_hooks = true ) {
		if ( ! $register_hooks ) {
			return;
		}
		add_action( 'woocommerce_before_cart_table', array( $this, 'render_cart_message' ) );
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_product_message' ) );
		add_filter( 'render_block_woocommerce/cart', array( $this, 'render_cart_block_message' ) );
		add_filter( 'render_block_woocommerce/add-to-cart-form', array( $this, 'render_product_block_message' ) );
		add_filter( 'render_block_woocommerce/add-to-cart-with-options', array( $this, 'render_product_block_message' ) );
	}

	/**
	 * Render the motivational message above the cart items table
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function render_cart_message(): void {
		$message = $this->get_cart_message();
		if ( '' !== $message ) {
			$this->render_message( $message );
		}
	}

	/** Return eligible cart text for both classic templates and the Store API. */
	public function get_cart_message(): string {
		if ( ! $this->should_display( 'wpp_cart_message_enabled', 'wpp_cart_message_mode', 'wpp_cart_message_threshold' ) ) {
			return '';
		}
		return $this->get_random_message( 'wpp_cart_messages' );
	}

	/** The Cart Block does not execute woocommerce_before_cart_table. */
	public function render_cart_block_message( string $content ): string {
		if ( is_admin() || '' === trim( $content ) ) {
			return $content;
		}

		// Keep the container even below the threshold so Store API updates can reveal it.
		wp_enqueue_script(
			'wpp-cart-messages',
			WPP_PLUGIN_URL . 'assets/js/cart-messages.js',
			array( 'wp-data', 'wc-blocks-data-store' ),
			WPP_VERSION,
			true
		);
		ob_start();
		$this->render_cart_message();
		$message = (string) ob_get_clean();
		return '<div class="wpp-cart-block-message" aria-live="polite">' . $message . '</div>' . $content;
	}

	/** Support block product templates without duplicating legacy hook output. */
	public function render_product_block_message( string $content ): string {
		if ( is_admin() || ! is_product() || '' === trim( $content ) || str_contains( $content, 'wpp-motivation-message' ) ) {
			return $content;
		}
		ob_start();
		$this->render_product_message();
		return $content . (string) ob_get_clean();
	}

	/**
	 * Render the motivational message under the add-to-cart button on single product pages
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function render_product_message(): void {
		if ( ! $this->should_display( 'wpp_product_message_enabled', 'wpp_product_message_mode', 'wpp_product_message_threshold' ) ) {
			return;
		}

		$message = $this->get_random_message( 'wpp_product_messages' );
		if ( '' === $message ) {
			return;
		}

		$this->render_message( $message );
	}

	/**
	 * Output the message markup
	 *
	 * @since 1.8.0
	 * @param string $message Message text.
	 * @return void
	 */
	private function render_message( string $message ): void {
		?>
		<div class="wpp-motivation-message" style="background:#fff8e1;border-left:4px solid #ffb300;border-radius:4px;padding:12px 16px;margin:15px 0;font-size:14px;line-height:1.5;color:#5f4500;">
			<?php echo esc_html( $message ); ?>
		</div>
		<?php
	}

	/**
	 * Determine whether a message location should be displayed
	 *
	 * @since 1.8.0
	 * @param string $enabled_option   Option name for the location's enabled toggle.
	 * @param string $mode_option      Option name for the display mode ('always'|'threshold').
	 * @param string $threshold_option Option name for the minimum cart subtotal threshold.
	 * @return bool
	 */
	private function should_display( string $enabled_option, string $mode_option, string $threshold_option ): bool {
		if ( (string) get_option( 'wpp_enabled' ) !== '1' ) {
			return false;
		}

		if ( ! Core_Permissions::can_access_priority_processing() ) {
			return false;
		}

		if ( (string) get_option( $enabled_option ) !== '1' ) {
			return false;
		}

		$mode = get_option( $mode_option, 'always' );
		if ( 'threshold' !== $mode ) {
			return true;
		}

		if ( ! WC()->cart ) {
			return false;
		}

		$threshold = (float) get_option( $threshold_option, '0' );
		return WC()->cart->get_subtotal() >= $threshold;
	}

	/**
	 * Pick a random message from a newline-separated option value
	 *
	 * @since 1.8.0
	 * @param string $option_name Option storing one message per line.
	 * @return string
	 */
	private function get_random_message( string $option_name ): string {
		$default = 'wpp_cart_messages' === $option_name
			? self::get_default_cart_messages()
			: self::get_default_product_messages();
		$raw = (string) get_option( $option_name, $default );

		$messages = array_values(
			array_filter(
				array_map( 'trim', explode( "\n", $raw ) ),
				static fn( string $line ): bool => '' !== $line
			)
		);

		if ( empty( $messages ) ) {
			return '';
		}

		return $messages[ array_rand( $messages ) ];
	}

	/**
	 * Default cart page messages (English source strings; translatable via the plugin textdomain)
	 *
	 * @since 1.8.0
	 * @return string
	 */
	public static function get_default_cart_messages(): string {
		$messages = array(
			__( '⚡ Skip the line — add Priority Processing and get your order moving before everyone else\'s.', 'woo-priority' ),
			__( '🚀 Time is money. Priority Processing means days less waiting.', 'woo-priority' ),
			__( '📦 Your order could be on its way faster — just add Priority Processing.', 'woo-priority' ),
			__( '🔥 Priority customers get their orders out about 2x faster on average.', 'woo-priority' ),
			__( '🎯 Need it now, not next week? Priority Processing is for you.', 'woo-priority' ),
			__( '⏱ Every minute you wait is a minute you didn\'t have to. Speed things up with one click.', 'woo-priority' ),
		);

		return implode( "\n", $messages );
	}

	/**
	 * Default product page messages (English source strings; translatable via the plugin textdomain)
	 *
	 * @since 1.8.0
	 * @return string
	 */
	public static function get_default_product_messages(): string {
		$messages = array(
			__( '⚡ In a hurry for this one? Add Priority Processing at checkout and get it first.', 'woo-priority' ),
			__( '🚀 Don\'t want to wait? Turn on Priority Processing on the next step.', 'woo-priority' ),
			__( '🎁 This item is in high demand — Priority Processing helps make sure it reaches you faster.', 'woo-priority' ),
			__( '🏆 Our VIP customers choose Priority Processing. Why not you?', 'woo-priority' ),
			__( '📦 Ready to ship the moment you order — with Priority Processing.', 'woo-priority' ),
			__( '⏳ Every extra day of waiting is a day too many. Speed it up with Priority Processing.', 'woo-priority' ),
		);

		return implode( "\n", $messages );
	}
}
