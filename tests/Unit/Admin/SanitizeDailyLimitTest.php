<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SanitizeDailyLimitTest extends TestCase
{
    private Admin_Settings $settings;

    protected function setUp(): void
    {
        wpp_reset();
        $this->settings = new Admin_Settings();
    }

    public function test_valid_integer_is_kept_as_string(): void
    {
        $this->assertSame('25', $this->settings->sanitize_daily_limit('25'));
    }

    public function test_negative_becomes_zero(): void
    {
        $this->assertSame('0', $this->settings->sanitize_daily_limit('-5'));
    }

    public function test_decimal_is_truncated(): void
    {
        $this->assertSame('7', $this->settings->sanitize_daily_limit('7.9'));
    }

    public function test_garbage_and_empty_become_zero(): void
    {
        $this->assertSame('0', $this->settings->sanitize_daily_limit('abc'));
        $this->assertSame('0', $this->settings->sanitize_daily_limit(''));
    }

    public function test_flag_sanitizer(): void
    {
        $this->assertSame('1', $this->settings->sanitize_flag('1'));
        $this->assertSame('0', $this->settings->sanitize_flag('yes'));
        $this->assertSame('0', $this->settings->sanitize_flag('0'));
    }

    public function test_new_options_in_get_settings(): void
    {
        $result = $this->settings->get_settings();
        $this->assertSame('0', $result['daily_limit']);
        $this->assertSame('0', $result['cart_button_enabled']);
    }
}
