<?php
/**
 * Cart page priority toggle
 * One-click "Add Priority Processing" button for the classic cart and the Cart Block
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.9.0
 */

declare(strict_types=1);

use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend Cart Button Class
 *
 * The toggle itself reuses the wpp_update_priority AJAX action, so the session
 * and fee behaviour are identical to the checkout checkbox.
 *
 * @since 1.9.0
 */
class Frontend_Cart_Button {

	public const EXTENSION_NAMESPACE = 'wpp-cart-button';

	/**
	 * Variants already rendered on this request, so the footer fallback can skip them
	 *
	 * @var array<string, bool>
	 */
	private array $rendered = array();

	/** Whether the script and its data were already enqueued on this request. */
	private bool $enqueued = false;

	/**
	 * Constructor
	 *
	 * @since 1.9.0
	 */
	public function __construct( bool $register_hooks = true ) {
		if ( ! $register_hooks ) {
			return;
		}
		// Runs before the "Proceed to checkout" button (priority 20) inside .cart_totals.
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'render_classic_button' ), 5 );
		add_filter( 'render_block_woocommerce/cart', array( $this, 'render_cart_block_button' ) );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_store_api_data' ) );
		add_action( 'wp_footer', array( $this, 'render_fallback' ), 20 );
	}

	/**
	 * Whether the button may be shown for the current cart and visitor
	 *
	 * @since 1.9.0
	 * @return bool
	 */
	public function is_eligible(): bool {
		if ( (string) get_option( 'wpp_enabled' ) !== '1' ) {
			return false;
		}

		if ( (string) get_option( 'wpp_cart_button_enabled', '0' ) !== '1' ) {
			return false;
		}

		if ( ! Core_Permissions::can_enable_priority_processing() ) {
			return false;
		}

		if ( class_exists( 'Core_Limits' ) && method_exists( 'Core_Limits', 'is_limit_reached' ) && Core_Limits::is_limit_reached() ) {
			return false;
		}

		// Same minimum order rule as the checkout checkbox (Frontend_Checkout::should_display_field).
		$min_amount = (float) get_option( 'wpp_min_order_amount', '0' );
		if ( $min_amount > 0 && WC()->cart && WC()->cart->get_subtotal() < $min_amount ) {
			return false;
		}

		return true;
	}

	/**
	 * Render the button inside the classic cart totals box
	 *
	 * @since 1.9.0
	 * @return void
	 */
	public function render_classic_button(): void {
		if ( ! empty( $this->rendered['classic'] ) || ! $this->is_eligible() ) {
			return;
		}

		$this->rendered['classic'] = true;
		$this->enqueue_assets( false );
		echo $this->get_button_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in get_button_markup().
	}

	/** The Cart Block does not execute woocommerce_proceed_to_checkout, so add a container hydrated by JS. */
	public function render_cart_block_button( string $content ): string {
		if ( is_admin() || '' === trim( $content ) || ! empty( $this->rendered['block'] ) ) {
			return $content;
		}

		$this->rendered['block'] = true;

		// Keep the container even when ineligible so Store API updates can reveal it.
		$this->enqueue_assets( true );
		$server = $this->is_eligible() ? $this->get_button_markup() : '';
		return '<div class="wpp-cart-button-block" aria-live="polite">' . $server . '</div>' . $content;
	}

	/**
	 * Register the Store API cart data read by cart-button.js
	 *
	 * @since 1.9.0
	 * @return void
	 */
	public function register_store_api_data(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => CartSchema::IDENTIFIER,
				'namespace'       => self::EXTENSION_NAMESPACE,
				'data_callback'   => array( $this, 'get_extension_data' ),
				'schema_callback' => array( $this, 'get_extension_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Store API cart data
	 *
	 * @since 1.9.0
	 * @return array<string, mixed>
	 */
	public function get_extension_data(): array {
		return array(
			'eligible'     => $this->is_eligible(),
			'is_active'    => Core_Permissions::is_priority_active(),
			'fee_amount'   => $this->get_fee_amount(),
			'add_label'    => __( 'Add Priority Processing', 'woo-priority' ),
			'remove_label' => __( 'Remove Priority Processing', 'woo-priority' ),
			'description'  => (string) get_option( 'wpp_description', __( 'Your order will be processed with priority and shipped via express delivery', 'woo-priority' ) ),
		);
	}

	/**
	 * Store API schema for the cart data
	 *
	 * @since 1.9.0
	 * @return array<string, array<string, mixed>>
	 */
	public function get_extension_schema(): array {
		$string = static fn( string $description ): array => array(
			'description' => $description,
			'type'        => 'string',
			'readonly'    => true,
		);

		return array(
			'eligible'     => array(
				'description' => __( 'Whether the cart page toggle may be shown', 'woo-priority' ),
				'type'        => 'boolean',
				'readonly'    => true,
			),
			'is_active'    => array(
				'description' => __( 'Whether priority processing is currently active', 'woo-priority' ),
				'type'        => 'boolean',
				'readonly'    => true,
			),
			'fee_amount'   => array(
				'description' => __( 'Priority processing fee amount', 'woo-priority' ),
				'type'        => 'number',
				'readonly'    => true,
			),
			'add_label'    => $string( __( 'Label for adding priority processing', 'woo-priority' ) ),
			'remove_label' => $string( __( 'Label for removing priority processing', 'woo-priority' ) ),
			'description'  => $string( __( 'Description text', 'woo-priority' ) ),
		);
	}

	/**
	 * Place the button with JS when a theme layout builder (e.g. WoodMart) bypasses the WooCommerce hooks
	 *
	 * @since 1.9.0
	 * @return void
	 */
	public function render_fallback(): void {
		if ( is_admin() || ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return;
		}

		if ( ! empty( $this->rendered['classic'] ) || ! empty( $this->rendered['block'] ) || ! $this->is_eligible() ) {
			return;
		}

		$this->rendered['classic'] = true;
		$this->enqueue_assets( false );

		// Selectors cover core WooCommerce markup plus common builder wrappers.
		$selectors = array( '.cart_totals', '.cart-collaterals', '.wd-cart-totals', '.woocommerce-cart-form' );

		wp_print_inline_script_tag(
			'(function(){var h=' . wp_json_encode( $this->get_button_markup() ) . ',s=' . wp_json_encode( $selectors ) . ';'
			. 'function go(){if(document.querySelector(".wpp-cart-button"))return true;'
			. 'for(var i=0;i<s.length;i++){var t=document.querySelector(s[i]);if(!t)continue;'
			. 'var w=document.createElement("div");w.innerHTML=h;var n=w.firstElementChild;if(!n)return true;'
			. 't.insertBefore(n,t.firstChild);return true;}return false;}'
			. 'if(!go()){document.addEventListener("DOMContentLoaded",go,{once:true});}'
			. 'if(window.jQuery){jQuery(document.body).on("updated_cart_totals",go);}})();'
		);
	}

	/** Configured fee, shared by the markup and the Store API data. */
	private function get_fee_amount(): float {
		return (float) get_option( 'wpp_fee_amount', '5.00' );
	}

	/**
	 * Button markup for the classic cart and the footer fallback (already escaped)
	 *
	 * @since 1.9.0
	 * @return string
	 */
	private function get_button_markup(): string {
		$active      = Core_Permissions::is_priority_active();
		$fee_amount  = $this->get_fee_amount();
		$description = (string) get_option( 'wpp_description', __( 'Your order will be processed with priority and shipped via express delivery', 'woo-priority' ) );
		$label       = $active
			? __( 'Remove Priority Processing', 'woo-priority' )
			: __( 'Add Priority Processing', 'woo-priority' );
		$icon        = $active ? '✓ ' : '⚡ ';

		$price = ( ! $active && $fee_amount > 0 )
			? ' <span class="wpp-cart-button__price">(+' . wc_price( $fee_amount ) . ')</span>'
			: '';

		$html  = '<div class="wpp-cart-button" style="margin:0 0 15px;">';
		$html .= '<button type="button" class="button wpp-cart-button__toggle" data-wpp-cart-toggle data-wpp-active="' . ( $active ? '1' : '0' ) . '" aria-pressed="' . ( $active ? 'true' : 'false' ) . '" style="width:100%;">';
		$html .= esc_html( $icon . $label ) . $price . '</button>';
		if ( '' !== $description ) {
			$html .= '<small class="wpp-cart-button__description" style="display:block;margin-top:6px;color:#6c757d;font-size:13px;line-height:1.4;">' . esc_html( $description ) . '</small>';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Enqueue the toggle script (cart page only, once per request)
	 *
	 * @since 1.9.0
	 * @param bool $block Whether the Cart Block store is needed.
	 * @return void
	 */
	private function enqueue_assets( bool $block ): void {
		if ( $this->enqueued ) {
			return;
		}
		$this->enqueued = true;

		wp_enqueue_script(
			'wpp-cart-button',
			WPP_PLUGIN_URL . 'assets/js/cart-button.js',
			$block ? array( 'wp-data', 'wc-blocks-data-store' ) : array(),
			WPP_VERSION,
			true
		);
		wp_localize_script(
			'wpp-cart-button',
			'wppCartButton',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wpp_nonce' ),
				'error'    => __( 'Unable to update priority processing. Please try again.', 'woo-priority' ),
			)
		);
	}
}
