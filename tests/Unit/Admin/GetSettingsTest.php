<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class GetSettingsTest extends TestCase
{
    private Admin_Settings $settings;

    protected function setUp(): void
    {
        wpp_reset();
        $this->settings = new Admin_Settings();
    }

    public function test_returns_defaults_when_no_options_set(): void
    {
        $result = $this->settings->get_settings();

        $this->assertSame('1',          $result['enabled']);
        $this->assertSame('5.00',       $result['fee_amount']);
        $this->assertSame('0',          $result['min_order_amount']);
        $this->assertSame('',           $result['cutoff_time']);
        $this->assertSame(['customer'], $result['allowed_user_roles']);
        $this->assertSame('1',          $result['allow_guests']);
    }

    public function test_returns_stored_min_order_amount(): void
    {
        wpp_set_option('wpp_min_order_amount', '75.00');
        $result = $this->settings->get_settings();
        $this->assertSame('75.00', $result['min_order_amount']);
    }

    public function test_returns_stored_cutoff_time(): void
    {
        wpp_set_option('wpp_cutoff_time', '16:30');
        $result = $this->settings->get_settings();
        $this->assertSame('16:30', $result['cutoff_time']);
    }

    public function test_all_keys_present(): void
    {
        $result = $this->settings->get_settings();
        $expected_keys = [
            'enabled', 'fee_amount', 'section_title', 'checkbox_label',
            'description', 'fee_label', 'allowed_user_roles', 'allow_guests',
            'min_order_amount', 'cutoff_time',
        ];

        foreach ($expected_keys as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: $key");
        }
    }
}
