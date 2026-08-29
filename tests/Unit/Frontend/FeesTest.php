<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class FeesTest extends TestCase
{
    private Frontend_Fees $fees;

    protected function setUp(): void
    {
        wpp_reset();
        $this->fees = new Frontend_Fees();
    }

    // -- add_priority_fee_to_cart() ---------------------------------------

    public function test_no_fee_added_when_priority_not_active_in_session(): void
    {
        WC()->session->set('priority_processing', false);

        $this->fees->add_priority_fee_to_cart();

        $this->assertSame([], WC()->cart->fees);
    }

    public function test_fee_added_with_configured_amount_and_label_when_priority_active(): void
    {
        WC()->session->set('priority_processing', true);
        wpp_set_option('wpp_fee_amount', '7.50');
        wpp_set_option('wpp_fee_label', 'Rush Fee');

        $this->fees->add_priority_fee_to_cart();

        $this->assertCount(1, WC()->cart->fees);
        $this->assertSame('Rush Fee', WC()->cart->fees[0]['label']);
        $this->assertSame(7.5, WC()->cart->fees[0]['amount']);
    }

    public function test_no_fee_added_when_configured_amount_is_zero(): void
    {
        WC()->session->set('priority_processing', true);
        wpp_set_option('wpp_fee_amount', '0');

        $this->fees->add_priority_fee_to_cart();

        $this->assertSame([], WC()->cart->fees);
    }

    public function test_no_fee_added_when_priority_is_not_available(): void
    {
        WC()->session->set('priority_processing', true);
        $GLOBALS['_wpp_can_enable'] = false;

        $this->fees->add_priority_fee_to_cart();

        $this->assertSame([], WC()->cart->fees);
    }

    public function test_session_value_as_string_one_counts_as_active(): void
    {
        WC()->session->set('priority_processing', '1');
        wpp_set_option('wpp_fee_amount', '5.00');

        $this->fees->add_priority_fee_to_cart();

        $this->assertCount(1, WC()->cart->fees);
    }

    // -- save_priority_to_order() -----------------------------------------

    public function test_order_meta_untouched_when_session_priority_not_set(): void
    {
        WC()->session->set('priority_processing', false);
        $order = new WC_Order();

        $this->fees->save_priority_to_order($order, []);

        $this->assertSame('', $order->get_meta('_priority_processing'));
        $this->assertFalse($order->saved);
    }

    public function test_order_meta_set_and_saved_when_session_priority_active(): void
    {
        WC()->session->set('priority_processing', true);
        wpp_set_option('wpp_fee_amount', '9.99');

        $order = new WC_Order();
        $this->fees->save_priority_to_order($order, []);

        $this->assertSame('yes', $order->get_meta('_priority_processing'));
        $this->assertSame('yes', $order->get_meta('_requires_express_shipping'));
        $this->assertSame(9.99, $order->get_meta('_priority_fee_amount'));
        $this->assertSame('express', $order->get_meta('_priority_service_level'));
        $this->assertTrue($order->saved);
    }

    public function test_order_meta_untouched_when_priority_is_not_available(): void
    {
        WC()->session->set('priority_processing', true);
        $GLOBALS['_wpp_can_enable'] = false;
        $order = new WC_Order();

        $this->fees->save_priority_to_order($order, []);

        $this->assertSame('', $order->get_meta('_priority_processing'));
        $this->assertFalse($order->saved);
    }
}
