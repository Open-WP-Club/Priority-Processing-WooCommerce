<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ShouldDisplayFieldTest extends TestCase
{
    private Frontend_Checkout $checkout;
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        wpp_reset();
        $this->checkout = new Frontend_Checkout();
        $this->method   = new ReflectionMethod(Frontend_Checkout::class, 'should_display_field');
    }

    private function invoke(): bool
    {
        return $this->method->invoke($this->checkout);
    }

    public function test_hidden_when_feature_is_disabled(): void
    {
        wpp_set_option('wpp_enabled', '0');
        $this->assertFalse($this->invoke());
    }

    public function test_hidden_when_user_has_no_permission(): void
    {
        wpp_set_option('wpp_enabled', '1');
        $GLOBALS['_wpp_can_access'] = false;
        $this->assertFalse($this->invoke());
    }

    public function test_shown_when_enabled_with_permission_and_no_minimum(): void
    {
        wpp_set_option('wpp_enabled', '1');
        wpp_set_option('wpp_min_order_amount', '0');
        $GLOBALS['_wpp_can_access']    = true;
        $GLOBALS['_wpp_cart_subtotal'] = 50.0;
        $this->assertTrue($this->invoke());
    }

    public function test_shown_when_cart_meets_minimum(): void
    {
        wpp_set_option('wpp_enabled', '1');
        wpp_set_option('wpp_min_order_amount', '50.00');
        $GLOBALS['_wpp_can_access']    = true;
        $GLOBALS['_wpp_cart_subtotal'] = 75.0;
        $this->assertTrue($this->invoke());
    }

    public function test_shown_when_cart_exactly_meets_minimum(): void
    {
        wpp_set_option('wpp_enabled', '1');
        wpp_set_option('wpp_min_order_amount', '50.00');
        $GLOBALS['_wpp_can_access']    = true;
        $GLOBALS['_wpp_cart_subtotal'] = 50.0;
        $this->assertTrue($this->invoke());
    }

    public function test_hidden_when_cart_is_below_minimum(): void
    {
        wpp_set_option('wpp_enabled', '1');
        wpp_set_option('wpp_min_order_amount', '100.00');
        $GLOBALS['_wpp_can_access']    = true;
        $GLOBALS['_wpp_cart_subtotal'] = 49.99;
        $this->assertFalse($this->invoke());
    }

    public function test_minimum_zero_never_blocks_display(): void
    {
        wpp_set_option('wpp_enabled', '1');
        wpp_set_option('wpp_min_order_amount', '0');
        $GLOBALS['_wpp_can_access']    = true;
        $GLOBALS['_wpp_cart_subtotal'] = 0.01;
        $this->assertTrue($this->invoke());
    }

    public function test_disabled_takes_precedence_over_everything(): void
    {
        wpp_set_option('wpp_enabled', '0');
        wpp_set_option('wpp_min_order_amount', '0');
        $GLOBALS['_wpp_can_access']    = true;
        $GLOBALS['_wpp_cart_subtotal'] = 999.0;
        $this->assertFalse($this->invoke());
    }
}
