<?php
/**
 * Admin Menu Handler
 * Manages admin menu registration and asset enqueuing
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.0.0
 */

declare(strict_types=1);

/**
 * Admin Menu Class
 *
 * @since 1.0.0
 */
class Admin_Menu {

	private readonly Core_Statistics $statistics;
	private readonly Admin_Dashboard $dashboard;

	public function __construct( Core_Statistics $statistics_instance, Admin_Dashboard $dashboard ) {
		$this->statistics = $statistics_instance;
		$this->dashboard  = $dashboard;

		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );

		add_filter(
			'plugin_action_links_' . plugin_basename( WPP_PLUGIN_DIR . 'woocommerce-priority-processing.php' ),
			[ $this, 'add_plugin_action_links' ]
		);
		add_filter( 'plugin_row_meta', [ $this, 'add_plugin_row_meta' ], 10, 2 );
	}

	/**
	 * Add Settings / Docs / Support links to the plugins list page
	 *
	 * @param array<string, string> $links
	 * @return array<string, string>
	 */
	public function add_plugin_action_links( array $links ): array {
		$plugin_links = [
			'settings' => sprintf(
				'<a href="%s" style="color:#2271b1;font-weight:600;">%s</a>',
				admin_url( 'admin.php?page=woo-priority-processing' ),
				__( 'Settings', 'woo-priority' )
			),
			'docs'     => sprintf(
				'<a href="%s" target="_blank" style="color:#50575e;">%s</a>',
				'https://openwpclub.com/docs/woocommerce-priority-processing/',
				__( 'Docs', 'woo-priority' )
			),
			'support'  => sprintf(
				'<a href="%s" target="_blank" style="color:#50575e;">%s</a>',
				'https://openwpclub.com/support/',
				__( 'Support', 'woo-priority' )
			),
		];

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Add GitHub link to plugin row meta
	 *
	 * @param array<int, string> $links
	 * @param string             $file
	 * @return array<int, string>
	 */
	public function add_plugin_row_meta( array $links, string $file ): array {
		if ( $file === plugin_basename( WPP_PLUGIN_DIR . 'woocommerce-priority-processing.php' ) ) {
			$links[] = sprintf(
				'<a href="%s" target="_blank" style="color:#50575e;">%s</a>',
				'https://github.com/openwpclub/woocommerce-priority-processing',
				__( 'GitHub', 'woo-priority' )
			);
		}
		return $links;
	}

	/**
	 * Register WooCommerce → Priority Processing submenu page
	 */
	public function add_admin_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Priority Processing', 'woo-priority' ),
			__( 'Priority Processing', 'woo-priority' ),
			'manage_woocommerce',
			'woo-priority-processing',
			[ $this, 'admin_page' ]
		);
	}

	/**
	 * Render admin page (uses the shared Admin_Dashboard instance)
	 */
	public function admin_page(): void {
		$this->dashboard->display_page();
	}

	/**
	 * Enqueue admin CSS + JS on our settings page
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function admin_scripts( string $hook ): void {
		if ( $hook !== 'woocommerce_page_woo-priority-processing' ) {
			return;
		}

		wp_enqueue_style( 'wpp-admin', WPP_PLUGIN_URL . 'assets/css/admin.css', [], WPP_VERSION );

		wp_enqueue_script(
			'wpp-admin-js',
			WPP_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			WPP_VERSION,
			true
		);

		wp_localize_script( 'wpp-admin-js', 'wpp_admin_ajax', [
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'wpp_admin_nonce' ),
			'refreshing_text'   => __( 'Refreshing…', 'woo-priority' ),
			'refresh_text'      => __( 'Refresh Stats', 'woo-priority' ),
			'error_refresh'     => __( 'Failed to refresh statistics. Please try again.', 'woo-priority' ),
			'text_active'       => __( 'Active', 'woo-priority' ),
			'text_inactive'     => __( 'Inactive', 'woo-priority' ),
			'text_allowed'      => __( 'Allowed', 'woo-priority' ),
			'text_denied'       => __( 'Denied', 'woo-priority' ),
			'text_shop_managers'  => __( 'Shop Managers & Administrators', 'woo-priority' ),
			'text_guests'         => __( 'Guest Users', 'woo-priority' ),
			'text_available_to'   => __( 'Priority processing is available to:', 'woo-priority' ),
			'text_only_managers'  => __( 'Only Shop Managers currently have access to priority processing', 'woo-priority' ),
			'text_available'      => __( 'Available to:', 'woo-priority' ),
			'text_no_access'      => __( 'No access granted', 'woo-priority' ),
		] );
	}

	/**
	 * @deprecated kept for back-compat if called externally
	 */
	public function get_statistics_handler(): Core_Statistics {
		return $this->statistics;
	}
}
