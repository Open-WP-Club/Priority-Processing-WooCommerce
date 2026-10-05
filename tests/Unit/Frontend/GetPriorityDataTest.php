<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class GetPriorityDataTest extends TestCase
{
    private Frontend_Blocks_Integration $blocks;
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        wpp_reset();
        $this->blocks = new Frontend_Blocks_Integration();
        $this->method = new ReflectionMethod(Frontend_Blocks_Integration::class, 'get_priority_data');
    }

    private function invoke(): array
    {
        return $this->method->invoke($this->blocks);
    }

    public function test_enabled_true_when_feature_on_and_user_has_access(): void
    {
        wpp_set_option('wpp_enabled', '1');
        $GLOBALS['_wpp_can_access'] = true;

        $this->assertTrue($this->invoke()['enabled']);
    }

    public function test_enabled_false_when_feature_off(): void
    {
        wpp_set_option('wpp_enabled', '0');
        $GLOBALS['_wpp_can_access'] = true;

        $this->assertFalse($this->invoke()['enabled']);
    }

    public function test_enabled_false_when_feature_on_but_user_lacks_access(): void
    {
        wpp_set_option('wpp_enabled', '1');
        $GLOBALS['_wpp_can_access'] = false;

        $this->assertFalse($this->invoke()['enabled']);
    }

    public function test_is_active_reflects_session_independent_of_enabled(): void
    {
        wpp_set_option('wpp_enabled', '0');
        WC()->session->set('priority_processing', true);

        $this->assertTrue($this->invoke()['is_active']);
    }

    public function test_is_active_false_when_session_not_set(): void
    {
        $this->assertFalse($this->invoke()['is_active']);
    }

    public function test_fee_amount_defaults_to_five(): void
    {
        $this->assertSame(5.0, $this->invoke()['fee_amount']);
    }

    public function test_fee_amount_reads_configured_value(): void
    {
        wpp_set_option('wpp_fee_amount', '8.25');
        $this->assertSame(8.25, $this->invoke()['fee_amount']);
    }

    public function test_fee_label_reads_configured_value(): void
    {
        wpp_set_option('wpp_fee_label', 'Custom Rush Fee');
        $this->assertSame('Custom Rush Fee', $this->invoke()['fee_label']);
    }
    public function test_cart_extension_respects_message_threshold_and_permissions(): void
    {
        wpp_set_option('wpp_enabled', '1');
        wpp_set_option('wpp_cart_message_enabled', '1');
        wpp_set_option('wpp_cart_messages', 'Cart upsell');
        wpp_set_option('wpp_cart_message_mode', 'threshold');
        wpp_set_option('wpp_cart_message_threshold', '100');
        $GLOBALS['_wpp_cart_subtotal'] = 99.0;
        $this->assertSame('', $this->blocks->extend_cart_data()['cart_message']);
        $GLOBALS['_wpp_cart_subtotal'] = 100.0;
        $this->assertSame('Cart upsell', $this->blocks->extend_cart_data()['cart_message']);
        $GLOBALS['_wpp_can_access'] = false;
        $this->assertSame('', $this->blocks->extend_cart_data()['cart_message']);
        $this->assertSame('string', $this->blocks->extend_cart_schema()['cart_message']['type']);
        $this->assertArrayNotHasKey('cart_message', $this->blocks->extend_checkout_schema());
    }

}
