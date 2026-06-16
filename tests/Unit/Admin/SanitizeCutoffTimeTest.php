<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SanitizeCutoffTimeTest extends TestCase
{
    private Admin_Settings $settings;

    protected function setUp(): void
    {
        wpp_reset();
        $this->settings = new Admin_Settings();
    }

    public function test_empty_string_returns_empty(): void
    {
        $this->assertSame('', $this->settings->sanitize_cutoff_time(''));
    }

    public function test_whitespace_only_returns_empty(): void
    {
        $this->assertSame('', $this->settings->sanitize_cutoff_time('   '));
    }

    public function test_valid_time_passes_through(): void
    {
        $this->assertSame('14:30', $this->settings->sanitize_cutoff_time('14:30'));
    }

    public function test_midnight_is_valid(): void
    {
        $this->assertSame('00:00', $this->settings->sanitize_cutoff_time('00:00'));
    }

    public function test_end_of_day_is_valid(): void
    {
        $this->assertSame('23:59', $this->settings->sanitize_cutoff_time('23:59'));
    }

    public function test_leading_spaces_are_stripped(): void
    {
        $this->assertSame('09:00', $this->settings->sanitize_cutoff_time('  09:00  '));
    }

    public function test_invalid_hour_returns_empty(): void
    {
        $this->assertSame('', $this->settings->sanitize_cutoff_time('25:00'));
    }

    public function test_invalid_minute_returns_empty(): void
    {
        $this->assertSame('', $this->settings->sanitize_cutoff_time('14:60'));
    }

    public function test_missing_leading_zero_returns_empty(): void
    {
        $this->assertSame('', $this->settings->sanitize_cutoff_time('9:00'));
    }

    public function test_non_time_string_returns_empty(): void
    {
        $this->assertSame('', $this->settings->sanitize_cutoff_time('noon'));
    }

    public function test_sql_injection_attempt_returns_empty(): void
    {
        $this->assertSame('', $this->settings->sanitize_cutoff_time("14:00'; DROP TABLE--"));
    }
}
