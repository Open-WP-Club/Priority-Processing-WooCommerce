<?php
/**
 * Admin Menu Handler
 * Manages plugin action links, the legacy page redirect and asset enqueuing
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

		add_action( 'admin_init', [ $this, 'redirect_legacy_page' ] );
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
				Admin_WC_Settings_Tab::get_tab_url(),
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
	 * Keep the old settings page URL working by sending it to the new settings tab
	 */
	public function redirect_legacy_page(): void {
		$url = Admin_WC_Settings_Tab::get_legacy_redirect_url();
		if ( $url === null ) {
			return;
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Enqueue admin CSS + JS on our WooCommerce settings tab
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function admin_scripts( string $hook ): void {
		if ( $hook !== 'woocommerce_page_wc-settings' || ! Admin_WC_Settings_Tab::is_tab_request() ) {
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
		] );
	}

	/**
	 * @deprecated kept for back-compat if called externally
	 */
	public function get_statistics_handler(): Core_Statistics {
		return $this->statistics;
	}
}
