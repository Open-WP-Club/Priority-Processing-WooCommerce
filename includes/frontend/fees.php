<?php
/**
 * Frontend Fees Handler
 * Manages fee calculation and order metadata for priority processing
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.0.0
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend Fees Class
 *
 * This class adds the priority fee as a cart fee and saves the related order metadata.
 *
 * @since 1.0.0
 */
class Frontend_Fees {
	public const FEE_META_KEY   = '_wpp_priority_fee';
	public const FEE_META_VALUE = 'yes';

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Add priority fee to cart as a separate line item.
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_priority_fee_to_cart' ), 10 );

		// Save priority processing status to orders during checkout.
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_priority_to_order' ), 10, 2 );

		// Mark the generated order fee so it can be identified independently
		// of its translated or merchant-configured display name.
		add_action( 'woocommerce_checkout_create_order_fee_item', array( $this, 'mark_priority_fee_item' ), 10, 4 );
	}

	/**
	 * Mark the priority fee while WooCommerce copies it from the cart.
	 *
	 * @since 1.8.0
	 * @param \WC_Order_Item_Fee $item    Order fee item.
	 * @param string             $fee_key Cart fee key.
	 * @param object             $fee     Cart fee object.
	 * @param \WC_Order          $order   Order object.
	 * @return void
	 */
	public function mark_priority_fee_item( \WC_Order_Item_Fee $item, string $fee_key, object $fee, \WC_Order $order ): void {
		$fee_label = (string) get_option( 'wpp_fee_label', 'Priority Processing & Express Shipping' );
		if (
			Core_Permissions::is_priority_active()
			&& Core_Permissions::can_enable_priority_processing()
			&& $item->get_name() === $fee_label
		) {
			$item->add_meta_data( self::FEE_META_KEY, self::FEE_META_VALUE, true );
		}
	}

	/**
	 * Determine whether an order fee belongs to this plugin.
	 *
	 * Exact-name checks retain compatibility with orders created before 1.8.0
	 * without risking a match against unrelated fees containing "Priority".
	 *
	 * @since 1.8.0
	 * @param \WC_Order_Item_Fee $fee              Order fee item.
	 * @param string             $configured_label Current configured fee label.
	 * @return bool
	 */
	public static function is_priority_fee( \WC_Order_Item_Fee $fee, string $configured_label ): bool {
		if ( self::FEE_META_VALUE === $fee->get_meta( self::FEE_META_KEY, true ) ) {
			return true;
		}

		$legacy_labels = array_unique(
			array(
				$configured_label,
				'Priority Processing & Express Shipping',
				__( 'Priority Processing & Express Shipping', 'woo-priority' ),
			)
		);

		return in_array( $fee->get_name(), $legacy_labels, true );
	}

	/**
	 * Add priority processing fee to cart as a separate line item
	 *
	 * @since 1.4.2
	 * @return void
	 */
	public function add_priority_fee_to_cart(): void {
		if ( ! Core_Permissions::is_priority_active() || ! Core_Permissions::can_enable_priority_processing() ) {
			return;
		}

		$fee_amount = floatval( get_option( 'wpp_fee_amount', '5.00' ) );
		$fee_label  = get_option( 'wpp_fee_label', 'Priority Processing & Express Shipping' );

		if ( $fee_amount > 0 && WC()->cart ) {
			// Add fee as a separate line item in cart.
			WC()->cart->add_fee( $fee_label, $fee_amount, true );
		}
	}

	/**
	 * Save priority processing data to order
	 *
	 * @since 1.0.0
	 * @param \WC_Order                $order Order object.
	 * @param array<string, mixed>     $data  Checkout data.
	 * @return void
	 */
	public function save_priority_to_order( \WC_Order $order, array $data ): void {
		if ( ! WC()->session ) {
			return;
		}

		$priority = WC()->session->get( 'priority_processing', false );
		if ( ( $priority === true || $priority === 1 || $priority === '1' )
			&& Core_Permissions::can_enable_priority_processing() ) {
			$this->apply_priority_to_order( $order );
		}
	}

	/**
	 * Apply priority processing to the order
	 *
	 * @since 1.0.0
	 * @param \WC_Order $order Order object.
	 * @return void
	 */
	private function apply_priority_to_order( \WC_Order $order ): void {
		$fee_amount = floatval( get_option( 'wpp_fee_amount', '5.00' ) );

		// Save priority meta data - WooCommerce handles fee transfer automatically.
		$order->update_meta_data( '_priority_processing', 'yes' );

		// Add shipping-specific meta data for shipping plugin integration.
		$order->update_meta_data( '_requires_express_shipping', 'yes' );
		$order->update_meta_data( '_priority_fee_amount', $fee_amount );
		$order->update_meta_data( '_priority_service_level', 'express' );

		// Fire action hook for shipping plugins that might want to integrate.
		do_action( 'wpp_priority_order_created', $order, $fee_amount );

		$order->save();
	}


}
