<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class CartButtonTest extends TestCase
{
    private Frontend_Cart_Button $button;

    protected function setUp(): void
    {
        wpp_reset();
        wpp_set_option('wpp_enabled', '1');
        wpp_set_option('wpp_cart_button_enabled', '1');
        $GLOBALS['_wpp_is_cart'] = true;
        $this->button = new Frontend_Cart_Button();
    }

    private function classic(): string
    {
        ob_start();
        $this->button->render_classic_button();
        return (string) ob_get_clean();
    }

    private function fallback(): string
    {
        ob_start();
        $this->button->render_fallback();
        return (string) ob_get_clean();
    }

    // -- hooks ------------------------------------------------------------

    public function test_registers_classic_hook_before_checkout_button_and_footer_fallback(): void
    {
        $classic = $GLOBALS['_wpp_actions']['woocommerce_proceed_to_checkout'] ?? [];
        $this->assertCount(1, $classic);
        $this->assertSame([$this->button, 'render_classic_button'], $classic[0]['callback']);
        $this->assertLessThan(20, $classic[0]['priority']);
        $this->assertCount(1, $GLOBALS['_wpp_actions']['wp_footer'] ?? []);
        $this->assertCount(1, $GLOBALS['_wpp_actions']['woocommerce_blocks_loaded'] ?? []);
    }

    public function test_can_be_built_without_hooks(): void
    {
        wpp_reset();
        new Frontend_Cart_Button(false);
        $this->assertSame([], $GLOBALS['_wpp_actions']);
    }

    // -- eligibility gates ------------------------------------------------

    public function test_eligible_with_defaults_enabled(): void
    {
        $this->assertTrue($this->button->is_eligible());
    }

    public function test_not_eligible_when_plugin_disabled(): void
    {
        wpp_set_option('wpp_enabled', '0');
        $this->assertFalse($this->button->is_eligible());
    }

    public function test_not_eligible_when_button_option_off_or_missing(): void
    {
        wpp_set_option('wpp_cart_button_enabled', '0');
        $this->assertFalse($this->button->is_eligible());
        unset($GLOBALS['_wpp_options']['wpp_cart_button_enabled']);
        $this->assertFalse($this->button->is_eligible());
    }

    public function test_not_eligible_without_permission(): void
    {
        $GLOBALS['_wpp_can_enable'] = false;
        $this->assertFalse($this->button->is_eligible());
    }

    public function test_minimum_order_amount_uses_cart_subtotal(): void
    {
        wpp_set_option('wpp_min_order_amount', '50');
        $GLOBALS['_wpp_cart_subtotal'] = 49.99;
        $this->assertFalse($this->button->is_eligible());
        $GLOBALS['_wpp_cart_subtotal'] = 50.0;
        $this->assertTrue($this->button->is_eligible());
    }

    public function test_not_eligible_when_daily_limit_reached(): void
    {
        if (!method_exists('Core_Limits', 'is_limit_reached')) {
            $this->markTestSkipped('Core_Limits::is_limit_reached() is not available yet.');
        }
        $reached = Core_Limits::is_limit_reached();
        $this->assertSame(!$reached, $this->button->is_eligible());
    }

    // -- rendering --------------------------------------------------------

    public function test_classic_button_shows_add_state_with_fee(): void
    {
        wpp_set_option('wpp_fee_amount', '7.50');
        $html = $this->classic();

        $this->assertStringContainsString('data-wpp-cart-toggle', $html);
        $this->assertStringContainsString('data-wpp-active="0"', $html);
        $this->assertStringContainsString('Add Priority Processing', $html);
        $this->assertStringContainsString('$7.50', $html);
    }

    public function test_classic_button_shows_remove_state_when_active(): void
    {
        WC()->session->set('priority_processing', true);
        $html = $this->classic();

        $this->assertStringContainsString('data-wpp-active="1"', $html);
        $this->assertStringContainsString('aria-pressed="true"', $html);
        $this->assertStringContainsString('Remove Priority Processing', $html);
        $this->assertStringNotContainsString('wpp-cart-button__price', $html);
    }

    public function test_description_is_escaped(): void
    {
        wpp_set_option('wpp_description', '<script>x</script>');
        $html = $this->classic();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_nothing_rendered_when_ineligible(): void
    {
        wpp_set_option('wpp_cart_button_enabled', '0');
        $this->assertSame('', $this->classic());
    }

    // -- duplicate prevention ---------------------------------------------

    public function test_classic_button_renders_only_once_per_request(): void
    {
        $this->assertNotSame('', $this->classic());
        $this->assertSame('', $this->classic());
    }

    public function test_fallback_prints_script_when_hooks_were_bypassed(): void
    {
        $out = $this->fallback();
        $this->assertStringContainsString('<script>', $out);
        $this->assertStringContainsString('wpp-cart-button', $out);
    }

    public function test_fallback_skipped_after_classic_render(): void
    {
        $this->classic();
        $this->assertSame('', $this->fallback());
    }

    public function test_fallback_skipped_after_block_render(): void
    {
        $this->button->render_cart_block_button('<div class="wp-block-woocommerce-cart"></div>');
        $this->assertSame('', $this->fallback());
    }

    public function test_fallback_not_printed_off_cart_page_or_when_ineligible(): void
    {
        $GLOBALS['_wpp_is_cart'] = false;
        $this->assertSame('', $this->fallback());

        $GLOBALS['_wpp_is_cart'] = true;
        $GLOBALS['_wpp_can_enable'] = false;
        $this->assertSame('', $this->fallback());
    }

    public function test_fallback_runs_only_once(): void
    {
        $this->assertNotSame('', $this->fallback());
        $this->assertSame('', $this->fallback());
    }

    public function test_block_filter_adds_container_once(): void
    {
        $content = '<div class="wp-block-woocommerce-cart"></div>';
        $first   = $this->button->render_cart_block_button($content);
        $this->assertStringContainsString('wpp-cart-button-block', $first);
        $this->assertStringContainsString($content, $first);
        $this->assertSame($content, $this->button->render_cart_block_button($content));
    }

    public function test_block_filter_leaves_empty_content_alone(): void
    {
        $this->assertSame('  ', $this->button->render_cart_block_button('  '));
    }

    // -- Store API data ---------------------------------------------------

    public function test_extension_data_reflects_state_and_matches_schema(): void
    {
        wpp_set_option('wpp_fee_amount', '3.25');
        WC()->session->set('priority_processing', true);

        $data = $this->button->get_extension_data();
        $this->assertTrue($data['eligible']);
        $this->assertTrue($data['is_active']);
        $this->assertSame(3.25, $data['fee_amount']);
        $this->assertSame(array_keys($data), array_keys($this->button->get_extension_schema()));

        wpp_set_option('wpp_cart_button_enabled', '0');
        $this->assertFalse($this->button->get_extension_data()['eligible']);
    }
}
