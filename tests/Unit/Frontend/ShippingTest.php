<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ShippingTest extends TestCase
{
    private Frontend_Shipping $shipping;

    protected function setUp(): void
    {
        wpp_reset();
        $this->shipping = new Frontend_Shipping();
    }

    // -- check_priority_for_rates() -----------------------------------------

    public function test_rates_untouched_when_priority_not_active(): void
    {
        WC()->session->set('priority_processing', false);
        $rate = new WC_Shipping_Rate_Stub();

        $this->shipping->check_priority_for_rates(['flat_rate' => $rate], []);

        $this->assertSame([], $rate->meta);
    }

    public function test_metadata_added_to_all_rates_when_priority_active(): void
    {
        WC()->session->set('priority_processing', true);
        wpp_set_option('wpp_fee_amount', '6.50');
        $rate1 = new WC_Shipping_Rate_Stub();
        $rate2 = new WC_Shipping_Rate_Stub();

        $this->shipping->check_priority_for_rates(['flat_rate' => $rate1, 'express' => $rate2], []);

        $this->assertSame('yes', $rate1->meta['wpp_priority_processing']);
        $this->assertSame(6.5, $rate1->meta['wpp_priority_fee_amount']);
        $this->assertSame('yes', $rate2->meta['wpp_priority_processing']);
    }

    public function test_no_metadata_added_when_fee_amount_is_zero(): void
    {
        WC()->session->set('priority_processing', true);
        wpp_set_option('wpp_fee_amount', '0');
        $rate = new WC_Shipping_Rate_Stub();

        $this->shipping->check_priority_for_rates(['flat_rate' => $rate], []);

        $this->assertSame([], $rate->meta);
    }

    public function test_skips_rate_objects_without_add_meta_data_method(): void
    {
        WC()->session->set('priority_processing', true);
        wpp_set_option('wpp_fee_amount', '5.00');
        $plainRate = new stdClass();

        $result = $this->shipping->check_priority_for_rates(['flat_rate' => $plainRate], []);

        // Should not throw, and the rate is returned unmodified.
        $this->assertSame($plainRate, $result['flat_rate']);
    }

    public function test_returns_rates_array_unchanged_in_structure(): void
    {
        WC()->session->set('priority_processing', true);
        wpp_set_option('wpp_fee_amount', '5.00');
        $rate = new WC_Shipping_Rate_Stub();

        $result = $this->shipping->check_priority_for_rates(['flat_rate' => $rate], []);

        $this->assertArrayHasKey('flat_rate', $result);
        $this->assertSame($rate, $result['flat_rate']);
    }

    // -- get_priority_fee() --------------------------------------------------

    public function test_get_priority_fee_reads_configured_amount(): void
    {
        wpp_set_option('wpp_fee_amount', '12.34');
        $this->assertSame(12.34, $this->shipping->get_priority_fee());
    }

    public function test_get_priority_fee_defaults_to_five(): void
    {
        $this->assertSame(5.0, $this->shipping->get_priority_fee());
    }
}
