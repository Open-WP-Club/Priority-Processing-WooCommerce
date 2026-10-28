<?php
/**
 * WooCommerce settings tab
 * Registers the "Priority Processing" tab in WooCommerce > Settings and defines its fields
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.9.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_WC_Settings_Tab {

	public const TAB_ID           = 'priority-processing';
	public const LEGACY_PAGE_SLUG = 'woo-priority-processing';

	private ?Admin_Settings $settings;

	public function __construct( ?Admin_Settings $settings = null ) {
		$this->settings = $settings;

		add_filter( 'woocommerce_get_settings_pages', [ $this, 'register_page' ] );
		add_action( 'woocommerce_admin_field_wpp_toggle', [ $this, 'render_toggle_field' ] );
		add_action( 'woocommerce_admin_field_wpp_stats', [ $this, 'render_stats_field' ] );
		add_action( 'woocommerce_admin_field_wpp_access_summary', [ $this, 'render_access_summary_field' ] );

		// WooCommerce saves the fields itself; run each value through the plugin's own sanitizers.
		foreach ( array_keys( $this->get_sanitizers() ) as $option ) {
			add_filter( 'woocommerce_admin_settings_sanitize_option_' . $option, [ $this, 'sanitize_option' ], 10, 3 );
		}
	}

	/**
	 * URL of the settings tab (optionally a specific section)
	 */
	public static function get_tab_url( string $section = '' ): string {
		$url = 'admin.php?page=wc-settings&tab=' . self::TAB_ID;
		if ( $section !== '' ) {
			$url .= '&section=' . $section;
		}

		return admin_url( $url );
	}

	/**
	 * Whether the current admin request is for our settings tab
	 */
	public static function is_tab_request(): bool {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['tab'] ) ) : '';

		return $tab === self::TAB_ID;
	}

	/**
	 * Target for the old WooCommerce > Priority Processing page, or null when the request is for another page
	 */
	public static function get_legacy_redirect_url(): ?string {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['page'] ) ) : '';

		return $page === self::LEGACY_PAGE_SLUG ? self::get_tab_url() : null;
	}

	/**
	 * Add our settings page to WooCommerce's list of settings pages
	 *
	 * The page class extends WC_Settings_Page, so its file is only loaded here, once WooCommerce is available.
	 *
	 * @param array<int|string, mixed> $pages
	 * @return array<int|string, mixed>
	 */
	public function register_page( array $pages ): array {
		if ( ! class_exists( 'WC_Settings_Page' ) ) {
			return $pages;
		}

		require_once __DIR__ . '/class-wc-settings-priority.php';

		$pages[] = new WPP_WC_Settings_Page();

		return $pages;
	}

	/**
	 * Section id => label (the empty id is WooCommerce's default section)
	 *
	 * @return array<string, string>
	 */
	public static function get_sections(): array {
		return [
			''           => __( 'General', 'woo-priority' ),
			'access'     => __( 'Access', 'woo-priority' ),
			'display'    => __( 'Display', 'woo-priority' ),
			'messages'   => __( 'Messages', 'woo-priority' ),
			'badge'      => __( 'Customer Account Badge', 'woo-priority' ),
			'statistics' => __( 'Statistics', 'woo-priority' ),
		];
	}

	/**
	 * WooCommerce field definitions for a section
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_fields( string $section = '' ): array {
		switch ( $section ) {
			case 'access':
				return self::access_fields();
			case 'display':
				return self::display_fields();
			case 'messages':
				return self::messages_fields();
			case 'badge':
				return self::badge_fields();
			case 'statistics':
				return self::statistics_fields();
			default:
				return self::general_fields();
		}
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function general_fields(): array {
		return [
			[
				'title' => __( 'General', 'woo-priority' ),
				'type'  => 'title',
				'id'    => 'wpp_section_general',
			],
			[
				'title'   => __( 'Enable Feature', 'woo-priority' ),
				'id'      => 'wpp_enabled',
				'type'    => 'wpp_toggle',
				'default' => '1',
				'desc'    => __( 'Enable or disable the priority processing option at checkout', 'woo-priority' ),
			],
			[
				'title'             => __( 'Additional Fee', 'woo-priority' ),
				'id'                => 'wpp_fee_amount',
				'type'              => 'number',
				'default'           => '5.00',
				'desc_tip'          => __( 'Amount to charge for priority processing and express shipping', 'woo-priority' ),
				'custom_attributes' => [ 'step' => '0.01', 'min' => '0' ],
			],
			[
				'title'    => __( 'Fee Label', 'woo-priority' ),
				'id'       => 'wpp_fee_label',
				'type'     => 'text',
				'default'  => 'Priority Processing & Express Shipping',
				'desc_tip' => __( 'How the fee appears in cart totals and order summaries', 'woo-priority' ),
			],
			[
				'title'             => __( 'Minimum Order Amount', 'woo-priority' ),
				'id'                => 'wpp_min_order_amount',
				'type'              => 'number',
				'default'           => '0',
				'desc_tip'          => __( 'Minimum cart subtotal required to show the priority option. Set to 0 to disable.', 'woo-priority' ),
				'custom_attributes' => [ 'step' => '0.01', 'min' => '0' ],
			],
			[
				'title'             => __( 'Daily Limit', 'woo-priority' ),
				'id'                => 'wpp_daily_limit',
				'type'              => 'number',
				'default'           => '0',
				'desc'              => __( 'Maximum number of priority orders per day. Once reached, the option is hidden or disabled for customers. Set to 0 for unlimited.', 'woo-priority' ),
				'custom_attributes' => [ 'step' => '1', 'min' => '0' ],
			],
			[
				'title'    => __( 'Same-Day Cutoff Time', 'woo-priority' ),
				'id'       => 'wpp_cutoff_time',
				'type'     => 'time',
				'default'  => '',
				'desc_tip' => __( 'Show a live countdown at checkout until this time (store timezone). Leave empty to disable.', 'woo-priority' ),
			],
			[
				'type' => 'sectionend',
				'id'   => 'wpp_section_general',
			],
		];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function access_fields(): array {
		$roles = class_exists( 'Core_Permissions' ) ? Core_Permissions::get_available_user_roles() : [];

		return [
			[
				'title' => __( 'Access', 'woo-priority' ),
				'type'  => 'title',
				'id'    => 'wpp_section_access',
			],
			[
				'title'    => __( 'Allowed User Roles', 'woo-priority' ),
				'id'       => 'wpp_allowed_user_roles',
				'type'     => 'multiselect',
				'class'    => 'wc-enhanced-select',
				'css'      => 'min-width: 350px;',
				'default'  => [ 'customer' ],
				'options'  => $roles,
				'desc'     => __( 'Select which user roles can access the priority processing option. Shop Managers and Administrators always have access.', 'woo-priority' ),
				'desc_tip' => false,
			],
			[
				'title'   => __( 'Guest Access', 'woo-priority' ),
				'id'      => 'wpp_allow_guests',
				'type'    => 'wpp_toggle',
				'default' => '1',
				'desc'    => __( 'Allow non-logged-in users (guests) to access priority processing option', 'woo-priority' ),
			],
			[
				'title' => __( 'Current Access', 'woo-priority' ),
				'type'  => 'wpp_access_summary',
			],
			[
				'type' => 'sectionend',
				'id'   => 'wpp_section_access',
			],
		];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function display_fields(): array {
		return [
			[
				'title' => __( 'Display', 'woo-priority' ),
				'type'  => 'title',
				'id'    => 'wpp_section_display',
			],
			[
				'title'    => __( 'Section Title', 'woo-priority' ),
				'id'       => 'wpp_section_title',
				'type'     => 'text',
				'default'  => 'Express Options',
				'desc_tip' => __( 'Heading shown above the priority processing option', 'woo-priority' ),
			],
			[
				'title'    => __( 'Checkbox Label', 'woo-priority' ),
				'id'       => 'wpp_checkbox_label',
				'type'     => 'text',
				'css'      => 'min-width: 350px;',
				'default'  => 'Priority processing + Express shipping',
				'desc_tip' => __( 'Text displayed next to the checkbox option', 'woo-priority' ),
			],
			[
				'title'    => __( 'Help Text', 'woo-priority' ),
				'id'       => 'wpp_description',
				'type'     => 'textarea',
				'css'      => 'min-width: 350px; height: 75px;',
				'default'  => 'Your order will be processed with priority and shipped via express delivery',
				'desc_tip' => __( 'Additional explanation shown below the checkbox', 'woo-priority' ),
			],
			[
				'title'   => __( 'Cart Page Button', 'woo-priority' ),
				'id'      => 'wpp_cart_button_enabled',
				'type'    => 'wpp_toggle',
				'default' => '0',
				'desc'    => __( 'Show a one-click priority toggle on the cart page', 'woo-priority' ),
			],
			[
				'type' => 'sectionend',
				'id'   => 'wpp_section_display',
			],
		];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function messages_fields(): array {
		$modes = [
			'always'    => __( 'Always show', 'woo-priority' ),
			'threshold' => __( 'Only when cart subtotal is at least the threshold', 'woo-priority' ),
		];

		$fields = [
			[
				'title' => __( 'Motivational Messages', 'woo-priority' ),
				'type'  => 'title',
				'desc'  => __( 'Show a randomly picked upsell message on the cart page and on product pages to nudge shoppers toward Priority Processing. Enable each location once you have reviewed the wording below.', 'woo-priority' ),
				'id'    => 'wpp_section_messages',
			],
		];

		$locations = [
			'cart'    => [
				__( 'Cart Page Message', 'woo-priority' ),
				__( 'Shown under the products table on the cart page.', 'woo-priority' ),
				__( 'Cart Messages', 'woo-priority' ),
				Frontend_Messages::get_default_cart_messages(),
			],
			'product' => [
				__( 'Product Page Message', 'woo-priority' ),
				__( 'Shown under the "Add to cart" button on single product pages.', 'woo-priority' ),
				__( 'Product Messages', 'woo-priority' ),
				Frontend_Messages::get_default_product_messages(),
			],
		];

		foreach ( $locations as $key => [ $title, $desc, $messages_title, $default_messages ] ) {
			$fields[] = [
				'title'   => $title,
				'id'      => "wpp_{$key}_message_enabled",
				'type'    => 'wpp_toggle',
				'default' => '0',
				'desc'    => $desc,
			];
			$fields[] = [
				'title'   => __( 'Display', 'woo-priority' ),
				'id'      => "wpp_{$key}_message_mode",
				'type'    => 'radio',
				'default' => 'always',
				'options' => $modes,
			];
			$fields[] = [
				'title'             => __( 'Subtotal Threshold', 'woo-priority' ),
				'id'                => "wpp_{$key}_message_threshold",
				'type'              => 'number',
				'default'           => '0',
				'custom_attributes' => [ 'step' => '0.01', 'min' => '0' ],
			];
			$fields[] = [
				'title'    => $messages_title,
				'id'       => "wpp_{$key}_messages",
				'type'     => 'textarea',
				'css'      => 'min-width: 350px; height: 130px;',
				'default'  => $default_messages,
				'desc'     => __( 'One message per line. A random one is shown on each page load.', 'woo-priority' ),
				'desc_tip' => false,
			];
		}

		$fields[] = [
			'type' => 'sectionend',
			'id'   => 'wpp_section_messages',
		];

		return $fields;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function badge_fields(): array {
		return [
			[
				'title' => __( 'Customer Account Badge', 'woo-priority' ),
				'type'  => 'title',
				'desc'  => __( 'Show a confirmation badge on the order details view in My Account, so customers can see the priority status when they revisit an order later (the checkout thank-you page already shows its own confirmation message).', 'woo-priority' ),
				'id'    => 'wpp_section_badge',
			],
			[
				'title'   => __( 'Enable Badge', 'woo-priority' ),
				'id'      => 'wpp_account_badge_enabled',
				'type'    => 'wpp_toggle',
				'default' => '0',
				'desc'    => __( 'Shown on orders with priority processing when viewed via My Account > Orders.', 'woo-priority' ),
			],
			[
				'title'   => __( 'Badge Label', 'woo-priority' ),
				'id'      => 'wpp_account_badge_label',
				'type'    => 'text',
				'default' => Frontend_Account::get_default_label(),
			],
			[
				'title'   => __( 'Badge Message', 'woo-priority' ),
				'id'      => 'wpp_account_badge_message',
				'type'    => 'textarea',
				'css'     => 'min-width: 350px; height: 75px;',
				'default' => Frontend_Account::get_default_message(),
			],
			[
				'type' => 'sectionend',
				'id'   => 'wpp_section_badge',
			],
		];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function statistics_fields(): array {
		return [
			[
				'title' => __( 'Priority Processing Statistics', 'woo-priority' ),
				'type'  => 'title',
				'id'    => 'wpp_section_statistics',
			],
			[
				'type' => 'wpp_stats',
			],
			[
				'type' => 'sectionend',
				'id'   => 'wpp_section_statistics',
			],
		];
	}

	/**
	 * Option name => sanitizer method on Admin_Settings
	 *
	 * @return array<string, string>
	 */
	public function get_sanitizers(): array {
		return [
			'wpp_enabled'                   => 'sanitize_flag',
			'wpp_allow_guests'              => 'sanitize_flag',
			'wpp_cart_button_enabled'       => 'sanitize_flag',
			'wpp_cart_message_enabled'      => 'sanitize_flag',
			'wpp_product_message_enabled'   => 'sanitize_flag',
			'wpp_account_badge_enabled'     => 'sanitize_flag',
			'wpp_fee_amount'                => 'sanitize_amount',
			'wpp_min_order_amount'          => 'sanitize_amount',
			'wpp_cart_message_threshold'    => 'sanitize_amount',
			'wpp_product_message_threshold' => 'sanitize_amount',
			'wpp_daily_limit'               => 'sanitize_daily_limit',
			'wpp_cutoff_time'               => 'sanitize_cutoff_time',
			'wpp_allowed_user_roles'        => 'sanitize_user_roles',
			'wpp_cart_messages'             => 'sanitize_messages',
			'wpp_product_messages'          => 'sanitize_messages',
			'wpp_cart_message_mode'         => 'sanitize_message_mode',
			'wpp_product_message_mode'      => 'sanitize_message_mode',
		];
	}

	/**
	 * Filter callback for woocommerce_admin_settings_sanitize_option_{option}
	 *
	 * @param mixed                $value     Value WooCommerce prepared.
	 * @param array<string, mixed> $option    Field definition.
	 * @param mixed                $raw_value Value as posted.
	 * @return mixed
	 */
	public function sanitize_option( $value, $option = [], $raw_value = null ) {
		$id        = is_array( $option ) ? (string) ( $option['id'] ?? '' ) : '';
		$sanitizer = $this->get_sanitizers()[ $id ] ?? null;
		if ( $sanitizer === null ) {
			return $value;
		}

		// WooCommerce skips fields that were not posted (null); the sanitizers require a value.
		if ( null === $value && ( $option['type'] ?? '' ) !== 'wpp_toggle' ) {
			return null;
		}

		// Toggles are saved from the raw post value; WooCommerce's cleaning would turn them into yes/no.
		if ( ( $option['type'] ?? '' ) === 'wpp_toggle' ) {
			$value = is_scalar( $raw_value ) ? (string) $raw_value : '0';
		}

		return $this->get_settings_handler()->$sanitizer( $value );
	}

	private function get_settings_handler(): Admin_Settings {
		if ( $this->settings === null ) {
			$plugin         = class_exists( 'WooCommerce_Priority_Processing' ) ? WooCommerce_Priority_Processing::instance() : null;
			$this->settings = $plugin?->admin_settings ?? new Admin_Settings();
		}

		return $this->settings;
	}

	/**
	 * Render a checkbox field stored as '1' / '0'
	 *
	 * @param array<string, mixed> $field
	 */
	public function render_toggle_field( array $field ): void {
		$id      = (string) $field['id'];
		$current = (string) get_option( $id, $field['default'] ?? '0' );
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( (string) $field['title'] ); ?></label>
			</th>
			<td class="forminp forminp-checkbox">
				<input type="hidden" name="<?php echo esc_attr( $id ); ?>" value="0" />
				<label for="<?php echo esc_attr( $id ); ?>">
					<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" value="1" <?php checked( $current, '1' ); ?> />
					<?php echo esc_html( (string) ( $field['desc'] ?? '' ) ); ?>
				</label>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render the statistics overview (reuses the dashboard cards and refresh AJAX)
	 *
	 * @param array<string, mixed> $field
	 */
	public function render_stats_field( array $field ): void {
		$plugin = WooCommerce_Priority_Processing::instance();
		if ( $plugin->admin_dashboard === null ) {
			return;
		}

		echo '<tr valign="top"><td colspan="2" class="forminp">';
		$plugin->admin_dashboard->render_statistics_panel();
		echo '</td></tr>';
	}

	/**
	 * Render who currently has access to the priority option
	 *
	 * @param array<string, mixed> $field
	 */
	public function render_access_summary_field( array $field ): void {
		$summary = Core_Permissions::get_permission_summary();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php echo esc_html( (string) $field['title'] ); ?></th>
			<td class="forminp wpp-access-summary">
				<?php if ( ! empty( $summary ) ) : ?>
					<strong><?php esc_html_e( 'Priority processing is available to:', 'woo-priority' ); ?></strong>
					<ul>
						<?php foreach ( $summary as $role ) : ?>
							<li><?php echo esc_html( $role ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<strong><?php esc_html_e( 'No users currently have access to priority processing', 'woo-priority' ); ?></strong>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Reflects the saved settings: who can currently see and use the priority processing option', 'woo-priority' ); ?></p>
			</td>
		</tr>
		<?php
	}
}
