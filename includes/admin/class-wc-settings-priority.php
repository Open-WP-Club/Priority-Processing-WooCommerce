<?php
/**
 * WooCommerce settings page for Priority Processing
 * Loaded on demand by Admin_WC_Settings_Tab, once WC_Settings_Page exists.
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.9.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPP_WC_Settings_Page extends WC_Settings_Page {

	public function __construct() {
		$this->id    = Admin_WC_Settings_Tab::TAB_ID;
		$this->label = __( 'Priority Processing', 'woo-priority' );

		parent::__construct();
	}

	/**
	 * @return array<string, string>
	 */
	protected function get_own_sections() {
		return Admin_WC_Settings_Tab::get_sections();
	}

	/**
	 * @return array<string, string>
	 */
	public function get_sections() {
		return apply_filters( 'woocommerce_get_sections_' . $this->id, Admin_WC_Settings_Tab::get_sections() );
	}

	/**
	 * @param string $section_id Section id.
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_section_core( $section_id ) {
		return Admin_WC_Settings_Tab::get_fields( (string) $section_id );
	}

	/**
	 * @param string $current_section Section id.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_settings( $current_section = '' ) {
		return Admin_WC_Settings_Tab::get_fields( (string) $current_section );
	}

	/**
	 * Output the section fields; the statistics overview has nothing to save
	 */
	public function output() {
		global $current_section, $hide_save_button;

		if ( $current_section === 'statistics' ) {
			$hide_save_button = true;
		}

		parent::output();
	}
}
