<?php
/**
 * Frontend Checkout Handler
 * Handles priority processing checkbox display and integration
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.0.0
 */

declare(strict_types=1);

/**
 * Frontend Checkout Class
 *
 * @since 1.0.0
 */
class Frontend_Checkout {

  public function __construct() {
    // Enqueue scripts and styles
    add_action('wp_enqueue_scripts', [$this, 'frontend_scripts']);

    // Handle checkout field display (our custom styled version only)
    add_action('woocommerce_after_order_notes', [$this, 'display_priority_section']);

    // Save the field value when order is created
    add_action('woocommerce_checkout_update_order_meta', [$this, 'save_priority_field'], 10, 1);
  }

  /**
   * Display priority section with custom styling
   */
  public function display_priority_section($checkout) {
    if (!$this->should_display_field()) {
      return;
    }

    $section_title  = get_option('wpp_section_title', __('Express Options', 'woo-priority'));
    $fee_amount     = get_option('wpp_fee_amount', '5.00');
    $checkbox_label = get_option('wpp_checkbox_label', __('Priority processing + Express shipping', 'woo-priority'));
    $description    = get_option('wpp_description', __('Your order will be processed with priority and shipped via express delivery', 'woo-priority'));
    $is_checked     = $this->get_checkbox_state();
    $cutoff_info    = $this->get_cutoff_info();

?>
    <div id="wpp-priority-section" class="wpp-priority-section" style="background: #f8f9fa; border: 2px solid #dee2e6; border-radius: 6px; padding: 20px; margin: 20px 0;">
      <h3 style="margin: 0 0 15px 0; color: #495057; font-size: 16px; font-weight: 600;">⚡ <?php echo esc_html($section_title); ?></h3>
      <div class="wpp-priority-field-wrapper">
        <label class="wpp-priority-label" style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
          <input
            type="checkbox"
            name="priority_processing"
            id="priority_processing"
            class="wpp-priority-checkbox input-checkbox"
            value="1"
            style="margin: 2px 0 0 0; cursor: pointer; flex-shrink: 0;"
            <?php checked($is_checked, true); ?> />
          <span class="wpp-label-content" style="flex: 1;">
            <strong style="color: #28a745; font-weight: 600; display: block; font-size: 14px;">
              <?php echo esc_html($checkbox_label); ?>
              <span class="wpp-price" style="color: #dc3545; font-weight: 600; margin-left: 5px;">(+ <?php echo wc_price($fee_amount); ?> )</span>
            </strong>
            <?php if (!empty($description)): ?>
              <small class="description" style="color: #6c757d; font-size: 13px; line-height: 1.4; display: block;"><?php echo esc_html($description); ?></small>
            <?php endif; ?>
          </span>
        </label>
      </div>
      <?php if ($cutoff_info): ?>
        <div class="wpp-cutoff-notice" style="margin-top: 10px; font-size: 12px; color: #856404; background: #fff3cd; border: 1px solid #ffd966; border-radius: 4px; padding: 5px 10px; display: inline-block;">
          ⏱ <?php echo esc_html($cutoff_info['message']); ?>
          <?php if ($cutoff_info['remaining_seconds'] > 0): ?>
            <span id="wpp-countdown" data-ts="<?php echo esc_attr((string) $cutoff_info['cutoff_ts']); ?>"></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
<?php
  }

  /**
   * Save priority field value to order meta
   */
  public function save_priority_field($order_id) {
    // Verify nonce for security
    if (!isset($_POST['woocommerce-process-checkout-nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce-process-checkout-nonce'])), 'woocommerce-process_checkout')) {
      return;
    }

    if (isset($_POST['priority_processing']) && sanitize_text_field(wp_unslash($_POST['priority_processing'])) === '1') {
      // Update session
      if (WC()->session) {
        WC()->session->set('priority_processing', true);
      }
    } else {
      // Clear session if not checked
      if (WC()->session) {
        WC()->session->set('priority_processing', false);
      }
    }
  }

  /**
   * Check if priority field should be displayed
   */
  private function should_display_field() {
    if ( get_option( 'wpp_enabled' ) !== '1' ) {
      return false;
    }

    if (!Core_Permissions::can_access_priority_processing()) {
      return false;
    }

    $min_amount = (float) get_option( 'wpp_min_order_amount', '0' );
    if ( $min_amount > 0 && WC()->cart ) {
      if ( WC()->cart->get_subtotal() < $min_amount ) {
        return false;
      }
    }

    return true;
  }

  /**
   * Build cutoff time info for the checkout notice.
   *
   * @return array{message: string, remaining_seconds: int, cutoff_ts: int}|null
   */
  private function get_cutoff_info( ?DateTimeImmutable $now = null ): ?array {
    $cutoff_time = get_option( 'wpp_cutoff_time', '' );
    if ( empty( $cutoff_time ) ) {
      return null;
    }

    $timezone = wp_timezone();
    $now      = $now ?? new DateTimeImmutable( 'now', $timezone );
    $cutoff   = DateTimeImmutable::createFromFormat( 'H:i', $cutoff_time, $timezone );

    if ( ! $cutoff ) {
      return null;
    }

    $cutoff    = $cutoff->setDate( (int) $now->format( 'Y' ), (int) $now->format( 'n' ), (int) $now->format( 'j' ) );
    $remaining = $cutoff->getTimestamp() - $now->getTimestamp();

    if ( $remaining > 0 ) {
      $hours   = (int) floor( $remaining / 3600 );
      $minutes = (int) floor( ( $remaining % 3600 ) / 60 );
      $message = $hours > 0
        /* translators: 1: hours 2: minutes */
        ? sprintf( __( 'Order in the next %1$dh %2$dm for same-day priority', 'woo-priority' ), $hours, $minutes )
        /* translators: %d: minutes */
        : sprintf( __( 'Order in the next %d minutes for same-day priority', 'woo-priority' ), $minutes );
      return [
        'message'          => $message,
        'remaining_seconds' => $remaining,
        'cutoff_ts'        => $cutoff->getTimestamp() * 1000,
      ];
    }

    return [
      'message'          => __( 'Same-day priority cutoff has passed — next-day priority available', 'woo-priority' ),
      'remaining_seconds' => 0,
      'cutoff_ts'        => 0,
    ];
  }

  /**
   * Get current checkbox state from session
   */
  private function get_checkbox_state() {
    if (!WC()->session) {
      return false;
    }

    $session_priority = WC()->session->get('priority_processing', false);
    return ($session_priority === true || $session_priority === '1' || $session_priority === 1);
  }

  /**
   * Enqueue frontend scripts and styles
   */
  public function frontend_scripts() {
    if (!is_checkout()) {
      return;
    }

    // Enqueue frontend CSS
    wp_enqueue_style(
      'wpp-frontend',
      WPP_PLUGIN_URL . 'assets/css/frontend.css',
      [],
      WPP_VERSION
    );

    // Enqueue JavaScript for AJAX handling
    wp_enqueue_script(
      'wpp-frontend-blocks',
      WPP_PLUGIN_URL . 'assets/js/frontend-blocks.js',
      ['jquery', 'wc-checkout'],
      WPP_VERSION,
      true
    );

    // Localize script with necessary data
    $cutoff_info = $this->get_cutoff_info();
    wp_localize_script('wpp-frontend-blocks', 'wppData', [
      'ajax_url'   => admin_url('admin-ajax.php'),
      'nonce'      => wp_create_nonce('wpp_nonce'),
      'fee_amount' => get_option('wpp_fee_amount', '5.00'),
      'fee_label'  => get_option('wpp_fee_label', __('Priority Processing & Express Shipping', 'woo-priority')),
      'cutoff_ts'  => $cutoff_info ? $cutoff_info['cutoff_ts'] : 0,
    ]);
  }
}
