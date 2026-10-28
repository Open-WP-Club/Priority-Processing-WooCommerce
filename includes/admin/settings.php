<?php

/**
 * Admin Settings Handler
 * Manages settings registration, validation, and form processing
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Settings
{
  public function __construct()
  {
    add_action('admin_init', [$this, 'register_settings']);
  }

  /**
   * Register all plugin settings
   */
  public function register_settings(): void
  {
    register_setting( 'wpp_settings', 'wpp_enabled', [
      'sanitize_callback' => fn( $v ) => $v === '1' ? '1' : '0',
    ] );

    register_setting( 'wpp_settings', 'wpp_fee_amount', [
      'sanitize_callback' => fn( $v ) => (string) max( 0, round( (float) $v, 2 ) ),
    ] );

    register_setting( 'wpp_settings', 'wpp_checkbox_label', [
      'sanitize_callback' => 'sanitize_text_field',
    ] );

    register_setting( 'wpp_settings', 'wpp_description', [
      'sanitize_callback' => 'sanitize_textarea_field',
    ] );

    register_setting( 'wpp_settings', 'wpp_fee_label', [
      'sanitize_callback' => 'sanitize_text_field',
    ] );

    register_setting( 'wpp_settings', 'wpp_section_title', [
      'sanitize_callback' => 'sanitize_text_field',
    ] );

    register_setting( 'wpp_settings', 'wpp_allowed_user_roles', [
      'sanitize_callback' => [ $this, 'sanitize_user_roles' ],
    ] );

    register_setting( 'wpp_settings', 'wpp_allow_guests', [
      'sanitize_callback' => fn( $v ) => $v === '1' ? '1' : '0',
    ] );

    register_setting( 'wpp_settings', 'wpp_min_order_amount', [
      'sanitize_callback' => fn( $v ) => (string) max( 0, round( (float) $v, 2 ) ),
    ] );

    register_setting( 'wpp_settings', 'wpp_cutoff_time', [
      'sanitize_callback' => [ $this, 'sanitize_cutoff_time' ],
    ] );

    register_setting( 'wpp_settings', 'wpp_cart_message_enabled', [
      'sanitize_callback' => fn( $v ) => $v === '1' ? '1' : '0',
    ] );

    register_setting( 'wpp_settings', 'wpp_cart_messages', [
      'sanitize_callback' => [ $this, 'sanitize_messages' ],
    ] );

    register_setting( 'wpp_settings', 'wpp_cart_message_mode', [
      'sanitize_callback' => [ $this, 'sanitize_message_mode' ],
    ] );

    register_setting( 'wpp_settings', 'wpp_cart_message_threshold', [
      'sanitize_callback' => fn( $v ) => (string) max( 0, round( (float) $v, 2 ) ),
    ] );

    register_setting( 'wpp_settings', 'wpp_product_message_enabled', [
      'sanitize_callback' => fn( $v ) => $v === '1' ? '1' : '0',
    ] );

    register_setting( 'wpp_settings', 'wpp_product_messages', [
      'sanitize_callback' => [ $this, 'sanitize_messages' ],
    ] );

    register_setting( 'wpp_settings', 'wpp_product_message_mode', [
      'sanitize_callback' => [ $this, 'sanitize_message_mode' ],
    ] );

    register_setting( 'wpp_settings', 'wpp_product_message_threshold', [
      'sanitize_callback' => fn( $v ) => (string) max( 0, round( (float) $v, 2 ) ),
    ] );

    register_setting( 'wpp_settings', 'wpp_daily_limit', [
      'sanitize_callback' => [ $this, 'sanitize_daily_limit' ],
    ] );

    register_setting( 'wpp_settings', 'wpp_cart_button_enabled', [
      'sanitize_callback' => [ $this, 'sanitize_flag' ],
    ] );

    register_setting( 'wpp_settings', 'wpp_account_badge_enabled', [
      'sanitize_callback' => fn( $v ) => $v === '1' ? '1' : '0',
    ] );

    register_setting( 'wpp_settings', 'wpp_account_badge_label', [
      'sanitize_callback' => 'sanitize_text_field',
    ] );

    register_setting( 'wpp_settings', 'wpp_account_badge_message', [
      'sanitize_callback' => 'sanitize_textarea_field',
    ] );
  }

  /**
   * Sanitize a checkbox value to '1' / '0'
   */
  public function sanitize_flag( $value ): string {
    return $value === '1' ? '1' : '0';
  }

  /**
   * Sanitize a money amount to a non-negative decimal string
   */
  public function sanitize_amount( $value ): string {
    return (string) max( 0, round( (float) $value, 2 ) );
  }

  /**
   * Sanitize the daily priority order limit (0 = unlimited)
   */
  public function sanitize_daily_limit( $value ): string {
    return (string) max( 0, (int) $value );
  }

  /**
   * Sanitize a newline-separated list of messages, dropping empty lines
   */
  public function sanitize_messages( string $value ): string {
    $lines = array_filter(
      array_map( 'sanitize_text_field', explode( "\n", str_replace( "\r\n", "\n", $value ) ) ),
      fn( $line ) => $line !== ''
    );

    return implode( "\n", $lines );
  }

  /**
   * Sanitize the message display mode to a known value
   */
  public function sanitize_message_mode( string $value ): string {
    return $value === 'threshold' ? 'threshold' : 'always';
  }

  public function sanitize_cutoff_time( string $value ): string {
    $value = trim( $value );
    if ( empty( $value ) ) {
      return '';
    }
    return preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $value ) ? $value : '';
  }

  /**
   * Sanitize user roles to ensure they're saved as an array
   */
  public function sanitize_user_roles($roles)
  {
    if (empty($roles)) {
      return ['customer']; // Default fallback
    }

    // Ensure it's an array
    if (!is_array($roles)) {
      $roles = [$roles];
    }

    // Sanitize each role
    $sanitized_roles = [];
    foreach ($roles as $role) {
      $clean_role = sanitize_text_field($role);
      if (!empty($clean_role)) {
        $sanitized_roles[] = $clean_role;
      }
    }

    // Return array or default if empty
    return !empty($sanitized_roles) ? $sanitized_roles : ['customer'];
  }

  /**
   * Get current settings values
   */
  public function get_settings()
  {
    return [
      'enabled' => get_option('wpp_enabled', '1'),
      'fee_amount' => get_option('wpp_fee_amount', '5.00'),
      'section_title' => get_option('wpp_section_title', 'Express Options'),
      'checkbox_label' => get_option('wpp_checkbox_label', 'Priority processing + Express shipping'),
      'description' => get_option('wpp_description', 'Your order will be processed with priority and shipped via express delivery'),
      'fee_label' => get_option('wpp_fee_label', 'Priority Processing & Express Shipping'),
      'allowed_user_roles' => get_option('wpp_allowed_user_roles', ['customer']),
      'allow_guests'      => get_option('wpp_allow_guests', '1'),
      'min_order_amount'  => get_option('wpp_min_order_amount', '0'),
      'daily_limit'       => get_option('wpp_daily_limit', '0'),
      'cart_button_enabled' => get_option('wpp_cart_button_enabled', '0'),
      'cutoff_time'       => get_option('wpp_cutoff_time', ''),
      'cart_message_enabled'      => get_option('wpp_cart_message_enabled', '0'),
      'cart_messages'             => get_option('wpp_cart_messages', Frontend_Messages::get_default_cart_messages()),
      'cart_message_mode'         => get_option('wpp_cart_message_mode', 'always'),
      'cart_message_threshold'    => get_option('wpp_cart_message_threshold', '0'),
      'product_message_enabled'   => get_option('wpp_product_message_enabled', '0'),
      'product_messages'          => get_option('wpp_product_messages', Frontend_Messages::get_default_product_messages()),
      'product_message_mode'      => get_option('wpp_product_message_mode', 'always'),
      'product_message_threshold' => get_option('wpp_product_message_threshold', '0'),
      'account_badge_enabled' => get_option('wpp_account_badge_enabled', '0'),
      'account_badge_label'   => get_option('wpp_account_badge_label', Frontend_Account::get_default_label()),
      'account_badge_message' => get_option('wpp_account_badge_message', Frontend_Account::get_default_message()),
    ];
  }
}
